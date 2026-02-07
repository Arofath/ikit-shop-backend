<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Supplier;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = [
            ['name' => 'K-Four Cambodia', 'phone' => '023 123 456', 'email' => 'info@kfour.com.kh', 'address' => 'Phnom Penh'],
            ['name' => 'Chantra Computer', 'phone' => '012 888 999', 'email' => 'sales@chantra.com', 'address' => 'Siem Reap'],
            ['name' => 'PTC Computer', 'phone' => '023 222 333', 'email' => 'contact@ptc.com.kh', 'address' => 'Phnom Penh'],
        ];

        foreach ($suppliers as $s) {
            Supplier::create($s);
        }
    }
}
