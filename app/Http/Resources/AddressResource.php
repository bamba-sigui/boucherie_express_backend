<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AddressResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'label'      => $this->label,
            'address'    => $this->address,
            'city'       => $this->city,
            'postalCode' => $this->postal_code,
            'latitude'   => $this->latitude ? (float) $this->latitude : null,
            'longitude'  => $this->longitude ? (float) $this->longitude : null,
            'isDefault'  => (bool) $this->is_default,
        ];
    }
}
