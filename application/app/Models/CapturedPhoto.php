<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CapturedPhoto extends Model
{
    use HasFactory;

    protected $table = 'captured_photos';

    public $timestamps = false;

    protected $fillable = [
        'device_id',
        'camera_type',
        'file_url',
        'captured_at',
    ];

    protected $casts = [
        'captured_at' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id', 'device_id');
    }
}
