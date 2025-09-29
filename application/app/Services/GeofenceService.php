<?php

// app/Services/GeofenceService.php
namespace App\Services;

use App\Models\Alert;
use App\Models\Device;
use App\Models\Geofence;
use App\Models\Location;

class GeofenceService
{
    public function checkViolations(Device $device, Location $location)
    {
        // Get all active geofences for this device's parent
        $geofences = Geofence::where('parent_id', $device->parent_id)
            ->where('is_active', true)
            ->get();

        foreach ($geofences as $geofence) {
            $isInside = $geofence->isWithinRadius(
                $location->latitude,
                $location->longitude
            );

            // Check if device just left the geofence
            $previousLocation = Location::where('device_id', $device->id)
                ->where('id', '<', $location->id)
                ->orderBy('timestamp', 'desc')
                ->first();

            if ($previousLocation) {
                $wasInside = $geofence->isWithinRadius(
                    $previousLocation->latitude,
                    $previousLocation->longitude
                );

                // Trigger alert if status changed
                if ($wasInside && !$isInside) {
                    $this->createAlert($device, $geofence, 'left');
                } elseif (!$wasInside && $isInside) {
                    $this->createAlert($device, $geofence, 'entered');
                }
            }
        }
    }

    private function createAlert(Device $device, Geofence $geofence, string $action)
    {
        $message = sprintf(
            '%s %s zona aman "%s"',
            $device->device_name,
            $action === 'left' ? 'keluar dari' : 'memasuki',
            $geofence->name
        );

        Alert::create([
            'parent_id' => $device->parent_id,
            'device_id' => $device->id,
            'type' => 'geofence_violation',
            'message' => $message,
            'is_read' => false,
        ]);

        // Here you can add push notification logic
        // $this->sendPushNotification($device->parent, $message);
    }
}
