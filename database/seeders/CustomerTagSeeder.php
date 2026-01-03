<?php

namespace Database\Seeders;

use App\Models\CustomerTag;
use Illuminate\Database\Seeder;

class CustomerTagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tags = [
            [
                'name' => 'Frecuente',
                'color' => '#10B981',
            ],
            [
                'name' => 'Moroso',
                'color' => '#EF4444',
            ],
            [
                'name' => 'Interesado en Ofertas',
                'color' => '#F59E0B',
            ],
            [
                'name' => 'Alto Valor',
                'color' => '#FFD700',
            ],
            [
                'name' => 'Recomendado',
                'color' => '#8B5CF6',
            ],
            [
                'name' => 'Necesita Atención',
                'color' => '#F97316',
            ],
            [
                'name' => 'Satisfecho',
                'color' => '#06B6D4',
            ],
            [
                'name' => 'Inactivo',
                'color' => '#6B7280',
            ],
        ];

        // Get all companies to seed tags for each one
        $companies = \App\Models\Company::all();

        foreach ($companies as $company) {
            foreach ($tags as $tagData) {
                CustomerTag::create(array_merge($tagData, [
                    'company_id' => $company->id,
                ]));
            }
        }
    }
}
