<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EncyclopediaUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $encyclopediaId,
        public readonly string $type,
        public readonly array $payload = [],
    ) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('encyclopedia.'.$this->encyclopediaId)];
    }

    public function broadcastAs(): string
    {
        return 'encyclopedia.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'encyclopedia_id' => $this->encyclopediaId,
            'type' => $this->type,
            'payload' => $this->payload,
            'occurred_at' => now()->toIso8601String(),
        ];
    }
}
