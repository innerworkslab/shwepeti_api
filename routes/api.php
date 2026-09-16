<?php

use App\Enums\UserRoleEnum;
use App\Http\Controllers\Api\V1\Admin\AuditLogController;
use App\Http\Controllers\Api\V1\Admin\AuthController;
use App\Http\Controllers\Api\V1\Admin\InventoryAdjustmentController;
use App\Http\Controllers\Api\V1\Admin\InventoryLedgerController;
use App\Http\Controllers\Api\V1\Admin\InventoryStockBalanceController;
use App\Http\Controllers\Api\V1\Admin\InventoryTransferController;
use App\Http\Controllers\Api\V1\Admin\ItemCategoryController;
use App\Http\Controllers\Api\V1\Admin\ItemController;
use App\Http\Controllers\Api\V1\Admin\ItemUnitConversionController;
use App\Http\Controllers\Api\V1\Admin\MenuCategoryController;
use App\Http\Controllers\Api\V1\Admin\MenuController;
use App\Http\Controllers\Api\V1\Admin\RoomCategoryController;
use App\Http\Controllers\Api\V1\Admin\RoomController;
use App\Http\Controllers\Api\V1\Admin\StockInController;
use App\Http\Controllers\Api\V1\Admin\StockOutController;
use App\Http\Controllers\Api\V1\Admin\UnitController;
use App\Http\Controllers\Api\V1\Admin\UnitGroupController;
use App\Http\Controllers\Api\V1\Admin\UserController;
use App\Http\Controllers\Api\V1\Admin\WarehouseController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/admin')->group(function (): void {
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);

        Route::middleware('role:'.UserRoleEnum::HotelAdministrator->value)->group(function (): void {
            Route::apiResource('users', UserController::class)->except(['update', 'delete']);
            Route::post('users/{user}/restore', [UserController::class, 'restore']);
            Route::apiResource('room-categories', RoomCategoryController::class)->except(['update', 'delete']);
            Route::post('room-categories/{roomCategory}/toggle-active', [RoomCategoryController::class, 'toggleActive']);
            Route::post('room-categories/{roomCategory}/restore', [RoomCategoryController::class, 'restore']);
            Route::apiResource('rooms', RoomController::class)->except(['update', 'delete']);
            Route::post('rooms/{room}/status', [RoomController::class, 'status']);
            Route::post('rooms/{room}/restore', [RoomController::class, 'restore']);
            Route::apiResource('unit-groups', UnitGroupController::class)->except(['update', 'delete']);
            Route::post('unit-groups/{unitGroup}/toggle-active', [UnitGroupController::class, 'toggleActive']);
            Route::apiResource('units', UnitController::class)->except(['update', 'delete']);
            Route::post('units/{unit}/toggle-active', [UnitController::class, 'toggleActive']);
            Route::apiResource('item-categories', ItemCategoryController::class)->except(['update', 'delete']);
            Route::post('item-categories/{itemCategory}/toggle-active', [ItemCategoryController::class, 'toggleActive']);
            Route::apiResource('items', ItemController::class)->except(['update', 'delete']);
            Route::post('items/{item}/toggle-active', [ItemController::class, 'toggleActive']);
            Route::apiResource('item-unit-conversions', ItemUnitConversionController::class)->except(['update', 'delete']);
            Route::post('item-unit-conversions/{itemUnitConversion}/toggle-active', [ItemUnitConversionController::class, 'toggleActive']);
            Route::apiResource('menu-categories', MenuCategoryController::class)->except(['update', 'delete']);
            Route::post('menu-categories/{menuCategory}/toggle-active', [MenuCategoryController::class, 'toggleActive']);
            Route::apiResource('menus', MenuController::class)->except(['update', 'delete']);
            Route::post('menus/{menu}/toggle-active', [MenuController::class, 'toggleActive']);
            Route::post('menus/{menu}/toggle-available', [MenuController::class, 'toggleAvailable']);
            Route::apiResource('warehouses', WarehouseController::class)->except(['update', 'delete']);
            Route::get('warehouses/{warehouse}/items', [ItemController::class, 'byWarehouse']);
            Route::post('warehouses/{warehouse}/toggle-active', [WarehouseController::class, 'toggleActive']);
            Route::apiResource('stock-ins', StockInController::class)->except(['update', 'delete']);
            Route::post('stock-ins/{stockIn}/status', [StockInController::class, 'status']);
            Route::apiResource('stock-outs', StockOutController::class)->except(['update', 'delete']);
            Route::post('stock-outs/{stockOut}/status', [StockOutController::class, 'status']);
            Route::apiResource('inventory-transfers', InventoryTransferController::class)->except(['update', 'delete']);
            Route::post('inventory-transfers/{inventoryTransfer}/status', [InventoryTransferController::class, 'status']);
            Route::apiResource('inventory-adjustments', InventoryAdjustmentController::class)->except(['update', 'delete']);
            Route::post('inventory-adjustments/{inventoryAdjustment}/status', [InventoryAdjustmentController::class, 'status']);
            Route::apiResource('inventory-ledgers', InventoryLedgerController::class)->only(['index', 'show']);
            Route::apiResource('inventory-stock-balances', InventoryStockBalanceController::class)->only(['index', 'show']);
            Route::apiResource('audit-logs', AuditLogController::class)->only(['index', 'show']);
        });
    });
});
