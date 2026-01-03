<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use App\Models\Tenant\Company;
use App\Models\Tenant\Branch;
use App\Models\Tenant\Plan;
use App\Models\Tenant\Subscription;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Creating Super Admin...');

        // Create or get Super Admin role
        $superAdminRole = Role::firstOrCreate(
            ['slug' => 'super_admin'],
            [
                'name' => 'Super Administrador',
                'description' => 'Rol con acceso total al sistema y gestión de todos los tenants',
                'is_system' => true,
                'tenant_id' => null, // Global role, not tied to any tenant
            ]
        );

        $this->command->info("Super Admin role created: {$superAdminRole->name}");

        // Create Super Admin Company
        $superAdminCompany = Company::firstOrCreate(
            ['slug' => 'super-admin'],
            [
                'name' => 'Super Admin',
                'legal_name' => 'Super Admin Company',
                'rtn' => '00000000000000', // RTN genérico para super admin
                'email' => 'admin@possaas.com',
                'phone' => '0000-0000',
                'address' => 'Sistema Central',
                'is_active' => true,
            ]
        );

        $this->command->info("Super Admin company created: {$superAdminCompany->name}");

        // Create a plan for super admin (or get free plan)
        $plan = Plan::where('slug', 'enterprise')->first();
        if (!$plan) {
            $plan = Plan::where('slug', 'free')->first();
        }
        if (!$plan) {
            $plan = Plan::first();
        }

        // Create subscription for super admin company
        if ($plan && !$superAdminCompany->subscription) {
            Subscription::create([
                'tenant_id' => $superAdminCompany->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'trial_ends_at' => null,
                'expires_at' => now()->addYears(10), // Suscripción de 10 años
                'started_at' => now(),
            ]);
            $this->command->info("Subscription created for Super Admin company");
        }

        // Create main branch for super admin company
        $mainBranch = Branch::firstOrCreate(
            [
                'tenant_id' => $superAdminCompany->id,
                'is_main' => true,
            ],
            [
                'name' => 'Principal',
                'code' => 'ADMIN-001',
                'address' => 'Sistema Central',
                'phone' => '0000-0000',
                'is_active' => true,
            ]
        );

        $this->command->info("Main branch created: {$mainBranch->name}");

        // Create Super Admin User
        $superAdmin = User::firstOrCreate(
            ['email' => 'admin@possaas.com'],
            [
                'tenant_id' => $superAdminCompany->id,
                'branch_id' => $mainBranch->id,
                'name' => 'Super Admin',
                'password' => Hash::make('password'), // Change this in production!
                'status' => 'active',
            ]
        );

        // Attach super admin role
        if (!$superAdmin->hasRole('super_admin')) {
            $superAdmin->roles()->attach($superAdminRole->id);
            $this->command->info("Super Admin role attached to user");
        }

        $this->command->info("\n✅ Super Admin created successfully!");
        $this->command->info("Email: admin@possaas.com");
        $this->command->info("Password: password");
        $this->command->warn("\n⚠️  IMPORTANTE: Cambia la contraseña en producción!");
    }
}
