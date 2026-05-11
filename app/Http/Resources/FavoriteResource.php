<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FavoriteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'user_id'    => $this->user_id,
            'product_id' => $this->product_id,
            
            // 🌟 ហៅ StorefrontProductResource មកប្រើ ដើម្បីឱ្យចេញរូបភាព និងតម្លៃត្រឹមត្រូវដូចកន្ត្រកដែរ
            'product'    => new StorefrontProductResource($this->whenLoaded('product')),
            
            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}
