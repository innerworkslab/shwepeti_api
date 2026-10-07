<?php

use App\Http\Controllers\Api\V1\Pos\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/pos')->group(function (): void {
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware(['auth:sanctum', 'portal:pos'])->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});
