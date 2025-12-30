<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    /**
     * Order status constants
     */
    const STATUS_OPEN = 1;
    const STATUS_FILLED = 2;
    const STATUS_CANCELLED = 3;

    /**
     * Order side constants
     */
    const SIDE_BUY = 'buy';
    const SIDE_SELL = 'sell';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'symbol',
        'side',
        'price',
        'amount',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'amount' => 'decimal:8',
            'status' => 'integer',
        ];
    }

    /**
     * Get the user that owns the order.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get buy trades for this order.
     */
    public function buyTrades()
    {
        return $this->hasMany(Trade::class, 'buy_order_id');
    }

    /**
     * Get sell trades for this order.
     */
    public function sellTrades()
    {
        return $this->hasMany(Trade::class, 'sell_order_id');
    }

    /**
     * Check if order is open.
     */
    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    /**
     * Check if order is filled.
     */
    public function isFilled(): bool
    {
        return $this->status === self::STATUS_FILLED;
    }

    /**
     * Check if order is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }
}
