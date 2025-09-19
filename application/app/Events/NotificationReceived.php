<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\NotificationMirror;
use App\Models\FamilyMember;

class NotificationReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $notification;
    public $familyId;

    public function __construct(NotificationMirror $notification)
    {
        $this->notification = $notification;

        // Get family ID for broadcasting
        $familyMember = FamilyMember::where('user_id', $notification->child_user_id)->first();
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
            'id' => $this->notification->id,
            'child_id' => $this->notification->child_user_id,
            'app_package' => $this->notification->app_package,
            'title' => $this->notification->title,
            'content' => $this->notification->content,
            'priority' => $this->notification->priority,
            'category' => $this->notification->category,
            'timestamp' => $this->notification->timestamp->toISOString(),
        ];
    }
}
