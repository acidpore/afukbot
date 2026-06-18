<?php

use App\Modules\Auth\AuthController;
use App\Modules\Auth\RegisterController;
use App\Modules\Auth\AdminPermissionController;
use App\Modules\Auth\PushSubscriptionController;
use App\Modules\Auth\NotificationController;
use App\Http\Middleware\EnsureAuthenticated;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Support\Facades\Route;

// ── Public pages ───────────────────────────────────────────
Route::get('/', fn() => view('app', ['page' => ['component' => 'Landing']]));
Route::get('/login', fn() => view('app', ['page' => ['component' => 'Login']]));
Route::get('/dashboard', fn() => view('app', ['page' => ['component' => 'Dashboard']]));

// ── Auth API ───────────────────────────────────────────────
Route::get('/sanctum/csrf-cookie', fn() => response()->json(['csrf' => csrf_token()]));
Route::post('/auth/login',    [AuthController::class,   'login'])->middleware('throttle:login');
Route::post('/auth/logout',   [AuthController::class,   'logout']);
Route::get('/auth/me',        [AuthController::class,   'me']);
Route::post('/auth/register', [RegisterController::class, 'register'])->middleware('throttle:register');

// Register page
Route::get('/register', fn() => view('app', ['page' => ['component' => 'Register']]));

// ── Protected Module Routes ────────────────────────────────
Route::middleware(EnsureAuthenticated::class)->group(function () {
    // Users management
    Route::get('/auth/users',           [RegisterController::class, 'getUsers']);
    Route::get('/auth/pending-count',   [RegisterController::class, 'pendingCount']);
    Route::post('/auth/users/{id}/approve',  [RegisterController::class, 'approve']);
    Route::post('/auth/users/{id}/reject',   [RegisterController::class, 'reject']);
    Route::delete('/auth/users/{id}',        [RegisterController::class, 'destroy']);

    // Push subscriptions
    Route::get('/push/vapid-key',    [PushSubscriptionController::class, 'vapidPublicKey']);
    Route::post('/push/subscribe',   [PushSubscriptionController::class, 'store']);
    Route::post('/push/unsubscribe', [PushSubscriptionController::class, 'destroy']);

    // In-app notifications (super admin only)
    Route::middleware(EnsureSuperAdmin::class)->group(function () {
        Route::get('/notifications',             [NotificationController::class, 'index']);
        Route::post('/notifications/{id}/read',  [NotificationController::class, 'markRead']);
        Route::post('/notifications/read-all',   [NotificationController::class, 'markAllRead']);
    });

    // Super admin only: permission management + activity log
    Route::middleware(EnsureSuperAdmin::class)->group(function () {
        Route::get('/auth/users/{id}/permissions',  [AdminPermissionController::class, 'index']);
        Route::put('/auth/users/{id}/permissions',  [AdminPermissionController::class, 'update']);
        Route::get('/activity-logs', fn() => response()->json(
            \App\Models\ActivityLog::with('user:id,name,email')
                ->orderByDesc('created_at')
                ->limit(100)
                ->get()
        ));
    });
    require __DIR__.'/../app/Modules/Inventory/routes.php';
    require __DIR__.'/../app/Modules/Employee/routes.php';
    require __DIR__.'/../app/Modules/Attendance/routes.php';
    require __DIR__.'/../app/Modules/Payroll/routes.php';
    require __DIR__.'/../app/Modules/Sales/routes.php';
    require __DIR__.'/../app/Modules/Expense/routes.php';
    require __DIR__.'/../app/Modules/Income/routes.php';
    require __DIR__.'/../app/Modules/ManualPiutang/routes.php';
    require __DIR__.'/../app/Modules/Budget/routes.php';
    require __DIR__.'/../app/Modules/SuratJalan/routes.php';
    require __DIR__.'/../app/Modules/MbgApi/routes.php';
});

// Telegram webhook tidak perlu auth (dipanggil oleh Telegram server)
require __DIR__.'/../app/Modules/Telegram/routes.php';
