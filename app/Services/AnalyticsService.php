<?php

namespace App\Services;

use App\Models\NotificationMirror;
use App\Models\Location;
use App\Models\Alert;
use Carbon\Carbon;

class AnalyticsService
{
    public function getChildAnalytics($childId, $period = 'week', $date = null)
    {
        $date = $date ? Carbon::parse($date) : now();
        [$startDate, $endDate] = $this->getPeriodRange($period, $date);

        return [
            'notifications' => $this->getNotificationAnalytics($childId, $startDate, $endDate),
            'locations' => $this->getLocationAnalytics($childId, $startDate, $endDate),
            'alerts' => $this->getAlertAnalytics($childId, $startDate, $endDate),
            'activity_patterns' => $this->getActivityPatterns($childId, $startDate, $endDate),
            'summary' => $this->getAnalyticsSummary($childId, $startDate, $endDate)
        ];
    }

    private function getNotificationAnalytics($childId, $startDate, $endDate)
    {
        $notifications = NotificationMirror::where('child_user_id', $childId)
            ->whereBetween('timestamp', [$startDate, $endDate])
            ->get();

        $byApp = $notifications->groupBy('app_package')
            ->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'percentage' => 0 // Will be calculated after
                ];
            });

        $total = $notifications->count();

        // Calculate percentages
        $byApp = $byApp->map(function ($item) use ($total) {
            $item['percentage'] = $total > 0 ? round(($item['count'] / $total) * 100, 2) : 0;
            return $item;
        });

        return [
            'total' => $total,
            'by_app' => $byApp,
            'avg_per_day' => $this->calculateDailyAverage($total, $startDate, $endDate),
            'most_active_app' => $byApp->sortByDesc('count')->keys()->first()
        ];
    }

    private function getLocationAnalytics($childId, $startDate, $endDate)
    {
        $locations = Location::where('user_id', $childId)
            ->whereBetween('timestamp', [$startDate, $endDate])
            ->orderBy('timestamp')
            ->get();

        $totalDistance = $this->calculateTotalDistance($locations);
        $averageSpeed = $this->calculateAverageSpeed($locations);

        return [
            'total_updates' => $locations->count(),
            'distance_traveled_km' => round($totalDistance / 1000, 2),
            'avg_speed_kmh' => round($averageSpeed, 2),
            'most_visited_areas' => $this->getMostVisitedAreas($locations)
        ];
    }

    private function getAlertAnalytics($childId, $startDate, $endDate)
    {
        $alerts = Alert::where('child_user_id', $childId)
            ->whereBetween('triggered_at', [$startDate, $endDate])
            ->get();

        $byType = $alerts->groupBy('type')->map->count();
        $byPriority = $alerts->groupBy('priority')->map->count();

        return [
            'total' => $alerts->count(),
            'by_type' => $byType,
            'by_priority' => $byPriority,
            'critical_count' => $byPriority->get('critical', 0),
            'resolution_rate' => $this->calculateResolutionRate($alerts)
        ];
    }

    private function getActivityPatterns($childId, $startDate, $endDate)
    {
        $notifications = NotificationMirror::where('child_user_id', $childId)
            ->whereBetween('timestamp', [$startDate, $endDate])
            ->get();

        $hourlyPattern = $notifications->groupBy(function ($notification) {
            return $notification->timestamp->format('H');
        })->map->count();

        $dailyPattern = $notifications->groupBy(function ($notification) {
            return $notification->timestamp->format('w'); // 0=Sunday, 6=Saturday
        })->map->count();

        return [
            'hourly_distribution' => $hourlyPattern,
            'daily_distribution' => $dailyPattern,
            'peak_hour' => $hourlyPattern->keys()->sortByDesc(function ($hour) use ($hourlyPattern) {
                return $hourlyPattern[$hour];
            })->first(),
            'most_active_day' => $this->getDayName($dailyPattern->keys()->sortByDesc(function ($day) use ($dailyPattern) {
                return $dailyPattern[$day];
            })->first())
        ];
    }

    private function getAnalyticsSummary($childId, $startDate, $endDate)
    {
        return [
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
                'days' => $startDate->diffInDays($endDate) + 1
            ],
            'last_activity' => $this->getLastActivity($childId),
            'safety_score' => $this->calculateSafetyScore($childId, $startDate, $endDate)
        ];
    }

    private function getPeriodRange($period, $date)
    {
        return match ($period) {
            'day' => [$date->copy()->startOfDay(), $date->copy()->endOfDay()],
            'week' => [$date->copy()->startOfWeek(), $date->copy()->endOfWeek()],
            'month' => [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()],
            default => [$date->copy()->startOfWeek(), $date->copy()->endOfWeek()]
        };
    }

    private function calculateDailyAverage($total, $startDate, $endDate)
    {
        $days = $startDate->diffInDays($endDate) + 1;
        return $days > 0 ? round($total / $days, 2) : 0;
    }

    private function calculateTotalDistance($locations)
    {
        if ($locations->count() < 2) return 0;

        $totalDistance = 0;
        $previousLocation = null;

        foreach ($locations as $location) {
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
                'visit_count' => $group->count()
            ];
        })->sortByDesc('visit_count')->take(5)->values();
    }

    private function calculateResolutionRate($alerts)
    {
        $total = $alerts->count();
        $resolved = $alerts->where('is_read', true)->count();

        return $total > 0 ? round(($resolved / $total) * 100, 2) : 0;
    }

    private function getDayName($dayNumber)
    {
        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        return $days[$dayNumber] ?? 'Unknown';
    }

    private function getLastActivity($childId)
    {
        $lastNotification = NotificationMirror::where('child_user_id', $childId)
            ->latest('timestamp')->first();

        $lastLocation = Location::where('user_id', $childId)
            ->latest('timestamp')->first();

        $activities = collect([$lastNotification?->timestamp, $lastLocation?->timestamp])
            ->filter()
            ->sort()
            ->last();

        return $activities ? $activities->diffForHumans() : 'No activity recorded';
    }

    private function calculateSafetyScore($childId, $startDate, $endDate)
    {
        $alerts = Alert::where('child_user_id', $childId)
            ->whereBetween('triggered_at', [$startDate, $endDate])
            ->get();

        $score = 100; // Start with perfect score

        // Deduct points based on alert severity
        foreach ($alerts as $alert) {
            $deduction = match ($alert->priority) {
                'critical' => 20,
                'high' => 10,
                'medium' => 5,
                'low' => 2,
                default => 0
            };
            $score -= $deduction;
        }

        return max(0, min(100, $score)); // Keep between 0-100
    }
}
