<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Company;
use App\Models\Branch;
use App\Models\Fiscal\CAI;

class CAISeeder extends Seeder
{
    public function run(): void
    {
        $company1 = Company::first();

        if (!$company1) {
            $this->command->error('No se encontró ninguna compañía. Ejecuta DatabaseSeeder primero.');
            return;
        }

        $branch1 = Branch::where('tenant_id', $company1->id)->first();

        if (!$branch1) {
            $this->command->error('No se encontró ninguna sucursal para la compañía.');
            return;
        }

        // Check if CAI already exists
        $existingCAI = CAI::where('tenant_id', $company1->id)
            ->where('branch_id', $branch1->id)
            ->where('cai_number', 'A1B2C3-D4E5F6-G7H8I9-J0K1L2')
            ->first();

        if ($existingCAI) {
            $this->command->info('CAI ya existe. Saltando creación.');
            return;
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

        $this->command->info('✅ CAI creado exitosamente!');
        $this->command->info('CAI Number: ' . $cai1->cai_number);
        $this->command->info('Branch: ' . $branch1->name);
        $this->command->info('Correlativos creados: 10');
    }
}
