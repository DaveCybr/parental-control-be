<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class ParentModel extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'parents';
    public $timestamps = false;

    protected $fillable = [
        'email',
        'password',
        'family_code',
        'fcm_token',              // BARU
        'fcm_token_updated_at',   // BARU
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'fcm_token_updated_at' => 'datetime', // BARU
    ];

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class, 'parent_id');
    }

    public function geofences(): HasMany
    {
        return $this->hasMany(Geofence::class, 'parent_id');
    }

    /**
     * Check if parent has valid FCM token
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
