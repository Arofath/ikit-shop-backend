<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ProductSeries;
use App\Models\Brand;
use Illuminate\Support\Str;


class ProductSeriesSeeder extends Seeder
{
    public function run(): void
    {
        // កំណត់ស៊េរីផលិតផលតាម Brand នីមួយៗ
        $seriesData = [
            'Apple'    => ['MacBook Pro', 'MacBook Air', 'iMac', 'Mac Mini'],
            'Asus'     => ['ROG Zephyrus', 'TUF Gaming', 'Zenbook', 'Vivobook'],
            'MSI'      => ['Katana', 'Stealth', 'Raider', 'Modern'],
            'Dell'     => ['XPS', 'Alienware', 'Inspiron', 'Latitude'],
            'Samsung'  => ['Galaxy Book Ultra', 'Galaxy Book Pro'],
            'HP'       => ['Spectre', 'Envy', 'Pavilion', 'Victus'],
            'Lenovo'   => ['ThinkPad', 'Legion', 'Yoga', 'IdeaPad'],
            'Razer'    => ['Blade 14', 'Blade 15', 'Blade 16'],
            'Logitech' => ['MX Series', 'G Series', 'Signature'],
            'Sony'     => ['Alpha Series', 'ZV Series'],
        ];

        foreach ($seriesData as $brandName => $seriesList) {
            $brand = Brand::where('name', $brandName)->first();

            if ($brand) {
                foreach ($seriesList as $name) {
                    ProductSeries::create([
                        'brand_id'    => $brand->id,
                        'name'        => $name,
                        'slug'        => Str::slug($name),
                        'description' => "High-performance {$name} series from {$brandName}.",
                        'is_active'   => true,
                    ]);
                }
            }
        }
    }
}
