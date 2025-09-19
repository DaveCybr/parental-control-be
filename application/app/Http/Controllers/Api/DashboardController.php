<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FamilyMember;
use App\Models\Location;
use App\Models\NotificationMirror;
use App\Models\Alert;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function parentDashboard(): JsonResponse
    {
        $user = auth()->user();

        $familyMember = FamilyMember::where('user_id', $user->id)
            ->where('role', 'parent')
            ->first();

        if (!$familyMember) {
            return response()->json([
                'success' => false,
                'message' => 'Not a parent user'
            ], 403);
        }

        // Get all children in the family
        $children = FamilyMember::with('user')
            ->where('family_id', $familyMember->family_id)
            ->where('role', 'child')
            ->get();

        $childrenData = [];
        foreach ($children as $child) {
            // Latest location
            $latestLocation = Location::where('user_id', $child->user_id)
                ->latest('timestamp')
                ->first();

            // Today's notifications count
            $todayNotifications = NotificationMirror::where('child_user_id', $child->user_id)
                ->whereDate('timestamp', today())
                ->count();

            // Unread alerts count
            $unreadAlerts = Alert::where('child_user_id', $child->user_id)
                ->where('is_read', false)
                ->count();

            // Critical alerts count (last 24 hours)
            $criticalAlerts = Alert::where('child_user_id', $child->user_id)
                ->where('priority', 'critical')
                ->where('triggered_at', '>=', now()->subDay())
                ->count();

            $childrenData[] = [
                'child' => $child->user,
                'latest_location' => $latestLocation,
                'today_notifications' => $todayNotifications,
                'unread_alerts' => $unreadAlerts,
                'critical_alerts_24h' => $criticalAlerts,
                'last_seen' => $latestLocation ? $latestLocation->timestamp : null,
                'battery_level' => $latestLocation ? $latestLocation->battery_level : null,
            ];
        }

        // Family overview stats
        $totalAlerts = Alert::whereIn('child_user_id', $children->pluck('user_id'))
            ->where('triggered_at', '>=', now()->subWeek())
            ->count();

        $totalNotifications = NotificationMirror::whereIn('child_user_id', $children->pluck('user_id'))
            ->whereDate('timestamp', today())
            ->count();

        return response()->json([
            'success' => true,
            'dashboard' => [
                'children' => $childrenData,
                'family_stats' => [
                    'total_children' => $children->count(),
                    'alerts_this_week' => $totalAlerts,
                    'notifications_today' => $totalNotifications,
                ]
            ]
        ]);
    }

    public function childDashboard(): JsonResponse
    {
        $user = auth()->user();

        if ($user->role !== 'child') {
            return response()->json([
                'success' => false,
                'message' => 'Not a child user'
            ], 403);
        }

        // Today's stats
        $todayNotifications = NotificationMirror::where('child_user_id', $user->id)
            ->whereDate('timestamp', today())
            ->count();

        $weeklyNotifications = NotificationMirror::where('child_user_id', $user->id)
            ->where('timestamp', '>=', now()->subWeek())
            ->count();

        // Location updates today
        $todayLocationUpdates = Location::where('user_id', $user->id)
            ->whereDate('timestamp', today())
            ->count();

        // Active alerts
        $activeAlerts = Alert::where('child_user_id', $user->id)
            ->where('is_read', false)
            ->orderBy('triggered_at', 'desc')
            ->limit(5)
            ->get();

        // Get family info
        $familyMember = FamilyMember::where('user_id', $user->id)->first();
        $family = $familyMember ? $familyMember->family : null;

        return response()->json([
            'success' => true,
            'dashboard' => [
                'user' => $user,
                'family' => $family,
                'stats' => [
                    'notifications_today' => $todayNotifications,
                    'notifications_week' => $weeklyNotifications,
                    'location_updates_today' => $todayLocationUpdates,
                    'active_alerts' => $activeAlerts->count(),
                ],
                'recent_alerts' => $activeAlerts
            ]
        ]);
    }

    public function childAnalytics($childId, Request $request): JsonResponse
    {
        $request->validate([
            'period' => 'nullable|in:day,week,month',
            'date' => 'nullable|date'
        ]);

        if (!$this->verifyParentChildRelationship(auth()->user()->id, $childId)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $period = $request->period ?? 'week';
        $date = $request->date ? Carbon::parse($request->date) : now();

        switch ($period) {
            case 'day':
                $startDate = $date->startOfDay();
                $endDate = $date->endOfDay();
                break;
            case 'week':
                $startDate = $date->startOfWeek();
                $endDate = $date->endOfWeek();
                break;
            case 'month':
                $startDate = $date->startOfMonth();
                $endDate = $date->endOfMonth();
                break;
        }

        // Notifications analytics
        $notificationStats = NotificationMirror::where('child_user_id', $childId)
            ->whereBetween('timestamp', [$startDate, $endDate])
            ->selectRaw('app_package, COUNT(*) as count')
            ->groupBy('app_package')
            ->orderBy('count', 'desc')
            ->get();

        // Location analytics
        $locationCount = Location::where('user_id', $childId)
            ->whereBetween('timestamp', [$startDate, $endDate])
            ->count();

        // Alerts analytics
        $alertStats = Alert::where('child_user_id', $childId)
            ->whereBetween('triggered_at', [$startDate, $endDate])
            ->selectRaw('type, priority, COUNT(*) as count')
            ->groupBy('type', 'priority')
            ->get();

        // Daily activity pattern (hourly breakdown)
        $hourlyActivity = NotificationMirror::where('child_user_id', $childId)
            ->whereBetween('timestamp', [$startDate, $endDate])
            ->selectRaw('HOUR(timestamp) as hour, COUNT(*) as count')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        return response()->json([
            'success' => true,
            'analytics' => [
                'period' => $period,
                'date_range' => [
                    'start' => $startDate->toISOString(),
                    'end' => $endDate->toISOString()
                ],
                'notifications' => [
                    'total' => $notificationStats->sum('count'),
                    'by_app' => $notificationStats
                ],
                'locations' => [
                    'total_updates' => $locationCount
                ],
                'alerts' => [
                    'total' => $alertStats->sum('count'),
                    'by_type_priority' => $alertStats
                ],
                'hourly_activity' => $hourlyActivity
            ]
        ]);
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
