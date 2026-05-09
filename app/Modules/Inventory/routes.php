<?php

use App\Modules\Inventory\InventoryController;
use Illuminate\Support\Facades\Route;

Route::prefix('inventory')->group(function () {
    Route::get('/items', [InventoryController::class, 'index']);
    Route::get('/valuasi', [InventoryController::class, 'valuasi']);
    Route::post('/items', [InventoryController::class, 'store']);
    Route::put('/items/{id}', [InventoryController::class, 'update']);
    Route::delete('/items/{id}', [InventoryController::class, 'destroy']);
    
    Route::post('/adjust', [InventoryController::class, 'adjust']);
    Route::get('/transactions', [InventoryController::class, 'transactions']);
    Route::get('/categories', [InventoryController::class, 'categories']);
    Route::post('/categories', [InventoryController::class, 'storeCategory']);
    Route::post('/items/bulk', [InventoryController::class, 'bulkStore']);
});
