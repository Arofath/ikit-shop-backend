<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WarrantyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'duration_months' => $this->duration_months,
            'type'            => $this->type, // MANUFACTURER, STORE, etc.
            'description'     => $this->description,
            'is_active'       => (bool) $this->is_active,
            'created_at'      => $this->created_at->format('Y-m-d H:i:s'),
            // បង្ហាញចំនួនផលិតផលដែលប្រើប្រាស់កិច្ចសន្យាធានានេះ
            'products_count'  => $this->whenCounted('products'),
        ];
    }
}
