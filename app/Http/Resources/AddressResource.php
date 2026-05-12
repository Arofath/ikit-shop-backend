<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'receiver_name'  => $this->receiver_name,
            'receiver_phone' => $this->receiver_phone,
            'address_detail' => $this->address_detail,
            'city'           => $this->city,

            // បូកបញ្ចូលគ្នាដើម្បីងាយស្រួលបង្ហាញលើ UI
            'full_address'   => $this->address_detail . ', ' . $this->city,

            'is_default'     => (bool) $this->is_default,
            'created_at'     => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
