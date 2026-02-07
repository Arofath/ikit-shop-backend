<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Slideshow;
use App\Models\ProductSeries;

class SlideshowSeeder extends Seeder
{
    public function run(): void
    {
        // ទាញយក Series ខ្លះៗមកធ្វើជា Link
        $macbook = ProductSeries::where('name', 'MacBook Pro')->first();
        $tuf = ProductSeries::where('name', 'TUF Gaming')->first();
        $rog = ProductSeries::where('name', 'ROG Zephyrus')->first();

        $slides = [
            [
                'image_path' => 'banners/macbook_promo.webp',
                'product_series_id' => $macbook?->id,
                'position' => 1,
                'is_active' => true,
            ],
            [
                'image_path' => 'banners/tuf_gaming_f16.webp',
                'product_series_id' => $tuf?->id,
                'position' => 2,
                'is_active' => true,
            ],
            [
                'image_path' => 'banners/rog_zephyrus_promo.webp',
                'product_series_id' => $rog?->id,
                'position' => 3,
                'is_active' => true,
            ],
        ];

        foreach ($slides as $slide) {
            Slideshow::create($slide);
        }
    }
}
