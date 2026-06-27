<?php

use App\Modules\Budget\BudgetController;
use Illuminate\Support\Facades\Route;

// Periods (RAB per periode)
Route::get('/budget/periods', [BudgetController::class, 'indexPeriods']);
Route::post('/budget/periods', [BudgetController::class, 'storePeriod']);
Route::delete('/budget/periods/{id}', [BudgetController::class, 'destroyPeriod']);

// Proposals (Pengajuan)
Route::get('/budget/proposals', [BudgetController::class, 'indexProposals']);
Route::post('/budget/proposals', [BudgetController::class, 'storeProposal']);
Route::put('/budget/proposals/{id}', [BudgetController::class, 'updateProposal']);
Route::delete('/budget/proposals/{id}', [BudgetController::class, 'destroyProposal']);

// Categories
Route::get('/budget/categories', [BudgetController::class, 'indexCategories']);
Route::post('/budget/categories', [BudgetController::class, 'storeCategory']);
Route::put('/budget/categories/{id}', [BudgetController::class, 'updateCategory']);
Route::delete('/budget/categories/{id}', [BudgetController::class, 'destroyCategory']);

// Items
Route::post('/budget/items', [BudgetController::class, 'storeItem']);
Route::post('/budget/items/bulk', [BudgetController::class, 'bulkStoreItems']);
Route::put('/budget/items/{id}', [BudgetController::class, 'updateItem']);
Route::delete('/budget/items/{id}', [BudgetController::class, 'destroyItem']);

// Transactions
Route::get('/budget/transactions', [BudgetController::class, 'indexTransactions']);
Route::post('/budget/transactions', [BudgetController::class, 'storeTransaction']);
Route::put('/budget/transactions/{id}', [BudgetController::class, 'updateTransaction']);
Route::delete('/budget/transactions/{id}', [BudgetController::class, 'destroyTransaction']);
Route::post('/budget/transactions/{id}/receipt', [BudgetController::class, 'uploadReceipt']);

// Dashboard
Route::get('/budget/summary', [BudgetController::class, 'summary']);
Route::get('/budget/trend', [BudgetController::class, 'trend']);

// Period setting
Route::get('/budget/period-setting', [BudgetController::class, 'getPeriodSetting']);
Route::put('/budget/period-setting', [BudgetController::class, 'setPeriodSetting']);
