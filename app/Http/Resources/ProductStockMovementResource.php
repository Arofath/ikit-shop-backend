<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductStockMovementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'product'          => [
                'id'   => $this->product_id,
                'name' => $this->product->name ?? 'N/A',
                // 🌟 ១. បន្ថែម SKU
                'sku'  => $this->product->sku ?? 'N/A',
                // 🌟 ២. បន្ថែម Images ដើម្បីឱ្យ Frontend អាចទាញរូប Thumbnail បាន
                'images' => $this->product && $this->product->relationLoaded('images')
                    ? $this->product->images->map(function ($img) {
                        return [
                            'url' => $img->image_path, // ប្រើឈ្មោះ Column រូបភាពរបស់អ្នក
                            'is_thumbnail' => $img->is_thumbnail
                        ];
                    })
                    : [],
            ],
            'supplier'         => $this->supplier_id ? [
                'id'   => $this->supplier_id,
                'name' => $this->supplier->name ?? 'N/A',
            ] : null,
            'type'             => $this->type,
            'quantity'         => $this->quantity,
            'balance_after'    => $this->balance_after,
            'cost_price'       => $this->cost_price ? number_format($this->cost_price, 2) : null,
            'reference_number' => $this->reference_number,
            'note'             => $this->note,

            // 🌟 ៣. កុំភ្លេចដូរឈ្មោះ Key នេះទៅ created_at វិញ ទើបថ្ងៃខែលោតចេញ!
            'created_at'       => $this->created_at ? $this->created_at->toIso8601String() : null,
        ];
    }
}

/*
'product_name'     => $this->product->name ?? 'N/A',
'supplier_name'    => $this->supplier->name ?? 'N/A',
*/