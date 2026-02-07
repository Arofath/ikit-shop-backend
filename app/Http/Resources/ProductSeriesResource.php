<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductSeriesResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'slug'        => $this->slug,
            'description' => $this->description,
            'is_active'   => (bool) $this->is_active,
            // បន្ថែមព័ត៌មាន Brand ដើម្បីដឹងថា Series នេះជារបស់ម៉ាកអ្វី (ឧ៖ ASUS)
            'brand'       => new BrandResource($this->whenLoaded('brand')),
            // បន្ថែមចំនួនផលិតផលដែលមានក្នុង Series នេះ
            'products_count' => $this->whenCounted('products'),
        ];
    }
}
