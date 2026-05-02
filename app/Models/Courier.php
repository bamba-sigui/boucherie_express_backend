<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Courier extends Model
{
    protected $fillable = [
        'name', 'phone', 'photo_url', 'vehicle', 'license_plate',
        'rating', 'is_active', 'is_available',
        'current_lat', 'current_lng', 'current_heading', 'location_updated_at',
    ];

    protected $casts = [
        'is_active'           => 'boolean',
        'is_available'        => 'boolean',
        'rating'              => 'float',
        'location_updated_at' => 'datetime',
    ];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function activeOrder()
    {
        return $this->hasOne(Order::class)->where('status', 'delivering');
    }
}
