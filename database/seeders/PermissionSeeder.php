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

            // Grupos de Clientes
            ['group' => 'Grupos de Clientes', 'name' => 'Ver Grupos de Clientes', 'slug' => 'view_customer_groups', 'description' => 'Ver listado de grupos de clientes'],
            ['group' => 'Grupos de Clientes', 'name' => 'Crear Grupos de Clientes', 'slug' => 'create_customer_groups', 'description' => 'Crear nuevos grupos de clientes'],
            ['group' => 'Grupos de Clientes', 'name' => 'Editar Grupos de Clientes', 'slug' => 'edit_customer_groups', 'description' => 'Modificar grupos de clientes'],
            ['group' => 'Grupos de Clientes', 'name' => 'Eliminar Grupos de Clientes', 'slug' => 'delete_customer_groups', 'description' => 'Eliminar grupos de clientes'],
            ['group' => 'Grupos de Clientes', 'name' => 'Gestionar Segmentación', 'slug' => 'manage_customer_groups', 'description' => 'Calcular análisis RFM y gestionar segmentación'],

            // Tags de Clientes
            ['group' => 'Tags de Clientes', 'name' => 'Ver Tags de Clientes', 'slug' => 'view_customer_tags', 'description' => 'Ver listado de tags de clientes'],
            ['group' => 'Tags de Clientes', 'name' => 'Crear Tags de Clientes', 'slug' => 'create_customer_tags', 'description' => 'Crear nuevos tags'],
            ['group' => 'Tags de Clientes', 'name' => 'Editar Tags de Clientes', 'slug' => 'edit_customer_tags', 'description' => 'Modificar tags'],
            ['group' => 'Tags de Clientes', 'name' => 'Eliminar Tags de Clientes', 'slug' => 'delete_customer_tags', 'description' => 'Eliminar tags'],

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

            // Devoluciones
            ['group' => 'Devoluciones', 'name' => 'Ver Devoluciones', 'slug' => 'view_returns', 'description' => 'Ver listado de devoluciones'],
            ['group' => 'Devoluciones', 'name' => 'Crear Devoluciones', 'slug' => 'create_returns', 'description' => 'Crear nuevas devoluciones'],
            ['group' => 'Devoluciones', 'name' => 'Aprobar Devoluciones', 'slug' => 'approve_returns', 'description' => 'Aprobar devoluciones de clientes'],
            ['group' => 'Devoluciones', 'name' => 'Rechazar Devoluciones', 'slug' => 'reject_returns', 'description' => 'Rechazar devoluciones'],

            // Cotizaciones
            ['group' => 'Cotizaciones', 'name' => 'Ver Cotizaciones', 'slug' => 'view_quotations', 'description' => 'Ver listado de cotizaciones'],
            ['group' => 'Cotizaciones', 'name' => 'Crear Cotizaciones', 'slug' => 'create_quotations', 'description' => 'Crear nuevas cotizaciones'],
            ['group' => 'Cotizaciones', 'name' => 'Editar Cotizaciones', 'slug' => 'edit_quotations', 'description' => 'Modificar cotizaciones'],
            ['group' => 'Cotizaciones', 'name' => 'Eliminar Cotizaciones', 'slug' => 'delete_quotations', 'description' => 'Eliminar cotizaciones'],
            ['group' => 'Cotizaciones', 'name' => 'Convertir a Venta', 'slug' => 'convert_quotations', 'description' => 'Convertir cotización a venta'],

            // Proveedores
            ['group' => 'Proveedores', 'name' => 'Ver Proveedores', 'slug' => 'view_suppliers', 'description' => 'Ver listado de proveedores'],
            ['group' => 'Proveedores', 'name' => 'Crear Proveedores', 'slug' => 'create_suppliers', 'description' => 'Crear nuevos proveedores'],
            ['group' => 'Proveedores', 'name' => 'Editar Proveedores', 'slug' => 'edit_suppliers', 'description' => 'Modificar proveedores'],
            ['group' => 'Proveedores', 'name' => 'Eliminar Proveedores', 'slug' => 'delete_suppliers', 'description' => 'Eliminar proveedores'],

            // Marcas
            ['group' => 'Marcas', 'name' => 'Ver Marcas', 'slug' => 'view_brands', 'description' => 'Ver listado de marcas'],
            ['group' => 'Marcas', 'name' => 'Crear Marcas', 'slug' => 'create_brands', 'description' => 'Crear nuevas marcas'],
            ['group' => 'Marcas', 'name' => 'Editar Marcas', 'slug' => 'edit_brands', 'description' => 'Modificar marcas'],
            ['group' => 'Marcas', 'name' => 'Eliminar Marcas', 'slug' => 'delete_brands', 'description' => 'Eliminar marcas'],

            // Unidades
            ['group' => 'Unidades', 'name' => 'Ver Unidades', 'slug' => 'view_units', 'description' => 'Ver listado de unidades de medida'],
            ['group' => 'Unidades', 'name' => 'Crear Unidades', 'slug' => 'create_units', 'description' => 'Crear nuevas unidades'],
            ['group' => 'Unidades', 'name' => 'Editar Unidades', 'slug' => 'edit_units', 'description' => 'Modificar unidades'],
            ['group' => 'Unidades', 'name' => 'Eliminar Unidades', 'slug' => 'delete_units', 'description' => 'Eliminar unidades'],

            // Promociones
            ['group' => 'Promociones', 'name' => 'Ver Promociones', 'slug' => 'view_promotions', 'description' => 'Ver listado de promociones'],
            ['group' => 'Promociones', 'name' => 'Crear Promociones', 'slug' => 'create_promotions', 'description' => 'Crear nuevas promociones'],
            ['group' => 'Promociones', 'name' => 'Editar Promociones', 'slug' => 'edit_promotions', 'description' => 'Modificar promociones'],
            ['group' => 'Promociones', 'name' => 'Eliminar Promociones', 'slug' => 'delete_promotions', 'description' => 'Eliminar promociones'],
            ['group' => 'Promociones', 'name' => 'Activar/Desactivar Promociones', 'slug' => 'toggle_promotions', 'description' => 'Activar o desactivar promociones'],
            ['group' => 'Promociones', 'name' => 'Aplicar Cupones', 'slug' => 'apply_coupons', 'description' => 'Aplicar cupones de descuento en ventas'],

            // Importación/Exportación
            ['group' => 'Importación/Exportación', 'name' => 'Importar Productos', 'slug' => 'import_products', 'description' => 'Importar productos desde CSV/Excel'],
            ['group' => 'Importación/Exportación', 'name' => 'Importar Clientes', 'slug' => 'import_customers', 'description' => 'Importar clientes desde CSV/Excel'],
            ['group' => 'Importación/Exportación', 'name' => 'Importar Inventario', 'slug' => 'import_inventory', 'description' => 'Importar inventario inicial'],
            ['group' => 'Importación/Exportación', 'name' => 'Actualizar Precios Masivos', 'slug' => 'update_prices_bulk', 'description' => 'Actualizar precios de productos en masa'],
            ['group' => 'Importación/Exportación', 'name' => 'Exportar Datos', 'slug' => 'export_data', 'description' => 'Exportar datos a Excel/CSV'],
            ['group' => 'Importación/Exportación', 'name' => 'Descargar Plantillas', 'slug' => 'download_templates', 'description' => 'Descargar plantillas de importación'],

            // Códigos de Barras
            ['group' => 'Códigos de Barras', 'name' => 'Generar Códigos de Barras', 'slug' => 'generate_barcodes', 'description' => 'Generar códigos de barras para productos'],
            ['group' => 'Códigos de Barras', 'name' => 'Imprimir Etiquetas', 'slug' => 'print_labels', 'description' => 'Imprimir etiquetas con códigos de barras'],
            ['group' => 'Códigos de Barras', 'name' => 'Imprimir Etiquetas Masivas', 'slug' => 'print_labels_bulk', 'description' => 'Imprimir etiquetas de múltiples productos'],

            // Programa de Lealtad
            ['group' => 'Lealtad', 'name' => 'Ver Programa de Lealtad', 'slug' => 'view_loyalty_program', 'description' => 'Ver configuración del programa de lealtad'],
            ['group' => 'Lealtad', 'name' => 'Configurar Programa de Lealtad', 'slug' => 'configure_loyalty_program', 'description' => 'Configurar programa de puntos'],
            ['group' => 'Lealtad', 'name' => 'Gestionar Niveles de Lealtad', 'slug' => 'manage_loyalty_tiers', 'description' => 'Crear y editar niveles de lealtad'],
            ['group' => 'Lealtad', 'name' => 'Ver Puntos de Clientes', 'slug' => 'view_customer_points', 'description' => 'Ver puntos de clientes'],
            ['group' => 'Lealtad', 'name' => 'Inscribir Clientes', 'slug' => 'enroll_customers_loyalty', 'description' => 'Inscribir clientes en programa de lealtad'],
            ['group' => 'Lealtad', 'name' => 'Canjear Puntos', 'slug' => 'redeem_points', 'description' => 'Canjear puntos de clientes'],
            ['group' => 'Lealtad', 'name' => 'Ajustar Puntos', 'slug' => 'adjust_points', 'description' => 'Ajustar puntos manualmente (admin)'],
            ['group' => 'Lealtad', 'name' => 'Ver Transacciones de Puntos', 'slug' => 'view_points_transactions', 'description' => 'Ver historial de puntos'],

            // Gift Cards / Tarjetas de Regalo
            ['group' => 'Gift Cards', 'name' => 'Ver Gift Cards', 'slug' => 'view_gift_cards', 'description' => 'Ver listado de tarjetas de regalo'],
            ['group' => 'Gift Cards', 'name' => 'Vender Gift Cards', 'slug' => 'sell_gift_cards', 'description' => 'Vender tarjetas de regalo'],
            ['group' => 'Gift Cards', 'name' => 'Consultar Balance', 'slug' => 'check_gift_card_balance', 'description' => 'Consultar balance de gift card'],
            ['group' => 'Gift Cards', 'name' => 'Redimir Gift Cards', 'slug' => 'redeem_gift_cards', 'description' => 'Aplicar gift card en ventas'],
            ['group' => 'Gift Cards', 'name' => 'Recargar Gift Cards', 'slug' => 'reload_gift_cards', 'description' => 'Recargar saldo de gift cards'],
            ['group' => 'Gift Cards', 'name' => 'Anular Gift Cards', 'slug' => 'void_gift_cards', 'description' => 'Anular tarjetas de regalo'],
            ['group' => 'Gift Cards', 'name' => 'Ver Reportes de Gift Cards', 'slug' => 'view_gift_card_reports', 'description' => 'Ver estadísticas de gift cards'],

            // Crédito / Cuentas por Cobrar
            ['group' => 'Crédito', 'name' => 'Ver Créditos', 'slug' => 'view_credit', 'description' => 'Ver ventas al crédito y cuentas por cobrar'],
            ['group' => 'Crédito', 'name' => 'Registrar Pagos', 'slug' => 'create_credit_payments', 'description' => 'Registrar pagos de clientes al crédito'],
            ['group' => 'Crédito', 'name' => 'Ver Estado de Cuenta', 'slug' => 'view_customer_statement', 'description' => 'Ver estado de cuenta de clientes'],
            ['group' => 'Crédito', 'name' => 'Ver Reporte de Antigüedad', 'slug' => 'view_aging_report', 'description' => 'Ver reporte de antigüedad de saldos'],
            ['group' => 'Crédito', 'name' => 'Dashboard de Cuentas por Cobrar', 'slug' => 'view_receivables_dashboard', 'description' => 'Ver dashboard de cuentas por cobrar'],

            // Auditoría
            ['group' => 'Auditoría', 'name' => 'Ver Logs de Auditoría', 'slug' => 'view_audit_logs', 'description' => 'Acceder a logs de auditoría del sistema'],
            ['group' => 'Auditoría', 'name' => 'Exportar Logs', 'slug' => 'export_audit_logs', 'description' => 'Exportar logs de auditoría'],

            // Notificaciones
            ['group' => 'Notificaciones', 'name' => 'Ver Notificaciones', 'slug' => 'view_notifications', 'description' => 'Ver notificaciones del sistema'],
            ['group' => 'Notificaciones', 'name' => 'Configurar Notificaciones', 'slug' => 'configure_notifications', 'description' => 'Configurar alertas y notificaciones'],
            ['group' => 'Notificaciones', 'name' => 'Enviar Notificaciones', 'slug' => 'send_notifications', 'description' => 'Enviar notificaciones manuales'],
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
