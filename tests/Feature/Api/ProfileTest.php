<?php

namespace Tests\Feature\Api;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test: GET /api/profile returns authenticated user's balance and assets
     */
    public function test_profile_endpoint_returns_user_balance_and_assets(): void
    {
        $user = User::factory()->create(['balance' => 10000.00]);
        
        Asset::create([
            'user_id' => $user->id,
            'symbol' => 'BTC',
            'amount' => 0.5,
            'locked_amount' => 0.1,
        ]);

        Asset::create([
            'user_id' => $user->id,
            'symbol' => 'ETH',
            'amount' => 10.0,
            'locked_amount' => 0,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/profile');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'balance',
                'assets' => [
                    '*' => ['symbol', 'amount', 'locked_amount', 'available'],
                ],
            ])
            ->assertJson([
                'balance' => 10000.00,
            ]);

        $assets = $response->json('assets');
        $this->assertCount(2, $assets);
    }

    /**
     * Test: GET /api/profile requires authentication
     */
    public function test_profile_endpoint_requires_authentication(): void
    {
        $response = $this->getJson('/api/profile');

        $response->assertStatus(401);
    }
}
