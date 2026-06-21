# Arsitektur Sistem

## Stack

| Layer | Teknologi |
|---|---|
| Backend | Laravel 11 (PHP) |
| Frontend | Vue 3 + TypeScript (SPA via Vite 8) |
| Database | MySQL (production) / SQLite (testing) |
| CSS | Tailwind CSS v4 |
| Icon | PrimeIcons |
| Auth | Laravel Session (cookie-based) |
| AI | Groq — Whisper (STT) + Llama 3.1 (NLU) |
| Deploy | VPS + Nginx + SSL (HTTPS wajib) |

## Dependencies Frontend

| Package | Versi | Dipakai untuk |
|---|---|---|
| vue | ^3.5 | Framework utama |
| axios | ^1.16 | HTTP client |
| jspdf + jspdf-autotable | ^4.2 / ^5.0 | Generate PDF invoice & laporan |
| pdf-lib | ^1.17 | Merge PDF lampiran + generated PDF |
| xlsx | ^0.18 | Export/import Excel |
| lucide-vue-next | ^1.0 | Icon tambahan (selain PrimeIcons) |
| tailwindcss | ^4.0 | CSS utility |

## Pola Routing

Semua halaman di-serve lewat satu blade: `resources/views/app.blade.php`.
Laravel pass `page.component` ke blade, Vue render komponen yang sesuai.

| URL | Komponen |
|---|---|
| `/` | `Landing.vue` |
| `/login` | `Login.vue` |
| `/register` | `Register.vue` |
| `/forgot-password` | `ForgotPassword.vue` |
| `/reset-password` | `ResetPassword.vue` |
| `/dashboard` | `Dashboard.vue` (SPA utama) |

Navigasi antar modul di dalam dashboard menggunakan `?tab=` query param tanpa full page reload.

### Auth Flow
1. GET `/sanctum/csrf-cookie` → ambil CSRF token
2. POST `/auth/login` → set session cookie
3. Redirect ke `/dashboard`
4. Setiap request selanjutnya membawa session cookie otomatis

### Middleware
| Middleware | Fungsi |
|---|---|
| `EnsureAuthenticated` | Wajib login (semua module routes) |
| `EnsureSuperAdmin` | Wajib role `super_admin` |
| `SecurityHeaders` | Set security headers di response |
| `throttle:login` | Rate limit login |
| `throttle:register` | Rate limit register |

### Endpoint Tambahan (tidak di module routes)
| Method | Endpoint | Keterangan |
|---|---|---|
| GET | `/auth/me` | Data user + permissions |
| GET | `/push/vapid-key` | VAPID public key |
| POST | `/push/subscribe` | Daftarkan device |
| POST | `/push/unsubscribe` | Hapus subscription |
| GET | `/notifications` | 30 notif terbaru + unread_count |
| POST | `/notifications/{id}/read` | Mark satu notif dibaca |
| POST | `/notifications/read-all` | Mark semua dibaca |
| GET | `/auth/users/{id}/permissions` | Baca permission admin |
| PUT | `/auth/users/{id}/permissions` | Update permission |
| GET | `/activity-logs` | 100 activity log terbaru |
| POST | `/auth/forgot-password` | Kirim link reset password via email (throttle 5/menit) |
| POST | `/auth/reset-password` | Validasi token + simpan password baru |
| GET | `/bank-accounts` | Daftar rekening bank (semua auth) |
| POST | `/bank-accounts` | Tambah rekening (super admin) |
| PUT | `/bank-accounts/{id}` | Edit rekening (super admin) |
| DELETE | `/bank-accounts/{id}` | Hapus rekening (super admin) |

### NotificationBell
- Polling tiap **30 detik** via `setInterval`
- Badge merah: `unreadCount` (dari `res.data.unread_count`)
- Klik notif → mark read + redirect ke `notif.url`
- Icon type: `user` (biru), `warning` (amber), default (primary)
- Tombol push: cek `Notification.permission === 'granted'` untuk state aktif/tidak

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

## Navigasi SPA

- Semua modul ada di `Dashboard.vue` via `v-else-if="activeTab === '...'"` — tidak ada Vue Router
- Tab aktif disimpan di URL sebagai `?tab=xxx` agar bisa di-refresh/bookmark
- Navigasi internal via `window.dispatchEvent(new CustomEvent('tab-navigate', { detail: id }))`
- Cross-module data refresh via `window.dispatchEvent(new CustomEvent('sales-updated'))`

## Jalur Pengurangan Stok

Ada 3 cara stok bisa berkurang secara otomatis:

1. **Manual Adjust** — dari halaman Inventory (butuh permission `can_adjust`)
2. **Ship Invoice** — saat status invoice berubah `rencana` → `sudah_dikirim`
3. **Buat Surat Jalan** — stok dipotong saat SJ dibuat, dikembalikan saat SJ dihapus

Stok **tidak boleh minus** — backend validasi sebelum semua operasi OUT.

## Scheduled Jobs

| Command | Jadwal | Fungsi |
|---|---|---|
| `calibration:check` | Setiap Senin 08:00 | Cek kalibrasi > 7 hari → push notif ke super admin |

## Telegram Integration

`app/Modules/Telegram/` — bot dua arah, bukan sekadar notifikasi. Detail lengkap di `docs/telegram-bot.md`.

Fitur utama:
- **Voice note → stok:** transkripsi via Groq Whisper, parse intent via LLM (llama-3.1-8b-instant), fuzzy search barang, apply langsung
- **Buat invoice** step-by-step via chat, session di Cache
- **Approval user** baru via inline keyboard tanpa buka web
- **Cek stok & valuasi** via perintah teks

## Push Notification

Detail lengkap di `docs/pwa-push.md`.

Flow:
1. Super admin klik "Aktifkan" di NotificationBell
2. `usePush.ts` → ambil VAPID public key dari `/push/vapid-key`
3. Register service worker + subscribe PushManager
4. Simpan subscription ke `/push/subscribe`
5. Backend (`WebPushService.php`) kirim push saat ada trigger (user baru, kalibrasi overdue)

## useAuth — Permission System

`resources/js/composables/useAuth.ts` — singleton (state di module level, shared antar komponen).

```ts
can(feature, action) // action: 'view' | 'create' | 'edit' | 'delete' | 'adjust'
// super_admin → selalu true
// admin tanpa permission record → default true untuk 'view', false untuk lainnya
```

State: `user`, `loaded` — di-fetch sekali via `loadUser()`, tidak re-fetch selama sesi.
