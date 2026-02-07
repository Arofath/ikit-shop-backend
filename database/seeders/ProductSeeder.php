<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use Illuminate\Support\Str;
use App\Models\ProductSeries;
use App\Models\Warranty;


class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // ទាញយកទិន្នន័យចាំបាច់
        $categories = Category::whereNotNull('parent_id')->get();
        $warranties = Warranty::all();

        // បង្កើតផលិតផលគំរូ
        $products = [
            [
                'name' => 'MacBook Pro M3 14"',
                'brand' => 'Apple',
                'series' => 'MacBook Pro',
                'price' => 1999,
                'specs' => [
                    // កែ key -> spec_key, value -> spec_value និងបន្ថែម spec_group
                    ['spec_group' => 'Processor', 'spec_key' => 'CPU', 'spec_value' => 'Apple M3 Chip'],
                    ['spec_group' => 'Memory', 'spec_key' => 'RAM', 'spec_value' => '16GB Unified Memory'],
                    ['spec_group' => 'Storage', 'spec_key' => 'Storage', 'spec_value' => '512GB SSD'],
                    ['spec_group' => 'Display', 'spec_key' => 'Screen', 'spec_value' => '14.2-inch Liquid Retina XDR'],
                ],
                'images' => ['products/macbook-1.webp', 'products/macbook-2.webp']
            ],
            [
                'name' => 'Asus TUF Gaming F16',
                'brand' => 'Asus',
                'series' => 'TUF Gaming',
                'price' => 1299,
                'specs' => [
                    ['spec_group' => 'Processor', 'spec_key' => 'CPU', 'spec_value' => 'Intel Core i7-13650HX'],
                    ['spec_group' => 'Graphics', 'spec_key' => 'GPU', 'spec_value' => 'RTX 4060 8GB'],
                    ['spec_group' => 'Memory', 'spec_key' => 'RAM', 'spec_value' => '16GB DDR5'],
                    ['spec_group' => 'Display', 'spec_key' => 'Screen', 'spec_value' => '16" FHD+ 165Hz'],
                ],
                'images' => ['products/tuf-1.webp', 'products/tuf-2.webp']
            ],
        ];

        foreach ($products as $p) {
            $brand = Brand::where('name', $p['brand'])->first();
            $series = ProductSeries::where('name', $p['series'])->first();
            $warranty = $warranties->random();

            if ($brand && $series) {
                // ១. បង្កើត Product
                $product = Product::create([
                    'name'              => $p['name'],
                    'slug'              => Str::slug($p['name']) . '-' . Str::random(5),
                    'sku'               => strtoupper(substr($brand->name, 0, 3)) . '-' . rand(1000, 9999),
                    'category_id'       => $categories->random()->id,
                    'brand_id'          => $brand->id,
                    'product_series_id' => $series->id,
                    'warranty_id'       => $warranty->id,
                    'price'             => $p['price'],
                    'discount_percent'  => rand(0, 10),
                    'description'       => "Premium {$p['name']} with full warranty and high performance.",
                    'is_active'         => true,
                ]);

                // ២. បង្កើត Specs (ប្រើ Relationship: specs())
                if (isset($p['specs'])) {
                    $product->specs()->createMany($p['specs']);
                }

                // ៣. បង្កើត Images (ប្រើ Relationship: images())
                if (isset($p['images'])) {
                    foreach ($p['images'] as $index => $path) {
                        $product->images()->create([
                            'image_path' => $path,
                            'is_thumbnail' => $index === 0 // រូបភាពទី១ ជាផ្ទាំងមេ
                        ]);
                    }
                }
            }
        }
    }
}
