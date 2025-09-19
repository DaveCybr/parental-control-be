<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppSettings extends Model
{
    use HasFactory;

    protected $fillable = [
        'family_id',
        'child_user_id',
        'notification_filters',
        'blocked_keywords',
        'location_update_interval',
        'screen_mirroring_enabled',
        'geofence_settings',
    ];

    protected $casts = [
        'notification_filters' => 'array',
        'blocked_keywords' => 'array',
        'location_update_interval' => 'integer',
        'screen_mirroring_enabled' => 'boolean',
        'geofence_settings' => 'array',
    ];

    public function family()
    {
        return $this->belongsTo(Family::class);
    }

    public function child()
    {
        return $this->belongsTo(User::class, 'child_user_id');
    }

    /**
     * Get default notification filters for new settings
     */
    public static function getDefaultNotificationFilters(): array
    {
        return [
            'com.whatsapp' => true,
            'com.instagram.android' => true,
            'org.telegram.messenger' => true,
            'com.facebook.katana' => true,
            'com.snapchat.android' => true,
            'com.zhiliaoapp.musically' => true, // TikTok
            'com.twitter.android' => true,
            'com.discord' => true,
            'com.google.android.youtube' => false, // Usually too noisy
            'com.android.chrome' => false,
        ];
    }

    /**
     * Get default geofence settings
     */
    public static function getDefaultGeofenceSettings(): array
    {
        return [
            'alert_on_exit' => true,
            'alert_on_enter_danger' => true,
            'auto_check_interval' => 30, // seconds
            'minimum_accuracy' => 50, // meters
            'alert_cooldown' => 1800, // 30 minutes in seconds
        ];
    }

    /**
     * Check if an app package should be monitored
     */
    public function shouldMonitorApp(string $appPackage): bool
    {
        return $this->notification_filters[$appPackage] ?? false;
    }

    /**
     * Check if content contains blocked keywords
     */
    public function hasBlockedKeyword(string $content): ?string
    {
        if (empty($this->blocked_keywords)) {
            return null;
        }

        $content = strtolower($content);

        foreach ($this->blocked_keywords as $keyword) {
            if (strpos($content, strtolower($keyword)) !== false) {
                return $keyword;
            }
        }

        return null;
    }

    /**
     * Get location update interval with battery optimization
     */
    public function getOptimizedLocationInterval(int $batteryLevel): int
    {
        $baseInterval = $this->location_update_interval;

        // Increase interval when battery is low
        if ($batteryLevel <= 10) {
            return $baseInterval * 4; // 4x longer when critically low
        } elseif ($batteryLevel <= 20) {
            return $baseInterval * 2; // 2x longer when low
        } elseif ($batteryLevel <= 50) {
            return (int)($baseInterval * 1.5); // 1.5x longer when medium
        }

        return $baseInterval;
    }
}
