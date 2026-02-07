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
                'name' => $this->product->name ?? 'N/A',
            ],
            // ព័ត៌មាននៃការទិញចូល
            'purchase_info' => [
                'date'             => $this->created_at->format('Y-m-d H:i:s'),
                'reference_number' => $this->stockMovement->reference_number ?? 'N/A',
                'supplier_name'    => $this->stockMovement->supplier->name ?? 'N/A',
            ],
            // ព័ត៌មាននៃការលក់ចេញ (បង្ហាញតែពេលលក់រួច)
            'sale_info' => $this->sold_movement_id ? [
                'date'             => $this->updated_at->format('Y-m-d H:i:s'),
                'reference_number' => $this->soldMovement->reference_number ?? 'N/A',
            ] : null,
        ];
    }
}
