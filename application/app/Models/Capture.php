<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Capture extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'file_path',
        'file_url',
        'file_size',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
