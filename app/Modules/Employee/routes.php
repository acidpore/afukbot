<?php

use App\Modules\Employee\EmployeeController;
use Illuminate\Support\Facades\Route;

Route::prefix('employees')->group(function () {
    Route::get('/', [EmployeeController::class, 'index']);
    Route::post('/', [EmployeeController::class, 'store']);
    Route::get('/{id}', [EmployeeController::class, 'show']);
    Route::put('/{id}', [EmployeeController::class, 'update']);
    Route::delete('/{id}', [EmployeeController::class, 'destroy']);

    Route::get('/meta/departments', [EmployeeController::class, 'departments']);
    Route::get('/meta/positions', [EmployeeController::class, 'positions']);
    
    Route::post('/{id}/documents', [EmployeeController::class, 'uploadDocument']);
});
