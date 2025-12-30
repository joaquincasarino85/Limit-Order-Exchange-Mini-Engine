<?php

use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // Profile endpoint
    Route::get('/profile', [ProfileController::class, 'show']);
    
    // Orders endpoints
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel']);
    
    // User endpoint (default from Sanctum)
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});
