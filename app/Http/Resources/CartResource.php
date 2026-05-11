<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // គណនាតម្លៃសរុបនៃកន្ត្រកទាំងមូល (សរុប item_total_price ទាំងអស់បញ្ចូលគ្នា)
        $totalCartPrice = 0;
        if ($this->relationLoaded('items')) {
            foreach ($this->items as $item) {
                if ($item->relationLoaded('product')) {
                    // បញ្ជាក់៖ សូមប្តូរ 'price' ទៅតាមឈ្មោះ Field ពិតនៅក្នុង table products របស់អ្នក
                    $totalCartPrice += ($item->quantity * $item->product->final_price);
                }
            }
        }

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'total_items' => $this->relationLoaded('items') ? $this->items->sum('quantity') : 0,
            'total_cart_price' => $totalCartPrice,
            'items' => CartItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
        ];
    }
}
