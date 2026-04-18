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
                // 🌟 ការពារ Error បើ Product ស្មើ null
                'name' => ($this->relationLoaded('product') && $this->product) ? $this->product->name : 'N/A',
                'sku'  => ($this->relationLoaded('product') && $this->product) ? $this->product->sku : null,
            ],
            // ព័ត៌មាននៃការទិញចូល
            'purchase_info' => $this->whenLoaded('stockMovement', function () {
                return [
                    // 🌟 ឆែកមើលបើមាន created_at ទើប format
                    'date'             => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : 'N/A',
                    // 🌟 ដាក់ ?-> ការពារ Error បើអត់មាន stockMovement
                    'reference_number' => $this->stockMovement?->reference_number ?? 'N/A',
                    'supplier_name'    => $this->stockMovement?->supplier?->name ?? 'N/A',
                ];
            }),
            // ព័ត៌មាននៃការលក់ចេញ (បង្ហាញតែពេលលក់រួច)
            'sale_info' => $this->whenLoaded('soldMovement', function () {
                // បើមិនទាន់លក់ចេញទេ (គ្មាន sold_movement_id) ឱ្យចេញ null
                if (!$this->sold_movement_id) {
                    return null;
                }

                return [
                    // 🌟 ឆែកមើលបើមាន updated_at ទើប format
                    'date'             => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : 'N/A',
                    // 🌟 ដាក់ ?-> ការពារ Error
                    'reference_number' => $this->soldMovement?->reference_number ?? 'N/A',
                ];
            }),
        ];
    }
}
