<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductSerialResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'serial_number' => $this->serial_number,
            'status'        => $this->status, // AVAILABLE, SOLD, etc.
            'product'       => [
                'id'   => $this->product_id,
                // 🌟 ប្រើ $this->whenLoaded ដើម្បីកុំឱ្យ Error ពេលអត់បាន Eager Load Product
                'name' => $this->relationLoaded('product') ? $this->product->name : 'N/A',
                'sku'  => $this->relationLoaded('product') ? $this->product->sku : null, // ថែម SKU បន្តិចក៏ល្អ
            ],
            // ព័ត៌មាននៃការទិញចូល
            'purchase_info' => $this->whenLoaded('stockMovement', function () {
                return [
                    'date'             => $this->created_at->format('Y-m-d H:i:s'),
                    'reference_number' => $this->stockMovement->reference_number ?? 'N/A',
                    // 🌟 ប្រើ Optional Helper (?->) ការពារការ Error បើអត់មាន Supplier
                    'supplier_name'    => $this->stockMovement->supplier?->name ?? 'N/A',
                ];
            }),
            // ព័ត៌មាននៃការលក់ចេញ (បង្ហាញតែពេលលក់រួច)
            'sale_info' => $this->sold_movement_id ? clone $this->whenLoaded('soldMovement', function () {
                return [
                    'date'             => $this->updated_at->format('Y-m-d H:i:s'),
                    'reference_number' => $this->soldMovement->reference_number ?? 'N/A',
                ];
            }) : null,
        ];
    }
}
