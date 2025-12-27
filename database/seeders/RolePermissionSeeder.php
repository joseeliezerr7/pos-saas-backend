<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Permission;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define permissions for each role
        $rolePermissions = [
            'Super Administrador' => 'all', // All permissions

            'Administrador' => [
                // Dashboard
                'view_dashboard',

                // POS
                'access_pos', 'create_sales', 'apply_discounts', 'cancel_sales',

                // Products
                'view_products', 'create_products', 'edit_products', 'delete_products',

                // Categories
                'view_categories', 'create_categories', 'edit_categories', 'delete_categories',

                // Customers
                'view_customers', 'create_customers', 'edit_customers', 'delete_customers',

                // Sales
                'view_sales', 'view_sale_details', 'void_sales',

                // Invoices
                'view_invoices', 'create_invoices', 'void_invoices',

                // Inventory
                'view_inventory', 'adjust_inventory', 'transfer_inventory',

                // Purchases
                'view_purchases', 'create_purchases', 'edit_purchases', 'delete_purchases',

                // Expenses
                'view_expenses', 'create_expenses', 'edit_expenses', 'delete_expenses',

                // Cash Registers
                'view_cash_registers', 'create_cash_registers', 'edit_cash_registers', 'delete_cash_registers',
                'open_cash_register', 'close_cash_register', 'add_cash_transactions',
                'view_cash_register_history', 'view_cash_register_reports',

                // Reports
                'view_reports', 'export_reports',

                // Users
                'view_users', 'create_users', 'edit_users', 'delete_users',

                // Roles
                'view_roles', 'create_roles', 'edit_roles', 'delete_roles',

                // Settings
                'view_settings', 'edit_settings', 'manage_branches',
            ],

            'Gerente' => [
                // Dashboard
                'view_dashboard',

                // POS
                'access_pos', 'create_sales', 'apply_discounts',

                // Products
                'view_products', 'create_products', 'edit_products',

                // Categories
                'view_categories', 'create_categories', 'edit_categories',

                // Customers
                'view_customers', 'create_customers', 'edit_customers',

                // Sales
                'view_sales', 'view_sale_details', 'void_sales',

                // Invoices
                'view_invoices', 'create_invoices',

                // Inventory
                'view_inventory', 'adjust_inventory', 'transfer_inventory',

                // Purchases
                'view_purchases', 'create_purchases', 'edit_purchases',

                // Expenses
                'view_expenses', 'create_expenses', 'edit_expenses',

                // Cash Registers
                'view_cash_registers', 'open_cash_register', 'close_cash_register',
                'add_cash_transactions', 'view_cash_register_history', 'view_cash_register_reports',

                // Reports
                'view_reports', 'export_reports',

                // Users
                'view_users',
            ],

            'Cajero' => [
                // Dashboard
                'view_dashboard',

                // POS
                'access_pos', 'create_sales',

                // Products
                'view_products',

                // Categories
                'view_categories',

                // Customers
                'view_customers', 'create_customers', 'edit_customers',

                // Sales
                'view_sales', 'view_sale_details',

                // Invoices
                'view_invoices', 'create_invoices',

                // Cash Registers
                'view_cash_registers', 'open_cash_register', 'close_cash_register', 'add_cash_transactions',
            ],

            'Inventario' => [
                // Dashboard
                'view_dashboard',

                // Products
                'view_products', 'create_products', 'edit_products',

                // Categories
                'view_categories', 'create_categories', 'edit_categories',

                // Inventory
                'view_inventory', 'adjust_inventory', 'transfer_inventory',

                // Purchases
                'view_purchases', 'create_purchases', 'edit_purchases', 'delete_purchases',

                // Expenses
                'view_expenses', 'create_expenses', 'edit_expenses', 'delete_expenses',

                // Reports
                'view_reports',
            ],
        ];

        foreach ($rolePermissions as $roleName => $permissions) {
            $role = Role::where('name', $roleName)->first();

            if (!$role) {
                $this->command->warn("Role '{$roleName}' not found, skipping...");
                continue;
            }

            if ($permissions === 'all') {
                // Assign all permissions
                $allPermissions = Permission::all()->pluck('id')->toArray();
                $role->permissions()->sync($allPermissions);
                $this->command->info("Assigned ALL permissions to '{$roleName}'");
            } else {
                // Assign specific permissions by slug
                $permissionIds = Permission::whereIn('slug', $permissions)->pluck('id')->toArray();
                $role->permissions()->sync($permissionIds);
                $this->command->info("Assigned " . count($permissionIds) . " permissions to '{$roleName}'");
            }
        }

        $this->command->info("\nPermissions assigned successfully to all roles!");
    }
}
