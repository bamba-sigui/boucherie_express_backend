<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'status', 'total', 'items', 'shipping_address',
        'total_price', 'delivery_fee', 'total_amount', 'delivery_address',
        'address_id', 'payment_method', 'payment_status', 'payment_reference',
        'courier_id', 'eta', 'note', 'ordered_at',
    ];

    protected $casts = [
        'items'      => 'array',
        'total'      => 'decimal:2',
        'eta'        => 'datetime',
        'ordered_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tracking()
    {
        return $this->hasMany(OrderTracking::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistories()
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at');
    }

    public function courier()
    {
        return $this->belongsTo(Courier::class);
    }

    public function address()
    {
        return $this->belongsTo(Address::class);
    }
}
