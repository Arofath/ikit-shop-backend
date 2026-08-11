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

        // ២. បង្កើតបញ្ជី Permissions ដោយបែងចែក group_name ស្របតាម Sidebar ទាំងស្រុង
        $permissions = [
            // Analytics & Reports
            ['name' => 'view-dashboard', 'group_name' => 'Analytics'],
            ['name' => 'view-reports', 'group_name' => 'Analytics'],

            // Catalog (Products, Categories, Brands, Warranties)
            ['name' => 'view-products', 'group_name' => 'Catalog'],
            ['name' => 'create-products', 'group_name' => 'Catalog'],
            ['name' => 'edit-products', 'group_name' => 'Catalog'],
            ['name' => 'delete-products', 'group_name' => 'Catalog'],
            ['name' => 'manage-categories', 'group_name' => 'Catalog'],
            ['name' => 'manage-brands', 'group_name' => 'Catalog'],

            // Inventory & Logistics
            ['name' => 'manage-inventory', 'group_name' => 'Inventory & Logistics'],
            ['name' => 'manage-serials', 'group_name' => 'Inventory & Logistics'],
            ['name' => 'manage-suppliers', 'group_name' => 'Inventory & Logistics'],

            // Sales & Orders
            ['name' => 'view-orders', 'group_name' => 'Sales & Orders'],
            ['name' => 'edit-orders', 'group_name' => 'Sales & Orders'],
            ['name' => 'delete-orders', 'group_name' => 'Sales & Orders'],

            // Storefront
            ['name' => 'manage-storefront', 'group_name' => 'Storefront'],

            // Customer Support
            ['name' => 'manage-support', 'group_name' => 'Customer Support'],

            // User Management
            ['name' => 'view-users', 'group_name' => 'User Management'],
            ['name' => 'manage-users', 'group_name' => 'User Management'],
            ['name' => 'manage-roles', 'group_name' => 'User Management'],

            // Settings & System
            ['name' => 'manage-settings', 'group_name' => 'Settings & System'],
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
        $saleStaffRole->syncPermissions([
            'view-dashboard',
            'view-orders',
            'edit-orders',
            'view-reports'
        ]);

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        // Admin បានសិទ្ធិគ្រប់យ៉ាងលើកលែងតែ manage-roles
        $adminRole->syncPermissions([
            'view-dashboard',
            'view-reports',
            'view-products',
            'create-products',
            'edit-products',
            'delete-products',
            'manage-categories',
            'manage-brands',
            'manage-inventory',
            'manage-serials',
            'manage-suppliers',
            'view-orders',
            'edit-orders',
            'delete-orders',
            'manage-storefront',
            'manage-support',
            'view-users',
            'manage-users',
            'manage-settings'
        ]);

        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin']);
        // Super Admin អាចយកគ្រប់ Permissions ទាំងអស់ដោយស្វ័យប្រវត្តិក៏បាន ឬ sync ទាំងអស់ក៏បាន
        $superAdminRole->syncPermissions(Permission::all());
    }
}
