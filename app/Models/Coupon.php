<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $fillable = [
        'code', 'type', 'value', 'min_order_amount', 'max_uses',
        'uses_count', 'max_uses_per_user', 'starts_at', 'expires_at',
        'is_active', 'first_order_only', 'premium_only',
    ];

    protected $casts = [
        'is_active'       => 'boolean',
        'first_order_only'=> 'boolean',
        'premium_only'    => 'boolean',
        'starts_at'       => 'datetime',
        'expires_at'      => 'datetime',
    ];
}
