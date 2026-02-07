<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SlideshowResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'image_url'  => $this->image_path,
            'position'   => $this->position,
            'is_active'  => $this->is_active,
            'series'     => $this->whenLoaded('series', function () {
                return [
                    'id'   => $this->series->id,
                    'name' => $this->series->name,
                    'slug' => $this->series->slug,
                ];
            }),
        ];
    }
}
