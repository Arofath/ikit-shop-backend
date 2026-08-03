<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SaleStaffSeeder extends Seeder
{
    public function run(): void
    {
        // ១. បង្កើតគណនី Sale Staff (ប្រើ firstOrCreate ដើម្បីកុំឱ្យ Error ជាន់គ្នាពេល Run ច្រើនដង)
        $saleStaff = User::firstOrCreate(
            ['email' => 'fcfc6002@gmail.com'], // Email សម្រាប់ Login
            [
                'name' => 'Rofath (Sale Staff)',
                'phone_number' => '099123456',
                'password' => Hash::make('password123'), // លេខសម្ងាត់សម្រាប់ Login
                'role' => 'sale_staff',
                'is_active' => true,
                'email_verified_at' => now(), // Verify រួចរាល់
                'is_2fa_enabled' => false, // បិទ 2FA សិនដើម្បីងាយស្រួល Test
            ]
        );

        // ២. បង្កើត Profile ភ្ជាប់ជាមួយគណនីនេះ (ដោយសារ API យើងមានហៅប្រើ load('profile'))
        $saleStaff->profile()->firstOrCreate(
            ['user_id' => $saleStaff->id],
            [
                'gender' => 'Male',
                'position' => 'Sales Representative',
                'bio' => 'I am a test sale staff account.',
            ]
        );

        // ៣. ផ្តល់តួនាទី (Role) ពី Spatie
        $saleStaff->assignRole('sale_staff');

        $this->command->info('គណនី Sale Staff ត្រូវបានបង្កើតដោយជោគជ័យ!');
        $this->command->info('Email: sale@ikit.com');
        $this->command->info('Password: password123');
    }
}
