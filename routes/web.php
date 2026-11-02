<?php

use App\Http\Controllers\CashController;
use App\Http\Controllers\CashierShiftController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\IncomeController;
use App\Http\Controllers\LaptopController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\PelangganController;
use App\Http\Controllers\ProdukController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ResetDataController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/{notification}/open', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::get('/search', GlobalSearchController::class)->name('global-search');
    Route::get('/dashboard', DashboardController::class)
        ->middleware('permission:view_dashboard')
        ->name('dashboard');

    Route::get('/products', [ProdukController::class, 'index'])
        ->middleware('permission:view_products')->name('products.index');
    Route::redirect('/categories', '/products')->name('categories.index');
    Route::get('/products/create', [ProdukController::class, 'create'])
        ->middleware('permission:create_products')->name('products.create');
    Route::post('/products', [ProdukController::class, 'store'])
        ->middleware('permission:create_products')->name('products.store');
    Route::get('/products/{product}/edit', [ProdukController::class, 'edit'])
        ->middleware('permission:edit_products')->name('products.edit');
    Route::put('/products/{product}', [ProdukController::class, 'update'])
        ->middleware('permission:edit_products')->name('products.update');
    Route::delete('/products/{product}', [ProdukController::class, 'destroy'])
        ->middleware('permission:delete_products')->name('products.destroy');
    Route::post('/customers/quick', [PelangganController::class, 'quickStore'])
        ->middleware('permission:create_sales')->name('customers.quick-store');
    Route::post('/service/customers/quick', [PelangganController::class, 'quickStore'])
        ->middleware('permission:create_service')->name('service.customers.quick-store');

    Route::get('/laptops', [LaptopController::class, 'index'])
        ->middleware('permission:view_stock')->name('laptops.index');
    Route::get('/laptops/create', [LaptopController::class, 'create'])
        ->middleware('permission:adjust_stock')->name('laptops.create');
    Route::post('/laptops', [LaptopController::class, 'store'])
        ->middleware('permission:adjust_stock')->name('laptops.store');
    Route::get('/laptops/{laptop}', [LaptopController::class, 'show'])
        ->middleware('permission:view_stock')->name('laptops.show');
    Route::get('/laptops/{laptop}/edit', [LaptopController::class, 'edit'])
        ->middleware('permission:adjust_stock')->name('laptops.edit');
    Route::put('/laptops/{laptop}', [LaptopController::class, 'update'])
        ->middleware('permission:adjust_stock')->name('laptops.update');

    Route::get('/suppliers', [SupplierController::class, 'index'])
        ->middleware('permission:view_purchases')->name('suppliers.index');
    Route::get('/suppliers/create', [SupplierController::class, 'create'])
        ->middleware('permission:create_purchases')->name('suppliers.create');
    Route::post('/suppliers', [SupplierController::class, 'store'])
        ->middleware('permission:create_purchases')->name('suppliers.store');
    Route::get('/suppliers/{supplier}', [SupplierController::class, 'show'])
        ->middleware('permission:view_purchases')->name('suppliers.show');
    Route::get('/suppliers/{supplier}/edit', [SupplierController::class, 'edit'])
        ->middleware('permission:edit_purchases')->name('suppliers.edit');
    Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])
        ->middleware('permission:edit_purchases')->name('suppliers.update');

    Route::get('/purchase-order', [PurchaseOrderController::class, 'index'])
        ->middleware('permission:view_purchases')->name('purchase-orders.index');
    Route::get('/purchase-order/receiving', [PurchaseOrderController::class, 'receivingIndex'])
        ->middleware('permission:view_purchases')->name('purchase-orders.receiving.index');
    Route::get('/purchase-order/products/search', [PurchaseOrderController::class, 'products'])
        ->middleware('permission:create_purchases')->name('purchase-orders.products.search');
    Route::get('/purchase-order/create', [PurchaseOrderController::class, 'create'])
        ->middleware('permission:create_purchases')->name('purchase-orders.create');
    Route::post('/purchase-order', [PurchaseOrderController::class, 'store'])
        ->middleware('permission:create_purchases')->name('purchase-orders.store');
    Route::get('/purchase-order/{purchaseOrder}/edit', [PurchaseOrderController::class, 'edit'])
        ->whereNumber('purchaseOrder')->middleware('permission:edit_purchases')->name('purchase-orders.edit');
    Route::put('/purchase-order/{purchaseOrder}', [PurchaseOrderController::class, 'update'])
        ->whereNumber('purchaseOrder')->middleware('permission:edit_purchases')->name('purchase-orders.update');
    Route::post('/purchase-order/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve'])
        ->whereNumber('purchaseOrder')->middleware('permission:edit_purchases')->name('purchase-orders.approve');
    Route::post('/purchase-order/{purchaseOrder}/process', [PurchaseOrderController::class, 'startProcessing'])
        ->whereNumber('purchaseOrder')->middleware('permission:edit_purchases')->name('purchase-orders.process');
    Route::post('/purchase-order/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])
        ->whereNumber('purchaseOrder')->middleware('permission:edit_purchases')->name('purchase-orders.cancel');
    Route::post('/purchase-order/{purchaseOrder}/payments', [PurchaseOrderController::class, 'collectPayment'])
        ->whereNumber('purchaseOrder')->middleware('permission:edit_purchases')->name('purchase-orders.payments.store');
    Route::get('/purchase-order/{purchaseOrder}/receiving', [PurchaseOrderController::class, 'receiving'])
        ->whereNumber('purchaseOrder')->middleware('permission:edit_purchases')->name('purchase-orders.receiving');
    Route::post('/purchase-order/{purchaseOrder}/receiving', [PurchaseOrderController::class, 'storeReceiving'])
        ->whereNumber('purchaseOrder')->middleware('permission:edit_purchases')->name('purchase-orders.receiving.store');
    Route::get('/purchase-order/{purchaseOrder}', [PurchaseOrderController::class, 'show'])
        ->whereNumber('purchaseOrder')->middleware('permission:view_purchases')->name('purchase-orders.show');

    Route::get('/customers', [PelangganController::class, 'index'])
        ->middleware('permission:view_sales')->name('customers.index');
    Route::get('/customers/create', [PelangganController::class, 'create'])
        ->middleware('permission:create_sales')->name('customers.create');
    Route::post('/customers', [PelangganController::class, 'store'])
        ->middleware('permission:create_sales')->name('customers.store');
    Route::get('/customers/{customer}', [PelangganController::class, 'show'])
        ->middleware('permission:view_sales')->name('customers.show');
    Route::get('/customers/{customer}/edit', [PelangganController::class, 'edit'])
        ->middleware('permission:edit_sales')->name('customers.edit');
    Route::put('/customers/{customer}', [PelangganController::class, 'update'])
        ->middleware('permission:edit_sales')->name('customers.update');

    Route::get('/stock', [StockController::class, 'index'])
        ->middleware('permission:view_stock')->name('stock.index');
    Route::get('/stock/adjustments/create', [StockController::class, 'createAdjustment'])
        ->middleware('permission:view_stock,adjust_stock')->name('stock.adjustments.create');
    Route::post('/stock/adjustments', [StockController::class, 'storeAdjustment'])
        ->middleware('permission:view_stock,adjust_stock')->name('stock.adjustments.store');
    Route::get('/stock/products/{product}', [StockController::class, 'show'])
        ->middleware('permission:view_stock')->name('stock.products.show');

    Route::get('/sales/create', [SalesController::class, 'create'])
        ->middleware('permission:create_sales')->name('sales.create');
    Route::post('/sales', [SalesController::class, 'store'])
        ->middleware('permission:create_sales')->name('sales.store');
    Route::get('/sales/{sale}', [SalesController::class, 'show'])
        ->middleware('permission:view_sales')->name('sales.show');
    Route::post('/sales/{sale}/payments', [SalesController::class, 'collectPayment'])
        ->middleware('permission:collect_sales_payments')->name('sales.payments.store');

    Route::get('/service/create', [ServiceController::class, 'create'])
        ->middleware('permission:create_service')->name('service.create');
    Route::get('/service', [ServiceController::class, 'index'])
        ->middleware('permission:view_service')->name('module.service');
    Route::post('/service', [ServiceController::class, 'store'])
        ->middleware('permission:create_service')->name('service.store');
    Route::post('/service/{service}/progress', [ServiceController::class, 'updateProgress'])
        ->whereNumber('service')->middleware('permission:update_service_progress')->name('service.progress.store');
    Route::post('/service/{service}/handover', [ServiceController::class, 'handover'])
        ->whereNumber('service')->middleware('permission:create_service')->name('service.handover.store');
    Route::post('/service/{service}/approvals', [ServiceController::class, 'requestApproval'])
        ->whereNumber('service')->name('service.approvals.store');
    Route::post('/service/{service}/approvals/{approval}/decision', [ServiceController::class, 'decideApproval'])
        ->whereNumber('service')->whereNumber('approval')->name('service.approvals.decision');
    Route::get('/service/{service}/print/{document}', [ServiceController::class, 'printDocument'])
        ->whereNumber('service')->whereIn('document', ['intake', 'final'])
        ->middleware('permission:view_service')->name('service.print');
    Route::get('/service/{service}', [ServiceController::class, 'show'])
        ->whereNumber('service')->middleware('permission:view_service')->name('service.show');
    Route::post('/service/{service}/payments', [ServiceController::class, 'collectPayment'])
        ->whereNumber('service')->middleware('permission:create_service')->name('service.payments.store');

    Route::get('/income', [IncomeController::class, 'index'])
        ->middleware('permission:view_income')->name('module.income');
    Route::get('/income/create', [IncomeController::class, 'create'])
        ->middleware('permission:create_income')->name('income.create');
    Route::post('/income', [IncomeController::class, 'store'])
        ->middleware('permission:create_income')->name('income.store');

    Route::get('/expenses', [ExpenseController::class, 'index'])
        ->middleware('permission:view_expense')->name('expenses.index');
    Route::get('/expenses/create', [ExpenseController::class, 'create'])
        ->middleware('permission:create_expense')->name('expenses.create');
    Route::post('/expenses', [ExpenseController::class, 'store'])
        ->middleware('permission:create_expense')->name('expenses.store');
    Route::get('/expenses/{expense}/edit', [ExpenseController::class, 'edit'])
        ->whereNumber('expense')->middleware('permission:edit_expense')->name('expenses.edit');
    Route::put('/expenses/{expense}', [ExpenseController::class, 'update'])
        ->whereNumber('expense')->middleware('permission:edit_expense')->name('expenses.update');
    Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])
        ->whereNumber('expense')->middleware('permission:delete_expense')->name('expenses.destroy');

    Route::get('/cash', [CashController::class, 'index'])
        ->middleware('permission:view_cash')->name('module.cash');
    Route::post('/cash/movements', [CashController::class, 'storeMovement'])
        ->middleware('permission:view_cash')->name('cash.movements.store');

    Route::get('/reports', [ReportController::class, 'index'])
        ->middleware('permission:view_reports')->name('module.reports');
    Route::get('/reports/{report}', [ReportController::class, 'show'])
        ->where('report', '[a-z-]+')->middleware('permission:view_reports')->name('reports.show');
    Route::get('/profit-loss', [ReportController::class, 'profitLoss'])
        ->middleware('permission:view_profit_loss')->name('module.profit-loss');

    Route::get('/payroll', [PayrollController::class, 'index'])
        ->middleware('permission:view_payroll')->name('payroll.index');
    Route::get('/payroll/create', [PayrollController::class, 'create'])
        ->middleware('permission:manage_payroll')->name('payroll.create');
    Route::post('/payroll', [PayrollController::class, 'store'])
        ->middleware('permission:manage_payroll')->name('payroll.store');
    Route::post('/payroll/{payroll}/pay', [PayrollController::class, 'pay'])
        ->whereNumber('payroll')->middleware('permission:manage_payroll')->name('payroll.pay');
    Route::post('/payroll/{payroll}/cancel', [PayrollController::class, 'cancel'])
        ->whereNumber('payroll')->middleware('permission:manage_payroll')->name('payroll.cancel');

    Route::get('/admin/reset-data', [ResetDataController::class, 'show'])
        ->middleware('permission:manage_roles')->name('reset-data.show');
    Route::post('/admin/reset-data', [ResetDataController::class, 'reset'])
        ->middleware('permission:manage_roles')->name('reset-data.run');

    Route::get('/roles', [RolePermissionController::class, 'index'])
        ->middleware('permission:manage_roles')->name('module.roles');
    Route::get('/roles/create', [RolePermissionController::class, 'create'])
        ->middleware('permission:manage_roles')->name('roles.create');
    Route::post('/roles', [RolePermissionController::class, 'store'])
        ->middleware('permission:manage_roles')->name('roles.store');
    Route::get('/roles/{role}/edit', [RolePermissionController::class, 'edit'])
        ->middleware('permission:manage_roles')->name('roles.edit');
    Route::put('/roles/{role}', [RolePermissionController::class, 'update'])
        ->middleware('permission:manage_roles')->name('roles.update');
    Route::delete('/roles/{role}', [RolePermissionController::class, 'destroy'])
        ->middleware('permission:manage_roles')->name('roles.destroy');
    Route::get('/roles/{role}/permissions', [RolePermissionController::class, 'editPermissions'])
        ->middleware('permission:manage_roles')->name('roles.permissions.edit');
    Route::put('/roles/{role}/permissions', [RolePermissionController::class, 'updatePermissions'])
        ->middleware('permission:manage_roles')->name('roles.permissions.update');

    Route::get('/users/create', [UserManagementController::class, 'create'])
        ->middleware('permission:manage_users')->name('users.create');
    Route::post('/users', [UserManagementController::class, 'store'])
        ->middleware('permission:manage_users')->name('users.store');

    Route::get('/cash/shifts', [CashierShiftController::class, 'index'])
        ->middleware('permission:view_cash')->name('cash.shifts.index');
    Route::post('/cash/shifts/open', [CashierShiftController::class, 'open'])
        ->middleware('permission:view_cash')->name('cash.shifts.open');
    Route::post('/cash/shifts/{shift}/close', [CashierShiftController::class, 'close'])
        ->middleware('permission:view_cash')->name('cash.shifts.close');

    $modules = [
        'sales' => ['sales', 'view_sales'],
        'purchases' => ['purchases', 'view_purchases'],
        'service/progress' => ['service-progress', 'update_service_progress'],
        'ai' => ['ai', 'use_ai'],
        'users' => ['users', 'manage_users'],
    ];

    foreach ($modules as $path => [$module, $permission]) {
        Route::get('/'.$path, [ModuleController::class, 'show'])
            ->defaults('module', $module)
            ->middleware('permission:'.$permission)
            ->name('module.'.$module);
    }
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
