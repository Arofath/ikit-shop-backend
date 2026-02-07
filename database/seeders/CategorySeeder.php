<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Category;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Computers',
                'sub' => [
                    [
                        'name' => 'Laptops',
                        'sub' => [
                            ['name' => 'Gaming Laptops'],
                            ['name' => 'Business Laptops'],
                            ['name' => 'Ultrabooks'],
                        ]
                    ],
                    [
                        'name' => 'Desktops',
                        'sub' => [
                            ['name' => 'All-in-One'],
                            ['name' => 'Mini PC'],
                        ]
                    ],
                ]
            ],
            [
                'name' => 'Accessories',
                'sub' => [
                    ['name' => 'Keyboards'],
                    ['name' => 'Mice'],
                    ['name' => 'Monitors'],
                    ['name' => 'Headsets'],
                ]
            ],
            [
                'name' => 'Components',
                'sub' => [
                    ['name' => 'Graphic Cards'],
                    ['name' => 'Processors (CPU)'],
                    ['name' => 'Motherboards'],
                    ['name' => 'RAM'],
                ]
            ],
        ];

        foreach ($categories as $cat) {
            // បង្កើត Root Category (មេធំ)
            $parent = Category::create([
                'name'      => $cat['name'],
                'slug'      => Str::slug($cat['name']),
                'is_active' => true,
            ]);

            if (isset($cat['sub'])) {
                foreach ($cat['sub'] as $sub) {
                    // បង្កើត Sub-category ជាន់ទី ១
                    $child = Category::create([
                        'name'      => $sub['name'],
                        'slug'      => Str::slug($sub['name']),
                        'parent_id' => $parent->id,
                        'is_active' => true,
                    ]);

                    if (isset($sub['sub'])) {
                        foreach ($sub['sub'] as $deepSub) {
                            // បង្កើត Sub-category ជាន់ទី ២
                            Category::create([
                                'name'      => $deepSub['name'],
                                'slug'      => Str::slug($deepSub['name']),
                                'parent_id' => $child->id,
                                'is_active' => true,
                            ]);
                        }
                    }
                }
            }
        }
    }
}
