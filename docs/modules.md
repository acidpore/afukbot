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
| POST | `/expenses/scan-receipt` | Scan struk via Gemini Vision AI |
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

### Scan Struk (AI)
- Tombol **Scan Struk** di header (ungu, sebelah Import CSV)
- Klik → file picker terbuka (kamera di mobile via `capture="environment"`)
- Gambar dikirim ke `POST /expenses/scan-receipt`
- Backend kirim ke **Gemini 1.5 Flash** (Google AI) → parse nama item + harga
- Modal review muncul: tiap item bisa diedit nama, nominal, dan kategori
- Kategori di-guess otomatis berdasarkan keyword nama item (beras/telur → Belanja, makan/kopi → Makan, dll)
- Jika kategori "Lainnya" → muncul input custom kategori
- Tanggal struk bisa diubah sebelum simpan
- Klik Simpan → semua item masuk sekaligus sebagai expense entries terpisah
- **Config:** `GEMINI_API_KEY` di `.env`, entry di `config/services.php` → `services.gemini.key`
- **Backend:** `app/Modules/Expense/ReceiptScanController.php`

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
| Pengajuan | Rencana barang yang mau dibeli + analisa perbandingan |

### RAB Multi-Periode
- Baris chip periode di atas modul (urut terbaru → lama, dari `rab_periods`). Tombol **Buat RAB Baru** = clone kategori+item periode aktif ke periode baru, set aktif.
- Periode aktif = `is_active` di `rab_periods` → satu-satunya yang editable. Periode lama **read-only** (tombol edit/hapus disembunyikan via `canEdit`).
- Semua query (`getCategories`, `getSummary`, `getTransactions`, `getProposals`) menerima `?period_id=`; default = periode aktif.
- **Hapus item = soft delete** → realisasi periode tidak hilang. Hapus periode = wipe RAB+realisasi periode itu (dengan konfirmasi; periode terakhir tidak bisa dihapus).

### Tab Pengajuan
- CRUD `budget_proposals` (scope per periode). Tiap pengajuan: nama barang, merk + harga diusulkan, catatan, dan list **analisa** alternatif (`analysis` JSON: merk/harga/catatan).
- Status Pending / Terbeli (toggle, tidak auto-bikin realisasi). Pending tampil di atas, terbeli di bawah.

### Endpoint tambahan (periode & pengajuan)
| Method | Endpoint | Keterangan |
|---|---|---|
| GET | `/budget/periods` | List periode (terbaru dulu) |
| POST | `/budget/periods` | Buat periode baru (clone dari aktif) |
| DELETE | `/budget/periods/{id}` | Hapus periode |
| GET/POST | `/budget/proposals` | List / buat pengajuan |
| PUT/DELETE | `/budget/proposals/{id}` | Update / hapus pengajuan |

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

---

## 14. Mutasi Rekening

**File:** `resources/js/Pages/Modules/MutasiRekening/MutasiRekeningModule.vue`
**Backend:** `app/Modules/MutasiRekening/AccountMutationController.php`
**Model:** `App\Models\AccountMutation`, `App\Models\BankAccount`
**Tab ID:** `mutasi-rekening`

### Fitur Utama
- Pilih rekening PT (dari `bank_accounts`), filter bulan/tahun
- Pilihan rekening terakhir disimpan di **localStorage** (`mutasi_selected_account`)
- 3 summary cards: Saldo Rekening (total keseluruhan), Masuk bulan ini, Keluar bulan ini
- Tabel mutasi dengan running balance per transaksi
- **Dua tab:** Mutasi (riwayat) & Konsultan Pajak (analisis)

### Saldo Awal
- Disimpan sebagai baris `type = 'opening'` di tabel `account_mutations`
- Set via panel "Saldo Awal" di UI atau `PUT /account-mutations/opening`
- Saldo awal periode = opening + semua mutasi sebelum bulan yang dipilih

### Biaya Variabel
- Setiap transaksi **masuk** bisa ditambahkan biaya variabel (kirim, kuli, dll)
- Disimpan sebagai JSON `costs: [{label, amount}]` di kolom `costs`
- Mengurangi **laba bersih** — berpengaruh pada estimasi PPh Badan (jika > 4,8 M)
- Tidak mengurangi omzet (PPh Final 0,5% tetap dari total masuk)

### Cashflow Alert Card
Muncul otomatis berdasarkan total masuk **tahun berjalan** vs batas Rp 4,8 M:
| Status | Kondisi | Warna |
|---|---|---|
| Aman | < Rp 3,84 M (80%) | Hijau |
| Perlu Perhatian | Rp 3,84 M – 4,8 M | Kuning |
| Terlampaui | ≥ Rp 4,8 M | Merah |

Card juga menampilkan: laba bersih, biaya variabel, estimasi PPh Final 0,5% atau PPh Badan 22%.

### Tab Konsultan Pajak
- **Posisi pajak** — PPh Final (< 4,8 M) atau PPh Badan (≥ 4,8 M)
- **Proyeksi akhir tahun** — rata-rata masuk/bulan, estimasi omzet akhir tahun, peringatan berapa bulan lagi kena batas Rp 4,8 M
- **Angsuran PPh 25** — estimasi cicilan bulanan wajib (PPh Badan / 12), hanya muncul saat `over_limit`
- **Alur pembayaran pajak** — step-by-step interaktif: PPh Final (4 langkah) atau PPh Badan (PPh 25 → SPT Tahunan → PPh 29/28)
- **Rincian biaya variabel** — semua label biaya dirangkum dengan total tahunan
- **Klasifikasi pengeluaran** — auto-tag kategori:
  - `deductible`: gaji, sewa, operasional, supplier, transport, dll
  - `non-deductible`: tarik tunai, pribadi, prive
  - `review`: belum terkategori / tidak dikenali
  - Tombol **Klasifikasi** inline per baris `review` — rename kategori langsung dari tab pajak (bulk update semua transaksi kategori tsb)
- **Rekomendasi kontekstual** — tips spesifik berdasarkan kondisi data aktual
- **Timeline Omzet** — tabel masuk/keluar/akumulatif per bulan + badge Aman/Mendekati/Melampaui vs batas Rp 4,8 M. Mobile: card view per bulan
- **Export PDF** — tombol di header tab, generate laporan 1 halaman: ringkasan keuangan + tabel klasifikasi pengeluaran
- **AI Konsultan Pajak** — floating bubble `pi-sparkles` pojok kanan bawah (hanya muncul di tab pajak). Chat dengan LLM (Groq `llama-3.3-70b-versatile`) yang sudah diberi konteks data keuangan real. Support multi-turn, suggested questions, countdown rate limit

### Import Mutasi Bank (Mandiri)
- Tombol **Import** di header → file picker
- Upload CSV export dari Mandiri internet banking
- Backend deteksi format: `Nominal + DB/CR` atau `Debet + Kredit` terpisah
- Handle encoding Windows-1252, tanggal `DD/MM/YYYY`, angka dengan titik pemisah ribuan
- **Modal preview** — user review semua transaksi sebelum konfirmasi
- Duplikat (date + type + amount + description sama) otomatis diskip

### Kategori Autocomplete
- Input kategori di form menggunakan `<datalist>` native
- Opsi diambil dari endpoint `GET /account-mutations/categories` — kategori unik yang pernah dipakai

### Mobile
- Modal form muncul dari bawah layar (bottom sheet)
- FAB `+` fixed di kanan bawah — bisa catat transaksi tanpa scroll
- Card view menggantikan tabel pada layar kecil

### API Endpoints
| Method | Endpoint | Keterangan |
|---|---|---|
| GET | `/account-mutations` | List mutasi bulan + summary + running balance |
| POST | `/account-mutations` | Tambah transaksi |
| PUT | `/account-mutations/{id}` | Edit transaksi |
| DELETE | `/account-mutations/{id}` | Hapus transaksi |
| PUT | `/account-mutations/opening` | Set saldo awal |
| GET | `/account-mutations/categories` | Kategori unik per rekening |
| GET | `/account-mutations/tax-summary` | Analisis pajak tahunan (+ `projected_yearly_in`, `avg_monthly_in`, `months_to_limit`, `angsuran_pph25`, `monthly_breakdown`) |
| POST | `/account-mutations/reclassify` | Bulk update kategori pengeluaran per tahun |
| POST | `/account-mutations/import-preview` | Parse CSV Mandiri → preview rows |
| POST | `/account-mutations/import-commit` | Simpan hasil import ke DB |
| POST | `/tax-consultant/chat` | Chat AI konsultan pajak via Groq (multi-turn, konteks data real) |

---

## 15. Invoice Eksternal (Invoicing)

**File:** `resources/js/Pages/Modules/Invoicing/InvoicingModule.vue`
**Backend:** `app/Modules/Invoicing/`
**Models:** `App\Models\{Company, InvoiceCustomer, Invoice, InvoiceItem}`
**Tab ID:** `invoice-eksternal` (di bawah grup menu "Penjualan")

Modul **terpisah** dari Sales. Untuk membuat invoice pembelian dari luar (audit) dengan PPN 11%, dukungan banyak perusahaan penerbit dengan styling berbeda.

### Konsep
- **Layout vs branding dipisah:** 4 layout Blade (`modern`, `classic`, `minimal`, `bold`) di `resources/views/invoices/layouts/`, branding (logo, warna, bank, NPWP, prefix nomor) disimpan per `companies`. Layout pakai warna hex dari company.
- **PDF: `barryvdh/laravel-dompdf`** (sudah terinstall, tanpa Chromium). Layout dibuat **table-based** (bukan flexbox) agar render benar di dompdf & browser. Logo/ttd di-embed base64 agar muncul di PDF & preview.
- **Nomor invoice:** sequence per-company atomik (`Company::lockForUpdate`), format `{invoice_prefix}/{tahun}/{urut 3 digit}`.
- Hapus company = cascade hapus semua invoice-nya (ada konfirmasi).

### Mode PPN (penting)
- `exclusive`: harga item belum termasuk PPN → PPN ditambah di atas subtotal.
- `inclusive` (**reverse-tax**): total = jumlah item; DPP & PPN dihitung mundur (`DPP = total / 1,11`). Untuk kasus "mau total pas 350jt".
- Total **selalu dihitung di backend** (`InvoiceService::computeTotals`), frontend cuma mirror untuk live preview.

### Fitur UI
- List invoice (filter perusahaan/status), aksi icon-only: PDF, Preview, Edit, Hapus.
- Form: pilih perusahaan, customer inline, item dinamis, mode harga, PPN%, diskon, catatan, live total. **Preview** (draft, sebelum simpan) + edit **nomor invoice** manual saat edit.
- **Kelola Perusahaan:** CRUD company + upload logo & tanda tangan, pilih 2 warna brand & layout.
- Layout `modern` sudah "full": kop ber-aksen, kartu bill-to, tabel item bernomor + zebra, **terbilang** (ejaan rupiah), kotak pembayaran, tanda tangan 2 kolom (kiri penerbit, kanan penerima).

### API Endpoints
| Method | Endpoint | Keterangan |
|---|---|---|
| GET/POST | `/companies` | List / buat perusahaan |
| PUT/DELETE | `/companies/{id}` | Update (multipart, `_method=PUT` untuk file) / hapus |
| GET | `/invoices` | List (filter `company_id`, `status`) |
| POST | `/invoices` | Buat invoice |
| POST | `/invoices/preview` | Render HTML dari data belum disimpan (live preview) |
| GET | `/invoices/{id}` | Detail |
| PUT | `/invoices/{id}` | Update (boleh ubah `invoice_number`) |
| DELETE | `/invoices/{id}` | Hapus |
| GET | `/invoices/{id}/preview` | HTML preview invoice tersimpan |
| GET | `/invoices/{id}/pdf` | Download PDF (dompdf) |

### Seeder
`database/seeders/CompanySeeder.php` → 10 perusahaan dummy (termasuk Hao Hao), rotasi layout & warna. Jalankan: `php artisan db:seed --class=CompanySeeder`.
