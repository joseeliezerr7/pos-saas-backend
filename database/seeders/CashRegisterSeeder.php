<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CashRegister\CashRegister;
use App\Models\Tenant\Branch;

class CashRegisterSeeder extends Seeder
{
    public function run(): void
    {
        $branches = Branch::all();

        $counter = 1;
        foreach ($branches as $branch) {
            // Create 2 cash registers per branch
            CashRegister::create([
                'tenant_id' => $branch->tenant_id,
                'branch_id' => $branch->id,
                'name' => 'Caja Principal',
                'code' => sprintf('C%03d', $counter++),
                'status' => 'active',
            ]);

            CashRegister::create([
                'tenant_id' => $branch->tenant_id,
                'branch_id' => $branch->id,
                'name' => 'Caja Secundaria',
                'code' => sprintf('C%03d', $counter++),
                'status' => 'active',
            ]);
        }
    }
}
