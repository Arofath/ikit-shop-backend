<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $zoneName = $this->shippingZone ? $this->shippingZone->name : $this->city;
        $detail = trim($this->address_detail);

        // 🌟 មុខងារ Detect ទីតាំង 🌟
        // បើរកឃើញថាមានឈ្មោះខេត្តក្នុង address_detail រួចហើយ យើងមិនបូកថែមទេ
        if ($zoneName && stripos($detail, $zoneName) !== false) {
            // rtrim ជួយកាត់សញ្ញាក្បៀស ឬការដកឃ្លាដែលនៅសល់ចុងកន្ទុយចោល ក្នុងករណីគាត់វាយលើស
            $full_address = rtrim($detail, ' ,.-');
        } else {
            // បើមិនទាន់មាន ទើបយើងបូកឈ្មោះខេត្តពីក្រោយ
            $full_address = $detail . ($zoneName ? ', ' . $zoneName : '');
        }

        return [
            'id'               => $this->id,
            'receiver_name'    => $this->receiver_name,
            'receiver_phone'   => $this->receiver_phone,
            'address_detail'   => $this->address_detail,
            'shipping_zone_id' => $this->shipping_zone_id,
            'city'             => $zoneName,

            'full_address'     => $full_address, // 🌟 ប្រើអថេរដែលយើងបានត្រងរួចរាល់ខាងលើ

            'is_default'       => (bool) $this->is_default,
            'created_at'       => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
