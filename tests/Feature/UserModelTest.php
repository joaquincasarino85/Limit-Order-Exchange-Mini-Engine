<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Asset;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test: Un usuario puede tener múltiples assets (BTC, ETH, etc.)
     * 
     * ¿Qué validamos?
     * - La relación hasMany entre User y Asset funciona
     * - Podemos crear assets para un usuario
     */
    public function test_user_can_have_multiple_assets(): void
    {
        $user = User::factory()->create();

        // Crear assets para el usuario
        $btcAsset = Asset::factory()->create([
            'user_id' => $user->id,
            'symbol' => 'BTC',
            'amount' => 1.5,
        ]);

        $ethAsset = Asset::factory()->create([
            'user_id' => $user->id,
            'symbol' => 'ETH',
            'amount' => 10.0,
        ]);

        // Verificar que el usuario tiene los assets
        $this->assertCount(2, $user->assets);
        $this->assertTrue($user->assets->contains($btcAsset));
        $this->assertTrue($user->assets->contains($ethAsset));
    }

    /**
     * Test: Un usuario puede tener múltiples órdenes
     * 
     * ¿Qué validamos?
     * - La relación hasMany entre User y Order funciona
     */
    public function test_user_can_have_multiple_orders(): void
    {
        $user = User::factory()->create(['balance' => 10000]);

        $order1 = Order::factory()->create([
            'user_id' => $user->id,
            'side' => 'buy',
            'symbol' => 'BTC',
        ]);

        $order2 = Order::factory()->create([
            'user_id' => $user->id,
            'side' => 'sell',
            'symbol' => 'ETH',
        ]);

        $this->assertCount(2, $user->orders);
    }

    /**
     * Test: El balance inicial de un usuario es 0
     * 
     * ¿Qué validamos?
     * - Por defecto, los usuarios empiezan con balance 0
     */
    public function test_user_has_zero_balance_by_default(): void
    {
        $user = User::factory()->create();

        $this->assertEquals(0, $user->balance);
    }
}
