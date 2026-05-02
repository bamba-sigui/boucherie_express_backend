<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id', 'name', 'description', 'price', 'old_price',
        'stock', 'image', 'images', 'video_url', 'is_active',
        'unit', 'min_order_quantity', 'low_stock_threshold',
        'is_fresh', 'is_bio', 'is_halal', 'is_promoted', 'is_featured',
        'preparation_options', 'tags', 'farm_name', 'supplier_id',
        'slaughter_date', 'lot_number', 'storage_conditions',
    ];

    protected $casts = [
        'price'               => 'decimal:2',
        'is_active'           => 'boolean',
        'is_fresh'            => 'boolean',
        'is_bio'              => 'boolean',
        'is_halal'            => 'boolean',
        'is_promoted'         => 'boolean',
        'is_featured'         => 'boolean',
        'images'              => 'array',
        'preparation_options' => 'array',
        'tags'                => 'array',
        'slaughter_date'      => 'date',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }
}
