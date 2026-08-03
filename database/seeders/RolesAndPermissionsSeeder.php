<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // 🌟 ១. សម្អាត Cache របស់ Spatie ជាមុនសិន ដើម្បីកុំឱ្យមានបញ្ហាពេល Run
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 🌟 ២. បង្កើតបញ្ជី Permissions ទាំងអស់ដែលយើងត្រូវការ
        $permissions = [
            'view-dashboard',
            'manage-products',   // រួមបញ្ចូលការ បង្កើត កែប្រែ លុប ទំនិញ
            'manage-categories', // រួមបញ្ចូលការ បង្កើត កែប្រែ លុប ប្រភេទ
            'manage-brands',
            'manage-orders',     // ផ្លាស់ប្តូរស្ថានភាពបញ្ជាទិញ
            'manage-users',      // គ្រប់គ្រងគណនី Admin ឬ Customer ផ្សេងៗ
            'manage-settings',   // កែប្រែ Settings របស់ System
            'view-reports',
        ];

        // បញ្ចូល Permissions ទៅក្នុង Database
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // 🌟 ៣. បង្កើត Roles និងចង Permissions ទៅឱ្យ Role នីមួយៗ

        // ក. Role: Customer (ជាធម្មតាមិនសូវមាន Permission ពិសេសទេ ព្រោះសិទ្ធិទូទៅគឺអាចទិញទំនិញបាន)
        Role::firstOrCreate(['name' => 'customer']);

        // ខ. Role: Sale Staff (បុគ្គលិកផ្នែកលក់)
        $saleStaffRole = Role::firstOrCreate(['name' => 'sale_staff']);
        $saleStaffRole->givePermissionTo([
            'view-dashboard',
            'manage-orders',
            'view-reports'
        ]);

        // គ. Role: Admin (អ្នកគ្រប់គ្រងទូទៅ)
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $adminRole->givePermissionTo([
            'view-dashboard',
            'manage-products',
            'manage-categories',
            'manage-brands',
            'manage-orders',
            'view-reports',
            'manage-users' // អាចបង្កើត staff ថ្មីបាន តែមិនអាចប៉ះពាល់ super_admin
        ]);

        // ឃ. Role: Super Admin (មេធំ)
        // ចំណាំ៖ យើងមិនចាំបាច់ givePermissionTo ឱ្យ Super Admin ទេ ព្រោះយើងនឹងប្រើ Gate ដើម្បី Bypass គ្រប់សិទ្ធិរបស់គាត់
        Role::firstOrCreate(['name' => 'super_admin']);
    }
}
