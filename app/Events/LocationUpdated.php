<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Location;
use App\Models\FamilyMember;
use Illuminate\Support\Facades\Log;

class LocationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $location;
    public $familyId;

    public function __construct(Location $location)
    {
        $this->location = $location;

        // Get family ID for broadcasting
        $familyMember = FamilyMember::where('user_id', $location->user_id)->first();
        $this->familyId = $familyMember ? $familyMember->family_id : null;

        Log::info("LocationUpdated Event: familyId={$this->familyId}, userId={$location->user_id}");
    }

    public function broadcastOn(): array
    {
        Log::info("Broadcasting to channel: private-family.{$this->familyId}");
        return [
            new PrivateChannel('family.' . $this->familyId),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'child_id' => $this->location->user_id,
            'latitude' => (float) $this->location->latitude,
            'longitude' => (float) $this->location->longitude,
            'accuracy' => (float) $this->location->accuracy,
            'battery_level' => (int) $this->location->battery_level,
            'timestamp' => $this->location->timestamp->toISOString(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'location.updated';
    }
}
