<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\ScreenSession;

class ScreenSessionStarted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $session;

    public function __construct(ScreenSession $session)
    {
        $this->session = $session;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('child.' . $this->session->child_user_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'session_token' => $this->session->session_token,
            'parent_id' => $this->session->parent_user_id,
            'started_at' => $this->session->started_at->toISOString(),
        ];
    }
}
