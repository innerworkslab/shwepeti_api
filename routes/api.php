<?php

use App\Enums\UserRoleEnum;
use App\Http\Controllers\Api\V1\Admin\AuthController;
use App\Http\Controllers\Api\V1\Admin\ItemCategoryController;
use App\Http\Controllers\Api\V1\Admin\RoomCategoryController;
use App\Http\Controllers\Api\V1\Admin\RoomController;
use App\Http\Controllers\Api\V1\Admin\UnitController;
use App\Http\Controllers\Api\V1\Admin\UnitGroupController;
use App\Http\Controllers\Api\V1\Admin\UserController;
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
        });
    });
});
