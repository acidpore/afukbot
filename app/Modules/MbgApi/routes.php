<?php

use App\Modules\MbgApi\MbgApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('mbg')->group(function () {
    Route::get('/dashboard/overview',    [MbgApiController::class, 'dashboardOverview']);
    Route::get('/users',                 [MbgApiController::class, 'users']);
    Route::get('/organizations',         [MbgApiController::class, 'organizations']);
    Route::get('/subscriptions',         [MbgApiController::class, 'subscriptions']);
    Route::get('/plans',                 [MbgApiController::class, 'plans']);
    Route::get('/roles',                 [MbgApiController::class, 'roles']);
    Route::get('/vendors',               [MbgApiController::class, 'vendors']);
    Route::get('/sales',                 [MbgApiController::class, 'sales']);
    Route::get('/foundations',           [MbgApiController::class, 'foundations']);
    Route::get('/audit-logs',            [MbgApiController::class, 'auditLogs']);
    Route::get('/system-settings',       [MbgApiController::class, 'systemSettings']);
    Route::get('/notifications',         [MbgApiController::class, 'notifications']);
    Route::get('/kitchen-equipment',     [MbgApiController::class, 'kitchenEquipment']);
    Route::get('/marketplace-settings',  [MbgApiController::class, 'marketplaceSettings']);
    Route::get('/sales-payrolls',        [MbgApiController::class, 'salesPayrolls']);
});
