# UI Patterns & Frontend Conventions

Panduan pola UI yang dipakai konsisten di seluruh project.

---

## Layout

### MainLayout.vue
- **Sidebar persisten** di desktop (lebar 72 = 288px), overlay di mobile
- **Topbar** sticky, height h-20, backdrop blur
- **Command Palette** (Ctrl+K) — search semua halaman, navigasi dengan ↑↓ Enter Esc
- **NotificationBell** — hanya muncul untuk `isSuperAdmin`

### Navigasi
- Navigasi via `?tab=` query param, tanpa full page reload
- `window.dispatchEvent(new CustomEvent('tab-navigate', { detail: id }))` untuk navigate dari dalam komponen
- `window.dispatchEvent(new CustomEvent('sales-updated'))` setelah mutasi data sales → dashboard refresh

### Sidebar Menu Structure
- Item dengan children → tree (expand/collapse)
- Item tanpa children → flat button
- State aktif: background primary, teks putih, shadow
- State hover: background slate-50, teks primary

---

## Komponen & Class

### Cards
```html
<div class="premium-card bg-white">...</div>
```
`premium-card` adalah class Tailwind custom (didefinisikan di CSS global).

### Buttons
```html
<button class="btn-primary">...</button>   <!-- primary action -->
<button class="btn-secondary">...</button> <!-- secondary / cancel -->
```

### Loading State
```html
<LoadingState />  <!-- shared component -->
```

### Notifikasi
Menggunakan custom toast (tidak pakai library external). Dipanggil lewat:
```ts
// Pattern yang dipakai di tiap modul:
const toast = ref<{ show: boolean; message: string; type: 'success' | 'error' }>
```

---

## Pola CRUD

Hampir semua modul menggunakan pola yang sama:

1. **List** — tabel (desktop) + card (mobile), dengan search/filter
2. **Create/Edit** — modal overlay (`v-if="modalOpen"`)
3. **Delete** — `confirm()` native browser
4. **Loading** — disable tombol + spinner saat request berlangsung

### Form Validation (Frontend)
- Field required: cek `.trim()` atau `> 0` sebelum submit
- Nominal uang: `inputmode="numeric"` + format ribuan di display
- File upload: cek type + size sebelum kirim (max 5MB)

---

## Mobile Responsiveness
- `block md:hidden` — tampilkan card view di mobile
- `hidden md:block` — tampilkan tabel di desktop
- Sidebar: fixed overlay dengan backdrop di mobile, static di desktop
- Grid: `grid-cols-1 md:grid-cols-2` atau `md:grid-cols-4` untuk cards

---

## Permission-based UI
```vue
<!-- Sembunyikan tombol kalau tidak ada akses -->
<button v-if="can('inventory', 'create')">Tambah Item</button>
<button v-if="can('sales', 'edit')">Edit Invoice</button>
```

Super admin selalu bisa akses semua (can() return true).

---

## Format Tampilan
- **Rupiah:** `'Rp ' + val.toLocaleString('id-ID')`
- **Tanggal:** format Indonesia (`id-ID` locale)
- **Stok merah:** `quantity <= 5`
- **Status badge:** warna-warna dengan background subtle (amber, emerald, red, slate)

---

## File Upload
Upload selalu pakai `FormData` dengan `Content-Type: multipart/form-data`.

Pattern:
```ts
const form = new FormData()
form.append('receipt_file', file)
await api.uploadReceipt(id, form)
```

File yang diizinkan: `image/jpeg`, `image/png`, `image/webp`, `application/pdf`
Max size: **5MB**

---

## Export

### PDF
Menggunakan `jsPDF` + `jspdf-autotable`.
- Header perusahaan
- Tabel dengan autoTable
- Footer dengan tanda tangan
- Digunakan di: Sales (invoice), Expense (laporan), Income (laporan)

### CSV
Download via `Blob` + `URL.createObjectURL`.
- Sales: template item
- Expense: import template
- Income: import template
- Budget: export realisasi → compatible format import Expense

---

## Realtime
- **Stock polling:** setiap 8 detik di InventoryModule saat tab visible
- **Notification polling:** setiap 30 detik di NotificationBell
- **Visibility refresh:** `document.addEventListener('visibilitychange', ...)` di Dashboard
