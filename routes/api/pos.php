<?php

use App\Http\Controllers\Api\V1\Pos\AuthController;
use App\Http\Controllers\Api\V1\Pos\BookingController;
use App\Http\Controllers\Api\V1\Pos\BookingDashboardController;
use App\Http\Controllers\Api\V1\Pos\CashbookController;
use App\Http\Controllers\Api\V1\Pos\CashbookTransactionController;
use App\Http\Controllers\Api\V1\Pos\CashbookTransferController;
use App\Http\Controllers\Api\V1\Pos\FoodOrderController;
use App\Http\Controllers\Api\V1\Pos\ItemController;
use App\Http\Controllers\Api\V1\Pos\MenuController;
use App\Http\Controllers\Api\V1\Pos\RoomController;
use App\Http\Controllers\Api\V1\Pos\ServiceOrderController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/pos')->name('pos.')->group(function (): void {
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware(['auth:sanctum', 'portal:pos'])->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);

        Route::get('rooms/available', [RoomController::class, 'available'])->middleware('feature:rooms.view');
        Route::apiResource('rooms', RoomController::class)->only(['index', 'show'])
            ->middlewareFor('index', 'feature:rooms.view')
            ->middlewareFor('show', 'feature:rooms.view');
        Route::apiResource('menus', MenuController::class)->only(['index'])
            ->middlewareFor('index', 'feature:menus.view');
        Route::apiResource('items', ItemController::class)->only(['index'])
            ->middlewareFor('index', 'feature:items.view');
        Route::apiResource('cashbooks', CashbookController::class)->only(['index'])
            ->middlewareFor('index', 'feature:cashbooks.view');
        Route::post('cashbooks/close', [CashbookController::class, 'close'])->middleware('feature:cashbooks.close');
        Route::get('cashbooks/{cashbook}/closings', [CashbookController::class, 'closings'])->middleware('feature:cashbooks.view');
        Route::apiResource('cashbook-transactions', CashbookTransactionController::class)->only(['index'])
            ->middlewareFor('index', 'feature:cashbook_transactions.view');
        Route::apiResource('cashbook-transfers', CashbookTransferController::class)->only(['index', 'store', 'show'])
            ->middlewareFor('index', 'feature:cashbook_transfers.view')
            ->middlewareFor('store', 'feature:cashbook_transfers.create')
            ->middlewareFor('show', 'feature:cashbook_transfers.view');

        Route::get('booking-dashboard', [BookingDashboardController::class, 'index'])->middleware('feature:booking_dashboard.view');
        Route::apiResource('bookings', BookingController::class)->only(['index', 'store', 'show'])
            ->middlewareFor('index', 'feature:bookings.view')
            ->middlewareFor('store', 'feature:bookings.create')
            ->middlewareFor('show', 'feature:bookings.view');
        Route::post('bookings/{booking}/payments', [BookingController::class, 'partialPayment'])->middleware('feature:bookings.record_payment');
        Route::post('bookings/{booking}/check-in', [BookingController::class, 'checkIn'])->middleware('feature:bookings.check_in');
        Route::post('bookings/{booking}/checkout', [BookingController::class, 'checkoutWithPayment'])->middleware('feature:bookings.checkout');
        Route::post('bookings/{booking}/check-out', [BookingController::class, 'checkOut'])->middleware('feature:bookings.check_out');
        Route::post('bookings/{booking}/cancel', [BookingController::class, 'cancel'])->middleware('feature:bookings.cancel');

        Route::apiResource('food-orders', FoodOrderController::class)->only(['index', 'store', 'show'])
            ->middlewareFor('index', 'feature:food_orders.view')
            ->middlewareFor('store', 'feature:food_orders.create')
            ->middlewareFor('show', 'feature:food_orders.view');
        Route::post('food-orders/{foodOrder}/status', [FoodOrderController::class, 'status'])->middleware('feature:food_orders.update_status');

        Route::apiResource('service-orders', ServiceOrderController::class)->only(['index', 'store', 'show'])
            ->middlewareFor('index', 'feature:service_orders.view')
            ->middlewareFor('store', 'feature:service_orders.create')
            ->middlewareFor('show', 'feature:service_orders.view');
        Route::post('service-orders/{serviceOrder}/status', [ServiceOrderController::class, 'status'])->middleware('feature:service_orders.update_status');
    });
});
