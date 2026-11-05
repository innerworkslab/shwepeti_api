<?php

use App\Http\Controllers\Api\V1\Admin\AssetCategoryController;
use App\Http\Controllers\Api\V1\Admin\AuditLogController;
use App\Http\Controllers\Api\V1\Admin\AuthController;
use App\Http\Controllers\Api\V1\Admin\CashbookController;
use App\Http\Controllers\Api\V1\Admin\CashbookTransactionController;
use App\Http\Controllers\Api\V1\Admin\CashbookTransferController;
use App\Http\Controllers\Api\V1\Admin\FixedAssetController;
use App\Http\Controllers\Api\V1\Admin\HotelReportController;
use App\Http\Controllers\Api\V1\Admin\InventoryAdjustmentController;
use App\Http\Controllers\Api\V1\Admin\InventoryLedgerController;
use App\Http\Controllers\Api\V1\Admin\InventoryStockBalanceController;
use App\Http\Controllers\Api\V1\Admin\InventoryTransferController;
use App\Http\Controllers\Api\V1\Admin\ItemCategoryController;
use App\Http\Controllers\Api\V1\Admin\ItemController;
use App\Http\Controllers\Api\V1\Admin\ItemUnitConversionController;
use App\Http\Controllers\Api\V1\Admin\MenuCategoryController;
use App\Http\Controllers\Api\V1\Admin\MenuController;
use App\Http\Controllers\Api\V1\Admin\PermissionController;
use App\Http\Controllers\Api\V1\Admin\ProfitAndLossReportController;
use App\Http\Controllers\Api\V1\Admin\RoomCategoryController;
use App\Http\Controllers\Api\V1\Admin\RoomController;
use App\Http\Controllers\Api\V1\Admin\StockInController;
use App\Http\Controllers\Api\V1\Admin\StockOutController;
use App\Http\Controllers\Api\V1\Admin\SupplierApBalanceController;
use App\Http\Controllers\Api\V1\Admin\SupplierApLedgerController;
use App\Http\Controllers\Api\V1\Admin\SupplierApPaymentController;
use App\Http\Controllers\Api\V1\Admin\SupplierController;
use App\Http\Controllers\Api\V1\Admin\UnitController;
use App\Http\Controllers\Api\V1\Admin\UnitGroupController;
use App\Http\Controllers\Api\V1\Admin\UserController;
use App\Http\Controllers\Api\V1\Admin\WarehouseController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/admin')->group(function (): void {
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware(['auth:sanctum', 'portal:admin'])->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::get('permissions', [PermissionController::class, 'index'])->middleware('feature:users.manage_permissions');

        Route::get('reports/profit-and-loss', [ProfitAndLossReportController::class, 'index'])->middleware('feature:reports.profit_and_loss')->name('admin.reports.profit-and-loss');
        Route::get('reports/{report}', [HotelReportController::class, 'index'])->middleware('feature:reports.hotel')
            ->whereIn('report', ['daily-summary', 'room-occupancy', 'room-revenue', 'guest-balances', 'food-sales', 'top-selling-items', 'other-bills', 'inventory-balance', 'low-stock', 'depreciation'])
            ->name('admin.reports.hotel');
        Route::get('reports/{report}/export', [HotelReportController::class, 'export'])->middleware('feature:reports.hotel')
            ->whereIn('report', ['daily-summary', 'room-occupancy', 'room-revenue', 'guest-balances', 'food-sales', 'top-selling-items', 'other-bills', 'inventory-balance', 'low-stock', 'depreciation'])
            ->name('admin.reports.hotel.export');
        Route::apiResource('users', UserController::class)->except(['update', 'delete'])
            ->middlewareFor('index', 'feature:users.view')
            ->middlewareFor('show', 'feature:users.view')
            ->middlewareFor('store', 'feature:users.create,users.update')
            ->middlewareFor('destroy', 'feature:users.delete');
        Route::post('users/{user}/restore', [UserController::class, 'restore'])->middleware('feature:users.restore');
        Route::apiResource('room-categories', RoomCategoryController::class)->except(['update', 'delete'])
            ->middlewareFor('index', 'feature:room_categories.view')
            ->middlewareFor('show', 'feature:room_categories.view')
            ->middlewareFor('store', 'feature:room_categories.create,room_categories.update')
            ->middlewareFor('destroy', 'feature:room_categories.delete');
        Route::post('room-categories/{roomCategory}/toggle-active', [RoomCategoryController::class, 'toggleActive'])->middleware('feature:room_categories.toggle_active');
        Route::post('room-categories/{roomCategory}/restore', [RoomCategoryController::class, 'restore'])->middleware('feature:room_categories.restore');
        Route::apiResource('rooms', RoomController::class)->except(['update', 'delete'])
            ->middlewareFor('index', 'feature:rooms.view')
            ->middlewareFor('show', 'feature:rooms.view')
            ->middlewareFor('store', 'feature:rooms.create,rooms.update')
            ->middlewareFor('destroy', 'feature:rooms.delete');
        Route::post('rooms/{room}/status', [RoomController::class, 'status'])->middleware('feature:rooms.update_status');
        Route::post('rooms/{room}/restore', [RoomController::class, 'restore'])->middleware('feature:rooms.restore');
        Route::apiResource('unit-groups', UnitGroupController::class)->except(['update', 'delete'])
            ->middlewareFor('index', 'feature:unit_groups.view')
            ->middlewareFor('show', 'feature:unit_groups.view')
            ->middlewareFor('store', 'feature:unit_groups.create,unit_groups.update')
            ->middlewareFor('destroy', 'feature:unit_groups.delete');
        Route::post('unit-groups/{unitGroup}/toggle-active', [UnitGroupController::class, 'toggleActive'])->middleware('feature:unit_groups.toggle_active');
        Route::apiResource('units', UnitController::class)->except(['update', 'delete'])
            ->middlewareFor('index', 'feature:units.view')
            ->middlewareFor('show', 'feature:units.view')
            ->middlewareFor('store', 'feature:units.create,units.update')
            ->middlewareFor('destroy', 'feature:units.delete');
        Route::post('units/{unit}/toggle-active', [UnitController::class, 'toggleActive'])->middleware('feature:units.toggle_active');
        Route::apiResource('item-categories', ItemCategoryController::class)->except(['update', 'delete'])
            ->middlewareFor('index', 'feature:item_categories.view')
            ->middlewareFor('show', 'feature:item_categories.view')
            ->middlewareFor('store', 'feature:item_categories.create,item_categories.update')
            ->middlewareFor('destroy', 'feature:item_categories.delete');
        Route::post('item-categories/{itemCategory}/toggle-active', [ItemCategoryController::class, 'toggleActive'])->middleware('feature:item_categories.toggle_active');
        Route::post('items/import', [ItemController::class, 'import'])->middleware('feature:items.import')->name('admin.items.import');
        Route::get('items/import-template', [ItemController::class, 'importTemplate'])->middleware('feature:items.import')->name('admin.items.import-template');
        Route::apiResource('items', ItemController::class)->except(['update', 'delete'])
            ->middlewareFor('index', 'feature:items.view')
            ->middlewareFor('show', 'feature:items.view')
            ->middlewareFor('store', 'feature:items.create,items.update')
            ->middlewareFor('destroy', 'feature:items.delete');
        Route::post('items/price', [ItemController::class, 'updatePrice'])->middleware('feature:items.update_price');
        Route::post('items/{item}/toggle-active', [ItemController::class, 'toggleActive'])->middleware('feature:items.toggle_active');
        Route::get('items/{item}/price-histories', [ItemController::class, 'priceHistories'])->middleware('feature:items.view');
        Route::apiResource('item-unit-conversions', ItemUnitConversionController::class)->except(['update', 'delete'])
            ->middlewareFor('index', 'feature:item_unit_conversions.view')
            ->middlewareFor('show', 'feature:item_unit_conversions.view')
            ->middlewareFor('store', 'feature:item_unit_conversions.create,item_unit_conversions.update')
            ->middlewareFor('destroy', 'feature:item_unit_conversions.delete');
        Route::post('item-unit-conversions/{itemUnitConversion}/toggle-active', [ItemUnitConversionController::class, 'toggleActive'])->middleware('feature:item_unit_conversions.toggle_active');
        Route::apiResource('menu-categories', MenuCategoryController::class)->except(['update', 'delete'])
            ->middlewareFor('index', 'feature:menu_categories.view')
            ->middlewareFor('show', 'feature:menu_categories.view')
            ->middlewareFor('store', 'feature:menu_categories.create,menu_categories.update')
            ->middlewareFor('destroy', 'feature:menu_categories.delete');
        Route::post('menu-categories/{menuCategory}/toggle-active', [MenuCategoryController::class, 'toggleActive'])->middleware('feature:menu_categories.toggle_active');
        Route::post('menus/import', [MenuController::class, 'import'])->middleware('feature:menus.import')->name('admin.menus.import');
        Route::get('menus/import-template', [MenuController::class, 'importTemplate'])->middleware('feature:menus.import')->name('admin.menus.import-template');
        Route::apiResource('menus', MenuController::class)->except(['update', 'delete'])
            ->middlewareFor('index', 'feature:menus.view')
            ->middlewareFor('show', 'feature:menus.view')
            ->middlewareFor('store', 'feature:menus.create,menus.update')
            ->middlewareFor('destroy', 'feature:menus.delete');
        Route::post('menus/{menu}/toggle-active', [MenuController::class, 'toggleActive'])->middleware('feature:menus.toggle_active');
        Route::post('menus/{menu}/toggle-available', [MenuController::class, 'toggleAvailable'])->middleware('feature:menus.toggle_available');
        Route::apiResource('warehouses', WarehouseController::class)->except(['update', 'delete'])
            ->middlewareFor('index', 'feature:warehouses.view')
            ->middlewareFor('show', 'feature:warehouses.view')
            ->middlewareFor('store', 'feature:warehouses.create,warehouses.update')
            ->middlewareFor('destroy', 'feature:warehouses.delete');
        Route::get('warehouses/{warehouse}/items', [ItemController::class, 'byWarehouse'])->middleware('feature:items.view');
        Route::post('warehouses/{warehouse}/toggle-active', [WarehouseController::class, 'toggleActive'])->middleware('feature:warehouses.toggle_active');
        Route::apiResource('stock-ins', StockInController::class)->except(['update', 'delete'])
            ->middlewareFor('index', 'feature:stock_ins.view')
            ->middlewareFor('show', 'feature:stock_ins.view')
            ->middlewareFor('store', 'feature:stock_ins.create,stock_ins.update')
            ->middlewareFor('destroy', 'feature:stock_ins.delete');
        Route::post('stock-ins/{stockIn}/status', [StockInController::class, 'status'])->middleware('feature:stock_ins.update_status');
        Route::apiResource('stock-outs', StockOutController::class)->except(['update', 'delete'])
            ->middlewareFor('index', 'feature:stock_outs.view')
            ->middlewareFor('show', 'feature:stock_outs.view')
            ->middlewareFor('store', 'feature:stock_outs.create,stock_outs.update')
            ->middlewareFor('destroy', 'feature:stock_outs.delete');
        Route::post('stock-outs/{stockOut}/status', [StockOutController::class, 'status'])->middleware('feature:stock_outs.update_status');
        Route::apiResource('inventory-transfers', InventoryTransferController::class)->except(['update', 'delete'])
            ->middlewareFor('index', 'feature:inventory_transfers.view')
            ->middlewareFor('show', 'feature:inventory_transfers.view')
            ->middlewareFor('store', 'feature:inventory_transfers.create,inventory_transfers.update')
            ->middlewareFor('destroy', 'feature:inventory_transfers.delete');
        Route::post('inventory-transfers/{inventoryTransfer}/status', [InventoryTransferController::class, 'status'])->middleware('feature:inventory_transfers.update_status');
        Route::apiResource('inventory-adjustments', InventoryAdjustmentController::class)->except(['update', 'delete'])
            ->middlewareFor('index', 'feature:inventory_adjustments.view')
            ->middlewareFor('show', 'feature:inventory_adjustments.view')
            ->middlewareFor('store', 'feature:inventory_adjustments.create,inventory_adjustments.update')
            ->middlewareFor('destroy', 'feature:inventory_adjustments.delete');
        Route::post('inventory-adjustments/{inventoryAdjustment}/status', [InventoryAdjustmentController::class, 'status'])->middleware('feature:inventory_adjustments.update_status');
        Route::apiResource('inventory-ledgers', InventoryLedgerController::class)->only(['index', 'show'])
            ->middlewareFor('index', 'feature:inventory_ledgers.view')
            ->middlewareFor('show', 'feature:inventory_ledgers.view');
        Route::apiResource('inventory-stock-balances', InventoryStockBalanceController::class)->only(['index', 'show'])
            ->middlewareFor('index', 'feature:inventory_stock_balances.view')
            ->middlewareFor('show', 'feature:inventory_stock_balances.view');
        Route::apiResource('audit-logs', AuditLogController::class)->only(['index', 'show'])
            ->middlewareFor('index', 'feature:audit_logs.view')
            ->middlewareFor('show', 'feature:audit_logs.view');
        Route::apiResource('cashbooks', CashbookController::class)->except(['update'])
            ->middlewareFor('index', 'feature:cashbooks.view')
            ->middlewareFor('show', 'feature:cashbooks.view')
            ->middlewareFor('store', 'feature:cashbooks.create,cashbooks.update')
            ->middlewareFor('destroy', 'feature:cashbooks.delete');
        Route::post('cashbooks/close', [CashbookController::class, 'close'])->middleware('feature:cashbooks.close');
        Route::get('cashbooks/{cashbook}/closings', [CashbookController::class, 'closings'])->middleware('feature:cashbooks.view');
        Route::post('cashbooks/{cashbook}/toggle-active', [CashbookController::class, 'toggleActive'])->middleware('feature:cashbooks.toggle_active');
        Route::apiResource('cashbook-transactions', CashbookTransactionController::class)->only(['index', 'store', 'show'])
            ->middlewareFor('index', 'feature:cashbook_transactions.view')
            ->middlewareFor('store', 'feature:cashbook_transactions.create')
            ->middlewareFor('show', 'feature:cashbook_transactions.view');
        Route::apiResource('cashbook-transfers', CashbookTransferController::class)->only(['index', 'store', 'show'])
            ->middlewareFor('index', 'feature:cashbook_transfers.view')
            ->middlewareFor('store', 'feature:cashbook_transfers.create')
            ->middlewareFor('show', 'feature:cashbook_transfers.view');
        Route::apiResource('asset-categories', AssetCategoryController::class)->except(['update'])
            ->middlewareFor('index', 'feature:asset_categories.view')
            ->middlewareFor('show', 'feature:asset_categories.view')
            ->middlewareFor('store', 'feature:asset_categories.create,asset_categories.update')
            ->middlewareFor('destroy', 'feature:asset_categories.delete');
        Route::post('asset-categories/{assetCategory}/toggle-active', [AssetCategoryController::class, 'toggleActive'])->middleware('feature:asset_categories.toggle_active');
        Route::apiResource('fixed-assets', FixedAssetController::class)->except(['update'])
            ->middlewareFor('index', 'feature:fixed_assets.view')
            ->middlewareFor('show', 'feature:fixed_assets.view')
            ->middlewareFor('store', 'feature:fixed_assets.create,fixed_assets.update')
            ->middlewareFor('destroy', 'feature:fixed_assets.delete');
        Route::post('fixed-assets/{fixedAsset}/depreciation-settings', [FixedAssetController::class, 'depreciationSettings'])->middleware('feature:fixed_assets.update_depreciation_settings');
        Route::get('fixed-assets/{fixedAsset}/depreciations', [FixedAssetController::class, 'depreciations'])->middleware('feature:fixed_assets.view');
        Route::post('fixed-assets/{fixedAsset}/status', [FixedAssetController::class, 'status'])->middleware('feature:fixed_assets.update_status');
        Route::apiResource('suppliers', SupplierController::class)->except(['update'])
            ->middlewareFor('index', 'feature:suppliers.view')
            ->middlewareFor('show', 'feature:suppliers.view')
            ->middlewareFor('store', 'feature:suppliers.create,suppliers.update')
            ->middlewareFor('destroy', 'feature:suppliers.delete');
        Route::get('suppliers/{supplier}/ap-payments', [SupplierApPaymentController::class, 'bySupplier'])->middleware('feature:supplier_ap_payments.view');
        Route::post('suppliers/{supplier}/toggle-active', [SupplierController::class, 'toggleActive'])->middleware('feature:suppliers.toggle_active');
        Route::post('suppliers/{supplier}/restore', [SupplierController::class, 'restore'])->middleware('feature:suppliers.restore');
        Route::apiResource('supplier-ap-payments', SupplierApPaymentController::class)->except(['update'])
            ->middlewareFor('index', 'feature:supplier_ap_payments.view')
            ->middlewareFor('show', 'feature:supplier_ap_payments.view')
            ->middlewareFor('store', 'feature:supplier_ap_payments.create,supplier_ap_payments.update')
            ->middlewareFor('destroy', 'feature:supplier_ap_payments.delete');
        Route::post('supplier-ap-payments/{supplierApPayment}/status', [SupplierApPaymentController::class, 'status'])->middleware('feature:supplier_ap_payments.update_status');
        Route::apiResource('supplier-ap-balances', SupplierApBalanceController::class)->only(['index', 'show'])
            ->middlewareFor('index', 'feature:supplier_ap_balances.view')
            ->middlewareFor('show', 'feature:supplier_ap_balances.view');
        Route::apiResource('supplier-ap-ledgers', SupplierApLedgerController::class)->only(['index', 'show'])
            ->middlewareFor('index', 'feature:supplier_ap_ledgers.view')
            ->middlewareFor('show', 'feature:supplier_ap_ledgers.view');
    });
});
