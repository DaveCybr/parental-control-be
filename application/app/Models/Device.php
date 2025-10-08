<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Device extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'parent_id',
        'device_id',
        'device_name',
        'device_type',
        'fcm_token',              // BARU
        'fcm_token_updated_at',   // BARU
        'is_online',
        'last_seen',
    ];

    protected $casts = [
        'is_online' => 'boolean',
        'last_seen' => 'datetime',
        'created_at' => 'datetime',
        'fcm_token_updated_at' => 'datetime', // BARU
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ParentModel::class, 'parent_id');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class, 'device_id', 'device_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'device_id', 'device_id');
    }

    public function screenshots(): HasMany
    {
        return $this->hasMany(Screenshot::class, 'device_id', 'device_id');
    }

    public function latestLocation()
    {
        return $this->hasOne(Location::class, 'device_id', 'device_id')
            ->latestOfMany('timestamp');
    }

    /**
     * Check if device has valid FCM token
     */
    public function hasValidFcmToken(): bool
    {
        return !empty($this->fcm_token);
    }

    /**
     * Update FCM token
     */
    public function updateFcmToken(string $token): bool
    {
        return $this->update([
            'fcm_token' => $token,
            'fcm_token_updated_at' => now(),
        ]);
    }
}
