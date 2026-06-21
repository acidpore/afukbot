# Dokumentasi Modul — MBG Admin System

Detail fitur, endpoint, form fields, dan business logic per modul.

---

## 1. Dashboard (Overview)

**File:** `resources/js/Pages/Dashboard.vue`

### Statistik Cards
| Card | Sumber Data | Keterangan |
|---|---|---|
| Total Inventaris | `GET /inventory/items` | Jumlah jenis item di inventory |
| Total Invoice | `GET /sales` | Total semua invoice |
| Valuasi Stok + Piutang | `GET /inventory/valuasi` | Nilai stok + total piutang (invoice + manual) |
| Omzet Terkirim | `GET /sales` (filter `sudah_dikirim`) | Total grand_total invoice yang sudah dikirim |

### Sections
- **10 Barang Terlaris** — dari invoice `sudah_dikirim`, diurutkan by total qty, dengan mini progress bar
- **Piutang** — gabungan dari invoice belum lunas + manual piutang
- **Perlu Disiapkan** — barang dari invoice `rencana`, aggregated by item name, dengan list invoice-nya

### Piutang Manual
- Tambah: nama + nominal + keterangan (opsional)
- Hapus dengan konfirmasi
- Total manual + dari invoice dijumlah jadi "Total Piutang"

### Alert Banner Kalibrasi
Muncul di overview kalau `calibrationStatus.is_overdue === true`. Tombol langsung navigasi ke tab kalibrasi.

### Data Refresh
- Saat tab berubah ke `sales` atau `overview` → `fetchSales()` dipanggil ulang
- Saat tab browser kembali visible (`visibilitychange`) → `fetchAll()`
- Listen event `sales-updated` dari window untuk sinkronisasi dengan SalesModule

---

## 2. Inventory

**File:** `resources/js/Pages/Modules/Inventory/InventoryModule.vue`
**Backend:** `app/Modules/Inventory/`

### Views (Props `view`)
| View | Keterangan |
|---|---|
| `ruko` | Tampilkan item lokasi Ruko |
| `margomulyo` | Tampilkan item lokasi Margomulyo |
| `history` | Riwayat mutasi stok (stock transactions) |

### API Endpoints
| Method | Endpoint | Keterangan |
|---|---|---|
| GET | `/inventory/items` | Semua item (filter by location) |
| POST | `/inventory/items` | Buat item baru |
| PUT | `/inventory/items/{id}` | Update item |
| DELETE | `/inventory/items/{id}` | Hapus item |
| POST | `/inventory/items/bulk` | Bulk import CSV |
| GET | `/inventory/categories` | Semua kategori |
| POST | `/inventory/categories` | Buat kategori |
| POST | `/inventory/adjust` | Adjust stok (tambah/kurang/set) |
| GET | `/inventory/transactions` | Riwayat transaksi stok |
| GET | `/inventory/valuasi` | Total valuasi stok |

### Form Fields (Item)
| Field | Required | Keterangan |
|---|---|---|
| name | Ya | Nama barang |
| category_id | Ya | Kategori |
| quantity | Ya | Jumlah stok |
| unit | Ya | Satuan (pcs, kg, liter, dll) |
| harga_jual | Tidak | Harga jual per unit |
| description | Tidak | Deskripsi tambahan |
| location | Ya | `ruko` atau `margomulyo` |

### Stock Adjustment
- **Mode Delta:** Masuk (IN) / Keluar (OUT) dengan jumlah delta
- **Mode Set Aktual:** Langsung set ke nilai absolut
- Dicatat ke `stock_transactions` dengan: qty, type, stock_before, stock_after, date, notes, recorded_by_id

### Fitur Lain
- Filter by kategori + search by nama
- Sort by: nama, kategori, stok, satuan, harga_jual
- Pagination 10 item/halaman
- **Stok rendah (≤ 5)** → warna merah
- **Polling realtime setiap 8 detik** (update stok otomatis)
- Export CSV (nama, kategori, stok, satuan, harga_jual)
- Bulk import CSV dengan preview & validasi
- Mobile: card view | Desktop: tabel

### Permission Checks
- `can('inventory', 'create')` → tombol tambah item, import
- `can('inventory', 'edit')` → tombol edit
- `can('inventory', 'delete')` → tombol hapus
- `can('inventory', 'adjust')` → tombol update stok

---

## 3. Kalibrasi Stok

**File:** `resources/js/Pages/Modules/Inventory/CalibrationModule.vue`
**Docs lengkap:** `docs/calibration.md`

### API Endpoints
| Method | Endpoint | Keterangan |
|---|---|---|
| GET | `/inventory/calibration/status` | Status kalibrasi terakhir + `is_overdue` |
| GET | `/inventory/calibration/items` | Semua item untuk form kalibrasi |
| GET | `/inventory/calibration/history` | 20 kalibrasi terakhir |
| POST | `/inventory/calibration/apply` | Terapkan kalibrasi |

---

## 4. Sales / Invoice

**File:** `resources/js/Pages/Modules/Sales/SalesModule.vue`
**Backend:** `app/Modules/Sales/`

### API Endpoints
| Method | Endpoint | Keterangan |
|---|---|---|
| GET | `/sales` | Semua invoice |
| POST | `/sales` | Buat invoice baru |
| PUT | `/sales/{id}` | Update invoice |
| GET | `/sales/{id}` | Detail invoice |
| PATCH | `/sales/{id}/pay` | Tambah pembayaran (akumulatif) |
| PATCH | `/sales/{id}/set-payment` | Set nominal pembayaran (replace) |
| GET | `/sales/{id}/check-stock` | Cek ketersediaan stok sebelum kirim |
| PATCH | `/sales/{id}/ship` | Mark as shipped (`rencana` → `sudah_dikirim`) |
| PATCH | `/sales/{id}/revert-stock` | Revert ke `rencana` |
| DELETE | `/sales/{id}` | Hapus invoice |
| POST | `/sales/{id}/attachment` | Upload lampiran PDF |
| DELETE | `/sales/{id}/attachment` | Hapus lampiran |
| GET | `/sales/pending-items` | Barang dari invoice yang belum dikirim |

### Form Fields (Invoice)
| Field | Required | Keterangan |
|---|---|---|
| recipient_name | Ya | Nama penerima |
| recipient_address | Tidak | Alamat penerima |
| invoice_date | Ya | Tanggal invoice |
| shipped_at | Tidak | Tanggal kirim aktual |
| notes | Tidak | Catatan (diparse untuk nominal pembayaran) |
| sender_name | Tidak | Nama pengirim |
| sender_address | Tidak | Alamat pengirim |
| bank_account_name | Tidak | Nama rekening — diisi otomatis dari rekening terpilih |
| bank_name | Tidak | Nama bank — diisi otomatis dari rekening terpilih |
| bank_account_number | Tidak | Nomor rekening — diisi otomatis dari rekening terpilih |
| items[] | Ya (min 1) | Daftar barang |

### Form Fields (Item Invoice)
| Field | Required | Keterangan |
|---|---|---|
| item_name | Ya | Nama barang |
| description | Tidak | Deskripsi |
| qty | Ya | Jumlah |
| unit_price | Ya | Harga satuan |
| inventory_item_ids | Tidak | Link ke inventory (untuk potong stok) |

### Status Flow
```
[rencana] ──(ship)──> [sudah_dikirim]
[sudah_dikirim] ──(revert)──> [rencana]
```

### Status Pembayaran
| Kondisi | Label |
|---|---|
| `paid_amount == 0` | Belum Bayar |
| `0 < paid_amount < grand_total` | DP / Bayar Sebagian |
| `paid_amount >= grand_total` | Lunas |

### Business Logic
- Nomor invoice di-generate otomatis saat buat
- Grand total = sum(qty × unit_price) per item
- Stok dipotong saat status berubah ke `sudah_dikirim`, bukan saat invoice dibuat
- Notes di-parse untuk deteksi nominal pembayaran (regex IDR, "juta", "rb", "DP 50%")
- PDF invoice di-generate via jsPDF dengan: header perusahaan, tabel item, info pembayaran, detail bank pengirim
- Upload PDF lampiran bisa di-merge dengan generated PDF
- Modal kekurangan stok muncul jika stok tidak cukup saat ship

### Fitur Tambahan
- Pencarian item dengan pairing Badan/Tutup (misal: "Foodtray Badan" + "Foodtray Tutup" = Set)
- Bulk import item via Excel/CSV (dengan template download)
- Filter: status, bulan, search nama penerima

---

## 5. Surat Jalan

**File:** `resources/js/Pages/Modules/SuratJalan/SuratJalanModule.vue`
**Backend:** `app/Modules/SuratJalan/`

### API Endpoints
| Method | Endpoint | Keterangan |
|---|---|---|
| GET | `/surat-jalan` | Semua surat jalan |
| GET | `/surat-jalan/invoices-progress` | Invoice dengan progres pengiriman |
| GET | `/surat-jalan/invoices-completed` | Invoice yang sudah selesai semua dikirim |
| GET | `/surat-jalan/by-sale/{id}` | Surat jalan per invoice |
| POST | `/surat-jalan` | Buat surat jalan |
| DELETE | `/surat-jalan/{id}` | Hapus surat jalan |

### Form Fields (Surat Jalan)
| Field | Required | Keterangan |
|---|---|---|
| sale_id | Ya | Invoice yang dikirim |
| tanggal_kirim | Ya | Tanggal pengiriman |
| catatan | Tidak | Catatan |
| items[].sale_item_id | Ya | Item dari invoice |
| items[].qty_kirim | Ya | Jumlah yang dikirim |

### Status / Progress per Invoice
| Status | Kondisi |
|---|---|
| Belum Kirim | qty_total_kirim == 0 |
| Parsial | 0 < qty_total_kirim < qty_total_order |
| Selesai | qty_total_kirim >= qty_total_order |

### Business Logic
- Stok dipotong saat SJ dibuat (bukan saat invoice di-ship)
- Stok dikembalikan saat SJ dihapus
- Mendukung partial shipment: satu invoice bisa punya banyak SJ
- Nomor SJ di-generate otomatis
- Cek kekurangan stok sebelum SJ dibuat → tampilkan modal jika kurang
- Modal kekurangan stok punya opsi: Tambah Stok (delta) atau Set Stok Aktual

### Dashboard Surat Jalan
- **Cards:** Estimasi nilai belum kirim, total barang harus disiapkan
- **Alert:** Invoice yang belum dibayar > 7 hari (urgent, merah)
- **Picking list:** Rekapitulasi barang per nama + stok tersedia
- **Progress bar** per invoice
- **Tabs:** Aktif vs Riwayat (completed)
- **Filter:** Semua | Prioritas (sudah bayar) | Belum Kirim

### Urgency Badge (berdasarkan umur invoice)
- ≥ 3 hari belum dikirim → amber
- ≥ 7 hari belum dikirim → merah

---

## 6. Pengeluaran (Expense)

**File:** `resources/js/Pages/Modules/Expense/ExpenseModule.vue`
**Backend:** `app/Modules/Expense/`

### API Endpoints
| Method | Endpoint | Keterangan |
|---|---|---|
| GET | `/expenses` | Semua pengeluaran |
| POST | `/expenses` | Tambah pengeluaran |
| PUT | `/expenses/{id}` | Update pengeluaran |
| DELETE | `/expenses/{id}` | Hapus pengeluaran |
| GET | `/expenses/summary?month=` | Ringkasan per kategori |
| POST | `/expenses/import` | Import CSV |
| POST | `/expenses/{id}/receipt` | Upload struk |
| DELETE | `/expenses/{id}/receipt` | Hapus struk |

### Form Fields
| Field | Required | Keterangan |
|---|---|---|
| expense_date | Ya | Tanggal |
| category | Ya | Kategori (pilihan atau custom) |
| description | Ya | Deskripsi |
| amount | Ya | Nominal (> 0) |
| paid_by | Tidak | Dibayar oleh siapa |
| notes | Tidak | Catatan |
| receipt_file | Tidak | Struk (JPG/PNG/WebP/PDF, max 5MB) |

### Kategori Bawaan
`Go MBG`, `Makan`, `Afuk`, `Belanja`, `Lainnya`

Pilih "Lainnya" → input custom kategori muncul.

### Fitur
- Ringkasan per kategori dengan total
- Filter by bulan + kategori
- Import CSV: `tanggal, kategori, deskripsi, jumlah, dibayar_oleh, catatan`
- Export laporan PDF: ringkasan + tabel detail + tanda tangan
- Struk bisa diupload dan dilihat langsung dari list
- Entri yang berasal dari RAB ditandai (via `expense_transaction_id`)

---

## 7. Pemasukan (Income)

**File:** `resources/js/Pages/Modules/Income/IncomeModule.vue`
**Backend:** `app/Modules/Income/`

### API Endpoints
| Method | Endpoint | Keterangan |
|---|---|---|
| GET | `/incomes` | Semua pemasukan |
| POST | `/incomes` | Tambah pemasukan |
| PUT | `/incomes/{id}` | Update pemasukan |
| DELETE | `/incomes/{id}` | Hapus pemasukan |
| POST | `/incomes/import` | Import CSV |
| POST | `/incomes/{id}/receipt` | Upload bukti |
| DELETE | `/incomes/{id}/receipt` | Hapus bukti |

### Form Fields
| Field | Required | Keterangan |
|---|---|---|
| income_date | Ya | Tanggal |
| source | Ya | Sumber (teks bebas: "Ronald", "Rental", dll) |
| description | Ya | Deskripsi |
| amount | Ya | Nominal (> 0) |
| notes | Tidak | Catatan |
| receipt_file | Tidak | Bukti transfer (JPG/PNG/WebP/PDF, max 5MB) |

### Fitur
- Ringkasan per sumber dengan jumlah transaksi
- Filter by bulan + sumber
- Import CSV: `tanggal, sumber, deskripsi, jumlah, catatan`
- Export laporan PDF dengan ringkasan per sumber + tabel detail + tanda tangan
- Total pemasukan dipakai untuk kalkulasi saldo di laporan gabungan

---

## 8. RAB Tracking (Budget)

**File:** `resources/js/Pages/Modules/Budget/BudgetModule.vue`
**Backend:** `app/Modules/Budget/`

### Tabs
| Tab | Isi |
|---|---|
| Dashboard | Summary cards, status per kategori, trend 6 bulan |
| Master RAB | Kelola kategori & item anggaran |
| Realisasi | Catat & kelola transaksi aktual |

### API Endpoints
| Method | Endpoint | Keterangan |
|---|---|---|
| GET | `/budget/categories` | Daftar kategori |
| POST | `/budget/categories` | Buat kategori |
| PUT | `/budget/categories/{id}` | Update kategori |
| DELETE | `/budget/categories/{id}` | Hapus kategori |
| POST | `/budget/items` | Buat item anggaran |
| PUT | `/budget/items/{id}` | Update item |
| DELETE | `/budget/items/{id}` | Hapus item |
| POST | `/budget/items/bulk` | Bulk import |
| GET | `/budget/transactions` | Transaksi (filter: bulan/tanggal/item) |
| POST | `/budget/transactions` | Catat transaksi |
| PUT | `/budget/transactions/{id}` | Update transaksi |
| DELETE | `/budget/transactions/{id}` | Hapus transaksi |
| POST | `/budget/transactions/{id}/receipt` | Upload struk |
| GET | `/budget/summary` | Summary dashboard |
| GET | `/budget/trend` | Trend 6 bulan |
| GET | `/budget/period-setting` | Baca periode aktif |
| PUT | `/budget/period-setting` | Set periode aktif |

### Form Fields (Item Anggaran)
| Field | Required | Keterangan |
|---|---|---|
| name | Ya | Nama item |
| category_id | Ya | Kategori |
| unit_cost | Ya | Biaya per unit (Rp) |
| rate | Ya | Frekuensi: `harian`, `mingguan`, `dua_mingguan`, `bulanan`, `custom` |
| multiplier | Ya | Jumlah unit (default 1) |
| is_active | Tidak | Aktif/nonaktif |

### Kalkulasi Budget Bulanan
```
total_monthly = unit_cost × multiplier × rate_multiplier

rate_multiplier:
  harian        → × 30
  mingguan      → × 4
  dua_mingguan  → × 2
  bulanan       → × 1
  custom        → × 1
```

### Status Realisasi
| Status | Kondisi |
|---|---|
| On Track | realisasi ≤ rencana |
| Warning | 80% ≤ realisasi < 100% rencana |
| Over Budget | realisasi > rencana |

### Dashboard Cards
- Total Direncanakan
- Total Realisasi + persentase
- Sisa Anggaran
- Progress bar per kategori
- Trend 6 bulan (chart)

### Fitur Transaksi
- Link ke item anggaran (dropdown kategori → item)
- Upload struk (JPG/PNG/PDF)
- Export CSV kompatibel dengan format import Expense
- Filter: exact date | per bulan | kategori | item

---

## 9. MBG Admin

**File:** `resources/js/Pages/Modules/MbgApi/MbgModule.vue`
**Backend:** `app/Modules/MbgApi/`

Menampilkan data dari platform Go MBG via proxy API. Semua data read-only, ditampilkan sebagai tabel dinamis (kolom otomatis dari key response).

### API Endpoints (semua GET, proxy ke MBG API eksternal)
| Endpoint | Tab | Keterangan |
|---|---|---|
| `/mbg/dashboard/overview` | Overview | Statistik ringkasan platform |
| `/mbg/users` | Users | Daftar pengguna platform MBG |
| `/mbg/organizations` | Organisasi | Daftar organisasi |
| `/mbg/subscriptions` | Langganan | Data langganan aktif |
| `/mbg/plans` | Paket | Daftar paket berlangganan |
| `/mbg/roles` | Roles | Role di platform MBG |
| `/mbg/vendors` | Vendor | Daftar vendor |
| `/mbg/sales` | Sales | Data penjualan platform |
| `/mbg/foundations` | Yayasan | Daftar yayasan |
| `/mbg/audit-logs` | Audit Log | Log aktivitas platform |
| `/mbg/system-settings` | System Settings | Konfigurasi platform |
| `/mbg/notifications` | Notifikasi | Notifikasi platform |
| `/mbg/kitchen-equipment` | Peralatan Dapur | Data peralatan dapur |
| `/mbg/marketplace-settings` | Marketplace Settings | Setting marketplace |
| `/mbg/sales-payrolls` | Penggajian Sales | Payroll tim sales MBG |

### UI Pattern
- Response array → auto-render sebagai tabel (kolom = key object pertama)
- Response object → render sebagai grid card (key: value)
- Response array kosong → "Tidak ada data"
- Error → pesan error merah dari response API

---

## 10. Employee

**File:** `resources/js/Pages/Modules/Employee/EmployeeList.vue`
**Backend:** `app/Modules/Employee/`

### API Endpoints
| Method | Endpoint | Keterangan |
|---|---|---|
| GET | `/employees` | Daftar karyawan |
| GET | `/employees/{id}` | Detail karyawan (termasuk dokumen) |
| POST | `/employees` | Tambah karyawan |
| PUT | `/employees/{id}` | Update karyawan |
| DELETE | `/employees/{id}` | Hapus karyawan |
| GET | `/employees/departments` | Daftar departemen |
| GET | `/employees/positions` | Daftar jabatan |

### Form Fields
| Field | Required | Keterangan |
|---|---|---|
| employee_id | Auto | Format `MBG-XXXX`, di-generate random, tidak bisa diedit |
| first_name | Ya | |
| last_name | Ya | |
| email | Ya | |
| phone | Tidak | |
| department_id | Ya | Dropdown dari `/employees/departments` |
| position_id | Ya | Dropdown dari `/employees/positions` |
| hire_date | Ya | Default hari ini |
| base_salary | Ya | Angka |
| status | Ya | `ACTIVE` atau `INACTIVE` |
| documents | Tidak | Upload multiple file (PDF/JPG/PNG, max 2MB) |

### Fitur
- **List view:** tabel (desktop) + card (mobile), avatar inisial
- **Detail modal:** profil karyawan dua panel — kiri profil + info utama, kanan info lengkap + daftar dokumen
- **Dokumen:** bisa upload saat create/edit, ditampilkan di detail modal (dokumen_type + link buka)
- **Status badge:** hijau = ACTIVE, abu = INACTIVE

---

## 11. Absensi

**File:** `resources/js/Pages/Modules/Attendance/AttendanceModule.vue`
**Backend:** `app/Modules/Attendance/`

### API Endpoints
| Method | Endpoint | Keterangan |
|---|---|---|
| GET | `/attendance?date=` | Semua karyawan + status absensi untuk tanggal tertentu |
| POST | `/attendance` | Simpan absensi bulk (satu tanggal, semua karyawan) |

### Cara Kerja
- Pilih tanggal → load semua karyawan aktif + status absensi mereka (dari relasi `attendances`)
- Jika belum ada record untuk hari itu, default status: `PRESENT`, jam masuk: `08:00`, jam pulang: `17:00`
- Edit status, jam masuk, jam pulang, dan catatan langsung di tabel/card
- Klik **Simpan Absensi** → kirim semua sekaligus (bulk save)
- Input jam masuk/pulang di-disable jika status bukan `PRESENT`

### Status Absensi
| Value | Label | Warna |
|---|---|---|
| `PRESENT` | HADIR | Hijau |
| `ABSENT` | ALPA | Merah |
| `SICK` | SAKIT | Biru |
| `LEAVE` | IZIN | Amber |

### Form Fields (per karyawan)
| Field | Keterangan |
|---|---|
| employee_id | ID karyawan (FK) |
| status | Enum: PRESENT, ABSENT, SICK, LEAVE |
| check_in | Jam masuk (format HH:mm) |
| check_out | Jam pulang (format HH:mm) |
| notes | Catatan |

---

## 12. Payroll

**File:** `resources/js/Pages/Modules/Payroll/PayrollModule.vue`
**Backend:** `app/Modules/Payroll/`

### API Endpoints
| Method | Endpoint | Keterangan |
|---|---|---|
| GET | `/payroll?month=&year=` | Daftar payroll periode tertentu |
| POST | `/payroll/generate` | Generate payroll bulanan |
| PATCH | `/payroll/{id}/mark-paid` | Mark sebagai sudah dibayar |

### Cara Kerja
- Pilih bulan + tahun → load data payroll
- Klik **Generate Payroll** → sistem hitung gaji berdasarkan absensi bulan tersebut
- Generate menghasilkan: `base_salary`, `deduction` (potongan absensi), `net_salary`
- Status: `PENDING` (belum bayar) atau `PAID` (sudah bayar)
- Tombol **Bayar** hanya muncul kalau status `PENDING`
- Tombol **Print** (ikon printer) untuk cetak slip gaji

### Summary Cards
| Card | Keterangan |
|---|---|
| Total Pengeluaran Gaji | Sum `net_salary` semua karyawan periode ini |
| Status Pembayaran | PAID / total karyawan |
| Potongan Absensi | Sum `deduction` semua karyawan |

### Kalkulasi Payroll
```
net_salary = base_salary - deduction
deduction  = dihitung dari jumlah hari ABSENT/SICK/LEAVE (logika di backend PayrollService)
```

---

## 13. Settings

**File:** `resources/js/Pages/Modules/Settings/SettingsModule.vue`
**Backend:** `app/Modules/Auth/`, `app/Modules/Settings/BankAccountController.php`

### Tabs
| Tab | Akses | Isi |
|---|---|---|
| Manajemen User | Semua login | List user, approval, hapus |
| Hak Akses Admin | Super Admin only | Permission matrix |
| Rekening Bank | Super Admin only | CRUD rekening untuk invoice |
| Log Aktivitas | Super Admin only | 100 aktivitas terbaru |

### User Management
- Status: `pending`, `active`, `rejected`
- Approve / Reject user pending → kirim email notifikasi ke user
- Hapus user active (non-super-admin)
- Tidak bisa hapus super admin (dilindungi backend)

### Permission Matrix
- Baris: fitur (`inventory`, `sales`, `expenses`, `incomes`, `rab`, `employees`, `attendance`, `payroll`, `mbg`, `surat_jalan`)
- Kolom: aksi (`can_view`, `can_create`, `can_edit`, `can_delete`, `can_adjust`)
- Tombol "Semua" per kolom untuk bulk select/deselect
- `can_adjust` hanya relevan untuk `inventory`

### Rekening Bank
Data master rekening yang tampil di PDF invoice.

- Tambah / edit / hapus rekening
- Field: nama pemilik, nama bank, nomor rekening, is_default
- Satu rekening bisa ditandai **default** — otomatis terpilih saat buat invoice baru
- Jika set rekening baru sebagai default, rekening lama otomatis di-unset
- **Backend:** `app/Modules/Settings/BankAccountController.php`
- **Model:** `App\Models\BankAccount` — tabel `bank_accounts`

### Log Aktivitas
- 100 aktivitas terbaru dari tabel `activity_logs`
- Kolom: waktu, user, aksi (badge warna), deskripsi
- Badge: login (biru), delete/reject (merah), approved (hijau)

### API Endpoints
| Method | Endpoint | Akses | Keterangan |
|---|---|---|---|
| GET | `/auth/users` | Auth | Semua user |
| POST | `/auth/users/{id}/approve` | Auth | Setujui user |
| POST | `/auth/users/{id}/reject` | Auth | Tolak user |
| DELETE | `/auth/users/{id}` | Auth | Hapus user |
| GET | `/auth/users/{id}/permissions` | Super Admin | Baca permission |
| PUT | `/auth/users/{id}/permissions` | Super Admin | Update permission |
| GET | `/bank-accounts` | Auth | Daftar rekening bank |
| POST | `/bank-accounts` | Super Admin | Tambah rekening |
| PUT | `/bank-accounts/{id}` | Super Admin | Edit rekening |
| DELETE | `/bank-accounts/{id}` | Super Admin | Hapus rekening |
| GET | `/activity-logs` | Super Admin | 100 log terbaru |
