<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\CustomerGroupController;
use App\Http\Controllers\Api\CustomerTagController;
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

    // Customer Groups
    Route::prefix('customer-groups')->group(function () {
        Route::get('/', [CustomerGroupController::class, 'index'])->middleware('permission:view_customer_groups');
        Route::post('/', [CustomerGroupController::class, 'store'])->middleware('permission:create_customer_groups');
        Route::get('stats', [CustomerGroupController::class, 'stats'])->middleware('permission:view_customer_groups');
        Route::post('calculate-rfm', [CustomerGroupController::class, 'calculateRFM'])->middleware('permission:manage_customer_groups');
        Route::get('{id}', [CustomerGroupController::class, 'show'])->middleware('permission:view_customer_groups');
        Route::put('{id}', [CustomerGroupController::class, 'update'])->middleware('permission:edit_customer_groups');
        Route::delete('{id}', [CustomerGroupController::class, 'destroy'])->middleware('permission:delete_customer_groups');
        Route::get('{id}/prices', [CustomerGroupController::class, 'prices'])->middleware('permission:view_customer_groups');
        Route::post('{id}/prices', [CustomerGroupController::class, 'setPrice'])->middleware('permission:edit_customer_groups');
        Route::delete('{id}/prices/{priceId}', [CustomerGroupController::class, 'removePrice'])->middleware('permission:edit_customer_groups');
        Route::post('{id}/assign-customers', [CustomerGroupController::class, 'assignCustomers'])->middleware('permission:edit_customer_groups');
    });

    // Customer Tags
    Route::prefix('customer-tags')->group(function () {
        Route::get('/', [CustomerTagController::class, 'index'])->middleware('permission:view_customer_tags');
        Route::post('/', [CustomerTagController::class, 'store'])->middleware('permission:create_customer_tags');
        Route::get('{id}', [CustomerTagController::class, 'show'])->middleware('permission:view_customer_tags');
        Route::put('{id}', [CustomerTagController::class, 'update'])->middleware('permission:edit_customer_tags');
        Route::delete('{id}', [CustomerTagController::class, 'destroy'])->middleware('permission:delete_customer_tags');
        Route::post('{id}/assign', [CustomerTagController::class, 'assignToCustomers'])->middleware('permission:edit_customer_tags');
        Route::post('{id}/remove', [CustomerTagController::class, 'removeFromCustomers'])->middleware('permission:edit_customer_tags');
    });

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

    // Promotions
    Route::prefix('promotions')->group(function () {
        Route::get('/', [\App\Http\Controllers\API\PromotionController::class, 'index'])->middleware('permission:view_sales');
        Route::post('/', [\App\Http\Controllers\API\PromotionController::class, 'store'])->middleware('permission:create_sales');
        Route::get('{id}', [\App\Http\Controllers\API\PromotionController::class, 'show'])->middleware('permission:view_sales');
        Route::put('{id}', [\App\Http\Controllers\API\PromotionController::class, 'update'])->middleware('permission:create_sales');
        Route::delete('{id}', [\App\Http\Controllers\API\PromotionController::class, 'destroy'])->middleware('permission:create_sales');
        Route::post('{id}/toggle-active', [\App\Http\Controllers\API\PromotionController::class, 'toggleActive'])->middleware('permission:create_sales');
        Route::get('{id}/stats', [\App\Http\Controllers\API\PromotionController::class, 'stats'])->middleware('permission:view_sales');
        Route::post('validate-coupon', [\App\Http\Controllers\API\PromotionController::class, 'validateCoupon'])->middleware('permission:view_sales');
        Route::post('get-applicable', [\App\Http\Controllers\API\PromotionController::class, 'getApplicablePromotions'])->middleware('permission:view_sales');
        Route::post('apply', [\App\Http\Controllers\API\PromotionController::class, 'applyPromotion'])->middleware('permission:view_sales');
    });

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

    // Financial Reports (Advanced)
    Route::prefix('financial-reports')->group(function () {
        Route::post('profit-loss', [\App\Http\Controllers\Api\FinancialReportController::class, 'profitAndLoss'])->middleware('permission:view_reports');
        Route::post('balance-sheet', [\App\Http\Controllers\Api\FinancialReportController::class, 'balanceSheet'])->middleware('permission:view_reports');
        Route::post('cash-flow', [\App\Http\Controllers\Api\FinancialReportController::class, 'cashFlow'])->middleware('permission:view_reports');
        Route::post('product-profitability', [\App\Http\Controllers\Api\FinancialReportController::class, 'productProfitability'])->middleware('permission:view_reports');
        Route::post('category-profitability', [\App\Http\Controllers\Api\FinancialReportController::class, 'categoryProfitability'])->middleware('permission:view_reports');
        Route::post('branch-profitability', [\App\Http\Controllers\Api\FinancialReportController::class, 'branchProfitability'])->middleware('permission:view_reports');
        Route::post('monthly-comparison', [\App\Http\Controllers\Api\FinancialReportController::class, 'monthlyComparison'])->middleware('permission:view_reports');
        Route::post('comprehensive', [\App\Http\Controllers\Api\FinancialReportController::class, 'comprehensiveReport'])->middleware('permission:view_reports');
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

    // Barcodes
    Route::prefix('barcodes')->group(function () {
        Route::post('generate-unique', [\App\Http\Controllers\API\BarcodeController::class, 'generateUnique']);
        Route::post('generate-svg', [\App\Http\Controllers\API\BarcodeController::class, 'generateSVG']);
        Route::post('validate', [\App\Http\Controllers\API\BarcodeController::class, 'validate']);
        Route::post('labels', [\App\Http\Controllers\API\BarcodeController::class, 'generateLabels']);
        Route::post('labels/pdf', [\App\Http\Controllers\API\BarcodeController::class, 'generateLabelsPDF']);
    });

    // Import/Export
    Route::prefix('import-export')->group(function () {
        // Templates CSV
        Route::get('templates/products', [\App\Http\Controllers\API\ImportExportController::class, 'getProductTemplate']);
        Route::get('templates/customers', [\App\Http\Controllers\API\ImportExportController::class, 'getCustomerTemplate']);
        Route::get('templates/inventory', [\App\Http\Controllers\API\ImportExportController::class, 'getInventoryTemplate']);
        Route::get('templates/price-update', [\App\Http\Controllers\API\ImportExportController::class, 'getPriceUpdateTemplate']);

        // Templates Excel
        Route::get('templates/products/excel', [\App\Http\Controllers\API\ImportExportController::class, 'getProductTemplateExcel']);
        Route::get('templates/customers/excel', [\App\Http\Controllers\API\ImportExportController::class, 'getCustomerTemplateExcel']);
        Route::get('templates/inventory/excel', [\App\Http\Controllers\API\ImportExportController::class, 'getInventoryTemplateExcel']);
        Route::get('templates/price-update/excel', [\App\Http\Controllers\API\ImportExportController::class, 'getPriceUpdateTemplateExcel']);

        // Preview
        Route::post('preview', [\App\Http\Controllers\API\ImportExportController::class, 'previewImport']);

        // Import
        Route::post('import/products', [\App\Http\Controllers\API\ImportExportController::class, 'importProducts'])->middleware('permission:create_products');
        Route::post('import/customers', [\App\Http\Controllers\API\ImportExportController::class, 'importCustomers'])->middleware('permission:create_customers');
        Route::post('import/inventory', [\App\Http\Controllers\API\ImportExportController::class, 'importInventory'])->middleware('permission:adjust_inventory');
        Route::post('bulk-update-prices', [\App\Http\Controllers\API\ImportExportController::class, 'bulkUpdatePrices'])->middleware('permission:update_products');

        // Export
        Route::get('export/products', [\App\Http\Controllers\API\ImportExportController::class, 'exportProducts'])->middleware('permission:export_reports');
        Route::get('export/customers', [\App\Http\Controllers\API\ImportExportController::class, 'exportCustomers'])->middleware('permission:export_reports');
        Route::get('export/sales', [\App\Http\Controllers\API\ImportExportController::class, 'exportSales'])->middleware('permission:export_reports');
    });

    // Loyalty Program
    Route::prefix('loyalty')->group(function () {
        // Program
        Route::get('program', [\App\Http\Controllers\API\Loyalty\LoyaltyController::class, 'getProgram'])->middleware('permission:view_sales');
        Route::post('program', [\App\Http\Controllers\API\Loyalty\LoyaltyController::class, 'saveProgram'])->middleware('permission:create_sales');

        // Tiers
        Route::get('tiers', [\App\Http\Controllers\API\Loyalty\LoyaltyController::class, 'getTiers'])->middleware('permission:view_sales');
        Route::post('tiers', [\App\Http\Controllers\API\Loyalty\LoyaltyController::class, 'createTier'])->middleware('permission:create_sales');
        Route::put('tiers/{id}', [\App\Http\Controllers\API\Loyalty\LoyaltyController::class, 'updateTier'])->middleware('permission:create_sales');
        Route::delete('tiers/{id}', [\App\Http\Controllers\API\Loyalty\LoyaltyController::class, 'deleteTier'])->middleware('permission:create_sales');

        // Customer Loyalty
        Route::get('customers/{customerId}/summary', [\App\Http\Controllers\API\Loyalty\LoyaltyController::class, 'getCustomerSummary'])->middleware('permission:view_customers');
        Route::post('customers/{customerId}/enroll', [\App\Http\Controllers\API\Loyalty\LoyaltyController::class, 'enrollCustomer'])->middleware('permission:create_customers');
        Route::get('customers/{customerId}/transactions', [\App\Http\Controllers\API\Loyalty\LoyaltyController::class, 'getCustomerTransactions'])->middleware('permission:view_customers');

        // Points
        Route::post('redeem', [\App\Http\Controllers\API\Loyalty\LoyaltyController::class, 'redeemPoints'])->middleware('permission:create_sales');
        Route::post('adjust', [\App\Http\Controllers\API\Loyalty\LoyaltyController::class, 'adjustPoints'])->middleware('permission:create_sales');
    });

    // Gift Cards
    Route::prefix('gift-cards')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\GiftCardController::class, 'index'])->middleware('permission:view_sales');
        Route::post('/', [\App\Http\Controllers\Api\GiftCardController::class, 'store'])->middleware('permission:create_sales');
        Route::get('statistics', [\App\Http\Controllers\Api\GiftCardController::class, 'statistics'])->middleware('permission:view_reports');
        Route::post('check-balance', [\App\Http\Controllers\Api\GiftCardController::class, 'checkBalance'])->middleware('permission:view_sales');
        Route::post('redeem', [\App\Http\Controllers\Api\GiftCardController::class, 'redeem'])->middleware('permission:create_sales');
        Route::post('expire', [\App\Http\Controllers\Api\GiftCardController::class, 'expireCards'])->middleware('permission:create_sales');
        Route::get('{id}', [\App\Http\Controllers\Api\GiftCardController::class, 'show'])->middleware('permission:view_sales');
        Route::post('{id}/add-balance', [\App\Http\Controllers\Api\GiftCardController::class, 'addBalance'])->middleware('permission:create_sales');
        Route::post('{id}/void', [\App\Http\Controllers\Api\GiftCardController::class, 'void'])->middleware('permission:create_sales');
    });

    // Credit Management
    Route::prefix('credit')->group(function () {
        // Payments
        Route::get('payments', [\App\Http\Controllers\API\Credit\CustomerPaymentController::class, 'index'])
            ->middleware('permission:view_credit');
        Route::post('payments', [\App\Http\Controllers\API\Credit\CustomerPaymentController::class, 'store'])
            ->middleware('permission:create_credit_payments');
        Route::get('payments/{id}', [\App\Http\Controllers\API\Credit\CustomerPaymentController::class, 'show'])
            ->middleware('permission:view_credit');
        Route::get('payments/{id}/receipt', [\App\Http\Controllers\API\Credit\CustomerPaymentController::class, 'downloadReceipt'])
            ->middleware('permission:view_credit');

        // Credit Sales
        Route::get('sales', [\App\Http\Controllers\API\Credit\CreditSaleController::class, 'index'])
            ->middleware('permission:view_credit');
        Route::get('sales/{id}', [\App\Http\Controllers\API\Credit\CreditSaleController::class, 'show'])
            ->middleware('permission:view_credit');
        Route::get('customers/{customerId}/pending', [\App\Http\Controllers\API\Credit\CreditSaleController::class, 'customerPending'])
            ->middleware('permission:view_credit');

        // Reports
        Route::post('reports/statement', [\App\Http\Controllers\API\Credit\CreditReportController::class, 'customerStatement'])
            ->middleware('permission:view_reports');
        Route::get('reports/aging', [\App\Http\Controllers\API\Credit\CreditReportController::class, 'agingReport'])
            ->middleware('permission:view_reports');
        Route::get('reports/dashboard', [\App\Http\Controllers\API\Credit\CreditReportController::class, 'dashboard'])
            ->middleware('permission:view_reports');
    });
});

// Super Admin Routes - Gestión de todos los tenants
Route::middleware(['auth:sanctum', 'super_admin'])->prefix('super-admin')->group(function () {

    // Dashboard de Super Admin
    Route::get('dashboard', [\App\Http\Controllers\API\SuperAdmin\SuperAdminTenantController::class, 'dashboard']);

    // Gestión de Tenants
    Route::prefix('tenants')->group(function () {
        Route::get('/', [\App\Http\Controllers\API\SuperAdmin\SuperAdminTenantController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\API\SuperAdmin\SuperAdminTenantController::class, 'store']);
        Route::get('export', [\App\Http\Controllers\API\SuperAdmin\SuperAdminTenantController::class, 'export']);
        Route::get('{id}', [\App\Http\Controllers\API\SuperAdmin\SuperAdminTenantController::class, 'show']);
        Route::post('{id}/toggle-status', [\App\Http\Controllers\API\SuperAdmin\SuperAdminTenantController::class, 'toggleStatus']);
        Route::put('{id}/subscription', [\App\Http\Controllers\API\SuperAdmin\SuperAdminTenantController::class, 'updateSubscription']);
        Route::delete('{id}', [\App\Http\Controllers\API\SuperAdmin\SuperAdminTenantController::class, 'destroy']);
    });
});

// Plan Usage Endpoint (para tenants normales)
Route::middleware(['auth:sanctum', 'tenant.scope'])->group(function () {
    Route::get('plan-usage', [\App\Http\Controllers\API\Tenant\PlanUsageController::class, 'index']);
});

// Tenant Domains Management (para administradores de empresa)
Route::middleware(['auth:sanctum', 'tenant.scope', 'permission:manage_company'])->prefix('tenant-domains')->group(function () {
    Route::get('/', [\App\Http\Controllers\API\Tenant\TenantDomainController::class, 'index']);
    Route::post('/', [\App\Http\Controllers\API\Tenant\TenantDomainController::class, 'store']);
    Route::post('{domainId}/verify', [\App\Http\Controllers\API\Tenant\TenantDomainController::class, 'verify']);
    Route::post('{domainId}/set-primary', [\App\Http\Controllers\API\Tenant\TenantDomainController::class, 'setPrimary']);
    Route::delete('{domainId}', [\App\Http\Controllers\API\Tenant\TenantDomainController::class, 'destroy']);
});

// Plans endpoint (público o para super admin)
Route::get('plans', [\App\Http\Controllers\API\Tenant\PlanController::class, 'index']);
