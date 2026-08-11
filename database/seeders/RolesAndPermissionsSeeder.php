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
        // ១. សម្អាត Cache របស់ Spatie
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // ២. បង្កើតបញ្ជី Permissions ដោយមាន group_name
        $permissions = [
            ['name' => 'view-dashboard', 'group_name' => 'Analytics'],
            ['name' => 'view-reports', 'group_name' => 'Analytics'],
            ['name' => 'view-products', 'group_name' => 'Products'],
            ['name' => 'create-products', 'group_name' => 'Products'],
            ['name' => 'edit-products', 'group_name' => 'Products'],
            ['name' => 'delete-products', 'group_name' => 'Products'],
            ['name' => 'manage-serials', 'group_name' => 'Products'],
            ['name' => 'view-orders', 'group_name' => 'Orders'],
            ['name' => 'edit-orders', 'group_name' => 'Orders'],
            ['name' => 'delete-orders', 'group_name' => 'Orders'],
            ['name' => 'view-users', 'group_name' => 'User Management'],
            ['name' => 'manage-users', 'group_name' => 'User Management'],
            ['name' => 'manage-roles', 'group_name' => 'User Management'],
            ['name' => 'manage-settings', 'group_name' => 'Settings'],
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(
                ['name' => $perm['name']],
                ['group_name' => $perm['group_name']]
            );
        }

        // ៣. បង្កើត Roles និងចង Permissions
        Role::firstOrCreate(['name' => 'customer']);

        $saleStaffRole = Role::firstOrCreate(['name' => 'sale_staff']);
        // 🌟 កែប្រែ៖ ប្រើប្រាស់ឈ្មោះ Permission ឱ្យត្រូវនឹងខាងលើ
        $saleStaffRole->syncPermissions([
            'view-dashboard',
            'view-orders',
            'edit-orders',
            'view-reports'
        ]);

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        // 🌟 កែប្រែ៖ ផ្តល់សិទ្ធិទាំងអស់វាងលែងតែ manage-roles
        $adminRole->syncPermissions([
            'view-dashboard',
            'view-reports',
            'view-products',
            'create-products',
            'edit-products',
            'delete-products',
            'view-orders',
            'edit-orders',
            'delete-orders',
            'view-users',
            'manage-users'
        ]);

        Role::firstOrCreate(['name' => 'super_admin']);
    }
}
