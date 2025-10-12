<?php

namespace App\Services;

use App\Models\Device;
use App\Models\Location;
use App\Models\Geofence;
use Illuminate\Support\Facades\Log;
use App\Services\FCMService;

class GeofenceService
{
    protected $fcmService;

    public function __construct(FCMService $fcmService)
    {
        $this->fcmService = $fcmService;
    }

    /**
     * Check geofence violations for a device location
     * 
     * @param Device $device
     * @param Location $location
     * @return array Violations array
     */
    public function checkViolations(Device $device, Location $location): array
    {
        $violations = [];

        // Get all active geofences for this device's parent
        $geofences = Geofence::where('parent_id', $device->parent_id)
            ->where('is_active', true)
            ->get();

        if ($geofences->isEmpty()) {
            return $violations;
        }

        foreach ($geofences as $geofence) {
            // Check if device is OUTSIDE the geofence radius
            if (!$geofence->isWithinRadius($location->latitude, $location->longitude)) {
                $violations[] = [
                    'geofence_id' => $geofence->id,
                    'geofence_name' => $geofence->name,
                    'latitude' => $location->latitude,
                    'longitude' => $location->longitude,
                    'timestamp' => now()->toISOString(),
                ];

                // Send FCM notification to parent
                $this->sendGeofenceAlert($device, $geofence, $location);
            }
        }

        if (!empty($violations)) {
            Log::warning('Geofence violations detected', [
                'device_id' => $device->device_id,
                'violations_count' => count($violations),
                'violations' => $violations
            ]);
        }

        return $violations;
    }

    /**
     * Send geofence alert notification to parent
     * 
     * @param Device $device
     * @param Geofence $geofence
     * @param Location $location
     */
    protected function sendGeofenceAlert(Device $device, Geofence $geofence, Location $location): void
    {
        try {
            $parent = $device->parent;

            if (!$parent || !$parent->hasValidFcmToken()) {
                Log::warning('Parent FCM token not found', [
                    'parent_id' => $device->parent_id,
                    'device_id' => $device->device_id
                ]);
                return;
            }

            // Prepare notification data
            $notification = [
                'title' => '⚠️ Geofence Alert',
                'body' => "{$device->device_name} has left {$geofence->name}",
            ];

            $data = [
                'type' => 'GEOFENCE_VIOLATION',
                'device_id' => $device->device_id,
                'device_name' => $device->device_name,
                'geofence_id' => (string) $geofence->id,
                'geofence_name' => $geofence->name,
                'latitude' => (string) $location->latitude,
                'longitude' => (string) $location->longitude,
                'timestamp' => now()->toISOString(),
            ];

            // Send FCM notification
            $result = $this->fcmService->sendNotificationToParent(
                $parent->fcm_token,
                $notification,
                $data
            );

            if ($result['success']) {
                Log::info('Geofence alert sent to parent', [
                    'parent_id' => $parent->id,
                    'device_id' => $device->device_id,
                    'geofence_name' => $geofence->name
                ]);
            } else {
                Log::error('Failed to send geofence alert', [
                    'parent_id' => $parent->id,
                    'device_id' => $device->device_id,
                    'error' => $result['message']
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Geofence alert error', [
                'device_id' => $device->device_id,
                'geofence_id' => $geofence->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Calculate distance between two coordinates (Haversine formula)
     * 
     * @param float $lat1
     * @param float $lng1
     * @param float $lat2
     * @param float $lng2
     * @return float Distance in meters
     */
    public function calculateDistance($lat1, $lng1, $lat2, $lng2): float
    {
        $earthRadius = 6371000; // meters

        $latFrom = deg2rad($lat1);
        $lonFrom = deg2rad($lng1);
        $latTo = deg2rad($lat2);
        $lonTo = deg2rad($lng2);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
            cos($latFrom) * cos($latTo) *
            sin($lonDelta / 2) * sin($lonDelta / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
