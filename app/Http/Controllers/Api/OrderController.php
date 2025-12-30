<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    protected OrderService $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    /**
     * Get orders.
     * 
     * If 'my_orders' parameter is true, returns authenticated user's orders (all statuses).
     * Otherwise, returns all open orders for the orderbook.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $symbol = $request->query('symbol');
        $myOrders = $request->query('my_orders', false);
        
        if ($myOrders) {
            // Return user's own orders (all statuses)
            $query = Order::where('user_id', $request->user()->id)
                ->orderBy('created_at', 'desc');
            
            if ($symbol) {
                $query->where('symbol', $symbol);
            }
        } else {
            // Return all open orders for orderbook
            $query = Order::where('status', Order::STATUS_OPEN)
                ->orderBy('price', 'asc')
                ->orderBy('created_at', 'asc');
            
            if ($symbol) {
                $query->where('symbol', $symbol);
            }
        }
        
        $orders = $query->get()->map(function ($order) {
            return [
                'id' => $order->id,
                'user_id' => $order->user_id,
                'symbol' => $order->symbol,
                'side' => $order->side,
                'price' => (float) $order->price,
                'amount' => (float) $order->amount,
                'status' => $order->status,
                'created_at' => $order->created_at->toISOString(),
            ];
        });
        
        return response()->json($orders);
    }

    /**
     * Create a new limit order.
     * 
     * @param Request $request
     * @return JsonResponse
     * @throws ValidationException
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'symbol' => 'required|string|in:BTC,ETH',
            'side' => 'required|string|in:buy,sell',
            'price' => 'required|numeric|min:0.01',
            'amount' => 'required|numeric|min:0.00000001',
        ]);
        
        try {
            $order = $this->orderService->createOrder([
                'user_id' => $request->user()->id,
                'symbol' => $validated['symbol'],
                'side' => $validated['side'],
                'price' => $validated['price'],
                'amount' => $validated['amount'],
            ]);
            
            // Refresh order to get latest status (might be FILLED if matched immediately)
            $order->refresh();
            
            $response = [
                'id' => $order->id,
                'symbol' => $order->symbol,
                'side' => $order->side,
                'price' => (float) $order->price,
                'amount' => (float) $order->amount,
                'status' => $order->status,
                'created_at' => $order->created_at->toISOString(),
            ];
            
            // Add message if order was matched immediately
            if ($order->status === Order::STATUS_FILLED) {
                $response['message'] = 'Order matched immediately!';
                $response['matched'] = true;
            } else {
                $response['message'] = 'Order placed successfully';
                $response['matched'] = false;
            }
            
            return response()->json($response, 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Cancel an open order and release locked USD or assets.
     * 
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        return DB::transaction(function () use ($request, $id) {
            $order = Order::where('id', $id)
                ->where('user_id', $request->user()->id)
                ->lockForUpdate()
                ->firstOrFail();
            
            if (!$order->isOpen()) {
                return response()->json([
                    'message' => 'Order cannot be cancelled. It is not open.',
                ], 400);
            }
            
            $user = $request->user();
            
            if ($order->side === Order::SIDE_BUY) {
                // Release locked USD
                $lockedUsd = $order->price * $order->amount;
                $user = \App\Models\User::lockForUpdate()->find($user->id);
                $user->balance += $lockedUsd;
                $user->save();
            } else {
                // Release locked assets
                $asset = \App\Models\Asset::where('user_id', $user->id)
                    ->where('symbol', $order->symbol)
                    ->lockForUpdate()
                    ->first();
                
                if ($asset) {
                    $asset->locked_amount -= $order->amount;
                    $asset->amount += $order->amount;
                    $asset->save();
                }
            }
            
            $order->status = Order::STATUS_CANCELLED;
            $order->save();
            
            return response()->json([
                'message' => 'Order cancelled successfully',
                'order' => [
                    'id' => $order->id,
                    'status' => $order->status,
                ],
            ]);
        });
    }
}
