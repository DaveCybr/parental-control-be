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
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class, 'parent_id');
    }

    public function geofences(): HasMany
    {
        return $this->hasMany(Geofence::class, 'parent_id');
    }

    // public function alerts(): HasMany
    // {
    //     return $this->hasMany(Alert::class, 'parent_id');
    // }
}
