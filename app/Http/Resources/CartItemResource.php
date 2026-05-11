<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cart_id' => $this->cart_id,
            'product_id' => $this->product_id,
            'quantity' => $this->quantity,
            
            'product' => new StorefrontProductResource($this->whenLoaded('product')),

            // គណនាតម្លៃសរុបប្រចាំ Item (Quantity * Price)
            'item_total_price' => $this->relationLoaded('product') ? $this->quantity * $this->product->final_price : 0,

            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
        ];
    }
}
