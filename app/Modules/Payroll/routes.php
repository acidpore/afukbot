<?php

use App\Modules\Payroll\PayrollController;
use Illuminate\Support\Facades\Route;

Route::prefix('payrolls')->group(function () {
    Route::get('/', [PayrollController::class, 'index']);
    Route::post('/generate', [PayrollController::class, 'generate']);
    Route::post('/{id}/pay', [PayrollController::class, 'markAsPaid']);
});
