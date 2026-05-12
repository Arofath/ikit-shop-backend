<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'order_number'     => $this->order_number,

            // 🌟 ចំណុចខុសគ្នាពី Storefront៖ Admin ត្រូវការស្គាល់គណនីភ្ញៀវ
            'customer'         => [
                'id'    => $this->user->id ?? null,
                'name'  => $this->user->name ?? 'Unknown',
                'email' => $this->user->email ?? 'N/A',
                'phone' => $this->user->phone ?? 'N/A',
            ],

            'shipping_name'    => $this->shipping_name,
            'shipping_phone'   => $this->shipping_phone,
            'shipping_address' => $this->shipping_address,

            'subtotal'         => (float) $this->subtotal,
            'shipping_fee'     => (float) $this->shipping_fee,
            'grand_total'      => (float) $this->grand_total,

            'status'           => $this->status,
            'payment_status'   => $this->payment_status,
            'payment_method'   => $this->payment_method,
            'note'             => $this->note,

            'created_at'       => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at'       => $this->updated_at->format('Y-m-d H:i:s'),

            'items'            => OrderItemResource::collection($this->whenLoaded('items')),
            'payment'          => $this->whenLoaded('payment'),
        ];
    }
}
