<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'           => $this->firebase_uid ?? (string) $this->id,
            'email'        => $this->email,
            'name'         => $this->name,
            'phone'        => $this->phone,
            'photoUrl'     => $this->photo_url,
            'accountType'  => $this->account_type ?? 'b2c',
            'isPremium'    => (bool) ($this->is_premium ?? false),
            'premiumUntil' => $this->premium_until?->toIso8601String(),
            'addresses'    => AddressResource::collection($this->whenLoaded('addresses')),
            'createdAt'    => $this->created_at?->toIso8601String(),
        ];
    }
}
