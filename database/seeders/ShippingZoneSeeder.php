<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ShippingZone;

class ShippingZoneSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // បញ្ជីរាជធានី និងខេត្តទាំង ២៥ ជាមួយនឹងតម្លៃដឹកជញ្ជូនប៉ាន់ស្មាន
        $zones = [
            // រាជធានី និងខេត្តក្បែរៗ (តម្លៃដឹកថោក)
            ['name' => 'Phnom Penh', 'base_cost' => 1.50, 'free_shipping_threshold' => 50.00],
            ['name' => 'Kandal', 'base_cost' => 2.00, 'free_shipping_threshold' => 100.00],
            ['name' => 'Kampong Speu', 'base_cost' => 2.00, 'free_shipping_threshold' => null],
            ['name' => 'Takeo', 'base_cost' => 2.00, 'free_shipping_threshold' => null],

            // ខេត្តចម្ងាយមធ្យម 
            ['name' => 'Kampong Cham', 'base_cost' => 2.50, 'free_shipping_threshold' => null],
            ['name' => 'Tboung Khmum', 'base_cost' => 2.50, 'free_shipping_threshold' => null],
            ['name' => 'Kampong Chhnang', 'base_cost' => 2.50, 'free_shipping_threshold' => null],
            ['name' => 'Kampong Thom', 'base_cost' => 2.50, 'free_shipping_threshold' => null],
            ['name' => 'Prey Veng', 'base_cost' => 2.50, 'free_shipping_threshold' => null],
            ['name' => 'Svay Rieng', 'base_cost' => 2.50, 'free_shipping_threshold' => null],
            ['name' => 'Kampot', 'base_cost' => 2.50, 'free_shipping_threshold' => null],
            ['name' => 'Kep', 'base_cost' => 2.50, 'free_shipping_threshold' => null],
            ['name' => 'Preah Sihanouk', 'base_cost' => 2.50, 'free_shipping_threshold' => null],
            ['name' => 'Battambang', 'base_cost' => 2.50, 'free_shipping_threshold' => null],
            ['name' => 'Banteay Meanchey', 'base_cost' => 2.50, 'free_shipping_threshold' => null],
            ['name' => 'Pursat', 'base_cost' => 2.50, 'free_shipping_threshold' => null],
            ['name' => 'Siem Reap', 'base_cost' => 2.50, 'free_shipping_threshold' => null],

            // ខេត្តឆ្ងាយៗ (តម្លៃដឹកថ្លៃជាងគេ)
            ['name' => 'Koh Kong', 'base_cost' => 3.00, 'free_shipping_threshold' => null],
            ['name' => 'Kratie', 'base_cost' => 3.00, 'free_shipping_threshold' => null],
            ['name' => 'Oddar Meanchey', 'base_cost' => 3.00, 'free_shipping_threshold' => null],
            ['name' => 'Pailin', 'base_cost' => 3.00, 'free_shipping_threshold' => null],
            ['name' => 'Preah Vihear', 'base_cost' => 3.00, 'free_shipping_threshold' => null],
            ['name' => 'Stung Treng', 'base_cost' => 3.00, 'free_shipping_threshold' => null],
            ['name' => 'Mondulkiri', 'base_cost' => 3.50, 'free_shipping_threshold' => null],
            ['name' => 'Ratanakiri', 'base_cost' => 3.50, 'free_shipping_threshold' => null],
        ];

        // ប្រើ firstOrCreate ដើម្បីការពារកុំឱ្យចូលទិន្នន័យជាន់គ្នា ពេល Run Seeder ច្រើនដង
        foreach ($zones as $zone) {
            ShippingZone::firstOrCreate(
                ['name' => $zone['name']],
                [
                    'base_cost' => $zone['base_cost'],
                    'free_shipping_threshold' => $zone['free_shipping_threshold'],
                    'is_active' => true,
                ]
            );
        }
    }
}
