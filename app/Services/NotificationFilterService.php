<?php

namespace App\Services;

use App\Models\AppSettings;
use App\Models\FamilyMember;
use App\Models\Alert;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class NotificationFilterService
{
    /**
     * Check if notification should be filtered (not sent to parents)
     * Returns true if notification should be filtered OUT (blocked)
     */
    public function shouldFilterNotification($childId, $appPackage, $content): bool
    {
        // Get child's family settings using cache
        $cacheKey = "notification_filter_{$childId}";

        $settings = Cache::tags(['notification_filter', "child_{$childId}"])
            ->remember($cacheKey, 300, function () use ($childId) {
                return $this->getChildSettings($childId);
            });

        if (!$settings) {
            // If no settings found, allow all notifications by default
            return false;
        }

        // Check if app is in the monitored list
        $notificationFilters = $settings->notification_filters ?? [];

        // If app is specifically configured
        if (isset($notificationFilters[$appPackage])) {
            // Return true (filter) if app is set to false (not monitored)
            return !$notificationFilters[$appPackage];
        }

        // If app is not in settings, use default behavior
        // For unknown apps, allow notifications (return false = don't filter)
        return false;
    }

    /**
     * Check if notification content contains blocked keywords
     * Returns the blocked keyword if found, null otherwise
     */
    public function checkBlockedContent($childId, $title, $content): ?string
    {
        // Get child's family settings using cache
        $cacheKey = "blocked_keywords_{$childId}";

        $settings = Cache::tags(['notification_filter', "child_{$childId}"])
            ->remember($cacheKey, 300, function () use ($childId) {
                return $this->getChildSettings($childId);
            });

        if (!$settings || empty($settings->blocked_keywords)) {
            return null;
        }

        $fullContent = strtolower(trim($title . ' ' . $content));

        foreach ($settings->blocked_keywords as $keyword) {
            $keyword = trim($keyword);
            if (empty($keyword)) {
                continue;
            }

            // Use more precise matching - look for whole words when possible
            $keywordLower = strtolower($keyword);

            // Check for exact word match first
            if (preg_match('/\b' . preg_quote($keywordLower, '/') . '\b/', $fullContent)) {
                return $keyword;
            }

            // Fallback to substring match for non-word characters
            if (strpos($fullContent, $keywordLower) !== false) {
                return $keyword;
            }
        }

        return null;
    }

    /**
     * Trigger content alert when blocked keyword is detected
     */
    public function triggerContentAlert($childId, $appPackage, $keyword, $title, $content): void
    {
        try {
            // Check for duplicate alerts in last 10 minutes to prevent spam
            $recentAlert = Alert::where('child_user_id', $childId)
                ->where('type', 'content')
                ->where('triggered_at', '>=', now()->subMinutes(10))
                ->whereJsonContains('data->keyword', $keyword)
                ->whereJsonContains('data->app_package', $appPackage)
                ->first();

            if ($recentAlert) {
                // Don't create duplicate alert
                return;
            }

            $severity = $this->calculateAlertSeverity($keyword, $content);

            Alert::create([
                'child_user_id' => $childId,
                'type' => 'content',
                'priority' => $this->mapSeverityToPriority($severity),
                'title' => 'Blocked Content Detected',
                'message' => "Detected blocked keyword: {$keyword} in {$appPackage}",
                'data' => [
                    'app_package' => $appPackage,
                    'keyword' => $keyword,
                    'title' => $title,
                    'content_preview' => $this->getSafeContentPreview($content),
                    'severity' => $severity,
                    'detected_at' => now()->toISOString(),
                    'hash' => md5($appPackage . $keyword . $title) // For deduplication
                ],
                'triggered_at' => now(),
                'is_read' => false
            ]);
        } catch (\Exception $e) {
            // Log error but don't fail the notification processing
            Log::error('Failed to create content alert', [
                'child_id' => $childId,
                'error' => $e->getMessage(),
                'keyword' => $keyword,
                'app_package' => $appPackage
            ]);
        }
    }

    /**
     * Get child's app settings
     */
    private function getChildSettings($childId): ?AppSettings
    {
        $familyMember = FamilyMember::where('user_id', $childId)
            ->where('role', 'child')
            ->first();

        if (!$familyMember) {
            return null;
        }

        return AppSettings::where('family_id', $familyMember->family_id)
            ->where('child_user_id', $childId)
            ->first();
    }

    /**
     * Calculate alert severity based on keyword and content
     */
    private function calculateAlertSeverity($keyword, $content): string
    {
        // Define high-risk keywords
        $criticalKeywords = [
            'suicide',
            'kill myself',
            'self harm',
            'drugs',
            'violence',
            'bullying',
            'threat',
            'weapon',
            'abuse',
            'predator'
        ];

        $highRiskKeywords = [
            'inappropriate',
            'mature',
            'adult',
            'sexual',
            'explicit',
            'gambling',
            'alcohol',
            'tobacco',
            'dating'
        ];

        $keywordLower = strtolower($keyword);
        $contentLower = strtolower($content);

        // Check for critical severity
        foreach ($criticalKeywords as $criticalKeyword) {
            if (
                strpos($keywordLower, $criticalKeyword) !== false ||
                strpos($contentLower, $criticalKeyword) !== false
            ) {
                return 'critical';
            }
        }

        // Check for high severity
        foreach ($highRiskKeywords as $highRiskKeyword) {
            if (
                strpos($keywordLower, $highRiskKeyword) !== false ||
                strpos($contentLower, $highRiskKeyword) !== false
            ) {
                return 'high';
            }
        }

        // Default to medium severity
        return 'medium';
    }

    /**
     * Map severity to priority for alerts
     */
    private function mapSeverityToPriority($severity): string
    {
        return match ($severity) {
            'critical' => 'critical',
            'high' => 'high',
            'medium' => 'medium',
            default => 'low'
        };
    }

    /**
     * Get safe preview of content (sanitized for alerts)
     */
    private function getSafeContentPreview($content): string
    {
        // Remove sensitive information and truncate
        $preview = strip_tags($content);
        $preview = preg_replace('/\b\d{4}[\s\-]?\d{4}[\s\-]?\d{4}[\s\-]?\d{4}\b/', '[CARD]', $preview); // Credit cards
        $preview = preg_replace('/\b\d{3}[\s\-]?\d{3}[\s\-]?\d{4}\b/', '[PHONE]', $preview); // Phone numbers
        $preview = preg_replace('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', '[EMAIL]', $preview); // Emails

        return substr($preview, 0, 150) . (strlen($preview) > 150 ? '...' : '');
    }

    /**
     * Get app monitoring status
     */
    public function isAppMonitored($childId, $appPackage): bool
    {
        $settings = $this->getChildSettings($childId);

        if (!$settings) {
            // Default behavior - monitor social media apps
            $defaultMonitored = AppSettings::getDefaultNotificationFilters();
            return $defaultMonitored[$appPackage] ?? false;
        }

        $notificationFilters = $settings->notification_filters ?? [];
        return $notificationFilters[$appPackage] ?? false;
    }

    /**
     * Bulk update notification filters
     */
    public function updateNotificationFilters($childId, array $filters): bool
    {
        try {
            $familyMember = FamilyMember::where('user_id', $childId)
                ->where('role', 'child')
                ->first();

            if (!$familyMember) {
                return false;
            }

            AppSettings::updateOrCreate(
                [
                    'family_id' => $familyMember->family_id,
                    'child_user_id' => $childId,
                ],
                [
                    'notification_filters' => $filters
                ]
            );

            // Clear cache
            Cache::tags(['notification_filter', "child_{$childId}"])->flush();

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to update notification filters', [
                'child_id' => $childId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Bulk update blocked keywords
     */
    public function updateBlockedKeywords($childId, array $keywords): bool
    {
        try {
            // Sanitize keywords
            $sanitizedKeywords = array_filter(array_map(function ($keyword) {
                return trim(strtolower($keyword));
            }, $keywords));

            $familyMember = FamilyMember::where('user_id', $childId)
                ->where('role', 'child')
                ->first();

            if (!$familyMember) {
                return false;
            }

            AppSettings::updateOrCreate(
                [
                    'family_id' => $familyMember->family_id,
                    'child_user_id' => $childId,
                ],
                [
                    'blocked_keywords' => $sanitizedKeywords
                ]
            );

            // Clear cache
            Cache::tags(['notification_filter', "child_{$childId}"])->flush();

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to update blocked keywords', [
                'child_id' => $childId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get notification statistics for a child
     */
    public function getNotificationStats($childId, $period = 'week'): array
    {
        try {
            $dateRange = $this->getDateRange($period);

            return [
                'total' => \App\Models\NotificationMirror::where('child_user_id', $childId)
                    ->whereBetween('timestamp', $dateRange)->count(),
                'flagged' => \App\Models\NotificationMirror::where('child_user_id', $childId)
                    ->whereBetween('timestamp', $dateRange)
                    ->where('is_flagged', true)->count(),
                'alerts' => Alert::where('child_user_id', $childId)
                    ->where('type', 'content')
                    ->whereBetween('triggered_at', $dateRange)->count(),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get notification stats', [
                'child_id' => $childId,
                'error' => $e->getMessage()
            ]);
            return ['total' => 0, 'flagged' => 0, 'alerts' => 0];
        }
    }

    /**
     * Get date range for period
     */
    private function getDateRange($period): array
    {
        $now = now();

        return match ($period) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            default => [$now->copy()->subDays(7), $now->copy()]
        };
    }
}
