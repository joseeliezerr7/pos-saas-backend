<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Loyalty\LoyaltyProgram;
use App\Models\Loyalty\LoyaltyTier;
use Illuminate\Database\Seeder;

class LoyaltySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener todas las empresas activas
        $companies = Company::where('is_active', true)->get();

        foreach ($companies as $company) {
            // Verificar si ya tiene un programa de lealtad
            $existingProgram = LoyaltyProgram::where('tenant_id', $company->id)->first();

            if ($existingProgram) {
                $this->command->info("Empresa '{$company->name}' ya tiene un programa de lealtad.");
                continue;
            }

            // Crear programa de lealtad
            $program = LoyaltyProgram::create([
                'tenant_id' => $company->id,
                'name' => 'Programa de Lealtad',
                'description' => 'Gana puntos con cada compra y disfruta de beneficios exclusivos',
                'is_active' => true,
                'points_per_currency' => 1.00, // 1 punto por cada L.1 gastado
                'min_purchase_amount' => 0, // Sin mínimo de compra
                'point_value' => 0.10, // 1 punto = L.0.10 (10% cashback)
                'points_expire' => true,
                'expiration_days' => 365, // Los puntos expiran en 1 año
                'special_dates' => [],
                'birthday_multiplier' => 2.00, // Doble puntos en cumpleaños
            ]);

            // Crear tiers del programa
            $tiers = [
                [
                    'name' => 'Bronce',
                    'color' => '#CD7F32',
                    'min_points' => 0,
                    'order' => 1,
                    'discount_percentage' => 0,
                    'points_multiplier' => 1.00,
                    'benefits' => [
                        'Acumula puntos con cada compra',
                        'Acceso a promociones exclusivas',
                    ],
                ],
                [
                    'name' => 'Plata',
                    'color' => '#C0C0C0',
                    'min_points' => 1000,
                    'order' => 2,
                    'discount_percentage' => 5,
                    'points_multiplier' => 1.25,
                    'benefits' => [
                        '5% de descuento en todas las compras',
                        '25% más puntos por compra',
                        'Acceso a eventos especiales',
                    ],
                ],
                [
                    'name' => 'Oro',
                    'color' => '#FFD700',
                    'min_points' => 5000,
                    'order' => 3,
                    'discount_percentage' => 10,
                    'points_multiplier' => 1.50,
                    'benefits' => [
                        '10% de descuento en todas las compras',
                        '50% más puntos por compra',
                        'Acceso prioritario a nuevos productos',
                        'Ofertas exclusivas',
                    ],
                ],
                [
                    'name' => 'Platino',
                    'color' => '#E5E4E2',
                    'min_points' => 15000,
                    'order' => 4,
                    'discount_percentage' => 15,
                    'points_multiplier' => 2.00,
                    'benefits' => [
                        '15% de descuento en todas las compras',
                        'Doble puntos por compra',
                        'Asesor personal de compras',
                        'Envío gratuito en todos los pedidos',
                        'Acceso VIP a eventos',
                    ],
                ],
            ];

            foreach ($tiers as $tierData) {
                LoyaltyTier::create([
                    'loyalty_program_id' => $program->id,
                    ...$tierData,
                ]);
            }

            $this->command->info("Programa de lealtad creado para '{$company->name}' con 4 tiers.");
        }

        $this->command->info('Seeder de programas de lealtad completado.');
    }
}
