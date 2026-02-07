<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Brand;
use Illuminate\Support\Str;

class BrandSeeder extends Seeder
{
    public function run(): void
    {
        $brands = [
            ['name' => 'Apple', 'logo' => null],
            ['name' => 'Samsung', 'logo' => null],
            ['name' => 'Dell', 'logo' => null],
            ['name' => 'Asus', 'logo' => null],
            ['name' => 'Logitech', 'logo' => null],
            ['name' => 'Razer', 'logo' => null],
            ['name' => 'HP', 'logo' => null],
            ['name' => 'Lenovo', 'logo' => null],
            ['name' => 'Sony', 'logo' => null],
            ['name' => 'MSI', 'logo' => null],
        ];

        foreach ($brands as $brand) {
            Brand::create([
                'name'      => $brand['name'],
                'slug'      => Str::slug($brand['name']),
                'logo'      => $brand['logo'],
                'is_active' => true,
            ]);
        }
    }
}
