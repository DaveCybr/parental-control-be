<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScreenSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'child_user_id',
        'parent_user_id',
        'session_token',
        'is_active',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function child()
    {
        return $this->belongsTo(User::class, 'child_user_id');
    }

    public function parent()
    {
        return $this->belongsTo(User::class, 'parent_user_id');
    }
}
