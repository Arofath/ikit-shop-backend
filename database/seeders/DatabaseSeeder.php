<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        $this->call([
            // --- ដំណាក់កាលទី ១ ---
            CategorySeeder::class,
            BrandSeeder::class,
            WarrantySeeder::class,
            ProductSeriesSeeder::class,
            ProductSeeder::class, // (Specs និង Images រួមក្នុងនេះ)

            // --- ដំណាក់កាលទី ២ ---
            SupplierSeeder::class,
            StockSeeder::class, // (Movements និង Serials រួមក្នុងនេះ)

            // CMS / Frontend
            SlideshowSeeder::class,
        ]);
    }
}
