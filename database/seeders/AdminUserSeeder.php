<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@ikitshop.com'],
            [
                'name' => 'Sles Rofath',
                'phone_number' => '0965824220',
                'password' => Hash::make('Admin@2026'),
                'role' => 'admin',
                'provider' => 'local',
                'is_active' => true,
            ]
        );
    }
}
