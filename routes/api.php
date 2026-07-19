<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\GearItemController;
use App\Http\Controllers\Api\V1\ProductImportController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', fn () => response()->json([
        'data' => ['status' => 'ok'],
    ]));

    Route::prefix('auth')->group(function (): void {
        Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:auth');
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/logout', [AuthController::class, 'logout']);
        });
    });

    Route::middleware('auth:sanctum')->apiResource('categories', CategoryController::class);
    Route::middleware('auth:sanctum')->apiResource('gear-items', GearItemController::class);
    Route::middleware('auth:sanctum')->post('/gear-items/{gearItem}/image', [GearItemController::class, 'uploadImage']);
    Route::middleware('auth:sanctum')->delete('/gear-items/{gearItem}/image', [GearItemController::class, 'deleteImage']);
    Route::middleware('auth:sanctum')->apiResource('product-imports', ProductImportController::class)->only(['index', 'store', 'show', 'destroy']);
});
