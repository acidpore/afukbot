<?php

use App\Modules\Auth\AuthController;
use App\Modules\Auth\RegisterController;
use App\Http\Middleware\EnsureAuthenticated;
use Illuminate\Support\Facades\Route;

// ── Public pages ───────────────────────────────────────────
Route::get('/', fn() => view('app', ['page' => ['component' => 'Landing']]));
Route::get('/login', fn() => view('app', ['page' => ['component' => 'Login']]));
Route::get('/dashboard', fn() => view('app', ['page' => ['component' => 'Dashboard']]));

// ── Auth API ───────────────────────────────────────────────
Route::get('/sanctum/csrf-cookie', fn() => response()->json(['csrf' => csrf_token()]));
Route::post('/auth/login',    [AuthController::class,   'login']);
Route::post('/auth/logout',   [AuthController::class,   'logout']);
Route::get('/auth/me',        [AuthController::class,   'me']);
Route::post('/auth/register', [RegisterController::class, 'register']);

// Register page
Route::get('/register', fn() => view('app', ['page' => ['component' => 'Register']]));

// ── Protected Module Routes ────────────────────────────────
Route::middleware(EnsureAuthenticated::class)->group(function () {
    // Users management
    Route::get('/auth/users',           [RegisterController::class, 'getUsers']);
    Route::get('/auth/pending-count',   [RegisterController::class, 'pendingCount']);
    Route::post('/auth/users/{id}/approve', [RegisterController::class, 'approve']);
    Route::post('/auth/users/{id}/reject',  [RegisterController::class, 'reject']);
    require __DIR__.'/../app/Modules/Inventory/routes.php';
    require __DIR__.'/../app/Modules/Employee/routes.php';
    require __DIR__.'/../app/Modules/Attendance/routes.php';
    require __DIR__.'/../app/Modules/Payroll/routes.php';
    require __DIR__.'/../app/Modules/Sales/routes.php';
    require __DIR__.'/../app/Modules/Expense/routes.php';
    require __DIR__.'/../app/Modules/Income/routes.php';
    require __DIR__.'/../app/Modules/ManualPiutang/routes.php';
    require __DIR__.'/../app/Modules/Budget/routes.php';
});

// Telegram webhook tidak perlu auth (dipanggil oleh Telegram server)
require __DIR__.'/../app/Modules/Telegram/routes.php';
