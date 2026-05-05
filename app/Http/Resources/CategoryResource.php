<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
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
            'image'       => $this->image,
            'parent_id'   => $this->parent_id,
            'is_active'   => (bool) $this->is_active,
            // ការបង្ហាញ sub-categories (Nested)
            'children'    => CategoryResource::collection($this->whenLoaded('children')),
            'is_popular'  => (bool) $this->is_popular,
            'sort_order'  => $this->sort_order,
            'created_at'  => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at'  => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
