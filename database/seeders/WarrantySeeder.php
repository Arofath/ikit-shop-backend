<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Warranty;

class WarrantySeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            ['name' => '1 Year Local', 'duration_months' => 12, 'type' => 'STORE'],
            ['name' => '2 Years Global', 'duration_months' => 24, 'type' => 'MANUFACTURER'],
        ];

        foreach ($data as $w) {
            Warranty::create($w);
        }
    }
}
