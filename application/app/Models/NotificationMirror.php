<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationMirror extends Model
{
    use HasFactory;

    protected $fillable = [
        'child_user_id',
        'app_package',
        'title',
        'content',
        'priority',
        'category',
        'timestamp',
        'is_read',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
        'is_read' => 'boolean',
    ];

    public function child()
    {
        return $this->belongsTo(User::class, 'child_user_id');
    }
}
