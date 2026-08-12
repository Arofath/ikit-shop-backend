<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // 🌟 ១. ទាញយកឈ្មោះ Zone បើសិនជាមាន Relationship ជាមួយ ShippingZone 
        // 🌟 ប្រើប្រាស់ city ជាជម្រើសទី ២ (Fallback) សម្រាប់ទិន្នន័យចាស់ៗដែលមិនទាន់ Update
        $zoneName = $this->shippingZone ? $this->shippingZone->name : $this->city;

        return [
            'id'             => $this->id,
            'receiver_name'  => $this->receiver_name,
            'receiver_phone' => $this->receiver_phone,
            'address_detail' => $this->address_detail,

            // 🌟 ២. បោះ id ទៅឱ្យ Frontend សម្រាប់យកទៅ Match ជាមួយ Dropdown
            'shipping_zone_id' => $this->shipping_zone_id,
            'city'           => $zoneName,

            // 🌟 ៣. បូកបញ្ចូលគ្នាដើម្បីងាយស្រួលបង្ហាញលើ UI
            'full_address'   => $this->address_detail . ', ' . $zoneName,

            'is_default'     => (bool) $this->is_default,
            'created_at'     => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
