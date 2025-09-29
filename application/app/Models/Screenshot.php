<?php


// app/Models/Screenshot.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Screenshot extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'device_id',
        'file_url',
        'timestamp',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id');
    }
}