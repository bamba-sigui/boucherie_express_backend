<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                  => $this->id,
            'name'                => $this->name,
            'description'         => $this->description,
            'price'               => (int) $this->price,
            'oldPrice'            => $this->old_price ? (int) $this->old_price : null,
            'stock'               => $this->stock,
            'unit'                => $this->unit ?? 'kg',
            'images'              => $this->images ?? ($this->image ? [$this->image] : []),
            'categoryId'          => $this->category_id,
            'category'            => $this->whenLoaded('category', fn () => [
                'id'   => $this->category->id,
                'name' => $this->category->name,
            ]),
            'isActive'            => (bool) $this->is_active,
            'isFresh'             => (bool) ($this->is_fresh ?? true),
            'isBio'               => (bool) ($this->is_bio ?? false),
            'isHalal'             => (bool) ($this->is_halal ?? false),
            'isPromoted'          => (bool) ($this->is_promoted ?? false),
            'isFeatured'          => (bool) ($this->is_featured ?? false),
            'preparationOptions'  => $this->preparation_options ?? [],
            'farmName'            => $this->farm_name,
            'createdAt'           => $this->created_at?->toIso8601String(),
        ];
    }
}
