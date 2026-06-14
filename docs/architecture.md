# Arsitektur Sistem

## Stack

| Layer | Teknologi |
|---|---|
| Backend | Laravel 11 (PHP) |
| Frontend | Vue 3 + TypeScript (SPA via Vite) |
| Database | MySQL |
| CSS | Tailwind CSS |
| Icon | PrimeIcons |
| Auth | Laravel Session (cookie-based) |
| Deploy | VPS + Nginx + SSL (HTTPS wajib) |

## Pola Routing

Semua halaman di-serve lewat satu blade: `resources/views/app.blade.php`.
Frontend Vue menentukan halaman berdasarkan `data-page` attribute.
Navigasi antar modul menggunakan `?tab=` query param tanpa full page reload.

## Struktur Backend

```
app/
  Models/              — Eloquent models
  Modules/             — Fitur dikelompokkan per domain
    Auth/              — Login, register, permission, push, notification
    Inventory/         — Stok barang, kalibrasi
    Sales/             — Invoice penjualan
    SuratJalan/        — Surat jalan pengiriman
    Expense/           — Pengeluaran
    Income/            — Pemasukan
    Budget/            — RAB tracking
    Employee/          — Data karyawan
    Attendance/        — Absensi
    Payroll/           — Penggajian
    Telegram/          — Bot Telegram notifikasi
    MbgApi/            — Sinkronisasi MBG API
  Services/            — Logic lintas modul (WebPushService)
  Http/Middleware/     — EnsureAuthenticated, EnsureSuperAdmin
  Console/Commands/    — Scheduled jobs
```

Setiap modul memiliki: `routes.php` + `Controller.php` + `Service.php` (kalau ada business logic).

## Struktur Frontend

```
resources/js/
  Pages/
    Login.vue
    Register.vue
    Dashboard.vue        — SPA utama, semua tab di sini
    Modules/             — Komponen per fitur
      Inventory/
        InventoryModule.vue
        CalibrationModule.vue
      Settings/
        SettingsModule.vue
      Sales/, Expense/, dll.
  Layouts/
    MainLayout.vue       — Sidebar + topbar + command palette
  components/shared/
    Sidebar.vue
    LoadingState.vue
    NotificationBell.vue — Bell + dropdown notifikasi di topbar
  composables/
    useAuth.ts           — State user global (role, permissions, can())
    usePush.ts           — Register/unregister push subscription
  api/
    inventory.api.ts
    sales.api.ts
    calibration.api.ts
    expense.api.ts
    dll.
```

## Alur Autentikasi

1. User login via `/auth/login` → session cookie dibuat
2. Axios interceptor redirect ke `/login` kalau response 401
3. `useAuth.ts` fetch `/auth/me` untuk dapat data user + role + permissions
4. `isSuperAdmin` dan `can(feature, action)` digunakan di seluruh frontend untuk kontrol tampilan
