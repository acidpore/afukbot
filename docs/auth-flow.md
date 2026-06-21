# Auth Flow & Keamanan Login

Detail lengkap alur autentikasi, validasi, dan mekanisme keamanan yang sudah ada — sebagai referensi untuk penambahan keamanan ke depan.

---

## Halaman Publik

| URL | Komponen | Keterangan |
|---|---|---|
| `/` | `Landing.vue` | Landing page marketing, tidak butuh login |
| `/login` | `Login.vue` | Form login |
| `/register` | `Register.vue` | Form registrasi, hasil `pending` |
| `/forgot-password` | `ForgotPassword.vue` | Form kirim link reset via email |
| `/reset-password` | `ResetPassword.vue` | Form isi password baru (dari link email) |

---

## Alur Login

**File:** `Login.vue` → `POST /auth/login` → `AuthController::login()`

### Step Frontend
1. User submit form (email + password)
2. `GET /sanctum/csrf-cookie` → ambil CSRF token (catch error diabaikan agar tidak block)
3. `POST /auth/login` dengan `{ email, password }`
4. Sukses → `window.location.href = '/dashboard'` (hard redirect, bukan Vue Router)
5. Gagal → tampilkan pesan error dari `e.response.data.message`

### Form Fields Login
| Field | Tipe | Keterangan |
|---|---|---|
| email | email | required |
| password | password | required |
| remember | checkbox | Dikirim ke backend, kontrol session persistent |

Session persistent (ingat saya dicentang) → cookie tidak expire saat browser tutup. Tidak dicentang → session expire saat browser tutup.

---

## Backend Login — Mekanisme Keamanan

**File:** `app/Modules/Auth/AuthController.php`

### Rate Limiting Berlapis Dua
1. **Middleware `throttle:login`** di routes (Laravel built-in, limit default 60/menit per IP)
2. **Custom brute-force protection** via tabel `login_attempts`:
   - Max **5 gagal** dalam **15 menit** per email
   - Lockout: return 429 + pesan "coba lagi dalam 15 menit"
   - Countdown warning: sisa ≤ 2 percobaan → pesan "Sisa X percobaan"

### Status Check Setelah Auth
Setelah `Auth::attempt()` sukses, cek status user:
| Status | Response |
|---|---|
| `pending` | Logout + 403 "menunggu persetujuan admin" |
| `rejected` | Logout + 403 "akun ditolak" |
| `active` | Lanjut, return user data |

### Session Security
```php
$request->session()->regenerate(); // regenerate session ID setelah login
```
Mencegah **session fixation attack**.

### Activity Log
Setiap login berhasil dicatat ke `activity_logs`:
```
action: 'login', description: 'Login berhasil dari IP {ip}'
```

### Login Attempts Table
Semua percobaan (sukses maupun gagal) dicatat dengan: `email`, `ip_address`, `success`, `attempted_at`.

---

## Alur Reset Password

**File:** `ForgotPassword.vue` → `POST /auth/forgot-password` → `PasswordResetController::sendLink()`
**File:** `ResetPassword.vue` → `POST /auth/reset-password` → `PasswordResetController::reset()`

### Step
1. User klik "Lupa Password?" di halaman login → redirect ke `/forgot-password`
2. Isi email → `POST /auth/forgot-password` → Laravel generate token di tabel `password_reset_tokens`
3. Email dikirim ke inbox user berisi link: `/reset-password?token=xxx&email=xxx` (berlaku 60 menit)
4. User klik link → halaman `/reset-password` baca token + email dari query param
5. Isi password baru → `POST /auth/reset-password` → token divalidasi, password di-hash dan disimpan
6. Sukses → tampilkan pesan + link ke `/login`

> Response `POST /auth/forgot-password` selalu sukses 200 — tidak bocorkan apakah email terdaftar atau tidak.

Password baru harus memenuhi policy yang sama: min 8 karakter, 1 kapital, 1 angka.

Rate limit: `throttle:5,1` (5 request/menit per IP) pada endpoint forgot-password.

---

## Alur Register

**File:** `Register.vue` → `POST /auth/register` → `RegisterController::register()`

### Step Frontend
1. Submit form: `name`, `email`, `password`, `password_confirmation`
2. Sukses → tampilkan success state ("Pendaftaran Terkirim!")
3. Gagal → tampilkan error (Laravel validation errors di-flatten jadi satu string)

### Form Fields Register
| Field | Tipe | Required |
|---|---|---|
| name | text | Ya |
| email | email | Ya |
| password | password | Ya |
| password_confirmation | password | Ya |

### Validasi Backend
```php
'name'     => 'required|string|max:100'
'email'    => 'required|email:rfc,dns|unique:users,email'  // validasi DNS MX record
'password' => 'required|min:8|confirmed|regex:/[A-Z]/|regex:/[0-9]/'
```

**Password policy yang sudah ada:**
- Minimal 8 karakter
- Harus mengandung minimal 1 huruf kapital
- Harus mengandung minimal 1 angka
- Harus cocok dengan `password_confirmation`
- Email divalidasi sampai level DNS record (bukan sekadar format)

### Setelah Register
1. User dibuat dengan `status = 'pending'`
2. Notifikasi ke admin via **Telegram** (inline keyboard Approve/Reject)
3. Notifikasi ke super admin via **Web Push**
4. Return 201

`notifyUser()` mengirim email ke user via `Mail::raw()` dengan subject "Akun MBG System disetujui/ditolak".

---

## Logout

```php
Auth::logout();
$request->session()->invalidate();    // hapus semua data session
$request->session()->regenerateToken(); // buat CSRF token baru
```

Activity log: `action: 'logout'`.

---

## `/auth/me`

Dipakai `useAuth.ts` untuk load state user saat app pertama kali mount.

Response:
```json
{
  "user": {
    "id": 1,
    "name": "...",
    "email": "...",
    "role": "super_admin" | "admin",
    "permissions": {
      "inventory": { "can_view": true, "can_create": false, ... },
      ...
    }
  }
}
```

Untuk `super_admin`: `permissions` dikembalikan kosong `{}` — frontend `can()` sudah handle dengan return `true` untuk super admin tanpa cek permissions.

Untuk `admin`: permissions di-load dari `user_permissions` table. Jika feature belum ada recordnya, default: `can_view: true`, sisanya `false`.

---

## Status Fitur Keamanan

| Fitur | Status | Catatan |
|---|---|---|
| Checkbox "Ingat saya" | Sudah | `remember` dikirim ke backend, `Auth::attempt(..., $remember)` |
| Email notifikasi approved/rejected | Sudah | `notifyUser()` aktif via `Mail::raw()` |
| Reset password / lupa password | Sudah | Flow lengkap: `ForgotPassword.vue` + `ResetPassword.vue` + `PasswordResetController` |
| Password strength indicator | Sudah | 4 bar warna + label di `Register.vue` |
| Activity Log UI | Sudah | Tab "Log Aktivitas" di Settings, super admin only |
| Mail config | Sudah | Gmail SMTP, `MAIL_MAILER=smtp`, `MAIL_PORT=587`, TLS |
| Two-factor authentication (2FA) | Belum | Belum diimplementasi |
| Email DNS validation | Sudah | `email:rfc,dns` di validator register |
| Session regeneration | Sudah | `session()->regenerate()` setelah login |
| CSRF protection | Sudah | Via Sanctum CSRF cookie |
| Brute force protection | Sudah | 5 gagal / 15 menit per email |
| Security headers middleware | Sudah | `SecurityHeaders` middleware di routes |
| Activity log backend | Sudah | Login, logout, user approve/reject/delete |
