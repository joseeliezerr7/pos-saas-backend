<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Company;
use App\Models\Branch;
use App\Models\Catalog\Product;
use App\Models\Stock;

class InventorySeeder extends Seeder
{
    public function run(): void
    {
        $company1 = Company::first();

        if (!$company1) {
            $this->command->error('No se encontró ninguna compañía.');
            return;
        }

        $branch1 = Branch::where('tenant_id', $company1->id)->first();

        if (!$branch1) {
            $this->command->error('No se encontró ninguna sucursal.');
            return;
        }

        $products = Product::where('tenant_id', $company1->id)->get();

        if ($products->isEmpty()) {
            $this->command->error('No hay productos para crear inventario.');
            return;
        }

        $this->command->info('Creando stock para productos...');

        foreach ($products as $product) {
            // Check if stock already exists
            $existingStock = Stock::where('tenant_id', $company1->id)
                ->where('branch_id', $branch1->id)
                ->where('product_id', $product->id)
                ->where('variant_id', null)
                ->first();

            if ($existingStock) {
                $this->command->info("Stock ya existe para: {$product->name}");
                continue;
            }

            // Create stock with initial quantity
            Stock::create([
                'tenant_id' => $company1->id,
                'branch_id' => $branch1->id,
                'product_id' => $product->id,
                'variant_id' => null,
                'quantity' => 100, // Stock inicial de 100 unidades
                'min_stock' => $product->min_stock ?? 10,
                'max_stock' => 500,
                'average_cost' => $product->cost,
                'last_movement_at' => now(),
            ]);

            $this->command->info("✓ Stock creado para: {$product->name} (100 unidades)");
        }

        $this->command->info('');
        $this->command->info('✅ Stock creado exitosamente!');
        $this->command->info("Total productos con stock: {$products->count()}");
    }
}
