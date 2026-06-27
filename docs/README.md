# MBG Admin System — Dokumentasi

Dokumentasi internal sistem manajemen MBG Store.

## Daftar Dokumen

| Dokumen | Isi |
|---|---|
| [Arsitektur Sistem](./architecture.md) | Stack, struktur folder, pola kode, alur autentikasi |
| [Modul & Fitur](./modules.md) | Detail lengkap semua modul: endpoint, form, business logic |
| [Skema Database](./database.md) | Semua tabel, kolom, relasi, dan catatan penting |
| [UI Patterns](./ui-patterns.md) | Konvensi frontend, pola CRUD, export, permission UI |
| [Auth Flow & Keamanan Login](./auth-flow.md) | Login, register, validasi, rate limit, fitur keamanan yang ada & yang belum |
| [Hirarki Role & Akses](./roles-permissions.md) | Super Admin, Admin, permission system |
| [Fitur Kalibrasi Stok](./calibration.md) | Kalibrasi mingguan, reminder otomatis |
| [PWA & Push Notifikasi](./pwa-push.md) | Install ke HP, push notif, cara setup di VPS |
| [Telegram Bot](./telegram-bot.md) | Voice note stok, buat invoice, approval user via Telegram |
| [Panduan Deploy VPS](./deploy-vps.md) | Langkah lengkap apply semua fitur ke production |

## Modul Sistem

| Modul | Tab | Keterangan |
|---|---|---|
| Dashboard | `overview` | Statistik, piutang, barang perlu disiapkan |
| Stok Ruko | `inventory-ruko` | Inventory lokasi ruko |
| Stok Margomulyo | `inventory-margomulyo` | Inventory lokasi margomulyo |
| Mutasi Stok | `inventory-history` | Riwayat IN/OUT stok |
| Kalibrasi Stok | `inventory-calibration` | Kalibrasi fisik mingguan |
| Invoice | `sales` | Buat, kelola, kirim invoice |
| Invoice Eksternal | `invoice-eksternal` | Invoice multi-perusahaan untuk pembelian luar (audit), PPN 11%, PDF dompdf |
| Surat Jalan | `surat-jalan` | Pengiriman partial per invoice |
| Pengeluaran | `expenses` | Catat pengeluaran ruko |
| Pemasukan | `incomes` | Catat pemasukan ruko |
| RAB Tracking | `rab` | Anggaran & realisasi (multi-periode + tab Pengajuan) |
| MBG Admin | `mbg` | Sinkronisasi MBG API |
| Pengaturan | `settings` | User management, rekening bank, log aktivitas |
| Mutasi Rekening | `mutasi-rekening` | Mutasi rekening PT, konsultan pajak, import Mandiri |

---

> Rule pengembangan: lihat `rule.md` di root project.
