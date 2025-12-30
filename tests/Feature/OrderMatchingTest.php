<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Asset;
use App\Models\Order;
use App\Models\Trade;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderMatchingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test: A BUY order should match with the first valid SELL order
     * 
     * Matching rule:
     * - New BUY order matches with first SELL where: sell.price <= buy.price
     * 
     * Scenario:
     * - User A has SELL order: 0.01 BTC at $95,000
     * - User B creates BUY order: 0.01 BTC at $100,000
     * - Should match because $95,000 <= $100,000
     */
    public function test_buy_order_matches_with_sell_order_when_price_is_valid(): void
    {
        $orderService = new OrderService();

        // User A: wants to sell BTC
        $seller = User::factory()->create();
        Asset::create([
            'user_id' => $seller->id,
            'symbol' => 'BTC',
            'amount' => 0.5,
            'locked_amount' => 0,
        ]);

        // User B: wants to buy BTC
        $buyer = User::factory()->create(['balance' => 10000.00]);

        // Create SELL order: 0.01 BTC at $95,000
        $sellOrder = $orderService->createOrder([
            'user_id' => $seller->id,
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => 95000.00,
            'amount' => 0.01,
        ]);

        // Create BUY order: 0.01 BTC at $100,000
        // This order should automatically match with the sell order
        $buyOrder = $orderService->createOrder([
            'user_id' => $buyer->id,
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 100000.00,
            'amount' => 0.01,
        ]);

        // Refresh orders from DB
        $buyOrder->refresh();
        $sellOrder->refresh();

        // After match:
        // - Both orders should be status = 2 (filled)
        $this->assertEquals(Order::STATUS_FILLED, $buyOrder->status);
        $this->assertEquals(Order::STATUS_FILLED, $sellOrder->status);

        // - Buyer should receive 0.01 BTC
        $buyerAsset = Asset::where('user_id', $buyer->id)
            ->where('symbol', 'BTC')
            ->first();
        $this->assertNotNull($buyerAsset);
        $this->assertEquals(0.01, $buyerAsset->amount);

        // - Trade record should be created
        $trade = Trade::where('buy_order_id', $buyOrder->id)
            ->where('sell_order_id', $sellOrder->id)
            ->first();
        $this->assertNotNull($trade);
    }

    /**
     * Test: A SELL order should match with the first valid BUY order
     * 
     * Matching rule:
     * - New SELL order matches with first BUY where: buy.price >= sell.price
     */
    public function test_sell_order_matches_with_buy_order_when_price_is_valid(): void
    {
        $orderService = new OrderService();

        // User A: wants to buy BTC
        $buyer = User::factory()->create(['balance' => 10000.00]);

        // User B: wants to sell BTC
        $seller = User::factory()->create();
        Asset::create([
            'user_id' => $seller->id,
            'symbol' => 'BTC',
            'amount' => 0.5,
            'locked_amount' => 0,
        ]);

        // Create BUY order: 0.01 BTC at $100,000
        $buyOrder = $orderService->createOrder([
            'user_id' => $buyer->id,
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 100000.00,
            'amount' => 0.01,
        ]);

        // Create SELL order: 0.01 BTC at $95,000
        // This order should automatically match with the buy order
        $sellOrder = $orderService->createOrder([
            'user_id' => $seller->id,
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => 95000.00,
            'amount' => 0.01,
        ]);

        // Refresh orders from DB
        $buyOrder->refresh();
        $sellOrder->refresh();

        // Both orders should be filled
        $this->assertEquals(Order::STATUS_FILLED, $buyOrder->status);
        $this->assertEquals(Order::STATUS_FILLED, $sellOrder->status);
    }

    /**
     * Test: Orders should NOT match when prices don't overlap
     * 
     * Scenario:
     * - SELL order: 0.01 BTC at $100,000
     * - BUY order: 0.01 BTC at $95,000
     * - Should NOT match because $100,000 > $95,000
     */
    public function test_orders_do_not_match_when_prices_dont_overlap(): void
    {
        $orderService = new OrderService();

        // User A: wants to sell BTC at high price
        $seller = User::factory()->create();
        Asset::create([
            'user_id' => $seller->id,
            'symbol' => 'BTC',
            'amount' => 0.5,
            'locked_amount' => 0,
        ]);

        // User B: wants to buy BTC at low price
        $buyer = User::factory()->create(['balance' => 10000.00]);

        // Create SELL order: 0.01 BTC at $100,000
        $sellOrder = $orderService->createOrder([
            'user_id' => $seller->id,
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => 100000.00,
            'amount' => 0.01,
        ]);

        // Create BUY order: 0.01 BTC at $95,000
        // Should NOT match because buy price < sell price
        $buyOrder = $orderService->createOrder([
            'user_id' => $buyer->id,
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 95000.00,
            'amount' => 0.01,
        ]);

        // Refresh orders from DB
        $buyOrder->refresh();
        $sellOrder->refresh();

        // Both orders should remain open (not matched)
        $this->assertEquals(Order::STATUS_OPEN, $buyOrder->status);
        $this->assertEquals(Order::STATUS_OPEN, $sellOrder->status);

        // No trade should be created
        $trade = Trade::where('buy_order_id', $buyOrder->id)
            ->where('sell_order_id', $sellOrder->id)
            ->first();
        $this->assertNull($trade);
    }

    /**
     * Test: After match, balances should update correctly
     * 
     * Scenario: Match of 0.01 BTC at $95,000
     * - Buyer: loses $950 + commission, gains 0.01 BTC
     * - Seller: loses 0.01 BTC, gains $950
     * - Commission: 1.5% of $950 = $14.25
     */
    public function test_balances_update_correctly_after_match(): void
    {
        $orderService = new OrderService();

        // User A: wants to sell BTC
        $seller = User::factory()->create(['balance' => 0]);
        Asset::create([
            'user_id' => $seller->id,
            'symbol' => 'BTC',
            'amount' => 1.0,
            'locked_amount' => 0,
        ]);

        // User B: wants to buy BTC
        $buyer = User::factory()->create(['balance' => 10000.00]);

        // Create SELL order: 0.01 BTC at $95,000
        $sellOrder = $orderService->createOrder([
            'user_id' => $seller->id,
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => 95000.00,
            'amount' => 0.01,
        ]);

        // Create BUY order: 0.01 BTC at $100,000
        // This will match at $95,000 (sell order price)
        $buyOrder = $orderService->createOrder([
            'user_id' => $buyer->id,
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 100000.00,
            'amount' => 0.01,
        ]);

        // Refresh users from DB
        $buyer->refresh();
        $seller->refresh();

        // Buyer: started with $10,000
        // Locked: $1,000 (100,000 * 0.01)
        // Paid: $950 (95,000 * 0.01)
        // Commission: $14.25
        // Difference returned: $50
        // Final balance: $10,000 - $1,000 + $50 - $14.25 = $9,035.75
        $expectedBuyerBalance = 10000.00 - 1000.00 + 50.00 - 14.25;
        $this->assertEquals($expectedBuyerBalance, $buyer->balance);

        // Seller: started with $0, gains $950
        $this->assertEquals(950.00, $seller->balance);

        // Buyer should have 0.01 BTC
        $buyerAsset = Asset::where('user_id', $buyer->id)
            ->where('symbol', 'BTC')
            ->first();
        $this->assertNotNull($buyerAsset);
        $this->assertEquals(0.01, $buyerAsset->amount);
    }

    /**
     * Test: Commission of 1.5% is calculated correctly
     * 
     * Example from requirements:
     * - 0.01 BTC @ $95,000 = volume of $950
     * - Commission: $950 * 0.015 = $14.25
     */
    public function test_commission_is_calculated_correctly(): void
    {
        $orderService = new OrderService();

        // User A: wants to sell BTC
        $seller = User::factory()->create();
        Asset::create([
            'user_id' => $seller->id,
            'symbol' => 'BTC',
            'amount' => 1.0,
            'locked_amount' => 0,
        ]);

        // User B: wants to buy BTC
        $buyer = User::factory()->create(['balance' => 10000.00]);

        // Create SELL order: 0.01 BTC at $95,000
        $sellOrder = $orderService->createOrder([
            'user_id' => $seller->id,
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => 95000.00,
            'amount' => 0.01,
        ]);

        // Create BUY order: 0.01 BTC at $100,000
        $buyOrder = $orderService->createOrder([
            'user_id' => $buyer->id,
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 100000.00,
            'amount' => 0.01,
        ]);

        // Check trade record
        $trade = Trade::where('buy_order_id', $buyOrder->id)
            ->where('sell_order_id', $sellOrder->id)
            ->first();

        $this->assertNotNull($trade);
        
        // Volume: 0.01 BTC * $95,000 = $950
        $expectedVolume = 950.00;
        $expectedCommission = 14.25; // $950 * 0.015

        $this->assertEquals($expectedVolume, $trade->price * $trade->amount);
        $this->assertEquals($expectedCommission, $trade->commission);
    }
}
