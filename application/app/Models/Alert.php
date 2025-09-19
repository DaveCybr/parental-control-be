<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Alert extends Model
{
    use HasFactory;

    protected $fillable = [
        'child_user_id',
        'type', // geofence, emergency, content
        'priority', // critical, high, medium, low
        'title',
        'message',
        'data', // JSON data
        'is_read',
        'triggered_at',
    ];

    protected $casts = [
        'data' => 'array',
        'is_read' => 'boolean',
        'triggered_at' => 'datetime',
    ];

    public function child()
    {
        return $this->belongsTo(User::class, 'child_user_id');
    }
}
