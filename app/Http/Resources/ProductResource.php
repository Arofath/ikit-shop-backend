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

            // Gallery: បត់បែនតាមអ្នកប្រើប្រាស់
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
            'category'       => new CategoryResource($this->whenLoaded('category')),
            'brand'          => new BrandResource($this->whenLoaded('brand')),
            'warranty'       => $this->whenLoaded('warranty'),
            'product_series' => $this->whenLoaded('productSeries'),
            'specs'          => ProductSpecResource::collection($this->whenLoaded('specs')),

            'is_active'      => (bool) $this->is_active,
            'created_at'     => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at'     => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
