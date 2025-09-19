<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotificationMirror;
use App\Models\AppSettings;
use App\Models\FamilyMember;
use App\Models\Alert;
use App\Models\User;
use App\Services\NotificationFilterService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Events\NotificationReceived;
use App\Events\AlertTriggered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class NotificationController extends Controller
{
    protected $filterService;

    public function __construct(NotificationFilterService $filterService)
    {
        $this->filterService = $filterService;
    }

    /**
     * Send single notification from child device
     */
    public function send(Request $request): JsonResponse
    {
        $request->validate([
            'app_package' => 'required|string|max:255',
            'title' => 'required|string|max:255',
            'content' => 'required|string|max:2000',
            'priority' => 'required|integer|between:1,5',
            'category' => 'nullable|string|max:100',
        ]);

        $user = $request->user();

        if ($user->role !== 'child') {
            return response()->json([
                'success' => false,
                'message' => 'Only child devices can send notifications'
            ], 403);
        }

        // Check if notification should be filtered
        if ($this->filterService->shouldFilterNotification(
            $user->id,
            $request->app_package,
            $request->title . ' ' . $request->content
        )) {
            return response()->json([
                'success' => true,
                'message' => 'Notification filtered - app not monitored'
            ]);
        }

        // Check for blocked content
        $blockedKeyword = $this->filterService->checkBlockedContent(
            $user->id,
            $request->title,
            $request->content
        );

        $isContentBlocked = false;
        if ($blockedKeyword) {
            $isContentBlocked = true;
            $this->filterService->triggerContentAlert(
                $user->id,
                $request->app_package,
                $blockedKeyword,
                $request->title,
                $request->content
            );
        }

        $notification = NotificationMirror::create([
            'child_user_id' => $user->id,
            'app_package' => $request->app_package,
            'title' => $request->title,
            'content' => $request->content,
            'priority' => $request->priority,
            'category' => $request->category,
            'timestamp' => now(),
            'is_read' => false,
            'is_flagged' => $isContentBlocked,
        ]);

        // Broadcast to parent devices
        broadcast(new NotificationReceived($notification));

        return response()->json([
            'success' => true,
            'notification_id' => $notification->id,
            'message' => 'Notification sent successfully',
            'is_flagged' => $isContentBlocked
        ]);
    }

    /**
     * Send batch notifications from child device
     */
    public function batchSend(Request $request): JsonResponse
    {
        $request->validate([
            'notifications' => 'required|array|max:50',
            'notifications.*.app_package' => 'required|string|max:255',
            'notifications.*.title' => 'required|string|max:255',
            'notifications.*.content' => 'required|string|max:2000',
            'notifications.*.priority' => 'required|integer|between:1,5',
            'notifications.*.category' => 'nullable|string|max:100',
            'notifications.*.timestamp' => 'required|date',
        ]);

        $user = $request->user();

        if ($user->role !== 'child') {
            return response()->json([
                'success' => false,
                'message' => 'Only child devices can send notifications'
            ], 403);
        }

        $createdNotifications = [];
        $filteredCount = 0;
        $blockedCount = 0;

        DB::beginTransaction();

        try {
            foreach ($request->notifications as $notifData) {
                // Check filtering
                if ($this->filterService->shouldFilterNotification(
                    $user->id,
                    $notifData['app_package'],
                    $notifData['title'] . ' ' . $notifData['content']
                )) {
                    $filteredCount++;
                    continue;
                }

                // Check for blocked content
                $blockedKeyword = $this->filterService->checkBlockedContent(
                    $user->id,
                    $notifData['title'],
                    $notifData['content']
                );

                $isContentBlocked = false;
                if ($blockedKeyword) {
                    $isContentBlocked = true;
                    $blockedCount++;
                    $this->filterService->triggerContentAlert(
                        $user->id,
                        $notifData['app_package'],
                        $blockedKeyword,
                        $notifData['title'],
                        $notifData['content']
                    );
                }

                $notification = NotificationMirror::create([
                    'child_user_id' => $user->id,
                    'app_package' => $notifData['app_package'],
                    'title' => $notifData['title'],
                    'content' => $notifData['content'],
                    'priority' => $notifData['priority'],
                    'category' => $notifData['category'] ?? null,
                    'timestamp' => $notifData['timestamp'],
                    'is_read' => false,
                    'is_flagged' => $isContentBlocked,
                ]);

                $createdNotifications[] = $notification;

                // Broadcast each notification
                broadcast(new NotificationReceived($notification));
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Batch notifications processed successfully',
                'statistics' => [
                    'total_sent' => count($request->notifications),
                    'created' => count($createdNotifications),
                    'filtered' => $filteredCount,
                    'blocked' => $blockedCount,
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to process batch notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * List notifications for a specific child
     */
    public function list($childId, Request $request): JsonResponse
    {
        $request->validate([
            'date' => 'nullable|date',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'app_package' => 'nullable|string|max:255',
            'limit' => 'nullable|integer|min:1|max:500',
            'page' => 'nullable|integer|min:1',
            'priority' => 'nullable|integer|between:1,5',
            'category' => 'nullable|string|max:100',
            'search' => 'nullable|string|max:255',
            'only_flagged' => 'nullable|boolean',
        ]);

        if (!$this->verifyParentChildRelationship(auth()->user()->id, $childId)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $query = NotificationMirror::where('child_user_id', $childId)
            ->with(['child:id,name,email']);

        // Date filtering
        if ($request->date) {
            $query->whereDate('timestamp', $request->date);
        } elseif ($request->start_date && $request->end_date) {
            $query->whereBetween('timestamp', [$request->start_date, $request->end_date]);
        } else {
            // Default to last 7 days
            $query->where('timestamp', '>=', now()->subDays(7));
        }

        // App package filter
        if ($request->app_package) {
            $query->where('app_package', $request->app_package);
        }

        // Priority filter
        if ($request->priority) {
            $query->where('priority', $request->priority);
        }

        // Category filter
        if ($request->category) {
            $query->where('category', $request->category);
        }

        // Search in title and content
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('title', 'like', '%' . $request->search . '%')
                    ->orWhere('content', 'like', '%' . $request->search . '%');
            });
        }

        // Only flagged notifications
        if ($request->only_flagged) {
            $query->where('is_flagged', true);
        }

        // Ordering
        $query->orderBy('timestamp', 'desc');

        // Pagination
        $limit = $request->limit ?? 50;
        $notifications = $query->paginate($limit);

        return response()->json([
            'success' => true,
            'data' => $notifications->items(),
            'pagination' => [
                'current_page' => $notifications->currentPage(),
                'total_pages' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total_items' => $notifications->total(),
                'has_more' => $notifications->hasMorePages(),
            ],
            'summary' => $this->getNotificationSummary($childId, $request)
        ]);
    }

    /**
     * Get notification statistics for dashboard
     */
    public function statistics($childId, Request $request): JsonResponse
    {
        $request->validate([
            'period' => 'nullable|in:today,week,month,year',
            'timezone' => 'nullable|string|max:50'
        ]);

        if (!$this->verifyParentChildRelationship(auth()->user()->id, $childId)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $period = $request->period ?? 'week';
        $timezone = $request->timezone ?? 'UTC';

        $dateRange = $this->getDateRange($period, $timezone);

        $baseQuery = NotificationMirror::where('child_user_id', $childId)
            ->whereBetween('timestamp', $dateRange);

        // Calculate flagged count
        $flaggedCount = NotificationMirror::where('child_user_id', $childId)
            ->whereBetween('timestamp', $dateRange)
            ->where('is_flagged', true)
            ->count();

        $stats = [
            'total_notifications' => $baseQuery->count(),
            'flagged_notifications' => $flaggedCount,
            'by_priority' => $baseQuery->select('priority', DB::raw('count(*) as count'))
                ->groupBy('priority')
                ->pluck('count', 'priority')
                ->toArray(),
            'by_category' => $baseQuery->select('category', DB::raw('count(*) as count'))
                ->whereNotNull('category')
                ->groupBy('category')
                ->pluck('count', 'category')
                ->toArray(),
            'top_apps' => $baseQuery->select('app_package', DB::raw('count(*) as count'))
                ->groupBy('app_package')
                ->orderByDesc('count')
                ->limit(10)
                ->get()
                ->toArray(),
            'hourly_distribution' => $this->getHourlyDistribution($childId, $dateRange),
            'trend' => $this->getNotificationTrend($childId, $period, $timezone)
        ];

        return response()->json([
            'success' => true,
            'period' => $period,
            'date_range' => [
                'start' => $dateRange[0]->toISOString(),
                'end' => $dateRange[1]->toISOString()
            ],
            'statistics' => $stats
        ]);
    }

    /**
     * Mark notification as read by parent
     */
    public function markAsRead(Request $request): JsonResponse
    {
        $request->validate([
            'notification_ids' => 'required|array|max:100',
            'notification_ids.*' => 'required|integer|exists:notification_mirrors,id'
        ]);

        $user = auth()->user();
        $notificationIds = $request->notification_ids;

        // Get parent's family ID
        $parentFamilyMember = FamilyMember::where('user_id', $user->id)
            ->where('role', 'parent')
            ->first();

        if (!$parentFamilyMember) {
            return response()->json([
                'success' => false,
                'message' => 'Parent not found'
            ], 403);
        }

        // Verify all notifications belong to user's children
        $validNotifications = NotificationMirror::whereIn('id', $notificationIds)
            ->whereHas('child.familyMembers', function ($query) use ($parentFamilyMember) {
                $query->where('family_id', $parentFamilyMember->family_id)
                    ->where('role', 'child');
            })
            ->pluck('id')
            ->toArray();

        if (count($validNotifications) !== count($notificationIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Some notifications do not belong to your children'
            ], 403);
        }

        $updatedCount = NotificationMirror::whereIn('id', $validNotifications)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Notifications marked as read',
            'updated_count' => $updatedCount
        ]);
    }

    /**
     * Delete notifications (soft delete)
     */
    public function delete(Request $request): JsonResponse
    {
        $request->validate([
            'notification_ids' => 'required|array|max:100',
            'notification_ids.*' => 'required|integer|exists:notification_mirrors,id'
        ]);

        $user = auth()->user();
        $notificationIds = $request->notification_ids;

        // Get parent's family ID
        $parentFamilyMember = FamilyMember::where('user_id', $user->id)
            ->where('role', 'parent')
            ->first();

        if (!$parentFamilyMember) {
            return response()->json([
                'success' => false,
                'message' => 'Parent not found'
            ], 403);
        }

        // Verify all notifications belong to user's children
        $validNotifications = NotificationMirror::whereIn('id', $notificationIds)
            ->whereHas('child.familyMembers', function ($query) use ($parentFamilyMember) {
                $query->where('family_id', $parentFamilyMember->family_id)
                    ->where('role', 'child');
            })
            ->pluck('id')
            ->toArray();

        if (count($validNotifications) !== count($notificationIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Some notifications do not belong to your children'
            ], 403);
        }

        $deletedCount = NotificationMirror::whereIn('id', $validNotifications)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notifications deleted successfully',
            'deleted_count' => $deletedCount
        ]);
    }

    /**
     * Get app-specific notification settings
     */
    public function getAppSettings($childId): JsonResponse
    {
        if (!$this->verifyParentChildRelationship(auth()->user()->id, $childId)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        // Get child's family ID
        $childFamilyMember = FamilyMember::where('user_id', $childId)
            ->where('role', 'child')
            ->first();

        if (!$childFamilyMember) {
            return response()->json([
                'success' => false,
                'message' => 'Child not found'
            ], 404);
        }

        $settings = AppSettings::where('family_id', $childFamilyMember->family_id)
            ->where('child_user_id', $childId)
            ->first();

        if (!$settings) {
            // Return default settings
            return response()->json([
                'success' => true,
                'data' => [
                    'notification_filters' => AppSettings::getDefaultNotificationFilters(),
                    'blocked_keywords' => [],
                ]
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'notification_filters' => $settings->notification_filters ?? [],
                'blocked_keywords' => $settings->blocked_keywords ?? [],
            ]
        ]);
    }

    /**
     * Update app-specific notification settings
     */
    public function updateAppSettings($childId, Request $request): JsonResponse
    {
        $request->validate([
            'notification_filters' => 'nullable|array',
            'notification_filters.*' => 'boolean',
            'blocked_keywords' => 'nullable|array',
            'blocked_keywords.*' => 'string|max:100',
        ]);

        if (!$this->verifyParentChildRelationship(auth()->user()->id, $childId)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        // Get child's family ID
        $childFamilyMember = FamilyMember::where('user_id', $childId)
            ->where('role', 'child')
            ->first();

        if (!$childFamilyMember) {
            return response()->json([
                'success' => false,
                'message' => 'Child not found'
            ], 404);
        }

        DB::beginTransaction();

        try {
            $settings = AppSettings::updateOrCreate(
                [
                    'family_id' => $childFamilyMember->family_id,
                    'child_user_id' => $childId,
                ],
                [
                    'notification_filters' => $request->notification_filters ?? [],
                    'blocked_keywords' => $request->blocked_keywords ?? [],
                    'location_update_interval' => 60, // default
                    'screen_mirroring_enabled' => true, // default
                    'geofence_settings' => AppSettings::getDefaultGeofenceSettings(),
                ]
            );

            DB::commit();

            // Clear cache for this child's filter settings
            Cache::tags(['notification_filter', "child_{$childId}"])->flush();

            return response()->json([
                'success' => true,
                'message' => 'App settings updated successfully',
                'data' => [
                    'notification_filters' => $settings->notification_filters,
                    'blocked_keywords' => $settings->blocked_keywords,
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update app settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get recent alerts for child
     */
    public function getAlerts($childId, Request $request): JsonResponse
    {
        $request->validate([
            'limit' => 'nullable|integer|min:1|max:100',
            'status' => 'nullable|in:pending,acknowledged,resolved',
            'type' => 'nullable|in:content,geofence,emergency'
        ]);

        if (!$this->verifyParentChildRelationship(auth()->user()->id, $childId)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $query = Alert::where('child_user_id', $childId)
            ->with(['child:id,name,email']);

        if ($request->type) {
            $query->where('type', $request->type);
        }

        $alerts = $query->orderBy('triggered_at', 'desc')
            ->limit($request->limit ?? 20)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $alerts
        ]);
    }

    /**
     * Helper method to verify parent-child relationship
     */
    protected function verifyParentChildRelationship($parentId, $childId): bool
    {
        // Get parent's family
        $parentFamilyMember = FamilyMember::where('user_id', $parentId)
            ->where('role', 'parent')
            ->first();

        if (!$parentFamilyMember) {
            return false;
        }

        // Check if child is in the same family
        return FamilyMember::where('user_id', $childId)
            ->where('family_id', $parentFamilyMember->family_id)
            ->where('role', 'child')
            ->exists();
    }

    /**
     * Get notification summary for the list endpoint
     */
    protected function getNotificationSummary($childId, Request $request): array
    {
        $baseQuery = NotificationMirror::where('child_user_id', $childId);

        // Apply same date filters as main query
        if ($request->date) {
            $baseQuery->whereDate('timestamp', $request->date);
        } elseif ($request->start_date && $request->end_date) {
            $baseQuery->whereBetween('timestamp', [$request->start_date, $request->end_date]);
        } else {
            $baseQuery->where('timestamp', '>=', now()->subDays(7));
        }

        return [
            'total_notifications' => $baseQuery->count(),
            'flagged_count' => $baseQuery->where('is_flagged', true)->count(),
            'unread_count' => $baseQuery->where('is_read', false)->count(),
            'high_priority_count' => $baseQuery->whereIn('priority', [4, 5])->count(),
        ];
    }

    /**
     * Get date range based on period
     */
    protected function getDateRange($period, $timezone): array
    {
        try {
            $now = Carbon::now($timezone);
        } catch (\Exception $e) {
            $now = Carbon::now('UTC');
        }

        switch ($period) {
            case 'today':
                return [$now->copy()->startOfDay(), $now->copy()->endOfDay()];
            case 'week':
                return [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()];
            case 'month':
                return [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()];
            case 'year':
                return [$now->copy()->startOfYear(), $now->copy()->endOfYear()];
            default:
                return [$now->copy()->subDays(7), $now->copy()];
        }
    }

    /**
     * Get hourly distribution of notifications
     */
    protected function getHourlyDistribution($childId, $dateRange): array
    {
        $notifications = NotificationMirror::where('child_user_id', $childId)
            ->whereBetween('timestamp', $dateRange)
            ->select(DB::raw('HOUR(timestamp) as hour'), DB::raw('count(*) as count'))
            ->groupBy('hour')
            ->pluck('count', 'hour')
            ->toArray();

        // Fill missing hours with 0
        $hourlyData = [];
        for ($i = 0; $i < 24; $i++) {
            $hourlyData[$i] = $notifications[$i] ?? 0;
        }

        return $hourlyData;
    }

    /**
     * Get notification trend data
     */
    protected function getNotificationTrend($childId, $period, $timezone): array
    {
        $format = match ($period) {
            'today' => '%H:00',
            'week' => '%Y-%m-%d',
            'month' => '%Y-%m-%d',
            'year' => '%Y-%m',
            default => '%Y-%m-%d'
        };

        $dateRange = $this->getDateRange($period, $timezone);

        return NotificationMirror::where('child_user_id', $childId)
            ->whereBetween('timestamp', $dateRange)
            ->select(
                DB::raw("DATE_FORMAT(timestamp, '{$format}') as period"),
                DB::raw('count(*) as count')
            )
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->pluck('count', 'period')
            ->toArray();
    }
}
