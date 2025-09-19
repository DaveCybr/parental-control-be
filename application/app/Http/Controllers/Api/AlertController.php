<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\FamilyMember;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Events\AlertTriggered;

class AlertController extends Controller
{
    public function trigger(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:geofence,emergency,content,battery',
            'priority' => 'required|in:critical,high,medium,low',
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:1000',
            'data' => 'nullable|array',
        ]);

        $user = $request->user();

        $alert = Alert::create([
            'child_user_id' => $user->id,
            'type' => $request->type,
            'priority' => $request->priority,
            'title' => $request->title,
            'message' => $request->message,
            'data' => $request->data ?? [],
            'triggered_at' => now(),
        ]);

        // Broadcast alert to parents
        broadcast(new AlertTriggered($alert));

        return response()->json([
            'success' => true,
            'alert' => $alert,
            'message' => 'Alert triggered successfully'
        ], 201);
    }

    public function emergency(Request $request): JsonResponse
    {
        $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'emergency_type' => 'required|in:panic,accident,help,medical',
            'message' => 'nullable|string|max:500',
        ]);

        $user = $request->user();

        $alert = Alert::create([
            'child_user_id' => $user->id,
            'type' => 'emergency',
            'priority' => 'critical',
            'title' => 'Emergency Alert - ' . ucfirst($request->emergency_type),
            'message' => $request->message ?? 'Emergency button pressed by child',
            'data' => [
                'emergency_type' => $request->emergency_type,
                'location' => [
                    'latitude' => $request->latitude,
                    'longitude' => $request->longitude,
                ],
                'timestamp' => now()->toISOString(),
                'device_info' => [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]
            ],
            'triggered_at' => now(),
        ]);

        // Broadcast critical emergency alert
        broadcast(new AlertTriggered($alert));

        // You could also add SMS/Email notifications here for critical alerts
        // $this->sendEmergencyNotifications($alert);

        return response()->json([
            'success' => true,
            'alert' => $alert,
            'message' => 'Emergency alert sent successfully'
        ], 201);
    }

    public function list(Request $request): JsonResponse
    {
        $request->validate([
            'child_id' => 'nullable|integer',
            'type' => 'nullable|in:geofence,emergency,content,battery',
            'priority' => 'nullable|in:critical,high,medium,low',
            'is_read' => 'nullable|boolean',
            'limit' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        $user = auth()->user();

        if ($user->role === 'parent') {
            // Parent can see alerts for all their children
            $familyMember = FamilyMember::where('user_id', $user->id)->first();

            if (!$familyMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'Not part of any family'
                ], 400);
            }

            // Get all children in family
            $childrenIds = FamilyMember::where('family_id', $familyMember->family_id)
                ->where('role', 'child')
                ->pluck('user_id');

            $query = Alert::whereIn('child_user_id', $childrenIds);

            // Filter by specific child if requested
            if ($request->child_id) {
                if (!$childrenIds->contains($request->child_id)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Child not found in your family'
                    ], 404);
                }
                $query->where('child_user_id', $request->child_id);
            }
        } else {
            // Child can only see their own alerts
            $query = Alert::where('child_user_id', $user->id);
        }

        // Apply filters
        if ($request->type) {
            $query->where('type', $request->type);
        }

        if ($request->priority) {
            $query->where('priority', $request->priority);
        }

        if ($request->has('is_read')) {
            $query->where('is_read', $request->is_read);
        }

        // Pagination
        $limit = $request->limit ?? 20;
        $page = $request->page ?? 1;

        $alerts = $query->with('child:id,name,email')
            ->orderBy('triggered_at', 'desc')
            ->paginate($limit, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'alerts' => $alerts->items(),
            'pagination' => [
                'current_page' => $alerts->currentPage(),
                'last_page' => $alerts->lastPage(),
                'per_page' => $alerts->perPage(),
                'total' => $alerts->total(),
            ]
        ]);
    }

    public function markRead(Request $request): JsonResponse
    {
        $request->validate([
            'alert_ids' => 'required|array',
            'alert_ids.*' => 'integer|exists:alerts,id',
        ]);

        $user = auth()->user();
        $alertIds = $request->alert_ids;

        if ($user->role === 'parent') {
            // Verify parent can access these alerts
            $familyMember = FamilyMember::where('user_id', $user->id)->first();

            if (!$familyMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'Not part of any family'
                ], 400);
            }

            $childrenIds = FamilyMember::where('family_id', $familyMember->family_id)
                ->where('role', 'child')
                ->pluck('user_id');

            $validAlerts = Alert::whereIn('id', $alertIds)
                ->whereIn('child_user_id', $childrenIds)
                ->pluck('id');
        } else {
            // Child can only mark their own alerts as read
            $validAlerts = Alert::whereIn('id', $alertIds)
                ->where('child_user_id', $user->id)
                ->pluck('id');
        }

        $updatedCount = Alert::whereIn('id', $validAlerts)
            ->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'updated_count' => $updatedCount,
            'message' => "{$updatedCount} alerts marked as read"
        ]);
    }

    public function unreadCount(): JsonResponse
    {
        $user = auth()->user();

        if ($user->role === 'parent') {
            $familyMember = FamilyMember::where('user_id', $user->id)->first();

            if (!$familyMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'Not part of any family'
                ], 400);
            }

            $childrenIds = FamilyMember::where('family_id', $familyMember->family_id)
                ->where('role', 'child')
                ->pluck('user_id');

            $unreadCount = Alert::whereIn('child_user_id', $childrenIds)
                ->where('is_read', false)
                ->count();

            // Count by priority
            $priorityCounts = Alert::whereIn('child_user_id', $childrenIds)
                ->where('is_read', false)
                ->selectRaw('priority, COUNT(*) as count')
                ->groupBy('priority')
                ->pluck('count', 'priority');
        } else {
            $unreadCount = Alert::where('child_user_id', $user->id)
                ->where('is_read', false)
                ->count();

            $priorityCounts = Alert::where('child_user_id', $user->id)
                ->where('is_read', false)
                ->selectRaw('priority, COUNT(*) as count')
                ->groupBy('priority')
                ->pluck('count', 'priority');
        }

        return response()->json([
            'success' => true,
            'unread_count' => $unreadCount,
            'by_priority' => [
                'critical' => $priorityCounts['critical'] ?? 0,
                'high' => $priorityCounts['high'] ?? 0,
                'medium' => $priorityCounts['medium'] ?? 0,
                'low' => $priorityCounts['low'] ?? 0,
            ]
        ]);
    }

    public function delete($id): JsonResponse
    {
        $user = auth()->user();

        if ($user->role === 'parent') {
            $familyMember = FamilyMember::where('user_id', $user->id)->first();

            if (!$familyMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'Not part of any family'
                ], 400);
            }

            $childrenIds = FamilyMember::where('family_id', $familyMember->family_id)
                ->where('role', 'child')
                ->pluck('user_id');

            $alert = Alert::where('id', $id)
                ->whereIn('child_user_id', $childrenIds)
                ->first();
        } else {
            $alert = Alert::where('id', $id)
                ->where('child_user_id', $user->id)
                ->first();
        }

        if (!$alert) {
            return response()->json([
                'success' => false,
                'message' => 'Alert not found'
            ], 404);
        }

        $alert->delete();

        return response()->json([
            'success' => true,
            'message' => 'Alert deleted successfully'
        ]);
    }

    /**
     * Get alerts summary for dashboard
     */
    public function summary(): JsonResponse
    {
        $user = auth()->user();

        if ($user->role !== 'parent') {
            return response()->json([
                'success' => false,
                'message' => 'Only parents can access alerts summary'
            ], 403);
        }

        $familyMember = FamilyMember::where('user_id', $user->id)->first();

        if (!$familyMember) {
            return response()->json([
                'success' => false,
                'message' => 'Not part of any family'
            ], 400);
        }

        $childrenIds = FamilyMember::where('family_id', $familyMember->family_id)
            ->where('role', 'child')
            ->pluck('user_id');

        // Today's alerts
        $todayAlerts = Alert::whereIn('child_user_id', $childrenIds)
            ->whereDate('triggered_at', today())
            ->count();

        // This week's alerts
        $weekAlerts = Alert::whereIn('child_user_id', $childrenIds)
            ->where('triggered_at', '>=', now()->startOfWeek())
            ->count();

        // Recent critical alerts (last 24h)
        $criticalAlerts = Alert::whereIn('child_user_id', $childrenIds)
            ->where('priority', 'critical')
            ->where('triggered_at', '>=', now()->subDay())
            ->get();

        // Alerts by type (last 7 days)
        $alertsByType = Alert::whereIn('child_user_id', $childrenIds)
            ->where('triggered_at', '>=', now()->subWeek())
            ->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type');

        return response()->json([
            'success' => true,
            'summary' => [
                'today_alerts' => $todayAlerts,
                'week_alerts' => $weekAlerts,
                'critical_alerts_24h' => $criticalAlerts->count(),
                'recent_critical' => $criticalAlerts->take(5),
                'alerts_by_type' => $alertsByType,
            ]
        ]);
    }
}
