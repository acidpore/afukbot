# Skema Database — MBG Admin System

Dokumentasi tabel database berdasarkan migrations dan models.

---

## Tabel Utama

### `users`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| name | string | Nama user |
| email | string unique | Email login |
| password | string | Bcrypt hash |
| role | enum | `super_admin`, `admin` (default: `admin`) |
| status | enum | `pending`, `active`, `rejected` |
| created_at / updated_at | timestamp | |

### `user_permissions`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| user_id | FK users | |
| feature | string | Nama fitur |
| can_view | boolean | Default true |
| can_create | boolean | Default false |
| can_edit | boolean | Default false |
| can_delete | boolean | Default false |
| can_adjust | boolean | Default false (khusus inventory) |

Fitur yang dikontrol: `inventory`, `sales`, `expenses`, `incomes`, `rab`, `employees`, `attendance`, `payroll`, `mbg`, `surat_jalan`

---

## Inventory

### `categories`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| name | string | Nama kategori |

### `items`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| name | string | Nama barang |
| description | text nullable | |
| category_id | FK categories | |
| quantity | integer | Stok saat ini |
| unit | string | Satuan (pcs, kg, dll) |
| harga_jual | decimal nullable | Harga jual per unit |
| location | string | `ruko` atau `margomulyo` |
| aliases | json nullable | Alias nama untuk pencarian |

### `stock_transactions`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| item_id | FK items | |
| type | enum | `IN`, `OUT` |
| quantity | integer | Jumlah delta |
| stock_before | integer | Stok sebelum transaksi |
| stock_after | integer | Stok setelah transaksi |
| date | date | Tanggal transaksi |
| notes | text nullable | Catatan |
| recorded_by_id | FK users nullable | |

### `stock_calibrations`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| calibrated_by | FK users | |
| calibrated_at | date | Tanggal kalibrasi |
| notes | text nullable | |
| total_items | integer | Total item yang dicek |
| total_adjusted | integer | Total item yang berubah |

### `stock_calibration_items`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| calibration_id | FK stock_calibrations | |
| item_id | FK items | |
| item_name | string | Snapshot nama saat kalibrasi |
| qty_system | integer | Stok sistem sebelum kalibrasi |
| qty_physical | integer | Stok fisik hasil hitung |
| delta | integer | Selisih (positif=tambah, negatif=kurang) |

---

## Penjualan

### `sales`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| invoice_number | string unique | Auto-generated |
| recipient_name | string | Nama penerima |
| recipient_address | text nullable | |
| invoice_date | date | |
| notes | text nullable | Diparse untuk deteksi pembayaran |
| sender_name | string nullable | |
| sender_address | text nullable | |
| bank_account_name | string nullable | |
| bank_name | string nullable | |
| bank_account_number | string nullable | |
| grand_total | decimal | Total invoice |
| paid_amount | decimal | Jumlah yang sudah dibayar |
| status | enum | `rencana`, `sudah_dikirim` |
| shipped_at | timestamp nullable | |
| attachment_path | string nullable | Path file PDF lampiran |

### `sale_items`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| sale_id | FK sales | |
| item_name | string | Nama barang |
| description | text nullable | |
| qty | integer | Jumlah |
| unit_price | decimal | Harga satuan |
| total_price | decimal | qty × unit_price |
| inventory_item_ids | json nullable | Link ke items (untuk potong stok) |

---

## Surat Jalan

### `surat_jalans`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| nomor_sj | string unique | Auto-generated |
| sale_id | FK sales | |
| tanggal_kirim | date | |
| catatan | text nullable | |
| created_by | FK users | |

### `surat_jalan_items`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| surat_jalan_id | FK surat_jalans | |
| sale_item_id | FK sale_items | |
| qty_kirim | integer | Jumlah yang dikirim |

---

## Keuangan

### `expenses`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| expense_date | date | |
| category | string | Kategori pengeluaran |
| description | text | |
| amount | decimal | Nominal |
| paid_by | string nullable | |
| notes | text nullable | |
| receipt_path | string nullable | Path file struk |
| recorded_by_id | FK users nullable | |
| expense_transaction_id | FK expense_transactions nullable | Link ke RAB |

### `incomes`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| income_date | date | |
| source | string | Sumber pemasukan |
| description | text | |
| amount | decimal | Nominal |
| notes | text nullable | |
| receipt_path | string nullable | |
| recorded_by_id | FK users nullable | |

### `manual_piutangs`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| name | string | Nama/label piutang |
| amount | decimal | Nominal |
| notes | text nullable | |
| created_by_id | FK users nullable | |

---

## RAB (Budget)

> **RAB per periode (multi-periode).** Sumber kebenaran periode = `rab_periods`. Satu baris `is_active = true` adalah periode yang sedang diedit. "Buat RAB Baru" meng-clone seluruh kategori + item periode aktif ke periode baru lalu set aktif — sehingga RAB & realisasi periode lama tidak berubah saat periode aktif diedit. Periode lama bersifat read-only di UI.

### `rab_periods` (master periode — BARU)
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| name | string | Nama periode (mis. "Periode Juli 2026") |
| start_date | date | |
| end_date | date | |
| is_active | boolean | Hanya satu yang true = periode aktif/editable |

### `budget_period_setting` (LEGACY)
Single-row, sisa desain lama. **Tidak lagi dipakai** — rentang periode aktif sekarang dibaca/ditulis ke `rab_periods` (baris `is_active`). Endpoint `GET/PUT /budget/period-setting` kini mengoperasikan periode aktif di `rab_periods`.

### `budget_categories`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| name | string | |
| total_budget | bigint | Default 0 |
| period_id | FK rab_periods nullable | Scope kategori ke satu periode (nullOnDelete) |

### `budget_items`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| category_id | FK budget_categories (cascade) | |
| name | string | |
| unit_cost | bigint | |
| rate | enum | `harian`, `mingguan`, `dua_mingguan`, `bulanan`, `custom` |
| multiplier | integer | |
| total_monthly_budget | bigint generated | `unit_cost * multiplier` (kolom storedAs) |
| is_active | boolean | getCategories hanya menampilkan item `is_active = true` |
| deleted_at | timestamp nullable | **Soft delete** — hapus item TIDAK menghapus realisasinya |

> Hapus item = soft delete, jadi FK cascade ke `expense_transactions` tidak ter-trigger (realisasi historis aman). Relasi `ExpenseTransaction::budgetItem()` pakai `withTrashed()` agar nama item tetap tampil di Realisasi setelah item di-soft-delete.

### `expense_transactions` (Realisasi)
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| budget_item_id | FK budget_items (onDelete cascade) | |
| transaction_date | date | |
| amount | bigint | |
| note | string nullable | |
| receipt_path | string nullable | |
| deleted_at | timestamp nullable | Soft delete |

> Tiap transaksi di-mirror ke tabel `expenses` (kolom `expense_transaction_id`) agar tampil di halaman Pengeluaran. Di halaman Pengeluaran ada badge "RAB" untuk baris yang `expense_transaction_id`-nya terisi.

### `budget_periods` (snapshot bulanan)
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| month | string(7) | `YYYY-MM` |
| category_id | FK budget_categories | |
| planned_amount | bigint | |
| actual_amount | bigint | |
| variance | bigint generated | `planned_amount - actual_amount` |
| status | enum | `on_track`, `warning`, `over_budget` |
| | | unique(`month`, `category_id`) |

### `budget_proposals` (Pengajuan — BARU)
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| period_id | FK rab_periods (cascade) | |
| name | string | Barang yang mau dibeli (mis. "TV") |
| brand | string nullable | Merk diusulkan |
| price | bigint | Harga diusulkan |
| note | text nullable | Alasan/keterangan |
| analysis | json nullable | Array alternatif: `[{brand, price, note}]` |
| status | enum | `pending`, `bought` |
| bought_at | date nullable | Diisi saat ditandai terbeli |

---

## Invoice Eksternal (Invoicing) — MODUL BARU

> Modul invoice multi-perusahaan **terpisah** dari modul Sales. Tujuan: bikin invoice "pembelian dari luar" untuk keperluan audit. Wajib PPN 11% dengan dua mode harga. Tabel customer sengaja dinamai `invoice_customers` agar tidak bentrok.

### `companies` (penerbit invoice)
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| name / legal_name | string | |
| logo_path / signature_path | string nullable | File di disk `public`; accessor `logo_url`/`signature_url` |
| npwp, address, phone, email | string/text nullable | |
| bank_name, bank_account, bank_holder | string nullable | |
| brand_primary, brand_secondary | string(9) | Warna hex untuk styling layout |
| font_family | string | Default `Inter` |
| template_variant | enum | `modern`, `classic`, `minimal`, `bold` |
| invoice_prefix | string | Prefix nomor (mis. `INV/HAOHAO`) |
| invoice_counter | unsigned int | Sequence per-company, increment atomik |

### `invoice_customers`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| name | string | |
| company_address, phone, email, npwp | nullable | Dibuat inline saat simpan invoice |

### `invoices`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| company_id | FK companies (cascade) | |
| customer_id | FK invoice_customers nullable | |
| invoice_number | string unique | `{prefix}/{tahun}/{urut 3 digit}` |
| issue_date / due_date | date / nullable | |
| status | enum | `draft`, `sent`, `paid`, `overdue` |
| price_mode | enum | `exclusive` (PPN ditambah) / `inclusive` (harga sudah termasuk PPN) |
| subtotal | bigint | DPP (dasar pengenaan pajak) |
| discount | bigint | |
| tax_percent | decimal(5,2) | Default 11 |
| tax_amount | bigint | |
| total | bigint | |
| currency | string | Default `IDR` |
| notes | text nullable | |

### `invoice_items`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| invoice_id | FK invoices (cascade) | |
| description | string | |
| qty | decimal(12,2) | |
| unit_price | bigint | |
| line_total | bigint | `qty * unit_price` |

**Reverse-tax (`price_mode = inclusive`):** total = jumlah line item − diskon; `subtotal = round(total / (1 + tax%))`; `tax_amount = total − subtotal`. Contoh: 1 item 350.000.000 → DPP 315.315.315 + PPN 34.684.685 = total pas 350.000.000.

---

## Mutasi Rekening PT

### `bank_accounts`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| account_name | string | Nama pemilik rekening |
| bank_name | string | Nama bank |
| account_number | string | Nomor rekening |
| is_default | boolean | Rekening default untuk invoice |
| created_at / updated_at | timestamp | |

### `account_mutations`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| bank_account_id | FK bank_accounts cascade | |
| date | date | Tanggal transaksi |
| type | enum | `in`, `out`, `opening` |
| amount | unsignedBigInteger | Nominal (rupiah, tanpa desimal) |
| description | string nullable | Keterangan transaksi |
| category | string nullable | Kategori bebas (autocomplete dari history) |
| costs | json nullable | Biaya variabel `[{label, amount}]` — hanya untuk type `in` |
| created_at / updated_at | timestamp | |

**Catatan penting:**
- `type = opening` → saldo awal rekening, hanya satu per `bank_account_id`
- `costs` → biaya yang melekat pada pemasukan (biaya kirim, kuli, dll), mengurangi laba bersih
- Running balance dihitung on-the-fly di controller, tidak disimpan di DB
- Saldo awal periode = opening + semua mutasi sebelum bulan yang dipilih

---

## Karyawan & HR

### `employees`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| employee_id | string unique | ID karyawan |
| first_name | string | |
| last_name | string | |
| email | string | |
| phone | string | |
| position_id | FK positions | |
| department_id | FK departments | |
| hire_date | date | |
| status | enum | `ACTIVE`, `INACTIVE` |
| base_salary | decimal | Gaji pokok |

### `departments`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| name | string | |

### `positions`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| name | string | |

### `attendances`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| employee_id | FK employees | |
| date | date | |
| status | enum | `hadir`, `izin`, `sakit`, `alpha` |
| notes | text nullable | |

### `payrolls`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| employee_id | FK employees | |
| period | string | Bulan/tahun periode |
| base_salary | decimal | Snapshot saat generate |
| total_hadir | integer | |
| deductions | decimal | |
| net_salary | decimal | |
| generated_at | timestamp | |

---

## Notifikasi & Sistem

### `push_subscriptions`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| user_id | FK users | |
| endpoint | text | URL push endpoint device |
| public_key | text | |
| auth_token | text | |

### `admin_notifications`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| title | string | |
| message | text | |
| url | string nullable | Link navigasi saat diklik |
| read_at | timestamp nullable | Null = belum dibaca |
| created_at | timestamp | |

### `activity_logs`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| user_id | FK users nullable | |
| action | string | Aksi yang dilakukan |
| model_type | string nullable | |
| model_id | bigint nullable | |
| description | text nullable | |
| created_at | timestamp | |

### `login_attempts`
Tabel rate limiting untuk proteksi brute force login.

---

## Catatan Penting

- **Database:** MySQL (production) / SQLite (testing)
- **Sumber kebenaran:** File migrations di `database/migrations/`
- **Stok tidak boleh minus:** Validasi di backend sebelum OUT transaction
- **Invoice piutang:** Dihitung dari `grand_total - paid_amount` where `paid_amount > 0 AND paid_amount < grand_total` or status `sudah_dikirim` with remaining balance
