# Planning: Sistem Invoice Multi-Company (Laravel + Vue)

> Sistem invoice yang mendukung 10+ perusahaan dengan styling berbeda-beda, siap diintegrasikan ke stack Laravel + Vue 3.

---

## 1. Keputusan Arsitektur Inti

Kunci untuk "styling beda-beda per perusahaan" adalah **memisahkan layout dari branding**:

- **Layout variants** → 3–5 desain dasar (`modern`, `classic`, `minimal`, `bold`, `colorful`). Komponen/template ini ditulis sekali saja.
- **Branding tokens** → data per perusahaan (logo, warna, font, info bank, NPWP, dll) yang disuntik ke layout via CSS variables.

**Hasilnya:** 10+ perusahaan = beberapa layout × token masing-masing. Visual tetap beda jauh, tapi maintenance ringan. Membuat 10 file template manual akan menyulitkan saat revisi format.

---

## 2. Stack PDF

Untuk styling modern (flexbox/grid/Tailwind):

| Library | Engine | Kelebihan | Kekurangan |
|---------|--------|-----------|------------|
| **`spatie/laravel-pdf`** (utama) | Browsershot + Chromium | Fidelity terbaik, CSS modern full support | Perlu install headless Chrome |
| `barryvdh/laravel-dompdf` (fallback) | DomPDF | Ringan, tanpa Chromium | CSS terbatas (no flexbox proper) |

**Rekomendasi:** pakai Spatie kalau server mendukung headless Chrome.

---

## 3. Struktur Database

### `companies`
```
id, name, legal_name, logo_path, signature_path,
npwp, address, phone, email,
bank_name, bank_account, bank_holder,
brand_primary (#hex), brand_secondary (#hex),
font_family, template_variant (enum),
invoice_prefix, invoice_counter
```

### `customers`
```
id, name, company_address, phone, email, npwp
```

### `invoices`
```
id, company_id, customer_id, invoice_number,
issue_date, due_date, status (draft/sent/paid/overdue),
subtotal, discount, tax_percent, tax_amount, total,
currency, notes
```

### `invoice_items`
```
id, invoice_id, description, qty, unit_price, line_total
```

> **Catatan penting:** Nomor invoice per perusahaan punya sequence sendiri (mis. `INV/HAOHAO/2026/001`). Karena itu `invoice_prefix` + `invoice_counter` disimpan di tabel `companies`.

---

## 4. Strategi Styling Multi-Company

Branding masuk ke template lewat CSS variables:

```blade
<style>
  :root {
    --brand-primary: {{ $company->brand_primary }};
    --brand-secondary: {{ $company->brand_secondary }};
    --font-main: '{{ $company->font_family }}';
  }
</style>

@include("invoices.layouts.{$company->template_variant}", ['invoice' => $invoice])
```

### Folder layout
```
resources/views/invoices/layouts/
  modern.blade.php
  classic.blade.php
  minimal.blade.php
  bold.blade.php
```

Tiap layout pakai `var(--brand-primary)` dst — satu layout bisa dipakai banyak perusahaan dengan warna berbeda.

---

## 5. Struktur Backend Laravel

```
app/
  Models/
    Company.php
    Customer.php
    Invoice.php
    InvoiceItem.php
  Services/
    InvoiceNumberService.php   → generate nomor per company
    InvoicePdfService.php       → render PDF sesuai company->template_variant
  Http/Controllers/Api/
    CompanyController.php
    InvoiceController.php
    InvoicePdfController.php     → download/stream PDF
```

### Endpoint utama
| Method | Endpoint | Fungsi |
|--------|----------|--------|
| GET | `/api/companies` | Dropdown di Vue |
| POST | `/api/invoices` | Simpan invoice |
| GET | `/api/invoices/{id}/pdf` | Generate & download PDF |
| GET | `/api/invoices/{id}/preview` | Return HTML untuk live preview |

---

## 6. Struktur Frontend Vue

```
components/invoices/
  InvoiceForm.vue        → pilih company, customer, tambah items
  InvoicePreview.vue     → live preview (iframe ke endpoint preview)
  InvoiceItemRow.vue     → baris item dinamis
  CompanySelector.vue    → ganti company → branding preview ikut berubah
views/
  InvoiceIndex.vue       → list + filter by company/status
  InvoiceCreate.vue
```

Saat user ganti company di form, preview langsung ganti warna/logo karena ambil token dari company terpilih.

---

## 7. Roadmap Urutan Build

1. **Migrasi + model + seeder** (10 perusahaan dummy termasuk Hao Hao)
2. **InvoiceNumberService** (sequence per company)
3. **2–3 layout Blade** dulu (`modern`, `classic`, `minimal`)
4. **InvoicePdfService** + endpoint PDF/preview
5. **API CRUD** invoice
6. **Vue form + live preview**
7. **List + filter + status**

---

## 8. Catatan Implementasi Tambahan

- **Perhitungan total:** lakukan di backend (subtotal → diskon → pajak → total) agar konsisten, jangan andalkan kalkulasi frontend.
- **Logo & tanda tangan:** simpan path di storage, akses via `Storage::url()`. Untuk PDF Browsershot, pastikan pakai path absolut atau base64 agar gambar ter-render.
- **Pajak Indonesia:** sediakan field `tax_percent` (mis. PPN 11%) yang bisa diatur per invoice, plus opsi NPWP perusahaan & customer.
- **Status overdue:** bisa di-handle via scheduled job yang cek `due_date < today` dan status masih `sent`.
- **Font kustom:** kalau pakai font per perusahaan, daftarkan `@font-face` di template atau import Google Fonts sesuai `font_family`.
