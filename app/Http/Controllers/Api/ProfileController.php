<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    /**
     * Get authenticated user's profile with USD balance and asset balances.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Load assets with the user
        $user->load('assets');
        
        // Format assets for response
        $assets = $user->assets->map(function ($asset) {
            return [
                'symbol' => $asset->symbol,
                'amount' => (float) $asset->amount,
                'locked_amount' => (float) $asset->locked_amount,
                'available' => (float) $asset->amount, // Available = amount (locked is separate)
            ];
        });
        
        return response()->json([
            'balance' => (float) $user->balance,
            'assets' => $assets,
        ]);
    }
}
