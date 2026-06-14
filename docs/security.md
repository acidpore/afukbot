# Security Hardening

## Yang Sudah Diterapkan

### 1. Rate Limiting (Brute Force Protection)
- **Login**: maks 5 percobaan per menit per IP → HTTP 429 jika melebihi
- **Register**: maks 3 percobaan per menit per IP
- Diset via `RateLimiter::for()` di `routes/web.php` dengan named limiter `login` dan `register`

### 2. Security Headers (via `SecurityHeaders` middleware)
Berlaku untuk semua response:
| Header | Nilai |
|---|---|
| X-Content-Type-Options | nosniff |
| X-Frame-Options | SAMEORIGIN |
| X-XSS-Protection | 1; mode=block |
| Referrer-Policy | strict-origin-when-cross-origin |
| Permissions-Policy | geolocation=(), microphone=(), camera=() |
| Strict-Transport-Security | max-age=31536000 (HTTPS only) |

### 3. Password Policy (saat registrasi)
- Minimal 8 karakter
- Harus ada minimal 1 huruf kapital
- Harus ada minimal 1 angka
- Harus dikonfirmasi (password confirmation)

### 4. Session Hardening
Set ini di `.env` production:
```
SESSION_ENCRYPT=true        # Session terenkripsi di storage
SESSION_SECURE_COOKIE=true  # Cookie hanya dikirim via HTTPS
SESSION_HTTP_ONLY=true      # Cookie tidak bisa diakses JavaScript
SESSION_SAME_SITE=lax       # Proteksi CSRF dasar
```

### 5. CSRF Protection
- Laravel built-in CSRF token aktif untuk semua POST/PUT/DELETE
- Pengecualian hanya untuk `telegram/webhook` (dipanggil oleh server Telegram)

### 6. Role & Permission Checks
- Middleware `EnsureAuthenticated` → semua route protected
- Middleware `EnsureSuperAdmin` → route admin notifications & permission management
- Super admin tidak bisa dihapus (guard di `RegisterController::destroy()`)

---

## Rekomendasi Tambahan (Optional)

### Two-Factor Authentication (2FA)
Gunakan package `pragmarx/google2fa-laravel` jika ingin tambah keamanan login.

### Audit Log
Catat semua aksi sensitif (approve/reject user, ubah permission) ke tabel `audit_logs`.

### IP Whitelist untuk Super Admin
Batasi akses super admin hanya dari IP tertentu dengan middleware tambahan.
