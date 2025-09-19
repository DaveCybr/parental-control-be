<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Alert;
use App\Models\FamilyMember;

class AlertTriggered implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $alert;
    public $familyId;

    public function __construct(Alert $alert)
    {
        $this->alert = $alert;

        // Get family ID for broadcasting
        $familyMember = FamilyMember::where('user_id', $alert->child_user_id)->first();
        $this->familyId = $familyMember ? $familyMember->family_id : null;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('family.' . $this->familyId),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->alert->id,
            'child_id' => $this->alert->child_user_id,
            'type' => $this->alert->type,
            'priority' => $this->alert->priority,
            'title' => $this->alert->title,
            'message' => $this->alert->message,
            'data' => $this->alert->data,
            'triggered_at' => $this->alert->triggered_at->toISOString(),
        ];
    }
}
