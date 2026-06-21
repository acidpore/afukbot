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

### `budget_periods`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| name | string | Nama periode |
| start_date | date | |
| end_date | date | |

### `budget_period_settings`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| period_id | FK budget_periods | Periode aktif saat ini |

### `budget_categories`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| name | string | |

### `budget_items`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| category_id | FK budget_categories | |
| name | string | |
| unit_cost | decimal | |
| rate | enum | `harian`, `mingguan`, `dua_mingguan`, `bulanan`, `custom` |
| multiplier | integer | |
| is_active | boolean | |

### `expense_transactions`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| budget_item_id | FK budget_items | |
| date | date | |
| amount | decimal | |
| notes | text nullable | |
| receipt_path | string nullable | |

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
