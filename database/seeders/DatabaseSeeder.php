<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create test users with initial balance
        $user1 = User::factory()->create([
            'name' => 'Test User 1',
            'email' => 'test1@example.com',
            'password' => Hash::make('password'),
            'balance' => 100000.00, // $100,000 USD
        ]);

        $user2 = User::factory()->create([
            'name' => 'Test User 2',
            'email' => 'test2@example.com',
            'password' => Hash::make('password'),
            'balance' => 100000.00, // $100,000 USD
        ]);

        // Give User 1 some BTC to buy more
        Asset::create([
            'user_id' => $user1->id,
            'symbol' => 'BTC',
            'amount' => 0.5, // 0.5 BTC
            'locked_amount' => 0,
        ]);

        // Give User 2 some BTC and ETH to sell
        Asset::create([
            'user_id' => $user2->id,
            'symbol' => 'BTC',
            'amount' => 1.0, // 1 BTC
            'locked_amount' => 0,
        ]);

        Asset::create([
            'user_id' => $user2->id,
            'symbol' => 'ETH',
            'amount' => 10.0, // 10 ETH
            'locked_amount' => 0,
        ]);
    }
}
