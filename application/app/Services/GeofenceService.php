<?php

namespace App\Services;

use App\Models\Device;
use App\Models\Location;
use App\Models\Geofence;
use App\Models\Notification;
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

        return $violations;
    }

    /**
     * Send geofence alert notification to parent
     */
    protected function sendGeofenceAlert(Device $device, Geofence $geofence, Location $location): void
    {
        try {
            $parent = $device->parent;

            if (!$parent || !$parent->hasValidFcmToken()) {
                return;
            }

            // Prepare notification data
            $notification = [
                'title' => 'Peringatan Keamanan Anak',
                'body'  => "Perangkat {$device->device_name} telah meninggalkan zona '{$geofence->name}'. segera cek lokasinya.",
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

            // Save to notifications table using Eloquent model
            if ($result['success']) {
                Notification::create([
                    'device_id' => $device->device_id,
                    'app_name' => 'GeofenceService',
                    'title' => $notification['title'],
                    'content' => $notification['body'],
                    'timestamp' => now(),
                ]);
            }
        } catch (\Exception $e) {
            // Silent fail - bisa tambahkan report($e) untuk monitoring di production
        }
    }

    /**
     * Calculate distance between two coordinates (Haversine formula)
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
