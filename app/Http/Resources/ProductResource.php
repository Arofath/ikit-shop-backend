<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // ឆែកថាជា Admin API ឬអត់ ដើម្បីប្តូរ Format រូបភាព
        $isAdmin = $request->is('api/admin/*');

        return [
            'id'               => $this->id,
            'name'             => $this->name,
            'slug'             => $this->slug,
            'sku'              => $this->sku,
            'price'            => (float) $this->price,
            'cost_price'       => $this->cost_price,
            'discount_percent' => (float) $this->discount_percent,
            'final_price'      => (float) ($this->price - ($this->price * ($this->discount_percent / 100))),
            'description'      => $this->description,

            // Thumbnail: បត់បែនតាមអ្នកប្រើប្រាស់
            'thumbnail' => $this->whenLoaded('thumbnail', function () use ($isAdmin) {
                if ($isAdmin) {
                    return [
                        'id' => $this->thumbnail->id,
                        'url' => $this->thumbnail->image_path
                    ];
                }
                return $this->thumbnail->image_path;
            }),

            //Gallery: បត់បែនតាមអ្នកប្រើប្រាស់
            'images' => $this->whenLoaded('images', function () use ($isAdmin) {
                if ($isAdmin) {
                    return $this->images->map(fn($img) => [
                        'id'           => $img->id,
                        'url'          => $img->image_path,
                        'is_thumbnail' => $img->is_thumbnail,
                        'sort_order'   => $img->sort_order
                    ]);
                }
                return $this->images->pluck('image_path');
            }),

            // រក្សាទុកជា Object ដដែលដើម្បីឱ្យ Frontend ងាយស្រួលប្រើប្រាស់
            'categories' => CategoryResource::collection($this->whenLoaded('categories')),
            'brand'          => new BrandResource($this->whenLoaded('brand')),
            'warranty'       => $this->whenLoaded('warranty'),
            'specs'          => ProductSpecResource::collection($this->whenLoaded('specs')),
            'is_serialized' => (bool) $this->is_serialized,
            'is_recommended' => (bool) $this->is_recommended,
            'available_serials' => $this->whenLoaded('serials', function () {
                // ច្រោះយកតែ AVAILABLE
                return $this->serials->where('status', 'AVAILABLE')->pluck('serial_number');
            }),
            'is_active'      => (bool) $this->is_active,
            'current_stock'    => (int) ($this->current_stock ?? 0),
            'created_at'     => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at'     => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
