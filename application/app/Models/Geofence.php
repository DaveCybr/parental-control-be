<?php
// app/Models/Geofence.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Geofence extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'parent_id',
        'name',
        'latitude',
        'longitude',
        'radius',
        'is_active',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'radius' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ParentModel::class, 'parent_id');
    }

    // Calculate distance between two coordinates (Haversine formula)
    public function isWithinRadius($lat, $lng): bool
    {
        $earthRadius = 6371000; // meters
        
        $latFrom = deg2rad($this->latitude);
        $lonFrom = deg2rad($this->longitude);
        $latTo = deg2rad($lat);
        $lonTo = deg2rad($lng);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos($latFrom) * cos($latTo) *
             sin($lonDelta / 2) * sin($lonDelta / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        $distance = $earthRadius * $c;

        return $distance <= $this->radius;
    }
}
