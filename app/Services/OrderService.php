<?php

namespace App\Services;

use App\Events\OrderMatched;
use App\Models\Order;
use App\Models\Asset;
use App\Models\User;
use App\Models\Trade;
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

            // Attempt to match the order immediately
            $this->matchOrder($order);

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

    /**
     * Attempt to match a new order with existing orders.
     * 
     * Matching rules:
     * - New BUY order matches with first SELL where: sell.price <= buy.price
     * - New SELL order matches with first BUY where: buy.price >= sell.price
     * - Only full matches (no partial fills)
     * 
     * @param Order $order
     * @return bool True if matched, false otherwise
     */
    public function matchOrder(Order $order): bool
    {
        return DB::transaction(function () use ($order) {
            // Lock the order to prevent concurrent matching
            $order = Order::lockForUpdate()->find($order->id);
            
            // If order is already filled or cancelled, skip matching
            if (!$order->isOpen()) {
                return false;
            }

            $counterOrder = $this->findMatchingOrder($order);

            if (!$counterOrder) {
                return false;
            }

            // Lock the counter order
            $counterOrder = Order::lockForUpdate()->find($counterOrder->id);
            
            // Double-check both orders are still open
            if (!$order->isOpen() || !$counterOrder->isOpen()) {
                return false;
            }

            // Full match only: amounts must be exactly equal
            if ($order->amount != $counterOrder->amount) {
                return false;
            }

            // Execute the match
            $this->executeMatch($order, $counterOrder);

            return true;
        });
    }

    /**
     * Find a matching order for the given order.
     * 
     * @param Order $order
     * @return Order|null
     */
    protected function findMatchingOrder(Order $order): ?Order
    {
        if ($order->side === Order::SIDE_BUY) {
            // For BUY orders: find first SELL where sell.price <= buy.price
            // Order by price ASC (best price first), then by created_at ASC (FIFO)
            return Order::where('symbol', $order->symbol)
                ->where('side', Order::SIDE_SELL)
                ->where('status', Order::STATUS_OPEN)
                ->where('price', '<=', $order->price)
                ->where('user_id', '!=', $order->user_id) // Can't match with own orders
                ->orderBy('price', 'asc')
                ->orderBy('created_at', 'asc')
                ->lockForUpdate()
                ->first();
        } else {
            // For SELL orders: find first BUY where buy.price >= sell.price
            // Order by price DESC (best price first), then by created_at ASC (FIFO)
            return Order::where('symbol', $order->symbol)
                ->where('side', Order::SIDE_BUY)
                ->where('status', Order::STATUS_OPEN)
                ->where('price', '>=', $order->price)
                ->where('user_id', '!=', $order->user_id) // Can't match with own orders
                ->orderBy('price', 'desc')
                ->orderBy('created_at', 'asc')
                ->lockForUpdate()
                ->first();
        }
    }

    /**
     * Execute a match between two orders.
     * 
     * This method:
     * - Calculates commission (1.5% of trade volume)
     * - Updates balances for both users
     * - Marks both orders as filled
     * - Creates a Trade record
     * - Releases locked funds/assets
     * 
     * @param Order $buyOrder
     * @param Order $sellOrder
     */
    protected function executeMatch(Order $buyOrder, Order $sellOrder): void
    {
        // Determine execution price (use the price from the order that was already in the book)
        // For simplicity, we'll use the sell order's price (market maker gets priority)
        $executionPrice = $sellOrder->price;
        $amount = $buyOrder->amount; // Full match, same amount
        
        // Calculate trade volume and commission
        $volume = $executionPrice * $amount;
        $commissionRate = 0.015; // 1.5%
        $commission = $volume * $commissionRate;

        // Get users with locks
        $buyer = User::lockForUpdate()->find($buyOrder->user_id);
        $seller = User::lockForUpdate()->find($sellOrder->user_id);

        // Update buyer's balance and assets
        // Buyer already has USD locked, so we just need to:
        // - Release the locked USD (already deducted)
        // - Add the asset
        // - Deduct commission from remaining balance
        
        // Buyer receives the asset
        $this->addAssetToUser($buyer, $buyOrder->symbol, $amount);
        
        // Buyer pays commission (deduct from balance, which already has locked amount deducted)
        // Since we locked (price * amount), but execution is at sellOrder->price,
        // we need to handle the difference
        $buyerPaidUsd = $executionPrice * $amount;
        $buyerLockedUsd = $buyOrder->price * $amount;
        $difference = $buyerLockedUsd - $buyerPaidUsd;
        
        // Return difference to buyer's balance, then deduct commission
        $buyer->balance += $difference;
        $buyer->balance -= $commission;
        $buyer->save();

        // Update seller's balance and assets
        // Seller already has assets locked, so we need to:
        // - Release the locked assets (convert to USD)
        // - Add USD to balance (minus commission if we charge seller, but requirement says buyer pays)
        
        // Release locked assets
        $sellerAsset = Asset::where('user_id', $seller->id)
            ->where('symbol', $sellOrder->symbol)
            ->lockForUpdate()
            ->first();
        
        if ($sellerAsset) {
            $sellerAsset->locked_amount -= $amount;
            $sellerAsset->save();
        }
        
        // Seller receives USD (full amount, buyer pays commission)
        $seller->balance += $buyerPaidUsd;
        $seller->save();

        // Mark both orders as filled
        $buyOrder->status = Order::STATUS_FILLED;
        $buyOrder->save();
        
        $sellOrder->status = Order::STATUS_FILLED;
        $sellOrder->save();

        // Create trade record
        $trade = Trade::create([
            'buy_order_id' => $buyOrder->id,
            'sell_order_id' => $sellOrder->id,
            'symbol' => $buyOrder->symbol,
            'price' => $executionPrice,
            'amount' => $amount,
            'commission' => $commission,
        ]);

        // Load relationships for broadcasting
        $trade->load(['buyOrder', 'sellOrder']);

        // Broadcast the OrderMatched event to both users
        // This will send real-time updates via Pusher
        event(new OrderMatched($trade));
    }

    /**
     * Add asset to user's balance.
     * Creates asset record if it doesn't exist.
     * 
     * @param User $user
     * @param string $symbol
     * @param float $amount
     */
    protected function addAssetToUser(User $user, string $symbol, float $amount): void
    {
        $asset = Asset::where('user_id', $user->id)
            ->where('symbol', $symbol)
            ->lockForUpdate()
            ->first();

        if ($asset) {
            $asset->amount += $amount;
            $asset->save();
        } else {
            Asset::create([
                'user_id' => $user->id,
                'symbol' => $symbol,
                'amount' => $amount,
                'locked_amount' => 0,
            ]);
        }
    }
}

