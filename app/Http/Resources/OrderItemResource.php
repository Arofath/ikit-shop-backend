<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\ProductSerial;
use App\Models\ProductStockMovement;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // ២. ទាញយកលេខវិក្កយបត្រ (Order Number) តាមរយៈ Relationship ជាមួយ Order
        $orderNumber = $this->order->order_number ?? '';

        // ៣. រកមើលប្រវត្តិដកស្តុក (OUT) របស់ Product នេះ ក្នុងវិក្កយបត្រនេះ
        $outMovement = ProductStockMovement::where('reference_number', $orderNumber)
            ->where('product_id', $this->product_id)
            ->where('type', 'OUT')
            ->first();

        // ៤. ទាញយកបញ្ជីលេខ Serial ដែលបានស្កេនរួច
        $scannedSerials = [];
        if ($outMovement) {
            $scannedSerials = ProductSerial::where('sold_movement_id', $outMovement->id)
                ->pluck('serial_number')
                ->toArray();
        }

        // ៥. ផ្ទៀងផ្ទាត់ថាតើស្កេនគ្រប់ចំនួនហើយឬនៅ? (ធៀបនឹងចំនួន quantity ដែលបានកម្ម៉ង់)
        $isFulfilled = count($scannedSerials) >= $this->quantity;

        return [
            'id'           => $this->id,
            'product_id'   => $this->product_id,
            'product_name' => $this->product_name,
            'product_sku'  => $this->product_sku,
            'quantity'     => (int) $this->quantity,
            'unit_price'   => (float) $this->unit_price,
            'subtotal'     => (float) $this->subtotal,

            // 🌟 ហៅ StorefrontProductResource ដូចដើម
            'product'      => new StorefrontProductResource($this->whenLoaded('product')),

            // 🌟 ៦. បន្ថែមទិន្នន័យថ្មីទាំង ២ នេះទៅខាងចុង
            'scanned_serials'     => $scannedSerials,
            'is_serial_fulfilled' => $isFulfilled,
        ];
    }
}
