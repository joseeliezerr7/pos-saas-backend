<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Role;
use App\Models\Tenant\Company;
use App\Models\Tenant\Branch;
use App\Models\Tenant\Plan;
use App\Models\Tenant\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateSuperAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'superadmin:create
                            {--email=admin@possaas.com : Email del super administrador}
                            {--password=password : Contraseña del super administrador}
                            {--name=Super Admin : Nombre del super administrador}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crea un usuario Super Administrador con acceso total al sistema';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->option('email');
        $password = $this->option('password');
        $name = $this->option('name');

        $this->info('Creando Super Admin...');
        $this->newLine();

        // Check if user already exists
        $existingUser = User::withoutGlobalScope('tenant')->where('email', $email)->first();
        if ($existingUser) {
            $this->error("Ya existe un usuario con el email: {$email}");

            if ($this->confirm('¿Deseas actualizar la contraseña de este usuario?')) {
                $existingUser->password = Hash::make($password);
                $existingUser->save();
                $this->info('Contraseña actualizada exitosamente!');
            }

            // Ensure super admin role
            $superAdminRole = Role::where('slug', 'super_admin')->first();
            if ($superAdminRole && !$existingUser->hasRole('super_admin')) {
                $existingUser->roles()->attach($superAdminRole->id);
                $this->info('Rol super_admin asignado exitosamente!');
            }

            return Command::SUCCESS;
        }

        // Create or get Super Admin role
        $superAdminRole = Role::firstOrCreate(
            ['slug' => 'super_admin'],
            [
                'name' => 'Super Administrador',
                'description' => 'Rol con acceso total al sistema y gestión de todos los tenants',
                'is_system' => true,
                'tenant_id' => null,
            ]
        );
        $this->info("✓ Rol 'Super Administrador' verificado");

        // Create Super Admin Company
        $superAdminCompany = Company::firstOrCreate(
            ['slug' => 'super-admin'],
            [
                'name' => 'Super Admin',
                'legal_name' => 'Super Admin Company',
                'rtn' => '00000000000000', // RTN genérico para super admin
                'email' => $email,
                'phone' => '0000-0000',
                'address' => 'Sistema Central',
                'is_active' => true,
            ]
        );
        $this->info("✓ Empresa 'Super Admin' verificada");

        // Get or create a plan for super admin
        $plan = Plan::where('slug', 'enterprise')->first();
        if (!$plan) {
            $plan = Plan::where('slug', 'professional')->first();
        }
        if (!$plan) {
            $plan = Plan::where('slug', 'basic')->first();
        }
        if (!$plan) {
            $plan = Plan::first();
        }

        if (!$plan) {
            $this->error('No hay planes disponibles en el sistema. Ejecuta los seeders primero.');
            return Command::FAILURE;
        }

        // Create subscription for super admin company if it doesn't exist
        if (!$superAdminCompany->subscription) {
            Subscription::create([
                'tenant_id' => $superAdminCompany->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'trial_ends_at' => null,
                'expires_at' => now()->addYears(10), // Suscripción de 10 años
                'started_at' => now(),
            ]);
            $this->info("✓ Suscripción creada para Super Admin");
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
        $this->info("✓ Sucursal principal verificada");

        // Create Super Admin User
        $superAdmin = User::create([
            'tenant_id' => $superAdminCompany->id,
            'branch_id' => $mainBranch->id,
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'status' => 'active',
        ]);

        // Attach super admin role
        $superAdmin->roles()->attach($superAdminRole->id);

        $this->newLine();
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('✅ Super Admin creado exitosamente!');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->newLine();
        $this->table(
            ['Campo', 'Valor'],
            [
                ['Email', $email],
                ['Password', $password],
                ['Nombre', $name],
            ]
        );
        $this->newLine();
        $this->warn('⚠️  IMPORTANTE: Cambia la contraseña después del primer login!');
        $this->newLine();

        return Command::SUCCESS;
    }
}
