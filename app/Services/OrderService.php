<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderService
{
    /**
     * Create a new order with validation and fund locking.
     * 
     * This method handles:
     * - Validating sufficient balance/assets
     * - Locking funds/assets
     * - Creating the order
     * - Attempting to match the order
     * 
     * @param array $orderData
     * @return Order
     * @throws \Exception
     */
    public function createOrder(array $orderData): Order
    {
        return DB::transaction(function () use ($orderData) {
            $user = User::findOrFail($orderData['user_id']);
            $side = $orderData['side'];
            $symbol = $orderData['symbol'];
            $price = $orderData['price'];
            $amount = $orderData['amount'];

            if ($side === Order::SIDE_BUY) {
                $this->validateBuyOrder($user, $price, $amount);
                $this->lockUsdForBuyOrder($user, $price, $amount);
            } else {
                $this->validateSellOrder($user, $symbol, $amount);
                $this->lockAssetsForSellOrder($user, $symbol, $amount);
            }

            $order = Order::create([
                'user_id' => $user->id,
                'symbol' => $symbol,
                'side' => $side,
                'price' => $price,
                'amount' => $amount,
                'status' => Order::STATUS_OPEN,
            ]);

            // TODO: Attempt to match the order
            // $this->matchOrder($order);

            return $order;
        });
    }

    /**
     * Validate that user has sufficient balance for buy order.
     * 
     * @param User $user
     * @param float $price
     * @param float $amount
     * @throws \Exception
     */
    protected function validateBuyOrder(User $user, float $price, float $amount): void
    {
        $requiredUsd = $price * $amount;

        if ($user->balance < $requiredUsd) {
            throw new \Exception("Insufficient balance. Required: {$requiredUsd}, Available: {$user->balance}");
        }
    }

    /**
     * Lock USD from user's balance for buy order.
     * 
     * @param User $user
     * @param float $price
     * @param float $amount
     */
    protected function lockUsdForBuyOrder(User $user, float $price, float $amount): void
    {
        $requiredUsd = $price * $amount;
        
        // Use pessimistic locking to prevent race conditions
        $user = User::lockForUpdate()->find($user->id);
        $user->balance -= $requiredUsd;
        $user->save();
    }

    /**
     * Validate that user has sufficient assets for sell order.
     * 
     * @param User $user
     * @param string $symbol
     * @param float $amount
     * @throws \Exception
     */
    protected function validateSellOrder(User $user, string $symbol, float $amount): void
    {
        $asset = Asset::where('user_id', $user->id)
            ->where('symbol', $symbol)
            ->first();

        if (!$asset) {
            throw new \Exception("Asset {$symbol} not found for user");
        }

        if ($asset->amount < $amount) {
            throw new \Exception("Insufficient assets. Required: {$amount}, Available: {$asset->amount}");
        }
    }

    /**
     * Lock assets for sell order.
     * 
     * @param User $user
     * @param string $symbol
     * @param float $amount
     */
    protected function lockAssetsForSellOrder(User $user, string $symbol, float $amount): void
    {
        // Use pessimistic locking to prevent race conditions
        $asset = Asset::where('user_id', $user->id)
            ->where('symbol', $symbol)
            ->lockForUpdate()
            ->firstOrFail();

        $asset->amount -= $amount;
        $asset->locked_amount += $amount;
        $asset->save();
    }
}

