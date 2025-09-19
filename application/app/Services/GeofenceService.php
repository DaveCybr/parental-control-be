<?php

// app/Services/GeofenceService.php
namespace App\Services;

use App\Models\Geofence;
use App\Models\Alert;
use App\Models\FamilyMember;

class GeofenceService
{
    public function checkViolations($childId, $latitude, $longitude)
    {
        $childMember = FamilyMember::where('user_id', $childId)->first();
        if (!$childMember) return;

        $geofences = Geofence::where('family_id', $childMember->family_id)
            ->where('is_active', true)
            ->get();

        foreach ($geofences as $geofence) {
            $distance = $this->calculateDistance(
                $latitude,
                $longitude,
                $geofence->center_latitude,
                $geofence->center_longitude
            );

            $this->processGeofenceViolation($childId, $geofence, $distance);
        }
    }

    private function processGeofenceViolation($childId, $geofence, $distance)
    {
        $isInside = $distance <= $geofence->radius;
        $violationType = null;
        $priority = 'medium';

        if ($geofence->type === 'safe' && !$isInside) {
            $violationType = 'left_safe_zone';
            $priority = 'high';
        } elseif ($geofence->type === 'danger' && $isInside) {
            $violationType = 'entered_danger_zone';
            $priority = 'critical';
        }

        if ($violationType) {
            // Check if we already alerted for this geofence recently (prevent spam)
            $recentAlert = Alert::where('child_user_id', $childId)
                ->where('type', 'geofence')
                ->where('data->geofence_id', $geofence->id)
                ->where('triggered_at', '>=', now()->subMinutes(30))
                ->first();

            if (!$recentAlert) {
                Alert::create([
                    'child_user_id' => $childId,
                    'type' => 'geofence',
                    'priority' => $priority,
                    'title' => $this->getGeofenceAlertTitle($violationType),
                    'message' => $this->getGeofenceAlertMessage($violationType, $geofence->name),
                    'data' => [
                        'geofence_id' => $geofence->id,
                        'geofence_name' => $geofence->name,
                        'geofence_type' => $geofence->type,
                        'distance' => $distance,
                        'violation_type' => $violationType
                    ],
                    'triggered_at' => now()
                ]);
            }
        }
    }

    private function getGeofenceAlertTitle($violationType)
    {
        return match ($violationType) {
            'left_safe_zone' => 'Left Safe Zone',
            'entered_danger_zone' => 'Entered Danger Zone',
            default => 'Geofence Alert'
        };
    }

    private function getGeofenceAlertMessage($violationType, $geofenceName)
    {
        return match ($violationType) {
            'left_safe_zone' => "Child has left the safe zone: {$geofenceName}",
            'entered_danger_zone' => "Child has entered the danger zone: {$geofenceName}",
            default => "Geofence violation at: {$geofenceName}"
        };
    }

    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371000; // meters
        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
