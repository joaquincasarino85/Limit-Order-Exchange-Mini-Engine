<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Asset;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderCreationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test: Cannot create a BUY order without sufficient balance
     * 
     * What we validate:
     * - If user has $500 and wants to buy 0.01 BTC at $100,000
     *   they need $1,000 but only have $500 → MUST FAIL
     * 
     * Rule: balance >= (amount * price)
     */
    public function test_cannot_create_buy_order_without_sufficient_balance(): void
    {
        $user = User::factory()->create(['balance' => 500.00]); // Solo tiene $500
        $orderService = new OrderService();

        // Try to buy 0.01 BTC at $100,000 = needs $1,000
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient balance');

        $orderService->createOrder([
            'user_id' => $user->id,
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 100000.00,
            'amount' => 0.01,
        ]);
    }

    /**
     * Test: When creating a BUY order, USD must be LOCKED from balance
     * 
     * What we validate:
     * - If user has $10,000 and creates buy order for $1,000
     *   → balance should be $9,000 ($1,000 is locked)
     * - Order remains in "open" status
     */
    public function test_buy_order_locks_usd_from_balance(): void
    {
        $user = User::factory()->create(['balance' => 10000.00]);
        $orderService = new OrderService();

        // Create buy order: 0.01 BTC at $100,000 = $1,000
        $order = $orderService->createOrder([
            'user_id' => $user->id,
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 100000.00,
            'amount' => 0.01,
        ]);

        // Refresh user from DB
        $user->refresh();

        // Balance should have decreased by $1,000
        $this->assertEquals(9000.00, $user->balance);
        $this->assertEquals(Order::STATUS_OPEN, $order->status); // open
    }

    /**
     * Test: Cannot create a SELL order without sufficient assets
     * 
     * What we validate:
     * - If user has 0.5 BTC available and wants to sell 1 BTC → MUST FAIL
     * 
     * Rule: assets.amount >= order.amount
     */
    public function test_cannot_create_sell_order_without_sufficient_assets(): void
    {
        $user = User::factory()->create();
        $orderService = new OrderService();
        
        // Create asset with only 0.5 BTC
        Asset::create([
            'user_id' => $user->id,
            'symbol' => 'BTC',
            'amount' => 0.5,
            'locked_amount' => 0,
        ]);

        // Try to sell 1 BTC (but only has 0.5)
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient assets');

        $orderService->createOrder([
            'user_id' => $user->id,
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => 95000.00,
            'amount' => 1.0, // Wants to sell more than available
        ]);
    }

    /**
     * Test: When creating a SELL order, assets must be LOCKED
     * 
     * What we validate:
     * - If user has 1 BTC available and creates sell order for 0.5 BTC
     *   → amount should be 0.5 and locked_amount should be 0.5
     */
    public function test_sell_order_locks_assets(): void
    {
        $user = User::factory()->create();
        $orderService = new OrderService();
        
        $asset = Asset::create([
            'user_id' => $user->id,
            'symbol' => 'BTC',
            'amount' => 1.0,
            'locked_amount' => 0,
        ]);

        // Create sell order: 0.5 BTC
        $order = $orderService->createOrder([
            'user_id' => $user->id,
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => 95000.00,
            'amount' => 0.5,
        ]);

        // Refresh asset from DB
        $asset->refresh();

        // Available amount should decrease and locked_amount should increase
        $this->assertEquals(0.5, $asset->amount);
        $this->assertEquals(0.5, $asset->locked_amount);
    }
}
