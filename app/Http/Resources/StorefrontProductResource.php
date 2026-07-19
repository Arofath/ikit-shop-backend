<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StorefrontProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'name'             => $this->name,
            'slug'             => $this->slug,
            'sku'              => $this->sku,

            // 🌟 តម្លៃលក់ (លុបចោល cost_price ទាំងស្រុង)
            'price'            => (float) $this->price,
            'discount_percent' => (float) $this->discount_percent,
            'final_price'      => (float) ($this->price - ($this->price * ($this->discount_percent / 100))),

            'description'      => $this->description,

            // 🌟 រូបភាព (ទាញយកតែ URL មកតែម្តង ព្រោះ Storefront មិនត្រូវការ ID រូបភាពទេ)
            'thumbnail'        => $this->whenLoaded('thumbnail', fn() => $this->thumbnail->image_path),
            'images'           => $this->whenLoaded('images', fn() => $this->images->pluck('image_path')),

            // 🌟 ទំនាក់ទំនង (Relationships)
            'categories'       => CategoryResource::collection($this->whenLoaded('categories')),
            'brand'            => new BrandResource($this->whenLoaded('brand')),
            'warranty'         => $this->whenLoaded('warranty'),
            'specs'            => ProductSpecResource::collection($this->whenLoaded('specs')),

            // 🌟 ព័ត៌មានបន្ថែមសម្រាប់បង្ហាញ UI
            'is_recommended'   => (bool) $this->is_recommended,
            'current_stock'    => (int) ($this->current_stock ?? 0),
            'is_serialized' => (bool) $this->is_serialized,
        ];
    }
}
