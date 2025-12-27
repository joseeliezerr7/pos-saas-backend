<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            // Dashboard
            ['group' => 'Dashboard', 'name' => 'Ver Dashboard', 'slug' => 'view_dashboard', 'description' => 'Acceso al panel principal'],

            // POS
            ['group' => 'POS', 'name' => 'Acceder al POS', 'slug' => 'access_pos', 'description' => 'Acceso al punto de venta'],
            ['group' => 'POS', 'name' => 'Realizar Ventas', 'slug' => 'create_sales', 'description' => 'Crear nuevas ventas'],
            ['group' => 'POS', 'name' => 'Aplicar Descuentos', 'slug' => 'apply_discounts', 'description' => 'Aplicar descuentos en ventas'],
            ['group' => 'POS', 'name' => 'Cancelar Ventas', 'slug' => 'cancel_sales', 'description' => 'Cancelar ventas realizadas'],

            // Productos
            ['group' => 'Productos', 'name' => 'Ver Productos', 'slug' => 'view_products', 'description' => 'Ver listado de productos'],
            ['group' => 'Productos', 'name' => 'Crear Productos', 'slug' => 'create_products', 'description' => 'Crear nuevos productos'],
            ['group' => 'Productos', 'name' => 'Editar Productos', 'slug' => 'edit_products', 'description' => 'Modificar productos existentes'],
            ['group' => 'Productos', 'name' => 'Eliminar Productos', 'slug' => 'delete_products', 'description' => 'Eliminar productos'],

            // Categorías
            ['group' => 'Categorías', 'name' => 'Ver Categorías', 'slug' => 'view_categories', 'description' => 'Ver listado de categorías'],
            ['group' => 'Categorías', 'name' => 'Crear Categorías', 'slug' => 'create_categories', 'description' => 'Crear nuevas categorías'],
            ['group' => 'Categorías', 'name' => 'Editar Categorías', 'slug' => 'edit_categories', 'description' => 'Modificar categorías'],
            ['group' => 'Categorías', 'name' => 'Eliminar Categorías', 'slug' => 'delete_categories', 'description' => 'Eliminar categorías'],

            // Clientes
            ['group' => 'Clientes', 'name' => 'Ver Clientes', 'slug' => 'view_customers', 'description' => 'Ver listado de clientes'],
            ['group' => 'Clientes', 'name' => 'Crear Clientes', 'slug' => 'create_customers', 'description' => 'Crear nuevos clientes'],
            ['group' => 'Clientes', 'name' => 'Editar Clientes', 'slug' => 'edit_customers', 'description' => 'Modificar clientes'],
            ['group' => 'Clientes', 'name' => 'Eliminar Clientes', 'slug' => 'delete_customers', 'description' => 'Eliminar clientes'],

            // Ventas
            ['group' => 'Ventas', 'name' => 'Ver Ventas', 'slug' => 'view_sales', 'description' => 'Ver historial de ventas'],
            ['group' => 'Ventas', 'name' => 'Ver Detalles de Ventas', 'slug' => 'view_sale_details', 'description' => 'Ver detalles de ventas'],
            ['group' => 'Ventas', 'name' => 'Anular Ventas', 'slug' => 'void_sales', 'description' => 'Anular ventas'],

            // Facturas
            ['group' => 'Facturas', 'name' => 'Ver Facturas', 'slug' => 'view_invoices', 'description' => 'Ver listado de facturas'],
            ['group' => 'Facturas', 'name' => 'Crear Facturas', 'slug' => 'create_invoices', 'description' => 'Crear nuevas facturas'],
            ['group' => 'Facturas', 'name' => 'Anular Facturas', 'slug' => 'void_invoices', 'description' => 'Anular facturas'],

            // Inventario
            ['group' => 'Inventario', 'name' => 'Ver Inventario', 'slug' => 'view_inventory', 'description' => 'Ver listado de inventario'],
            ['group' => 'Inventario', 'name' => 'Ajustar Inventario', 'slug' => 'adjust_inventory', 'description' => 'Realizar ajustes de inventario'],
            ['group' => 'Inventario', 'name' => 'Transferir Inventario', 'slug' => 'transfer_inventory', 'description' => 'Transferir entre sucursales'],

            // Compras
            ['group' => 'Compras', 'name' => 'Ver Compras', 'slug' => 'view_purchases', 'description' => 'Ver listado de compras'],
            ['group' => 'Compras', 'name' => 'Crear Compras', 'slug' => 'create_purchases', 'description' => 'Crear nuevas compras'],
            ['group' => 'Compras', 'name' => 'Editar Compras', 'slug' => 'edit_purchases', 'description' => 'Modificar compras'],
            ['group' => 'Compras', 'name' => 'Eliminar Compras', 'slug' => 'delete_purchases', 'description' => 'Eliminar compras'],

            // Gastos
            ['group' => 'Gastos', 'name' => 'Ver Gastos', 'slug' => 'view_expenses', 'description' => 'Ver listado de gastos'],
            ['group' => 'Gastos', 'name' => 'Crear Gastos', 'slug' => 'create_expenses', 'description' => 'Crear nuevos gastos'],
            ['group' => 'Gastos', 'name' => 'Editar Gastos', 'slug' => 'edit_expenses', 'description' => 'Modificar gastos'],
            ['group' => 'Gastos', 'name' => 'Eliminar Gastos', 'slug' => 'delete_expenses', 'description' => 'Eliminar gastos'],

            // Cajas Registradoras
            ['group' => 'Cajas', 'name' => 'Ver Cajas Registradoras', 'slug' => 'view_cash_registers', 'description' => 'Ver listado de cajas registradoras'],
            ['group' => 'Cajas', 'name' => 'Crear Cajas Registradoras', 'slug' => 'create_cash_registers', 'description' => 'Crear nuevas cajas registradoras'],
            ['group' => 'Cajas', 'name' => 'Editar Cajas Registradoras', 'slug' => 'edit_cash_registers', 'description' => 'Modificar cajas registradoras'],
            ['group' => 'Cajas', 'name' => 'Eliminar Cajas Registradoras', 'slug' => 'delete_cash_registers', 'description' => 'Eliminar cajas registradoras'],
            ['group' => 'Cajas', 'name' => 'Abrir Caja', 'slug' => 'open_cash_register', 'description' => 'Abrir caja registradora con monto inicial'],
            ['group' => 'Cajas', 'name' => 'Cerrar Caja', 'slug' => 'close_cash_register', 'description' => 'Cerrar caja y realizar arqueo'],
            ['group' => 'Cajas', 'name' => 'Agregar Transacciones', 'slug' => 'add_cash_transactions', 'description' => 'Registrar ingresos/egresos en caja'],
            ['group' => 'Cajas', 'name' => 'Ver Historial de Cajas', 'slug' => 'view_cash_register_history', 'description' => 'Ver historial de aperturas y cierres'],
            ['group' => 'Cajas', 'name' => 'Ver Reportes de Cajas', 'slug' => 'view_cash_register_reports', 'description' => 'Acceder a reportes de cajas'],

            // Reportes
            ['group' => 'Reportes', 'name' => 'Ver Reportes', 'slug' => 'view_reports', 'description' => 'Acceder a reportes'],
            ['group' => 'Reportes', 'name' => 'Exportar Reportes', 'slug' => 'export_reports', 'description' => 'Exportar reportes'],

            // Usuarios
            ['group' => 'Usuarios', 'name' => 'Ver Usuarios', 'slug' => 'view_users', 'description' => 'Ver listado de usuarios'],
            ['group' => 'Usuarios', 'name' => 'Crear Usuarios', 'slug' => 'create_users', 'description' => 'Crear nuevos usuarios'],
            ['group' => 'Usuarios', 'name' => 'Editar Usuarios', 'slug' => 'edit_users', 'description' => 'Modificar usuarios'],
            ['group' => 'Usuarios', 'name' => 'Eliminar Usuarios', 'slug' => 'delete_users', 'description' => 'Eliminar usuarios'],

            // Roles
            ['group' => 'Roles', 'name' => 'Ver Roles', 'slug' => 'view_roles', 'description' => 'Ver listado de roles'],
            ['group' => 'Roles', 'name' => 'Crear Roles', 'slug' => 'create_roles', 'description' => 'Crear nuevos roles'],
            ['group' => 'Roles', 'name' => 'Editar Roles', 'slug' => 'edit_roles', 'description' => 'Modificar roles'],
            ['group' => 'Roles', 'name' => 'Eliminar Roles', 'slug' => 'delete_roles', 'description' => 'Eliminar roles'],

            // Configuración
            ['group' => 'Configuración', 'name' => 'Ver Configuración', 'slug' => 'view_settings', 'description' => 'Acceder a configuración'],
            ['group' => 'Configuración', 'name' => 'Editar Configuración', 'slug' => 'edit_settings', 'description' => 'Modificar configuración'],
            ['group' => 'Configuración', 'name' => 'Gestionar Sucursales', 'slug' => 'manage_branches', 'description' => 'Administrar sucursales'],
            ['group' => 'Configuración', 'name' => 'Gestionar Suscripción', 'slug' => 'manage_subscription', 'description' => 'Administrar suscripción'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['slug' => $permission['slug']],
                $permission
            );
        }

        $this->command->info('Permisos creados exitosamente: ' . count($permissions) . ' permisos');
    }
}
