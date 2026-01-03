<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Company;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Catalog\Product;
use App\Models\Customer;
use App\Models\Unit;
use App\Models\Fiscal\CAI;

class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        $company1 = Company::first();
        $branch1 = Branch::where('tenant_id', $company1->id)->first();

        // Create Units
        $this->command->info('Creando unidades...');
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

        // Create Categories
        $this->command->info('Creando categorías...');
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

        // Create Products
        $this->command->info('Creando productos...');
        $products = [
            [
                'tenant_id' => $company1->id,
                'category_id' => $catAlimentos->id,
                'name' => 'Arroz Diana 1kg',
                'sku' => 'ARR-001',
                'barcode' => '7501234567890',
                'description' => 'Arroz blanco de grano largo',
                'cost' => 25.00,
                'price' => 35.00,
                'tax_type' => 'taxed',
                'min_stock' => 20,
                'is_active' => true,
            ],
            [
                'tenant_id' => $company1->id,
                'category_id' => $catAlimentos->id,
                'name' => 'Frijoles Rojos 500g',
                'sku' => 'FRJ-001',
                'barcode' => '7501234567891',
                'description' => 'Frijoles rojos premium',
                'cost' => 18.00,
                'price' => 28.00,
                'tax_type' => 'taxed',
                'min_stock' => 15,
                'is_active' => true,
            ],
            [
                'tenant_id' => $company1->id,
                'category_id' => $catBebidas->id,
                'name' => 'Coca Cola 600ml',
                'sku' => 'BEB-001',
                'barcode' => '7501234567892',
                'description' => 'Refresco sabor cola',
                'cost' => 12.00,
                'price' => 20.00,
                'tax_type' => 'taxed',
                'min_stock' => 50,
                'is_active' => true,
            ],
            [
                'tenant_id' => $company1->id,
                'category_id' => $catBebidas->id,
                'name' => 'Agua Azul 1L',
                'sku' => 'BEB-002',
                'barcode' => '7501234567893',
                'description' => 'Agua purificada',
                'cost' => 5.00,
                'price' => 10.00,
                'tax_type' => 'taxed',
                'min_stock' => 100,
                'is_active' => true,
            ],
            [
                'tenant_id' => $company1->id,
                'category_id' => $catLimpieza->id,
                'name' => 'Cloro Clorox 1L',
                'sku' => 'LIM-001',
                'barcode' => '7501234567894',
                'description' => 'Blanqueador líquido',
                'cost' => 22.00,
                'price' => 35.00,
                'tax_type' => 'taxed',
                'min_stock' => 25,
                'is_active' => true,
            ],
        ];

        foreach ($products as $prod) {
            Product::create($prod);
        }

        // Create Customers
        $this->command->info('Creando clientes...');
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
        $this->command->info('Creando CAI...');
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

        // Generate correlatives for CAI (only first 10 for speed)
        $this->command->info('Generando correlativos para CAI (solo primeros 10 para pruebas)...');
        $correlativeService = app(\App\Services\CorrelativeService::class);

        for ($i = 1; $i <= 10; $i++) {
            \App\Models\Fiscal\Correlative::create([
                'cai_id' => $cai1->id,
                'number' => $i,
                'formatted_number' => sprintf('001-001-01-%08d', $i),
                'status' => 'available',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('✅ Datos de prueba creados exitosamente!');
        $this->command->info('');
        $this->command->info('=== RESUMEN ===');
        $this->command->info('Unidades: 5');
        $this->command->info('Categorías: 5');
        $this->command->info('Productos: 5');
        $this->command->info('Clientes: 3');
        $this->command->info('CAIs: 1');
        $this->command->info('');
        $this->command->info('Puedes crear más productos desde el sistema o ejecutar el seeder completo.');
    }
}
