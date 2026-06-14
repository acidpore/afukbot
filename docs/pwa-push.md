# PWA & Push Notifikasi

## PWA (Progressive Web App)

Website bisa di-install ke layar utama HP seperti aplikasi native.

File yang terlibat:
- `public/manifest.json` — metadata app (nama, ikon, warna tema)
- `public/sw.js` — service worker (handle push event + notif klik)
- `resources/views/app.blade.php` — meta tags PWA + link manifest

### Cara Install ke HP

**Android (Chrome):**
1. Buka website di Chrome
2. Login
3. Tap menu titik tiga → "Tambahkan ke layar utama"
4. Konfirmasi → ikon muncul di home screen

**iOS (Safari):**
1. Buka website di Safari
2. Tap ikon Share (kotak dengan panah ke atas)
3. Pilih "Add to Home Screen"
4. Konfirmasi

### Ikon yang Diperlukan

Taruh file di `public/icons/`:
- `icon-192.png` — 192x192 pixel (logo MBG)
- `icon-512.png` — 512x512 pixel (logo MBG)

---

## Push Notifikasi

Notifikasi yang dikirim ke super admin:

| Trigger | Judul | Kapan |
|---|---|---|
| User baru register | "User Baru Menunggu Persetujuan" | Realtime saat ada registrasi |
| Kalibrasi jatuh tempo | "Kalibrasi Stok Jatuh Tempo" | Setiap Senin 08:00 kalau > 7 hari |

### Cara Aktifkan Push di HP

1. Login sebagai super admin
2. Klik ikon bell di topbar kanan
3. Klik tombol **"Aktifkan"** di dalam dropdown
4. Izinkan notifikasi di popup browser
5. Label berubah jadi **"Aktif"** — selesai

Setelah diaktifkan, notifikasi akan masuk ke HP walau browser/tab tidak dibuka.

### Komponen

- `resources/js/components/shared/NotificationBell.vue` — bell di topbar, dropdown list notif, tombol aktifkan push
- `resources/js/composables/usePush.ts` — logic register/unregister subscription
- `app/Services/WebPushService.php` — kirim push + simpan ke DB
- `app/Modules/Auth/PushSubscriptionController.php` — simpan/hapus subscription
- `app/Modules/Auth/NotificationController.php` — list, mark read, mark all read

### Tabel Database

**`push_subscriptions`** — endpoint device yang subscribe
**`admin_notifications`** — notifikasi tersimpan untuk in-app notification center

### In-App Notification Center

Bell di topbar juga berfungsi sebagai notification center:
- Badge merah menunjukkan jumlah notif belum dibaca
- Dropdown menampilkan 30 notif terbaru
- Notif belum dibaca punya dot biru + background biru muda
- Klik notif → mark as read + navigate ke URL terkait
- "Tandai semua dibaca" untuk clear badge sekaligus
- Polling otomatis tiap 30 detik
