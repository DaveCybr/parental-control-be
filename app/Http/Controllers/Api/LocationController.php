<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\FamilyMember;
use App\Models\Alert;
use App\Models\User;
use App\Services\GeofenceService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Events\LocationUpdated;
use App\Events\AlertTriggered;

class LocationController extends Controller
{
    protected $geofenceService;

    public function __construct(GeofenceService $geofenceService)
    {
        $this->geofenceService = $geofenceService;
    }

    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'accuracy' => 'required|numeric|min:0',
            'battery_level' => 'required|integer|between:0,100',
        ]);

        $user = $request->user();

        // Check for low battery alert
        if ($request->battery_level <= 20) {
            $this->triggerLowBatteryAlert($user->id, $request->battery_level);
        }

        $location = Location::create([
            'user_id' => $user->id,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'accuracy' => $request->accuracy,
            'battery_level' => $request->battery_level,
            'timestamp' => now(),
        ]);

        // Check geofence violations using the service
        $this->geofenceService->checkViolations(
            $user->id,
            $request->latitude,
            $request->longitude
        );

        // Broadcast real-time location update
        broadcast(new LocationUpdated($location));

        return response()->json([
            'success' => true,
            'message' => 'Location updated successfully',
            'location_id' => $location->id
        ]);
    }

    public function track($childId): JsonResponse
    {
        // Verify parent-child relationship
        if (!$this->verifyParentChildRelationship(auth()->user()->id, $childId)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $latestLocation = Location::where('user_id', $childId)
            ->latest('timestamp')
            ->first();

        if (!$latestLocation) {
            return response()->json([
                'success' => false,
                'message' => 'No location data found'
            ], 404);
        }

        // Calculate time since last update
        $lastUpdate = now()->diffInMinutes($latestLocation->timestamp);

        // Get child info
        $child = User::find($childId);

        return response()->json([
            'success' => true,
            'child' => [
                'id' => $child->id,
                'name' => $child->name,
            ],
            'location' => $latestLocation,
            'last_update_minutes' => $lastUpdate,
            'is_recent' => $lastUpdate <= 10, // Consider recent if within 10 minutes
            'status' => $this->getLocationStatus($latestLocation, $lastUpdate)
        ]);
    }

    public function history($childId, Request $request): JsonResponse
    {
        $request->validate([
            'date' => 'nullable|date',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'limit' => 'nullable|integer|min:1|max:1000'
        ]);

        if (!$this->verifyParentChildRelationship(auth()->user()->id, $childId)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $query = Location::where('user_id', $childId);

        if ($request->date) {
            $query->whereDate('timestamp', $request->date);
        } elseif ($request->start_date && $request->end_date) {
            $query->whereBetween('timestamp', [$request->start_date, $request->end_date]);
        } else {
            // Default to last 7 days
            $query->where('timestamp', '>=', now()->subWeek());
        }

        $locations = $query->orderBy('timestamp', 'desc')
            ->limit($request->limit ?? 200)
            ->get();

        // Calculate total distance traveled (approximate)
        $totalDistance = $this->calculateTotalDistance($locations);

        // Get child info
        $child = User::find($childId);

        return response()->json([
            'success' => true,
            'child' => [
                'id' => $child->id,
                'name' => $child->name,
            ],
            'locations' => $locations,
            'total_points' => $locations->count(),
            'approximate_distance_km' => round($totalDistance / 1000, 2),
            'date_range' => [
                'start' => $locations->last()?->timestamp,
                'end' => $locations->first()?->timestamp
            ]
        ]);
    }

    public function trackAllChildren(): JsonResponse
    {
        $user = auth()->user();

        if ($user->role !== 'parent') {
            return response()->json([
                'success' => false,
                'message' => 'Only parents can access this endpoint'
            ], 403);
        }

        $familyMember = FamilyMember::where('user_id', $user->id)->first();

        if (!$familyMember) {
            return response()->json([
                'success' => false,
                'message' => 'Not part of any family'
            ], 400);
        }

        // Get all children in the family
        $children = FamilyMember::with('user')
            ->where('family_id', $familyMember->family_id)
            ->where('role', 'child')
            ->get();

        $childrenLocations = [];

        foreach ($children as $child) {
            $latestLocation = Location::where('user_id', $child->user_id)
                ->latest('timestamp')
                ->first();

            $lastUpdate = $latestLocation ?
                now()->diffInMinutes($latestLocation->timestamp) : null;

            $childrenLocations[] = [
                'child' => [
                    'id' => $child->user->id,
                    'name' => $child->user->name,
                    'email' => $child->user->email,
                ],
                'location' => $latestLocation,
                'last_update_minutes' => $lastUpdate,
                'is_recent' => $lastUpdate && $lastUpdate <= 10,
                'status' => $this->getLocationStatus($latestLocation, $lastUpdate)
            ];
        }

        return response()->json([
            'success' => true,
            'children_locations' => $childrenLocations,
            'total_children' => $children->count(),
            'last_updated' => now()->toISOString()
        ]);
    }

    /**
     * Get location statistics for a child
     */
    public function statistics($childId, Request $request): JsonResponse
    {
        $request->validate([
            'period' => 'nullable|in:today,week,month',
        ]);

        if (!$this->verifyParentChildRelationship(auth()->user()->id, $childId)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $period = $request->period ?? 'week';

        switch ($period) {
            case 'today':
                $startDate = now()->startOfDay();
                $endDate = now()->endOfDay();
                break;
            case 'week':
                $startDate = now()->startOfWeek();
                $endDate = now()->endOfWeek();
                break;
            case 'month':
                $startDate = now()->startOfMonth();
                $endDate = now()->endOfMonth();
                break;
        }

        $locations = Location::where('user_id', $childId)
            ->whereBetween('timestamp', [$startDate, $endDate])
            ->orderBy('timestamp')
            ->get();

        $totalDistance = $this->calculateTotalDistance($locations);
        $averageSpeed = $this->calculateAverageSpeed($locations);

        // Battery level analytics
        $batteryStats = [
            'average' => $locations->avg('battery_level'),
            'minimum' => $locations->min('battery_level'),
            'low_battery_incidents' => $locations->where('battery_level', '<=', 20)->count()
        ];

        return response()->json([
            'success' => true,
            'statistics' => [
                'period' => $period,
                'total_updates' => $locations->count(),
                'distance_km' => round($totalDistance / 1000, 2),
                'avg_speed_kmh' => round($averageSpeed, 2),
                'battery_stats' => $batteryStats,
                'most_visited_areas' => $this->getMostVisitedAreas($locations),
                'date_range' => [
                    'start' => $startDate->toDateString(),
                    'end' => $endDate->toDateString()
                ]
            ]
        ]);
    }

    private function triggerLowBatteryAlert($childId, $batteryLevel): void
    {
        // Check if we already sent a low battery alert in the last 2 hours
        $recentAlert = Alert::where('child_user_id', $childId)
            ->where('type', 'battery')
            ->where('triggered_at', '>=', now()->subHours(2))
            ->first();

        if (!$recentAlert) {
            $priority = match (true) {
                $batteryLevel <= 5 => 'critical',
                $batteryLevel <= 10 => 'high',
                default => 'medium'
            };

            $alert = Alert::create([
                'child_user_id' => $childId,
                'type' => 'battery',
                'priority' => $priority,
                'title' => 'Low Battery Alert',
                'message' => "Child's device battery is at {$batteryLevel}%",
                'data' => [
                    'battery_level' => $batteryLevel,
                    'alert_threshold' => 20,
                    'location_tracking_affected' => $batteryLevel <= 10
                ],
                'triggered_at' => now()
            ]);

            broadcast(new AlertTriggered($alert));
        }
    }

    private function calculateTotalDistance($locations)
    {
        if ($locations->count() < 2) {
            return 0;
        }

        $totalDistance = 0;
        $previousLocation = null;

        foreach ($locations->reverse() as $location) {
            if ($previousLocation) {
                $distance = $this->calculateDistance(
                    $previousLocation->latitude,
                    $previousLocation->longitude,
                    $location->latitude,
                    $location->longitude
                );
                $totalDistance += $distance;
            }
            $previousLocation = $location;
        }

        return $totalDistance;
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

    private function calculateAverageSpeed($locations)
    {
        if ($locations->count() < 2) return 0;

        $totalDistance = $this->calculateTotalDistance($locations);
        $totalTime = $locations->first()->timestamp->diffInHours($locations->last()->timestamp);

        return $totalTime > 0 ? ($totalDistance / 1000) / $totalTime : 0; // km/h
    }

    private function getMostVisitedAreas($locations)
    {
        // Simplified area clustering - group by approximate coordinates
        $areas = $locations->groupBy(function ($location) {
            return round($location->latitude, 3) . ',' . round($location->longitude, 3);
        });

        return $areas->map(function ($group, $coords) {
            [$lat, $lon] = explode(',', $coords);
            return [
                'latitude' => (float)$lat,
                'longitude' => (float)$lon,
                'visit_count' => $group->count(),
                'first_visit' => $group->min('timestamp'),
                'last_visit' => $group->max('timestamp'),
            ];
        })->sortByDesc('visit_count')->take(5)->values();
    }

    private function getLocationStatus($location, $lastUpdateMinutes)
    {
        if (!$location) {
            return 'no_data';
        }

        if ($lastUpdateMinutes <= 5) {
            return 'online';
        } elseif ($lastUpdateMinutes <= 30) {
            return 'recent';
        } elseif ($lastUpdateMinutes <= 120) {
            return 'offline';
        } else {
            return 'inactive';
        }
    }

    private function verifyParentChildRelationship($parentId, $childId): bool
    {
        $parentMember = FamilyMember::where('user_id', $parentId)
            ->where('role', 'parent')
            ->first();

        if (!$parentMember) return false;

        $childMember = FamilyMember::where('user_id', $childId)
            ->where('family_id', $parentMember->family_id)
            ->where('role', 'child')
            ->first();

        return $childMember !== null;
    }
}
