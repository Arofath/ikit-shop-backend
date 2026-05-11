<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'order_number'     => $this->order_number,

            // ព័ត៌មានដឹកជញ្ជូន
            'shipping_name'    => $this->shipping_name,
            'shipping_phone'   => $this->shipping_phone,
            'shipping_address' => $this->shipping_address,

            // ហិរញ្ញវត្ថុ
            'subtotal'         => (float) $this->subtotal,
            'shipping_fee'     => (float) $this->shipping_fee,
            'grand_total'      => (float) $this->grand_total,

            // ស្ថានភាព
            'status'           => $this->status,
            'payment_status'   => $this->payment_status,
            'payment_method'   => $this->payment_method,
            'note'             => $this->note,

            'created_at'       => $this->created_at->format('Y-m-d H:i:s'),

            // 🌟 ទំនាក់ទំនង (Relationships)
            'items'            => OrderItemResource::collection($this->whenLoaded('items')),
            'payment'          => $this->whenLoaded('payment'), // បើចង់ស្អាត អាចបង្កើត PaymentResource មួយទៀតក៏បាន
        ];
    }
}
