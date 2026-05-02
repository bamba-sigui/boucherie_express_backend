<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryZone extends Model
{
    protected $fillable = ['city', 'fee', 'free_delivery_threshold', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
