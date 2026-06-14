# Fitur Kalibrasi Stok

## Tujuan

Memastikan jumlah stok di sistem selalu cocok dengan kondisi fisik di gudang/ruko.
Wajib dilakukan setiap minggu agar data valuasi stok bisa dipercaya sebagai sistem permanen.

## Cara Kerja

1. Buka **Dashboard → Inventory → Kalibrasi Stok**
2. Sistem menampilkan semua barang beserta stok sistem saat ini
3. Masukkan jumlah fisik hasil hitung di kolom "Stok Fisik"
4. Kolom "Selisih" otomatis muncul — merah kalau kurang, hijau kalau lebih
5. Isi catatan (opsional), klik **Terapkan Kalibrasi**
6. Sistem adjust semua item yang berbeda sekaligus + catat ke riwayat

## Reminder Otomatis

Setiap Senin pagi jam 08:00, sistem cek apakah kalibrasi sudah dilakukan dalam 7 hari terakhir.
Kalau belum → kirim push notifikasi ke super admin + muncul banner kuning di dashboard overview.

Scheduler command: `php artisan calibration:check`

## Tabel Database

### `stock_calibrations`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `calibrated_by` | FK users | Siapa yang melakukan |
| `calibrated_at` | date | Tanggal kalibrasi |
| `notes` | text | Catatan opsional |
| `total_items` | int | Total item yang dicek |
| `total_adjusted` | int | Total item yang disesuaikan |

### `stock_calibration_items`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `calibration_id` | FK | Relasi ke kalibrasi |
| `item_id` | FK | Item yang dicek |
| `item_name` | string | Nama item saat kalibrasi |
| `qty_system` | int | Stok sistem sebelum kalibrasi |
| `qty_physical` | int | Stok fisik hasil hitung |
| `delta` | int | Selisih (positif = tambah, negatif = kurang) |

## API Endpoints

| Method | Endpoint | Keterangan |
|---|---|---|
| GET | `/inventory/calibration/status` | Status kalibrasi terakhir + is_overdue |
| GET | `/inventory/calibration/items` | Daftar semua item untuk form kalibrasi |
| GET | `/inventory/calibration/history` | Riwayat 20 kalibrasi terakhir |
| POST | `/inventory/calibration/apply` | Terapkan kalibrasi |

## Jalur yang Bisa Ubah Stok

Ada 3 jalur otomatis yang memotong/menambah stok di luar kalibrasi:

1. **Manual Adjust** — tombol "Update Stok" di halaman Inventory (dikontrol permission `can_adjust`)
2. **Mark as Shipped** — invoice di-mark sudah dikirim langsung dari halaman Penjualan
3. **Buat Surat Jalan** — stok dipotong saat surat jalan dibuat, dikembalikan saat dihapus
