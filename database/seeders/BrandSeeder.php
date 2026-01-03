<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Company;
use Illuminate\Database\Seeder;

class BrandSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener todas las empresas activas
        $companies = Company::where('is_active', true)->get();

        foreach ($companies as $company) {
            $brands = [
                [
                    'tenant_id' => $company->id,
                    'name' => 'Diana',
                    'slug' => 'diana',
                    'description' => 'Marca líder en alimentos de calidad',
                    'is_active' => true,
                ],
                [
                    'tenant_id' => $company->id,
                    'name' => 'Coca Cola',
                    'slug' => 'coca-cola',
                    'description' => 'Bebidas refrescantes',
                    'logo' => 'https://upload.wikimedia.org/wikipedia/commons/c/ce/Coca-Cola_logo.svg',
                    'is_active' => true,
                ],
                [
                    'tenant_id' => $company->id,
                    'name' => 'Clorox',
                    'slug' => 'clorox',
                    'description' => 'Productos de limpieza del hogar',
                    'is_active' => true,
                ],
                [
                    'tenant_id' => $company->id,
                    'name' => 'Bimbo',
                    'slug' => 'bimbo',
                    'description' => 'Pan y productos de panadería',
                    'is_active' => true,
                ],
                [
                    'tenant_id' => $company->id,
                    'name' => 'Nestlé',
                    'slug' => 'nestle',
                    'description' => 'Nutrición, salud y bienestar',
                    'is_active' => true,
                ],
                [
                    'tenant_id' => $company->id,
                    'name' => 'Colgate',
                    'slug' => 'colgate',
                    'description' => 'Cuidado personal',
                    'is_active' => true,
                ],
                [
                    'tenant_id' => $company->id,
                    'name' => 'Genérica',
                    'slug' => 'generica',
                    'description' => 'Productos sin marca específica',
                    'is_active' => true,
                ],
            ];

            foreach ($brands as $brandData) {
                Brand::create($brandData);
            }

            $this->command->info("Marcas creadas para '{$company->name}'");
        }

        $this->command->info('Seeder de marcas completado.');
    }
}
