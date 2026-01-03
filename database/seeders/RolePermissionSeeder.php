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

                // Customer Groups
                'view_customer_groups', 'create_customer_groups', 'edit_customer_groups',
                'delete_customer_groups', 'manage_customer_groups',

                // Customer Tags
                'view_customer_tags', 'create_customer_tags', 'edit_customer_tags', 'delete_customer_tags',

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

                // Returns
                'view_returns', 'create_returns', 'approve_returns', 'reject_returns',

                // Quotations
                'view_quotations', 'create_quotations', 'edit_quotations', 'delete_quotations', 'convert_quotations',

                // Suppliers
                'view_suppliers', 'create_suppliers', 'edit_suppliers', 'delete_suppliers',

                // Brands
                'view_brands', 'create_brands', 'edit_brands', 'delete_brands',

                // Units
                'view_units', 'create_units', 'edit_units', 'delete_units',

                // Promotions
                'view_promotions', 'create_promotions', 'edit_promotions', 'delete_promotions',
                'toggle_promotions', 'apply_coupons',

                // Import/Export
                'import_products', 'import_customers', 'import_inventory', 'update_prices_bulk',
                'export_data', 'download_templates',

                // Barcodes
                'generate_barcodes', 'print_labels', 'print_labels_bulk',

                // Loyalty
                'view_loyalty_program', 'configure_loyalty_program', 'manage_loyalty_tiers',
                'view_customer_points', 'enroll_customers_loyalty', 'redeem_points',
                'adjust_points', 'view_points_transactions',

                // Gift Cards
                'view_gift_cards', 'sell_gift_cards', 'check_gift_card_balance',
                'redeem_gift_cards', 'reload_gift_cards', 'void_gift_cards', 'view_gift_card_reports',

                // Audit
                'view_audit_logs', 'export_audit_logs',

                // Notifications
                'view_notifications', 'configure_notifications', 'send_notifications',
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

                // Customer Groups
                'view_customer_groups', 'create_customer_groups', 'edit_customer_groups',

                // Customer Tags
                'view_customer_tags', 'create_customer_tags', 'edit_customer_tags',

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

                // Returns
                'view_returns', 'create_returns', 'approve_returns',

                // Quotations
                'view_quotations', 'create_quotations', 'edit_quotations', 'convert_quotations',

                // Suppliers
                'view_suppliers', 'create_suppliers', 'edit_suppliers',

                // Brands
                'view_brands', 'create_brands', 'edit_brands',

                // Units
                'view_units', 'create_units', 'edit_units',

                // Promotions
                'view_promotions', 'create_promotions', 'edit_promotions', 'toggle_promotions', 'apply_coupons',

                // Import/Export
                'import_products', 'import_customers', 'import_inventory', 'update_prices_bulk',
                'export_data', 'download_templates',

                // Barcodes
                'generate_barcodes', 'print_labels', 'print_labels_bulk',

                // Loyalty
                'view_loyalty_program', 'view_customer_points', 'enroll_customers_loyalty',
                'redeem_points', 'view_points_transactions',

                // Gift Cards
                'view_gift_cards', 'sell_gift_cards', 'check_gift_card_balance',
                'redeem_gift_cards', 'reload_gift_cards', 'view_gift_card_reports',

                // Audit
                'view_audit_logs',

                // Notifications
                'view_notifications', 'send_notifications',
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

                // Returns
                'view_returns', 'create_returns',

                // Quotations
                'view_quotations', 'create_quotations', 'convert_quotations',

                // Promotions
                'view_promotions', 'apply_coupons',

                // Loyalty
                'view_customer_points', 'enroll_customers_loyalty', 'redeem_points', 'view_points_transactions',

                // Gift Cards
                'view_gift_cards', 'sell_gift_cards', 'check_gift_card_balance', 'redeem_gift_cards',

                // Notifications
                'view_notifications',
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

                // Suppliers
                'view_suppliers', 'create_suppliers', 'edit_suppliers',

                // Brands
                'view_brands', 'create_brands', 'edit_brands',

                // Units
                'view_units', 'create_units', 'edit_units',

                // Import/Export
                'import_products', 'import_inventory', 'update_prices_bulk',
                'export_data', 'download_templates',

                // Barcodes
                'generate_barcodes', 'print_labels', 'print_labels_bulk',

                // Notifications
                'view_notifications',
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
