<?php

namespace App\Events;

use App\Models\Device;
use App\Models\Location;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LocationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $device;
    public $location;
    public $violations;

    public function __construct(Device $device, Location $location, $violations = [])
    {
        $this->device = $device;
        $this->location = $location;
        $this->violations = $violations;
    }

    public function broadcastOn()
    {
        return [
            new Channel('location-updates'),
            new Channel('device.' . $this->device->device_id),
        ];
    }

    public function broadcastAs()
    {
        return 'location.updated';
    }

    public function broadcastWith()
    {
        return [
            'device_id' => $this->device->device_id,
            'device_name' => $this->device->name,
            'latitude' => $this->location->latitude,
            'longitude' => $this->location->longitude,
            'timestamp' => $this->location->timestamp,
            'is_online' => $this->device->is_online,
            'last_seen' => $this->device->last_seen,
            'violations' => $this->violations,
        ];
    }
}
