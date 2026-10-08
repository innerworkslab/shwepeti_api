<?php

use App\Http\Controllers\Api\V1\Pos\AuthController;
use App\Http\Controllers\Api\V1\Pos\BookingController;
use App\Http\Controllers\Api\V1\Pos\BookingDashboardController;
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

        Route::get('rooms/available', [RoomController::class, 'available']);
        Route::apiResource('rooms', RoomController::class)->only(['index', 'show']);
        Route::apiResource('menus', MenuController::class)->only(['index']);
        Route::apiResource('items', ItemController::class)->only(['index']);

        Route::get('booking-dashboard', [BookingDashboardController::class, 'index']);
        Route::apiResource('bookings', BookingController::class)->only(['index', 'store', 'show']);
        Route::post('bookings/{booking}/payments', [BookingController::class, 'partialPayment']);
        Route::post('bookings/{booking}/check-in', [BookingController::class, 'checkIn']);
        Route::post('bookings/{booking}/checkout', [BookingController::class, 'checkoutWithPayment']);
        Route::post('bookings/{booking}/check-out', [BookingController::class, 'checkOut']);
        Route::post('bookings/{booking}/cancel', [BookingController::class, 'cancel']);

        Route::apiResource('food-orders', FoodOrderController::class)->only(['index', 'store', 'show']);
        Route::post('food-orders/{foodOrder}/status', [FoodOrderController::class, 'status']);

        Route::apiResource('service-orders', ServiceOrderController::class)->only(['index', 'store', 'show']);
        Route::post('service-orders/{serviceOrder}/status', [ServiceOrderController::class, 'status']);
    });
});
