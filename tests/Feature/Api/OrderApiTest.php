<?php

namespace Tests\Feature\Api;

use App\Models\Asset;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test: GET /api/orders returns open orders
     */
    public function test_get_orders_returns_open_orders(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Order::factory()->create([
            'symbol' => 'BTC',
            'side' => 'buy',
            'status' => Order::STATUS_OPEN,
        ]);

        Order::factory()->create([
            'symbol' => 'BTC',
            'side' => 'sell',
            'status' => Order::STATUS_OPEN,
        ]);

        Order::factory()->create([
            'symbol' => 'BTC',
            'side' => 'buy',
            'status' => Order::STATUS_FILLED, // Should not appear
        ]);

        $response = $this->getJson('/api/orders?symbol=BTC');

        $response->assertStatus(200)
            ->assertJsonCount(2);
    }

    /**
     * Test: POST /api/orders creates a new order
     */
    public function test_create_order_endpoint_creates_order(): void
    {
        $user = User::factory()->create(['balance' => 10000.00]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 100000.00,
            'amount' => 0.01,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'symbol',
                'side',
                'price',
                'amount',
                'status',
                'created_at',
            ]);

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'symbol' => 'BTC',
            'side' => 'buy',
            'status' => Order::STATUS_OPEN,
        ]);
    }

    /**
     * Test: POST /api/orders validates input
     */
    public function test_create_order_validates_input(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/orders', [
            'symbol' => 'INVALID',
            'side' => 'invalid',
            'price' => -100,
            'amount' => -1,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['symbol', 'side', 'price', 'amount']);
    }

    /**
     * Test: POST /api/orders/{id}/cancel cancels an order
     */
    public function test_cancel_order_endpoint_cancels_order(): void
    {
        $user = User::factory()->create(['balance' => 10000.00]);
        Sanctum::actingAs($user);

        // Create a buy order (locks USD)
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'side' => 'buy',
            'price' => 100000.00,
            'amount' => 0.01,
            'status' => Order::STATUS_OPEN,
        ]);

        // Lock USD for the order
        $user->balance -= 1000.00; // 100000 * 0.01
        $user->save();

        $initialBalance = $user->balance;

        $response = $this->postJson("/api/orders/{$order->id}/cancel");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Order cancelled successfully',
            ]);

        $order->refresh();
        $this->assertEquals(Order::STATUS_CANCELLED, $order->status);

        // Balance should be restored
        $user->refresh();
        $this->assertEquals($initialBalance + 1000.00, $user->balance);
    }

    /**
     * Test: POST /api/orders/{id}/cancel only allows cancelling own orders
     */
    public function test_cancel_order_only_allows_own_orders(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        Sanctum::actingAs($user1);

        $order = Order::factory()->create([
            'user_id' => $user2->id, // Order belongs to user2
            'status' => Order::STATUS_OPEN,
        ]);

        $response = $this->postJson("/api/orders/{$order->id}/cancel");

        $response->assertStatus(404); // Not found (can't access other user's order)
    }
}
