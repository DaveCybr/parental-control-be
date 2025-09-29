<?php
// app/Models/Device.php
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
        'is_online',
        'last_seen',
    ];

    protected $casts = [
        'is_online' => 'boolean',
        'last_seen' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ParentModel::class, 'parent_id');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class, 'device_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'device_id');
    }

    public function screenshots(): HasMany
    {
        return $this->hasMany(Screenshot::class, 'device_id');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class, 'device_id');
    }

    public function latestLocation()
    {
        return $this->hasOne(Location::class, 'device_id')->latestOfMany('timestamp');
    }
}