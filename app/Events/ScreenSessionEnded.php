<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ScreenSessionEnded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $childId;
    public $sessionToken;

    public function __construct($childId, $sessionToken)
    {
        $this->childId = $childId;
        $this->sessionToken = $sessionToken;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('child.' . $this->childId),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'session_token' => $this->sessionToken,
            'ended_at' => now()->toISOString(),
        ];
    }
}
