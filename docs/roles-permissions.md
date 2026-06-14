# Hirarki Role & Akses

## Role yang Ada

| Role | Keterangan |
|---|---|
| `super_admin` | Akses penuh ke semua fitur, tidak bisa dihapus |
| `admin` | Akses terbatas sesuai izin yang diberikan super admin |

Kolom `role` ada di tabel `users`, default value: `admin`.

## Akun Super Admin

- Email: `mbgstore8080@gmail.com`
- Password: `@dmin12345`
- Dibuat via: `php artisan db:seed --class=SuperAdminSeeder`

## Sistem Permission (untuk Admin)

Tabel: `user_permissions`

| Kolom | Default | Keterangan |
|---|---|---|
| `can_view` | `true` | Bisa melihat halaman |
| `can_create` | `false` | Bisa tambah item baru |
| `can_edit` | `false` | Bisa edit data item |
| `can_delete` | `false` | Bisa hapus item |
| `can_adjust` | `false` | Bisa ubah jumlah stok (khusus inventory) |

Fitur yang dikontrol permission:

```
inventory | sales | expenses | incomes | rab
employees | attendance | payroll | mbg | surat_jalan
```

Permission `can_adjust` hanya relevan untuk fitur `inventory`.

## Cara Kerja di Frontend

File: `resources/js/composables/useAuth.ts`

```ts
const { isSuperAdmin, can } = useAuth()

// Super admin selalu return true
can('inventory', 'edit')    // cek edit inventory
can('inventory', 'adjust')  // cek update stok
can('sales', 'create')      // cek tambah invoice
```

Super admin tidak perlu permission — `can()` selalu `true` untuk super admin.

## Middleware Backend

- `EnsureAuthenticated` — wajib login
- `EnsureSuperAdmin` — wajib role `super_admin`

Routes yang dilindungi `EnsureSuperAdmin`:
- `GET/PUT /auth/users/{id}/permissions`
- `GET /notifications`
- `POST /notifications/{id}/read`
- `POST /notifications/read-all`

## Manajemen User di Settings

Halaman: Settings → Manajemen User

- Semua user bisa dilihat (pending, aktif, ditolak) beserta role-nya
- User status `pending` bisa di-Approve atau Reject
- User status `active` (bukan super admin) bisa dihapus permanen
- Super admin tidak bisa dihapus (dilindungi di backend)

Halaman: Settings → Hak Akses Admin (hanya muncul untuk super admin)

- Pilih admin dari dropdown
- Toggle checkbox per fitur per action
- Tombol "Semua" di header kolom untuk select/deselect semua sekaligus
- Klik "Simpan Hak Akses"
