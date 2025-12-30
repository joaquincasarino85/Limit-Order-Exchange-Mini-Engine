<?php

namespace App\Events;

use App\Models\Trade;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderMatched implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Trade $trade;
    public int $buyerId;
    public int $sellerId;

    /**
     * Create a new event instance.
     */
    public function __construct(Trade $trade)
    {
        $this->trade = $trade;
        $this->buyerId = $trade->buyOrder->user_id;
        $this->sellerId = $trade->sellOrder->user_id;
    }

    /**
     * Get the channels the event should broadcast on.
     * 
     * We broadcast to private channels for both buyer and seller.
     * Each user only receives events on their own private channel.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->buyerId),
            new PrivateChannel('user.' . $this->sellerId),
        ];
    }

    /**
     * The event's broadcast name.
     * 
     * This is what the frontend will listen for.
     */
    public function broadcastAs(): string
    {
        return 'order.matched';
    }

    /**
     * Get the data to broadcast.
     * 
     * This data will be sent to Pusher and received by the frontend.
     */
    public function broadcastWith(): array
    {
        return [
            'trade' => [
                'id' => $this->trade->id,
                'symbol' => $this->trade->symbol,
                'price' => (float) $this->trade->price,
                'amount' => (float) $this->trade->amount,
                'commission' => (float) $this->trade->commission,
                'buy_order_id' => $this->trade->buy_order_id,
                'sell_order_id' => $this->trade->sell_order_id,
                'created_at' => $this->trade->created_at->toISOString(),
            ],
            'buy_order' => [
                'id' => $this->trade->buyOrder->id,
                'status' => $this->trade->buyOrder->status,
            ],
            'sell_order' => [
                'id' => $this->trade->sellOrder->id,
                'status' => $this->trade->sellOrder->status,
            ],
        ];
    }
}
