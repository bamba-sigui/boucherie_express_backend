<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'              => $this->id,
            'status'          => $this->status,
            'paymentStatus'   => $this->payment_status ?? 'pending',
            'paymentMethod'   => $this->payment_method ?? null,
            'totalPrice'      => (int) ($this->total_price ?? $this->total ?? 0),
            'deliveryFee'     => (int) ($this->delivery_fee ?? 0),
            'totalAmount'     => (int) ($this->total_amount ?? $this->total ?? 0),
            'deliveryAddress' => $this->delivery_address ?? $this->shipping_address,
            'note'            => $this->note,
            'items'           => $this->whenLoaded('items', fn () => $this->items->map(fn ($i) => [
                'productId'   => $i->product_id,
                'productName' => $i->product_name,
                'price'       => (int) $i->price,
                'quantity'    => $i->quantity,
                'option'      => $i->option,
                'imageUrl'    => $i->image_url,
            ])),
            'tracking'        => $this->whenLoaded('tracking'),
            'orderedAt'       => ($this->ordered_at ?? $this->created_at)?->toIso8601String(),
        ];
    }
}
