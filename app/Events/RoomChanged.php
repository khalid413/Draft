<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class RoomChanged implements ShouldBroadcastNow
{
    public function __construct(private string $roomId, public int $version) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('room.'.$this->roomId)];
    }

    public function broadcastAs(): string
    {
        return 'room.changed';
    }

    public function broadcastWith(): array
    {
        return ['version' => $this->version];
    }
}
