# Panduan Deploy VPS

## Prasyarat

- VPS dengan Nginx + PHP 8.2+ + MySQL
- Domain dengan SSL aktif (HTTPS wajib untuk PWA & push notif)
- Node.js 18+ untuk build frontend

---

## 1. Upload & Setup Project

```bash
cd /var/www/
git clone <repo> absensi
cd absensi
composer install --no-dev
cp .env.example .env
php artisan key:generate
```

---

## 2. Konfigurasi `.env`

```env
APP_URL=https://domainmu.com

DB_HOST=127.0.0.1
DB_DATABASE=mbg_db
DB_USERNAME=mbg_user
DB_PASSWORD=password_db

VAPID_PUBLIC_KEY=...   # lihat langkah 4
VAPID_PRIVATE_KEY=...  # lihat langkah 4

TELEGRAM_BOT_TOKEN=...
TELEGRAM_ADMIN_CHAT_ID=...
```

---

## 3. Migrasi & Seeder

```bash
php artisan migrate
php artisan db:seed --class=SuperAdminSeeder
```

---

## 4. Generate VAPID Keys (untuk push notif)

```bash
php artisan tinker
```

Di dalam tinker:
```php
$keys = \Minishlink\WebPush\VAPID::createVapidKeys();
echo "Public: " . $keys['publicKey'] . "\n";
echo "Private: " . $keys['privateKey'] . "\n";
exit;
```

Salin hasilnya ke `.env`:
```env
VAPID_PUBLIC_KEY=hasil_public_key
VAPID_PRIVATE_KEY=hasil_private_key
```

Tambahkan ke `config/services.php`:
```php
'vapid' => [
    'public_key'  => env('VAPID_PUBLIC_KEY'),
    'private_key' => env('VAPID_PRIVATE_KEY'),
],
```

---

## 5. Install Package Web Push

```bash
composer require minishlink/web-push
```

---

## 6. Upload Ikon PWA

```bash
mkdir -p public/icons
```

Upload `icon-192.png` dan `icon-512.png` ke folder `public/icons/`.

---

## 7. Build Frontend

```bash
npm install
npm run build
```

---

## 8. Permission Storage

```bash
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

---

## 9. Konfigurasi Nginx

```nginx
server {
    listen 443 ssl;
    server_name domainmu.com;

    root /var/www/absensi/public;
    index index.php;

    ssl_certificate /etc/letsencrypt/live/domainmu.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/domainmu.com/privkey.pem;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Service worker harus bisa diakses dari root
    location /sw.js {
        add_header Cache-Control "no-cache";
        add_header Service-Worker-Allowed "/";
    }
}

# Redirect HTTP ke HTTPS
server {
    listen 80;
    server_name domainmu.com;
    return 301 https://$host$request_uri;
}
```

---

## 10. Setup Cron (Scheduler Laravel)

```bash
crontab -e
```

Tambahkan:
```
* * * * * cd /var/www/absensi && php artisan schedule:run >> /dev/null 2>&1
```

Scheduler yang berjalan otomatis:
- Setiap Senin 08:00 → `calibration:check` (push notif kalibrasi jatuh tempo)
- Setiap hari 02:00 → `backup:upload`
- Tanggal 1 & 15 jam 08:00 → `rab:check-due`

---

## 11. SSL (kalau belum ada)

```bash
apt install certbot python3-certbot-nginx
certbot --nginx -d domainmu.com
```

---

## Checklist Setelah Deploy

- [ ] Website bisa diakses via HTTPS
- [ ] Login super admin berhasil
- [ ] Settings → Hak Akses Admin muncul
- [ ] Install ke home screen HP berhasil
- [ ] Klik "Aktifkan" notifikasi → label berubah "Aktif"
- [ ] Test push: `php artisan calibration:check`
- [ ] Cron scheduler berjalan: `php artisan schedule:list`
