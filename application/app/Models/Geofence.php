<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Geofence extends Model
{
    use HasFactory;

    protected $fillable = [
        'family_id',
        'name',
        'center_latitude',
        'center_longitude',
        'radius', // in meters
        'type', // safe or danger
        'is_active',
    ];

    protected $casts = [
        'center_latitude' => 'decimal:8',
        'center_longitude' => 'decimal:8',
        'radius' => 'integer',
        'is_active' => 'boolean',
    ];

    public function family()
    {
        return $this->belongsTo(Family::class);
    }
}
