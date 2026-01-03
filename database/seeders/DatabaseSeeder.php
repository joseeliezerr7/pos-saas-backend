<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\Company;
use App\Models\User;
use App\Models\Branch;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Category;
use App\Models\Catalog\Product;
use App\Models\Customer;
use App\Models\Unit;
use App\Models\Fiscal\CAI;
use App\Models\Brand;
use App\Models\Stock;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create Plans
        $plans = [
            [
                'name' => 'Básico',
                'slug' => 'basic',
                'description' => 'Plan básico para pequeños negocios',
                'price_monthly' => 299.00,
                'price_yearly' => 2990.00,
                'features' => json_encode([
                    '1 sucursal',
                    '5 usuarios',
                    '500 productos',
                    '1,000 transacciones/mes',
                    'Facturación SAR',
                ]),
                'max_branches' => 1,
                'max_users' => 5,
                'max_products' => 500,
                'max_monthly_transactions' => 1000,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Profesional',
                'slug' => 'professional',
                'description' => 'Para negocios en crecimiento',
                'price_monthly' => 599.00,
                'price_yearly' => 5990.00,
                'features' => json_encode([
                    '3 sucursales',
                    '15 usuarios',
                    '2,000 productos',
                    '5,000 transacciones/mes',
                    'Facturación SAR',
                    'Reportes avanzados',
                ]),
                'max_branches' => 3,
                'max_users' => 15,
                'max_products' => 2000,
                'max_monthly_transactions' => 5000,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Empresarial',
                'slug' => 'enterprise',
                'description' => 'Para medianas y grandes empresas',
                'price_monthly' => 1299.00,
                'price_yearly' => 12990.00,
                'features' => json_encode([
                    '10 sucursales',
                    '50 usuarios',
                    '10,000 productos',
                    'Transacciones ilimitadas',
                    'Facturación SAR',
                    'Reportes avanzados',
                    'API acceso',
                ]),
                'max_branches' => 10,
                'max_users' => 50,
                'max_products' => 10000,
                'max_monthly_transactions' => 999999,
                'is_active' => true,
                'sort_order' => 3,
            ],
        ];

        foreach ($plans as $planData) {
            Plan::create($planData);
        }

        $basicPlan = Plan::where('slug', 'basic')->first();

        // Create Roles (system-wide roles with no tenant_id)
        $roles = [
            ['name' => 'Super Administrador', 'slug' => 'super_admin', 'description' => 'Acceso total al sistema', 'is_system' => true],
            ['name' => 'Administrador', 'slug' => 'admin', 'description' => 'Administrador de la empresa', 'is_system' => true],
            ['name' => 'Gerente', 'slug' => 'manager', 'description' => 'Gerente de sucursal', 'is_system' => true],
            ['name' => 'Cajero', 'slug' => 'cashier', 'description' => 'Cajero de punto de venta', 'is_system' => true],
            ['name' => 'Inventario', 'slug' => 'inventory', 'description' => 'Encargado de inventario', 'is_system' => true],
        ];

        foreach ($roles as $roleData) {
            Role::create($roleData);
        }

        // Create Test Company 1
        $company1 = Company::create([
            'name' => 'Comercial El Éxito',
            'legal_name' => 'Comercial El Éxito S.A. de C.V.',
            'rtn' => '08011990123456',
            'email' => 'info@elexito.hn',
            'phone' => '+504 2222-3333',
            'address' => 'Col. Tepeyac, Tegucigalpa, Francisco Morazán, Honduras',
            'is_active' => true,
            'settings' => json_encode([
                'currency' => 'HNL',
                'language' => 'es',
                'timezone' => 'America/Tegucigalpa',
                'tax_rate' => 15,
            ]),
        ]);

        // Create subscription for company 1
        $company1->subscriptions()->create([
            'plan_id' => $basicPlan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'trial_ends_at' => now()->addDays(15),
            'started_at' => now(),
            'expires_at' => now()->addMonth(),
            'auto_renew' => true,
        ]);

        // Create Branches for Company 1
        $branch1 = Branch::create([
            'tenant_id' => $company1->id,
            'name' => 'Sucursal Principal',
            'code' => 'PRIN-001',
            'phone' => '+504 2222-3333',
            'address' => 'Col. Tepeyac, Tegucigalpa, Honduras',
            'is_main' => true,
            'is_active' => true,
        ]);

        $branch1b = Branch::create([
            'tenant_id' => $company1->id,
            'name' => 'Sucursal Comayagüela',
            'code' => 'COMA-002',
            'phone' => '+504 2222-4444',
            'address' => 'Col. Kennedy, Comayagüela, Honduras',
            'is_main' => false,
            'is_active' => true,
        ]);

        // Create Admin User for Company 1
        $admin1 = User::create([
            'tenant_id' => $company1->id,
            'branch_id' => $branch1->id,
            'name' => 'Carlos Administrador',
            'email' => 'admin@elexito.hn',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);

        // Assign admin role
        $adminRole = Role::where('slug', 'admin')->first();
        $admin1->roles()->attach($adminRole->id);

        // Create Cashier User for Company 1
        $cashier1 = User::create([
            'tenant_id' => $company1->id,
            'branch_id' => $branch1->id,
            'name' => 'María Cajera',
            'email' => 'cajera@elexito.hn',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);

        $cashierRole = Role::where('slug', 'cashier')->first();
        $cashier1->roles()->attach($cashierRole->id);

        // Create Test Company 2
        $company2 = Company::create([
            'name' => 'Supermercado La Esperanza',
            'legal_name' => 'Supermercado La Esperanza S. de R.L.',
            'rtn' => '12121985654321',
            'email' => 'contacto@laesperanza.hn',
            'phone' => '+504 2555-4444',
            'address' => 'Barrio La Granja, San Pedro Sula, Cortés, Honduras',
            'is_active' => true,
            'settings' => json_encode([
                'currency' => 'HNL',
                'language' => 'es',
                'timezone' => 'America/Tegucigalpa',
                'tax_rate' => 15,
            ]),
        ]);

        // Create subscription for company 2
        $company2->subscriptions()->create([
            'plan_id' => $basicPlan->id,
            'status' => 'trial',
            'billing_cycle' => 'monthly',
            'trial_ends_at' => now()->addDays(15),
            'started_at' => now(),
            'expires_at' => now()->addMonth(),
            'auto_renew' => true,
        ]);

        // Create Branch for Company 2
        $branch2 = Branch::create([
            'tenant_id' => $company2->id,
            'name' => 'Sucursal Centro',
            'code' => 'CENT-001',
            'phone' => '+504 2555-4444',
            'address' => 'Barrio La Granja, San Pedro Sula',
            'is_main' => true,
            'is_active' => true,
        ]);

        // Create Admin User for Company 2
        $admin2 = User::create([
            'tenant_id' => $company2->id,
            'branch_id' => $branch2->id,
            'name' => 'José Gerente',
            'email' => 'gerente@laesperanza.hn',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);

        $admin2->roles()->attach($adminRole->id);

        // Create Units
        $units = [
            ['tenant_id' => $company1->id, 'name' => 'Unidad', 'abbreviation' => 'UND', 'is_active' => true],
            ['tenant_id' => $company1->id, 'name' => 'Caja', 'abbreviation' => 'CJ', 'is_active' => true],
            ['tenant_id' => $company1->id, 'name' => 'Paquete', 'abbreviation' => 'PQ', 'is_active' => true],
            ['tenant_id' => $company1->id, 'name' => 'Kilogramo', 'abbreviation' => 'KG', 'is_active' => true],
            ['tenant_id' => $company1->id, 'name' => 'Litro', 'abbreviation' => 'LT', 'is_active' => true],
        ];

        foreach ($units as $unit) {
            Unit::create($unit);
        }

        $unitUnd = Unit::where('tenant_id', $company1->id)->where('abbreviation', 'UND')->first();

        // Create Categories
        $categories = [
            ['tenant_id' => $company1->id, 'name' => 'Alimentos', 'slug' => 'alimentos', 'description' => 'Productos alimenticios', 'is_active' => true],
            ['tenant_id' => $company1->id, 'name' => 'Bebidas', 'slug' => 'bebidas', 'description' => 'Bebidas y refrescos', 'is_active' => true],
            ['tenant_id' => $company1->id, 'name' => 'Limpieza', 'slug' => 'limpieza', 'description' => 'Productos de limpieza', 'is_active' => true],
            ['tenant_id' => $company1->id, 'name' => 'Electrónicos', 'slug' => 'electronicos', 'description' => 'Artículos electrónicos', 'is_active' => true],
            ['tenant_id' => $company1->id, 'name' => 'Papelería', 'slug' => 'papeleria', 'description' => 'Útiles de oficina', 'is_active' => true],
        ];

        foreach ($categories as $cat) {
            Category::create($cat);
        }

        $catAlimentos = Category::where('tenant_id', $company1->id)->where('name', 'Alimentos')->first();
        $catBebidas = Category::where('tenant_id', $company1->id)->where('name', 'Bebidas')->first();
        $catLimpieza = Category::where('tenant_id', $company1->id)->where('name', 'Limpieza')->first();

        // Create Brands
        $brands = [
            ['tenant_id' => $company1->id, 'name' => 'Diana', 'slug' => 'diana', 'description' => 'Marca líder en alimentos', 'is_active' => true],
            ['tenant_id' => $company1->id, 'name' => 'Coca Cola', 'slug' => 'coca-cola', 'description' => 'Bebidas refrescantes', 'is_active' => true],
            ['tenant_id' => $company1->id, 'name' => 'Clorox', 'slug' => 'clorox', 'description' => 'Productos de limpieza', 'is_active' => true],
            ['tenant_id' => $company1->id, 'name' => 'Bimbo', 'slug' => 'bimbo', 'description' => 'Pan y panadería', 'is_active' => true],
            ['tenant_id' => $company1->id, 'name' => 'Genérica', 'slug' => 'generica', 'description' => 'Productos sin marca', 'is_active' => true],
        ];

        foreach ($brands as $brand) {
            Brand::create($brand);
        }

        $brandDiana = Brand::where('tenant_id', $company1->id)->where('slug', 'diana')->first();
        $brandCoca = Brand::where('tenant_id', $company1->id)->where('slug', 'coca-cola')->first();
        $brandClorox = Brand::where('tenant_id', $company1->id)->where('slug', 'clorox')->first();
        $brandGenerica = Brand::where('tenant_id', $company1->id)->where('slug', 'generica')->first();

        // Create Products
        $products = [
            [
                'tenant_id' => $company1->id,
                'category_id' => $catAlimentos->id,
                'brand_id' => $brandDiana->id,
                'name' => 'Arroz Diana 1kg',
                'sku' => 'ARR-001',
                'barcode' => '7501234567890',
                'description' => 'Arroz blanco de grano largo',
                'cost' => 25.00,
                'price' => 35.00,
                'tax_type' => 'taxed',
                'is_active' => true,
                'stock_quantity' => 100,
            ],
            [
                'tenant_id' => $company1->id,
                'category_id' => $catAlimentos->id,
                'brand_id' => $brandGenerica->id,
                'name' => 'Frijoles Rojos 500g',
                'sku' => 'FRJ-001',
                'barcode' => '7501234567891',
                'description' => 'Frijoles rojos premium',
                'cost' => 18.00,
                'price' => 28.00,
                'tax_type' => 'taxed',
                'is_active' => true,
                'stock_quantity' => 150,
            ],
            [
                'tenant_id' => $company1->id,
                'category_id' => $catBebidas->id,
                'brand_id' => $brandCoca->id,
                'name' => 'Coca Cola 600ml',
                'sku' => 'BEB-001',
                'barcode' => '7501234567892',
                'description' => 'Refresco sabor cola',
                'cost' => 12.00,
                'price' => 20.00,
                'tax_type' => 'taxed',
                'is_active' => true,
                'stock_quantity' => 200,
            ],
            [
                'tenant_id' => $company1->id,
                'category_id' => $catBebidas->id,
                'brand_id' => $brandGenerica->id,
                'name' => 'Agua Azul 1L',
                'sku' => 'BEB-002',
                'barcode' => '7501234567893',
                'description' => 'Agua purificada',
                'cost' => 5.00,
                'price' => 10.00,
                'tax_type' => 'taxed',
                'is_active' => true,
                'stock_quantity' => 300,
            ],
            [
                'tenant_id' => $company1->id,
                'category_id' => $catLimpieza->id,
                'brand_id' => $brandClorox->id,
                'name' => 'Cloro Clorox 1L',
                'sku' => 'LIM-001',
                'barcode' => '7501234567894',
                'description' => 'Blanqueador líquido',
                'cost' => 22.00,
                'price' => 35.00,
                'tax_type' => 'taxed',
                'is_active' => true,
                'stock_quantity' => 80,
            ],
        ];

        foreach ($products as $productData) {
            $stockQty = $productData['stock_quantity'];
            unset($productData['stock_quantity']);

            $product = Product::create($productData);

            // Create stock for both branches
            Stock::create([
                'tenant_id' => $company1->id,
                'product_id' => $product->id,
                'branch_id' => $branch1->id,
                'quantity' => $stockQty,
                'min_stock' => 10,
                'max_stock' => 500,
            ]);

            Stock::create([
                'tenant_id' => $company1->id,
                'product_id' => $product->id,
                'branch_id' => $branch1b->id,
                'quantity' => floor($stockQty / 2), // Half stock in second branch
                'min_stock' => 5,
                'max_stock' => 250,
            ]);
        }

        // Create Customers
        $customers = [
            [
                'tenant_id' => $company1->id,
                'name' => 'Juan Pérez',
                'rtn' => '12345678901234',
                'email' => 'juan.perez@example.com',
                'phone' => '+504 9999-1111',
                'address' => 'Col. Alameda, Tegucigalpa',
                'is_active' => true,
            ],
            [
                'tenant_id' => $company1->id,
                'name' => 'María García',
                'rtn' => '56789012345678',
                'email' => 'maria.garcia@example.com',
                'phone' => '+504 9999-2222',
                'address' => 'Col. Florencia, San Pedro Sula',
                'is_active' => true,
            ],
            [
                'tenant_id' => $company1->id,
                'name' => 'Comercial Honduras S.A.',
                'rtn' => '08011990654321',
                'email' => 'ventas@comercialhonduras.hn',
                'phone' => '+504 2555-3333',
                'address' => 'Barrio El Centro, Tegucigalpa',
                'is_active' => true,
            ],
        ];

        foreach ($customers as $cust) {
            Customer::create($cust);
        }

        // Create CAI for branch 1
        $cai1 = CAI::create([
            'tenant_id' => $company1->id,
            'branch_id' => $branch1->id,
            'cai_number' => 'A1B2C3-D4E5F6-G7H8I9-J0K1L2',
            'document_type' => 'FACTURA',
            'range_start' => '001-001-01-00000001',
            'range_end' => '001-001-01-00001000',
            'authorization_date' => now()->subDays(30)->toDateString(),
            'expiration_date' => now()->addMonths(6)->toDateString(),
            'status' => 'active',
            'total_documents' => 1000,
            'used_documents' => 0,
            'notes' => 'CAI para sucursal principal',
        ]);

        // Note: Correlatives can be generated later through the UI
        $this->command->info('CAI creado. Los correlativos se pueden generar desde el sistema.');

        // Create Permissions
        $this->call(PermissionSeeder::class);

        // Assign Permissions to Roles
        $this->call(RolePermissionSeeder::class);

        // Create Super Admin User
        $this->call(SuperAdminSeeder::class);

        // Call Cash Register Seeder
        $this->call(CashRegisterSeeder::class);

        // Call Customer Segmentation Seeders
        $this->call(CustomerGroupSeeder::class);
        $this->call(CustomerTagSeeder::class);

        // Call Loyalty Program Seeder
        $this->call(LoyaltySeeder::class);

        $this->command->info('✅ Seeder completado exitosamente!');
        $this->command->info('');
        $this->command->info('=== USUARIOS DE PRUEBA ===');
        $this->command->info('');
        $this->command->info('Empresa: Comercial El Éxito');
        $this->command->info('  Admin: admin@elexito.hn / password123');
        $this->command->info('  Cajera: cajera@elexito.hn / password123');
        $this->command->info('');
        $this->command->info('Empresa: Supermercado La Esperanza');
        $this->command->info('  Admin: gerente@laesperanza.hn / password123');
    }
}
