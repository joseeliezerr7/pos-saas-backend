<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\UnitController;
use App\Http\Controllers\Api\FileUploadController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\API\CashRegister\CashRegisterController;
use App\Http\Controllers\API\Settings\CAIController;
use App\Http\Controllers\API\Fiscal\InvoiceController;
use App\Http\Controllers\API\Sales\SaleController;
use App\Http\Controllers\API\Tenant\CompanyController;
use App\Http\Controllers\API\User\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
});

// Protected routes
Route::middleware(['auth:sanctum', 'tenant.scope', 'check.subscription'])->group(function () {

    // Auth endpoints
    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::get('me', [AuthController::class, 'me']);
    });

    // Company/Tenant management
    Route::prefix('company')->group(function () {
        Route::get('/', [CompanyController::class, 'show']);
        Route::put('/', [CompanyController::class, 'update']);
    });

    // Settings
    Route::prefix('settings')->group(function () {
        Route::get('company', [\App\Http\Controllers\API\Settings\CompanySettingsController::class, 'show'])->middleware('permission:view_settings');
        Route::put('company', [\App\Http\Controllers\API\Settings\CompanySettingsController::class, 'update'])->middleware('permission:edit_settings');
        Route::post('company/logo', [\App\Http\Controllers\API\Settings\CompanySettingsController::class, 'uploadLogo'])->middleware('permission:edit_settings');
        Route::delete('company/logo', [\App\Http\Controllers\API\Settings\CompanySettingsController::class, 'deleteLogo'])->middleware('permission:edit_settings');

        Route::get('cais', [\App\Http\Controllers\API\Settings\CAIController::class, 'index'])->middleware('permission:view_settings');
        Route::get('cais/active', [\App\Http\Controllers\API\Settings\CAIController::class, 'getActive'])->middleware('permission:view_settings');
        Route::post('cais', [\App\Http\Controllers\API\Settings\CAIController::class, 'store'])->middleware('permission:edit_settings');
        Route::get('cais/{id}', [\App\Http\Controllers\API\Settings\CAIController::class, 'show'])->middleware('permission:view_settings');
        Route::put('cais/{id}', [\App\Http\Controllers\API\Settings\CAIController::class, 'update'])->middleware('permission:edit_settings');
        Route::delete('cais/{id}', [\App\Http\Controllers\API\Settings\CAIController::class, 'destroy'])->middleware('permission:edit_settings');
    });

    // Branches
    Route::get('branches', [\App\Http\Controllers\API\Tenant\BranchController::class, 'index'])->middleware('permission:manage_branches');
    Route::post('branches', [\App\Http\Controllers\API\Tenant\BranchController::class, 'store'])->middleware('permission:manage_branches');
    Route::get('branches/{branch}', [\App\Http\Controllers\API\Tenant\BranchController::class, 'show'])->middleware('permission:manage_branches');
    Route::put('branches/{branch}', [\App\Http\Controllers\API\Tenant\BranchController::class, 'update'])->middleware('permission:manage_branches');
    Route::delete('branches/{branch}', [\App\Http\Controllers\API\Tenant\BranchController::class, 'destroy'])->middleware('permission:manage_branches');

    // Subscription
    Route::prefix('subscription')->group(function () {
        Route::get('/', [\App\Http\Controllers\API\Tenant\SubscriptionController::class, 'show']);
        Route::post('upgrade', [\App\Http\Controllers\API\Tenant\SubscriptionController::class, 'upgrade']);
        Route::post('cancel', [\App\Http\Controllers\API\Tenant\SubscriptionController::class, 'cancel']);
        Route::get('invoices', [\App\Http\Controllers\API\Tenant\SubscriptionController::class, 'invoices']);
    });

    // Plans (public listing)
    Route::get('plans', [\App\Http\Controllers\API\Tenant\PlanController::class, 'index']);

    // Categories
    Route::get('categories/tree', [CategoryController::class, 'tree'])->middleware('permission:view_categories');
    Route::get('categories', [CategoryController::class, 'index'])->middleware('permission:view_categories');
    Route::post('categories', [CategoryController::class, 'store'])->middleware('permission:create_categories');
    Route::get('categories/{category}', [CategoryController::class, 'show'])->middleware('permission:view_categories');
    Route::put('categories/{category}', [CategoryController::class, 'update'])->middleware('permission:edit_categories');
    Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->middleware('permission:delete_categories');

    // Products
    Route::get('products/search', [ProductController::class, 'search'])->middleware('permission:view_products');
    Route::get('products/barcode/{barcode}', [ProductController::class, 'findByBarcode'])->middleware('permission:view_products');
    Route::get('products', [ProductController::class, 'index'])->middleware('permission:view_products');
    Route::post('products', [ProductController::class, 'store'])->middleware('permission:create_products');
    Route::get('products/{product}', [ProductController::class, 'show'])->middleware('permission:view_products');
    Route::put('products/{product}', [ProductController::class, 'update'])->middleware('permission:edit_products');
    Route::delete('products/{product}', [ProductController::class, 'destroy'])->middleware('permission:delete_products');

    // Product Variants
    Route::get('products/{product}/variants', [\App\Http\Controllers\Api\ProductVariantController::class, 'index'])->middleware('permission:view_products');
    Route::post('products/{product}/variants', [\App\Http\Controllers\Api\ProductVariantController::class, 'store'])->middleware('permission:create_products');
    Route::get('products/{product}/variants/{variant}', [\App\Http\Controllers\Api\ProductVariantController::class, 'show'])->middleware('permission:view_products');
    Route::put('products/{product}/variants/{variant}', [\App\Http\Controllers\Api\ProductVariantController::class, 'update'])->middleware('permission:edit_products');
    Route::delete('products/{product}/variants/{variant}', [\App\Http\Controllers\Api\ProductVariantController::class, 'destroy'])->middleware('permission:delete_products');

    // Units
    Route::get('units', [\App\Http\Controllers\Api\UnitController::class, 'index'])->middleware('permission:view_products');
    Route::post('units', [\App\Http\Controllers\Api\UnitController::class, 'store'])->middleware('permission:create_products');
    Route::put('units/{unit}', [\App\Http\Controllers\Api\UnitController::class, 'update'])->middleware('permission:edit_products');
    Route::delete('units/{unit}', [\App\Http\Controllers\Api\UnitController::class, 'destroy'])->middleware('permission:delete_products');

    // File Upload
    Route::post('upload/image', [FileUploadController::class, 'uploadImage']);
    Route::post('upload/delete-image', [FileUploadController::class, 'deleteImage']);

    // Suppliers
    Route::get('suppliers', [\App\Http\Controllers\API\Catalog\SupplierController::class, 'index'])->middleware('permission:view_products');
    Route::post('suppliers', [\App\Http\Controllers\API\Catalog\SupplierController::class, 'store'])->middleware('permission:create_products');
    Route::get('suppliers/{supplier}', [\App\Http\Controllers\API\Catalog\SupplierController::class, 'show'])->middleware('permission:view_products');
    Route::put('suppliers/{supplier}', [\App\Http\Controllers\API\Catalog\SupplierController::class, 'update'])->middleware('permission:edit_products');
    Route::delete('suppliers/{supplier}', [\App\Http\Controllers\API\Catalog\SupplierController::class, 'destroy'])->middleware('permission:delete_products');

    // Brands
    Route::get('brands', [\App\Http\Controllers\API\Catalog\BrandController::class, 'index'])->middleware('permission:view_products');
    Route::post('brands', [\App\Http\Controllers\API\Catalog\BrandController::class, 'store'])->middleware('permission:create_products');
    Route::get('brands/{brand}', [\App\Http\Controllers\API\Catalog\BrandController::class, 'show'])->middleware('permission:view_products');
    Route::put('brands/{brand}', [\App\Http\Controllers\API\Catalog\BrandController::class, 'update'])->middleware('permission:edit_products');
    Route::delete('brands/{brand}', [\App\Http\Controllers\API\Catalog\BrandController::class, 'destroy'])->middleware('permission:delete_products');

    // Customers
    Route::get('customers/search', [CustomerController::class, 'search'])->middleware('permission:view_customers');
    Route::get('customers', [CustomerController::class, 'index'])->middleware('permission:view_customers');
    Route::post('customers', [CustomerController::class, 'store'])->middleware('permission:create_customers');
    Route::get('customers/{customer}', [CustomerController::class, 'show'])->middleware('permission:view_customers');
    Route::put('customers/{customer}', [CustomerController::class, 'update'])->middleware('permission:edit_customers');
    Route::delete('customers/{customer}', [CustomerController::class, 'destroy'])->middleware('permission:delete_customers');

    // Inventory/Stock
    Route::prefix('stock')->group(function () {
        Route::get('/', [\App\Http\Controllers\API\Inventory\StockController::class, 'index'])->middleware('permission:view_inventory');
        Route::get('branch/{branchId}', [\App\Http\Controllers\API\Inventory\StockController::class, 'byBranch'])->middleware('permission:view_inventory');
        Route::post('adjust', [\App\Http\Controllers\API\Inventory\StockController::class, 'adjust'])->middleware('permission:adjust_inventory');
        Route::post('transfer', [\App\Http\Controllers\API\Inventory\StockController::class, 'transfer'])->middleware('permission:transfer_inventory');
        Route::get('movements', [\App\Http\Controllers\API\Inventory\StockController::class, 'movements'])->middleware('permission:view_inventory');
        Route::get('low-stock', [\App\Http\Controllers\API\Inventory\StockController::class, 'lowStock'])->middleware('permission:view_inventory');
    });

    // Inventory Adjustments
    Route::prefix('inventory-adjustments')->group(function () {
        Route::get('/', [\App\Http\Controllers\API\Inventory\InventoryAdjustmentController::class, 'index'])->middleware('permission:view_inventory');
        Route::post('/', [\App\Http\Controllers\API\Inventory\InventoryAdjustmentController::class, 'store'])->middleware('permission:adjust_inventory');
        Route::get('{id}', [\App\Http\Controllers\API\Inventory\InventoryAdjustmentController::class, 'show'])->middleware('permission:view_inventory');
        Route::post('{id}/approve', [\App\Http\Controllers\API\Inventory\InventoryAdjustmentController::class, 'approve'])->middleware('permission:adjust_inventory');
        Route::post('{id}/reject', [\App\Http\Controllers\API\Inventory\InventoryAdjustmentController::class, 'reject'])->middleware('permission:adjust_inventory');
        Route::delete('{id}', [\App\Http\Controllers\API\Inventory\InventoryAdjustmentController::class, 'destroy'])->middleware('permission:adjust_inventory');
    });

    // Purchases
    Route::get('purchases', [\App\Http\Controllers\API\Purchase\PurchaseController::class, 'index'])->middleware('permission:view_purchases');
    Route::post('purchases', [\App\Http\Controllers\API\Purchase\PurchaseController::class, 'store'])->middleware('permission:create_purchases');
    Route::get('purchases/{purchase}', [\App\Http\Controllers\API\Purchase\PurchaseController::class, 'show'])->middleware('permission:view_purchases');
    Route::put('purchases/{purchase}', [\App\Http\Controllers\API\Purchase\PurchaseController::class, 'update'])->middleware('permission:edit_purchases');
    Route::delete('purchases/{purchase}', [\App\Http\Controllers\API\Purchase\PurchaseController::class, 'destroy'])->middleware('permission:delete_purchases');
    Route::post('purchases/{id}/receive', [\App\Http\Controllers\API\Purchase\PurchaseController::class, 'receive'])->middleware('permission:edit_purchases');

    // Expenses
    Route::prefix('expenses')->group(function () {
        Route::get('/', [\App\Http\Controllers\API\ExpenseController::class, 'index'])->middleware('permission:view_expenses');
        Route::post('/', [\App\Http\Controllers\API\ExpenseController::class, 'store'])->middleware('permission:create_expenses');
        Route::get('categories', [\App\Http\Controllers\API\ExpenseController::class, 'categories'])->middleware('permission:view_expenses');
        Route::get('payment-methods', [\App\Http\Controllers\API\ExpenseController::class, 'paymentMethods'])->middleware('permission:view_expenses');
        Route::get('statistics', [\App\Http\Controllers\API\ExpenseController::class, 'statistics'])->middleware('permission:view_expenses');
        Route::get('{id}', [\App\Http\Controllers\API\ExpenseController::class, 'show'])->middleware('permission:view_expenses');
        Route::put('{id}', [\App\Http\Controllers\API\ExpenseController::class, 'update'])->middleware('permission:edit_expenses');
        Route::delete('{id}', [\App\Http\Controllers\API\ExpenseController::class, 'destroy'])->middleware('permission:delete_expenses');
    });

    // Cash Registers
    Route::prefix('cash-registers')->group(function () {
        Route::get('/', [CashRegisterController::class, 'index'])->middleware('permission:view_cash_registers');
        Route::post('/', [CashRegisterController::class, 'store'])->middleware('permission:create_cash_registers');
        Route::get('reports', [CashRegisterController::class, 'reports'])->middleware('permission:view_cash_register_reports');
        Route::get('{id}', [CashRegisterController::class, 'show'])->middleware('permission:view_cash_registers');
        Route::put('{id}', [CashRegisterController::class, 'update'])->middleware('permission:edit_cash_registers');
        Route::delete('{id}', [CashRegisterController::class, 'destroy'])->middleware('permission:delete_cash_registers');
        Route::post('{id}/open', [CashRegisterController::class, 'open'])->middleware('permission:open_cash_register');
        Route::post('{id}/close', [CashRegisterController::class, 'close'])->middleware('permission:close_cash_register');
        Route::get('{id}/current', [CashRegisterController::class, 'currentOpening'])->middleware('permission:view_cash_registers');
        Route::post('{id}/transaction', [CashRegisterController::class, 'addTransaction'])->middleware('permission:add_cash_transactions');
        Route::get('{id}/summary', [CashRegisterController::class, 'summary'])->middleware('permission:view_cash_registers');
        Route::get('{id}/history', [CashRegisterController::class, 'history'])->middleware('permission:view_cash_register_history');
    });

    // Sales (POS)
    Route::prefix('sales')->group(function () {
        Route::get('/', [SaleController::class, 'index'])->middleware('permission:view_sales');
        Route::post('/', [SaleController::class, 'store'])->middleware('permission:create_sales');
        Route::get('{id}', [SaleController::class, 'show'])->middleware('permission:view_sale_details');
        Route::post('{id}/void', [SaleController::class, 'void'])->middleware('permission:void_sales');
        Route::get('statistics', [SaleController::class, 'statistics'])->middleware('permission:view_sales');
    });

    // Quotations
    Route::get('quotations', [\App\Http\Controllers\API\Sales\QuotationController::class, 'index'])->middleware('permission:view_sales');
    Route::post('quotations', [\App\Http\Controllers\API\Sales\QuotationController::class, 'store'])->middleware('permission:create_sales');
    Route::get('quotations/{quotation}', [\App\Http\Controllers\API\Sales\QuotationController::class, 'show'])->middleware('permission:view_sales');
    Route::put('quotations/{quotation}', [\App\Http\Controllers\API\Sales\QuotationController::class, 'update'])->middleware('permission:create_sales');
    Route::delete('quotations/{quotation}', [\App\Http\Controllers\API\Sales\QuotationController::class, 'destroy'])->middleware('permission:create_sales');
    Route::post('quotations/{id}/convert', [\App\Http\Controllers\API\Sales\QuotationController::class, 'convertToSale'])->middleware('permission:create_sales');

    // Returns (Devoluciones)
    Route::get('returns', [\App\Http\Controllers\API\Sales\ReturnController::class, 'index'])->middleware('permission:view_sales');
    Route::post('returns', [\App\Http\Controllers\API\Sales\ReturnController::class, 'store'])->middleware('permission:create_sales');
    Route::get('returns/{id}', [\App\Http\Controllers\API\Sales\ReturnController::class, 'show'])->middleware('permission:view_sales');
    Route::post('returns/{id}/complete', [\App\Http\Controllers\API\Sales\ReturnController::class, 'complete'])->middleware('permission:create_sales');
    Route::post('returns/{id}/cancel', [\App\Http\Controllers\API\Sales\ReturnController::class, 'cancel'])->middleware('permission:create_sales');

    // CAI (Fiscal - SAR Honduras)
    Route::prefix('cais')->group(function () {
        Route::get('/', [CAIController::class, 'index']);
        Route::post('/', [CAIController::class, 'store']);
        Route::get('{id}', [CAIController::class, 'show']);
        Route::put('{id}', [CAIController::class, 'update']);
        Route::get('{id}/status', [CAIController::class, 'status']);
    });

    // Correlatives
    Route::prefix('correlatives')->group(function () {
        Route::get('next', [\App\Http\Controllers\API\Fiscal\CorrelativeController::class, 'next']);
        Route::get('available-count', [\App\Http\Controllers\API\Fiscal\CorrelativeController::class, 'availableCount']);
    });

    // Invoices (requires CAI check)
    Route::middleware('check.cai')->group(function () {
        Route::prefix('invoices')->group(function () {
            Route::get('/', [InvoiceController::class, 'index'])->middleware('permission:view_invoices');
            Route::post('/', [InvoiceController::class, 'store'])->middleware('permission:create_invoices');
            Route::get('{id}', [InvoiceController::class, 'show'])->middleware('permission:view_invoices');
            Route::post('{id}/void', [InvoiceController::class, 'void'])->middleware('permission:void_invoices');
            Route::get('{id}/pdf', [InvoiceController::class, 'downloadPDF'])->middleware('permission:view_invoices');
            Route::post('{id}/email', [InvoiceController::class, 'sendEmail'])->middleware('permission:view_invoices');
            Route::get('validate/{number}', [InvoiceController::class, 'validateInvoiceNumber'])->middleware('permission:view_invoices');
        });
    });

    // Users and Roles
    Route::get('users', [UserController::class, 'index'])->middleware('permission:view_users');
    Route::post('users', [UserController::class, 'store'])->middleware('permission:create_users');
    Route::get('users/{user}', [UserController::class, 'show'])->middleware('permission:view_users');
    Route::put('users/{user}', [UserController::class, 'update'])->middleware('permission:edit_users');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->middleware('permission:delete_users');

    Route::get('roles', [\App\Http\Controllers\API\User\RoleController::class, 'index'])->middleware('permission:view_roles');
    Route::post('roles', [\App\Http\Controllers\API\User\RoleController::class, 'store'])->middleware('permission:create_roles');
    Route::get('roles/{role}', [\App\Http\Controllers\API\User\RoleController::class, 'show'])->middleware('permission:view_roles');
    Route::put('roles/{role}', [\App\Http\Controllers\API\User\RoleController::class, 'update'])->middleware('permission:edit_roles');
    Route::delete('roles/{role}', [\App\Http\Controllers\API\User\RoleController::class, 'destroy'])->middleware('permission:delete_roles');

    Route::get('permissions', [\App\Http\Controllers\API\User\PermissionController::class, 'index']);
    Route::get('permissions/grouped', [\App\Http\Controllers\API\User\PermissionController::class, 'grouped']);

    // Reports
    Route::prefix('reports')->group(function () {
        Route::post('sales', [\App\Http\Controllers\API\Report\SalesReportController::class, 'generate'])->middleware('permission:view_reports');
        Route::post('inventory', [\App\Http\Controllers\API\Report\InventoryReportController::class, 'generate'])->middleware('permission:view_reports');
        Route::post('financial', [\App\Http\Controllers\API\Report\FinancialReportController::class, 'generate'])->middleware('permission:view_reports');
        Route::post('sar/monthly', [\App\Http\Controllers\API\Report\SARReportController::class, 'monthly'])->middleware('permission:view_reports');
        Route::post('sar/dei', [\App\Http\Controllers\API\Report\SARReportController::class, 'dei'])->middleware('permission:view_reports');
        Route::get('{id}/download', [\App\Http\Controllers\API\Report\ReportController::class, 'download'])->middleware('permission:export_reports');
    });

    // Dashboard
    Route::prefix('dashboard')->group(function () {
        Route::get('stats', [\App\Http\Controllers\API\DashboardController::class, 'stats'])->middleware('permission:view_dashboard');
        Route::get('sales-chart', [\App\Http\Controllers\API\DashboardController::class, 'salesChart'])->middleware('permission:view_dashboard');
        Route::get('top-products', [\App\Http\Controllers\API\DashboardController::class, 'topProducts'])->middleware('permission:view_dashboard');
        Route::get('alerts', [\App\Http\Controllers\API\DashboardController::class, 'alerts'])->middleware('permission:view_dashboard');
    });

    // Notifications
    Route::prefix('notifications')->group(function () {
        Route::get('/', [\App\Http\Controllers\API\NotificationController::class, 'index']);
        Route::post('{id}/read', [\App\Http\Controllers\API\NotificationController::class, 'markAsRead']);
        Route::post('read-all', [\App\Http\Controllers\API\NotificationController::class, 'markAllAsRead']);
    });

    // Audit Logs
    Route::prefix('audit-logs')->group(function () {
        Route::get('/', [\App\Http\Controllers\API\Audit\AuditLogController::class, 'index']);
        Route::get('event-types', [\App\Http\Controllers\API\Audit\AuditLogController::class, 'eventTypes']);
        Route::get('auditable-types', [\App\Http\Controllers\API\Audit\AuditLogController::class, 'auditableTypes']);
        Route::get('{id}', [\App\Http\Controllers\API\Audit\AuditLogController::class, 'show']);
    });
});
