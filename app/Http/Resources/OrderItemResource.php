<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'product_id'   => $this->product_id,
            'product_name' => $this->product_name,
            'product_sku'  => $this->product_sku,
            'quantity'     => (int) $this->quantity,
            'unit_price'   => (float) $this->unit_price,
            'subtotal'     => (float) $this->subtotal,

            // 🌟 ហៅ StorefrontProductResource បើមាន Eager Load ដើម្បីទាញយករូបភាព (Thumbnail)
            'product'      => new StorefrontProductResource($this->whenLoaded('product')),
        ];
    }
}
