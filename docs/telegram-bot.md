# Telegram Bot — MBG Inventory Bot

Bot Telegram terintegrasi untuk operasional stok dan invoice tanpa buka browser.

**File:** `app/Modules/Telegram/TelegramService.php`
**Config:** `config/services.php` → `services.telegram.token`, `services.groq.key`

---

## Perintah Teks

| Perintah | Fungsi |
|---|---|
| `/help` atau `/start` | Tampilkan daftar perintah |
| `/stok` | Semua barang + stok + harga jual |
| `/stok [nama]` | Cari barang by keyword |
| `/valuasi` | Total nilai stok (qty × harga_jual), di-chunk jika > 4096 karakter |
| `/transaksi` | 10 transaksi stok terakhir |
| `/invoice` | Mulai alur pembuatan invoice step-by-step |

---

## Voice Note (AI)

Fitur utama — kirim voice note untuk update stok tanpa mengetik.

### Alur
1. Bot download audio dari Telegram
2. Transkripsi via **Groq Whisper** (`whisper-large-v3`, bahasa `id`)
3. Koreksi kata-kata yang sering salah transkripsi (tabel koreksi hardcoded)
4. Konversi kata angka Indonesia → angka (seribu → 1000, dll)
5. Deteksi intent:
   - **Valuasi global** (`total stok`, `valuasi`, dst) → `/valuasi`
   - **Cek stok item** (`berapa`, `cek`, `stok [nama]`) → tampilkan ringkasan item
   - **Perintah stok** → parse via LLM atau fallback regex
6. Fuzzy search barang di database
7. Jika 1 hasil → langsung apply
8. Jika >1 hasil → kirim inline keyboard pilihan

### Koreksi Kata Otomatis
Bot memiliki tabel koreksi untuk kata yang sering salah di transkripsi suara:

| Salah | Benar |
|---|---|
| food tray, foot tray, futray, ompreng, om preng | foodtray |
| timmer, timer, stimer | steamer |
| kluar, lower, color, kaluar | keluar |
| asok, masukan | masuk |
| loyang stainles | loyang stainless |

### Parsing Perintah (dua layer)

**Layer 1 — LLM (Groq `llama-3.1-8b-instant`):**
- Prompt terstruktur + daftar nama barang dari DB
- Bisa parse multi-perintah dalam satu voice note
- Return JSON array: `[{type, keyword, quantity}]`

**Layer 2 — Regex fallback:**
- Cocokkan kata arah (keluar/masuk) + angka terakhir
- Keyword = teks di antara kata arah dan angka
- Dipakai kalau LLM gagal atau return error

### Fuzzy Search Barang
Algoritma multi-layer, threshold skor 55%:
1. **Alias match** — item punya field `aliases` (JSON array), cocokkan ke alias
2. **LIKE match** — nama mengandung keyword atau sebaliknya (skor 100)
3. **similar_text** pada nama asli
4. **similar_text** pada nama tanpa spasi/simbol
5. **Levenshtein distance** → konversi ke persentase
6. Kembalikan semua item dalam range 15% dari skor tertinggi

### Pasangan Badan+Tutup (Foodtray)
Jika hasil fuzzy search mengandung item dengan kata "Badan" dan "Tutup" dari base name yang sama:
- Otomatis gabung jadi satu item set
- Harga = harga Badan + harga Tutup
- `inventory_item_ids` berisi kedua ID
- Tidak perlu tanya pilihan ke user

---

## Alur Buat Invoice via Telegram

Dipicu dengan `/invoice`. Session disimpan di Cache selama 30 menit.

### Step-by-step
```
Step 1: recipient_name    — Nama penerima
Step 2: recipient_address — Alamat (ketik - untuk kosong)
Step 3: invoice_date      — Tanggal (YYYY-MM-DD atau "hari ini")
Step 4: item_name         — Nama barang (fuzzy search di inventory)
        item_qty          — Jumlah
        item_price        — Harga satuan
        → Inline keyboard: Tambah Item | Selesai
Step 5: notes             — Catatan (ketik - untuk kosong)
Step 6: confirm           — Preview invoice + inline keyboard: Buat Invoice | Batal
```

Ketik `/batal` kapan saja untuk batalkan.

### Setelah Konfirmasi
Memanggil `SalesService::create()` langsung — invoice masuk ke database dan bisa dicetak dari web app.

---

## Approval User via Telegram

Saat ada user baru register, bot kirim notifikasi ke super admin dengan inline keyboard:
- **Setujui** → `user.status = active`
- **Tolak** → `user.status = rejected`

Update langsung ke database, tanpa perlu buka web app.

---

## Konfigurasi

File `.env`:
```
TELEGRAM_BOT_TOKEN=...
GROQ_API_KEY=...
```

Webhook harus didaftarkan ke Telegram API agar bot bisa menerima pesan. Lihat `app/Modules/Telegram/routes.php` untuk endpoint webhook.
