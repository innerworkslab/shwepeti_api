<?php

use App\Http\Controllers\Api\V1\Pos\AuthController;
use App\Http\Controllers\Api\V1\Pos\BookingController;
use App\Http\Controllers\Api\V1\Pos\RoomController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/pos')->name('pos.')->group(function (): void {
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware(['auth:sanctum', 'portal:pos'])->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);

        Route::get('rooms/available', [RoomController::class, 'available']);
        Route::apiResource('rooms', RoomController::class)->only(['index', 'show']);

        Route::apiResource('bookings', BookingController::class)->only(['index', 'store', 'show']);
        Route::post('bookings/{booking}/payments', [BookingController::class, 'partialPayment']);
        Route::post('bookings/{booking}/check-in', [BookingController::class, 'checkIn']);
        Route::post('bookings/{booking}/checkout', [BookingController::class, 'checkoutWithPayment']);
        Route::post('bookings/{booking}/check-out', [BookingController::class, 'checkOut']);
        Route::post('bookings/{booking}/cancel', [BookingController::class, 'cancel']);
    });
});
