<?php

namespace Database\Seeders;

use App\Models\CustomerGroup;
use Illuminate\Database\Seeder;

class CustomerGroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $groups = [
            [
                'name' => 'VIP',
                'description' => 'Clientes con mayor volumen de compras y fidelidad',
                'discount_percentage' => 15.00,
                'color' => '#FFD700',
                'priority' => 1,
                'is_active' => true,
            ],
            [
                'name' => 'Mayorista',
                'description' => 'Clientes que compran por mayor',
                'discount_percentage' => 10.00,
                'color' => '#3B82F6',
                'priority' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Corporativo',
                'description' => 'Empresas y negocios con contrato',
                'discount_percentage' => 12.00,
                'color' => '#8B5CF6',
                'priority' => 3,
                'is_active' => true,
            ],
            [
                'name' => 'Minorista',
                'description' => 'Clientes regulares',
                'discount_percentage' => 5.00,
                'color' => '#10B981',
                'priority' => 4,
                'is_active' => true,
            ],
            [
                'name' => 'Nuevo Cliente',
                'description' => 'Clientes recién registrados',
                'discount_percentage' => 0.00,
                'color' => '#6B7280',
                'priority' => 5,
                'is_active' => true,
            ],
        ];

        // Get all companies to seed groups for each one
        $companies = \App\Models\Company::all();

        foreach ($companies as $company) {
            foreach ($groups as $groupData) {
                CustomerGroup::create(array_merge($groupData, [
                    'company_id' => $company->id,
                ]));
            }
        }
    }
}
