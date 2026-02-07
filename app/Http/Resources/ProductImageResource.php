<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductImageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            //'product_id'   => $this->product_id,
            'image_url'    => $this->image_path, // យើងអាចកែឈ្មោះ key ឱ្យស្រួលយល់
            'is_thumbnail' => (bool) $this->is_thumbnail,
            'sort_order'   => $this->sort_order,
        ];
    }
}
