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
            'date'             => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}

/*
'product_name'     => $this->product->name ?? 'N/A',
'supplier_name'    => $this->supplier->name ?? 'N/A',
*/