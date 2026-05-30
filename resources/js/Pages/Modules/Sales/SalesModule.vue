<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { salesApi } from '../../../api/sales.api';
import { inventoryApi } from '../../../api/inventory.api';
import type { Sale, SaleItem } from '../../../types';
import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';
import { PDFDocument } from 'pdf-lib';
import * as XLSX from 'xlsx';

// ── State ──────────────────────────────────────────────────
const activeTab        = ref<'new' | 'belum_dikirim' | 'sudah_dikirim'>('new');
const historySubTab    = ref<'belum_dikirim' | 'sudah_dikirim'>('belum_dikirim');
const sales       = ref<Sale[]>([]);
const loading     = ref(false);
const submitting  = ref(false);
const errorMsg    = ref('');

// ── Confirm Modal ─────────────────────────────────────────
const confirmModal = ref<{ show: boolean; title: string; message: string; onConfirm: () => void }>({
    show: false, title: '', message: '', onConfirm: () => {},
});

function showConfirm(title: string, message: string, onConfirm: () => void) {
    confirmModal.value = { show: true, title, message, onConfirm };
}

// ── Toast ──────────────────────────────────────────────────
const toast = ref<{ show: boolean; message: string; type: 'error' | 'success' }>({ show: false, message: '', type: 'error' });
let toastTimer: ReturnType<typeof setTimeout>;

function showToast(message: string, type: 'error' | 'success' = 'error') {
    clearTimeout(toastTimer);
    toast.value = { show: true, message, type };
    toastTimer = setTimeout(() => { toast.value.show = false; }, 4000);
}
const successMsg  = ref('');

// Daftar barang dari inventory ruko
const inventoryItems = ref<any[]>([]);

// Search state per baris item: query + dropdown terbuka
const itemSearchQueries  = ref<string[]>(['']);
const itemDropdownOpen   = ref<boolean[]>([false]);

// ── Form ───────────────────────────────────────────────────
const form = ref({
    recipient_name:    '',
    recipient_address: '',
    invoice_date:      new Date().toISOString().slice(0, 10),
    shipped_at:        '',
    notes:             '',
});

const emptyItem = (): SaleItem => ({
    item_name:   '',
    description: '',
    qty:         null as any,
    unit_price:  null as any,
});

const items = ref<SaleItem[]>([emptyItem()]);

// ── Parser pembayaran dari keterangan ─────────────────────
function parsePaymentFromNotes(text: string, total: number): number {
    if (!text) return 0;
    const t = text.toLowerCase().trim();

    // Pola persen: "dp 50%" / "bayar 30%"
    const pctMatch = t.match(/(\d+(?:[.,]\d+)?)\s*%/);
    if (pctMatch) {
        const pct = parseFloat(pctMatch[1].replace(',', '.'));
        return Math.round((pct / 100) * total);
    }

    // Normalisasi ribuan/jutaan: "10 juta" "500rb" "500ribu" "1.5 juta"
    const normalized = t
        .replace(/(\d)[.,](\d{3})(?!\d)/g, '$1$2') // 10.000 → 10000
        .replace(/(\d+(?:[.,]\d+)?)\s*juta/g,  (_, n) => String(Math.round(parseFloat(n.replace(',', '.')) * 1_000_000)))
        .replace(/(\d+(?:[.,]\d+)?)\s*(ribu|rb|k)/g, (_, n) => String(Math.round(parseFloat(n.replace(',', '.')) * 1_000)));

    // Ambil angka pertama yang muncul setelah kata pembayaran
    const numMatch = normalized.match(/(?:dp|bayar|lunas|transfer|tf|pembayaran|cicil|angsuran|down\s*payment)[^\d]*(\d+)/i)
        ?? normalized.match(/(\d{4,})/); // fallback: angka >= 4 digit
    if (numMatch) {
        const val = parseInt(numMatch[1]);
        if (val > 0 && val <= total) return val;
    }
    return 0;
}

// ── Import Items Excel/CSV ─────────────────────────────────
const importItemsModal   = ref(false);
const importItemsPreview = ref<{ item_name: string; qty: number; unit_price: number; description: string }[]>([]);
const importItemsError   = ref('');

// ── Computed ───────────────────────────────────────────────
const grandTotal = computed(() =>
    items.value.reduce((sum, i) => sum + (Number(i.qty) || 0) * (Number(i.unit_price) || 0), 0)
);

const itemsValid = computed(() =>
    items.value.every(i => i.item_name.trim() && Number(i.qty) > 0 && Number(i.unit_price) >= 0)
)

const parsedPayment = computed(() => parsePaymentFromNotes(form.value.notes, grandTotal.value))
const editParsedPayment = computed(() => {
    if (!editModal.value.sale) return 0;
    const total = editModal.value.sale.status === 'belum_dikirim'
        ? editItems.value.reduce((s, i) => s + Number(i.qty) * Number(i.unit_price), 0)
        : editModal.value.sale.grand_total;
    return parsePaymentFromNotes(editForm.value.notes, total);
});

const formValid = computed(() =>
    form.value.recipient_name.trim() && form.value.invoice_date && itemsValid.value
);

const salesBelumDikirim = computed(() => sales.value.filter(s => s.status === 'belum_dikirim'));
const salesSudahDikirim = computed(() => sales.value.filter(s => s.status === 'sudah_dikirim'));

// ── Modal Edit ────────────────────────────────────────────
const editModal = ref<{ open: boolean; sale: Sale | null }>({ open: false, sale: null });
const editForm  = ref({ recipient_name: '', recipient_address: '', invoice_date: '', shipped_at: '', notes: '', sender_name: '', sender_address: '' });
const editItems = ref<SaleItem[]>([]);
const editSearchQueries  = ref<string[]>([]);
const editDropdownOpen   = ref<boolean[]>([]);
const editPriceDisplays  = ref<string[]>([]);
const editSubmitting      = ref(false);
const attachmentFile      = ref<File | null>(null);
const attachmentUploading = ref(false);

function openEditModal(sale: Sale) {
    editForm.value = {
        recipient_name:    sale.recipient_name,
        recipient_address: sale.recipient_address,
        invoice_date:      sale.invoice_date,
        shipped_at:        sale.shipped_at ? sale.shipped_at.slice(0, 10) : '',
        notes:             sale.notes,
        sender_name:       sale.sender_name ?? '',
        sender_address:    sale.sender_address ?? '',
    };
    editItems.value          = sale.items.map(i => ({ ...i }));
    editSearchQueries.value  = sale.items.map(i => i.item_name);
    editDropdownOpen.value   = sale.items.map(() => false);
    editPriceDisplays.value  = sale.items.map(i => Number(i.unit_price) > 0 ? Number(i.unit_price).toLocaleString('id-ID') : '');
    editModal.value          = { open: true, sale };
}

function addEditItem() {
    editItems.value.push({ item_name: '', description: '', qty: null as any, unit_price: null as any });
    editSearchQueries.value.push('');
    editDropdownOpen.value.push(false);
    editPriceDisplays.value.push('');
}

function removeEditItem(idx: number) {
    if (editItems.value.length > 1) {
        editItems.value.splice(idx, 1);
        editSearchQueries.value.splice(idx, 1);
        editDropdownOpen.value.splice(idx, 1);
        editPriceDisplays.value.splice(idx, 1);
    }
}

function onEditSearchInput(idx: number) {
    editItems.value[idx].item_name = '';
    editDropdownOpen.value[idx] = true;
}

function selectEditSuggestion(idx: number, sug: any) {
    editItems.value[idx].item_name          = sug.label;
    editItems.value[idx].unit_price         = sug.unit_price;
    editItems.value[idx].inventory_item_ids = sug.inventory_item_ids;
    editSearchQueries.value[idx]            = sug.label;
    editDropdownOpen.value[idx]             = false;
    editPriceDisplays.value[idx]            = sug.unit_price > 0 ? sug.unit_price.toLocaleString('id-ID') : '';
}

function closeEditDropdown(idx: number) {
    setTimeout(() => {
        editDropdownOpen.value[idx] = false;
        if (!editItems.value[idx].item_name) editSearchQueries.value[idx] = '';
    }, 150);
}

function onEditPriceInput(idx: number, e: Event) {
    const raw = (e.target as HTMLInputElement).value.replace(/\./g, '').replace(/\D/g, '');
    const num = parseInt(raw) || 0;
    editItems.value[idx].unit_price = num;
    editPriceDisplays.value[idx]   = num > 0 ? num.toLocaleString('id-ID') : '';
    (e.target as HTMLInputElement).value = editPriceDisplays.value[idx];
}

function onEditPriceFocus(idx: number, e: Event) {
    const raw = String(editItems.value[idx].unit_price || '');
    (e.target as HTMLInputElement).value = raw === '0' ? '' : raw;
}

function onEditPriceBlur(idx: number, e: Event) {
    const num = Number(editItems.value[idx].unit_price) || 0;
    editPriceDisplays.value[idx] = num > 0 ? num.toLocaleString('id-ID') : '';
    (e.target as HTMLInputElement).value = editPriceDisplays.value[idx];
}

const editGrandTotal = computed(() =>
    editItems.value.reduce((s, i) => s + (Number(i.qty) || 0) * (Number(i.unit_price) || 0), 0)
);

const editItemsValid = computed(() =>
    editItems.value.every(i => i.item_name.trim() && Number(i.qty) > 0 && Number(i.unit_price) >= 0)
);

async function submitEdit() {
    if (!editForm.value.recipient_name || !editForm.value.invoice_date) return;
    editSubmitting.value = true;
    try {
        const payload: any = {
            ...editForm.value,
            paid_amount: editParsedPayment.value,
        };
        if (editModal.value.sale?.status === 'belum_dikirim') {
            payload.items = editItems.value.map(i => ({ ...i, total_price: Number(i.qty) * Number(i.unit_price) }));
        }
        const res = await salesApi.update(editModal.value.sale!.id, payload);
        const idx = sales.value.findIndex(s => s.id === editModal.value.sale!.id);
        if (idx !== -1) sales.value[idx] = res.data.data;
        editModal.value.open = false;
        window.dispatchEvent(new CustomEvent('sales-updated'));
    } catch (e: any) {
        showToast(e?.response?.data?.message || 'Gagal menyimpan perubahan.');
    } finally {
        editSubmitting.value = false;
    }
}

async function uploadAttachment(sale: Sale) {
    if (!attachmentFile.value) return;
    attachmentUploading.value = true;
    try {
        const res = await salesApi.uploadAttachment(sale.id, attachmentFile.value);
        const idx = sales.value.findIndex(s => s.id === sale.id);
        if (idx !== -1) sales.value[idx] = res.data.data;
        if (editModal.value.sale?.id === sale.id) editModal.value.sale = res.data.data;
        attachmentFile.value = null;
    } catch (e: any) {
        showToast(e?.response?.data?.message || 'Gagal mengunggah lampiran.');
    } finally {
        attachmentUploading.value = false;
    }
}

async function removeAttachment(sale: Sale) {
    showConfirm('Hapus Lampiran', 'Hapus lampiran PDF ini?', async () => {
        try {
            const res = await salesApi.deleteAttachment(sale.id);
            const idx = sales.value.findIndex(s => s.id === sale.id);
            if (idx !== -1) sales.value[idx] = res.data.data;
            if (editModal.value.sale?.id === sale.id) editModal.value.sale = res.data.data;
        } catch (e: any) {
            showToast(e?.response?.data?.message || 'Gagal menghapus lampiran.');
        }
    });
}

// ── Modal Pembayaran ───────────────────────────────────────
const payModal = ref<{ open: boolean; mode: 'add' | 'edit'; sale: Sale | null; inputRaw: string; display: string }>({
    open: false, mode: 'add', sale: null, inputRaw: '', display: '',
});

function openPayModal(sale: Sale, mode: 'add' | 'edit' = 'add') {
    const prefill = mode === 'edit' ? Number(sale.paid_amount) : 0;
    payModal.value = {
        open: true, mode, sale,
        inputRaw: prefill > 0 ? String(prefill) : '',
        display:  prefill > 0 ? prefill.toLocaleString('id-ID') : '',
    };
}

function onPayInput(e: Event) {
    const raw = (e.target as HTMLInputElement).value.replace(/\./g, '').replace(/\D/g, '');
    const num = parseInt(raw) || 0;
    payModal.value.inputRaw = raw;
    payModal.value.display  = num > 0 ? num.toLocaleString('id-ID') : '';
    (e.target as HTMLInputElement).value = payModal.value.display;
}

async function submitPayment() {
    const amount = parseInt(payModal.value.inputRaw) || 0;
    if (!payModal.value.sale) return;
    try {
        const res = payModal.value.mode === 'edit'
            ? await salesApi.setPayment(payModal.value.sale.id, amount)
            : await salesApi.pay(payModal.value.sale.id, amount);
        const idx = sales.value.findIndex(s => s.id === payModal.value.sale!.id);
        if (idx !== -1) sales.value[idx] = res.data.data;
        payModal.value.open = false;
        window.dispatchEvent(new CustomEvent('sales-updated'));
    } catch (e: any) {
        showToast(e?.response?.data?.message || 'Gagal mencatat pembayaran.');
    }
}

function paymentStatus(sale: Sale): { label: string; color: string } {
    if (sale.paid_amount >= sale.grand_total) return { label: 'Lunas', color: 'text-emerald-700 bg-emerald-50' };
    if (sale.paid_amount > 0)                 return { label: 'DP', color: 'text-blue-700 bg-blue-50' };
    return { label: 'Belum Bayar', color: 'text-red-600 bg-red-50' };
}

function sisaTagihan(sale: Sale): number {
    return Math.max(0, sale.grand_total - sale.paid_amount);
}

// ── Set detection (Badan + Tutup) ─────────────────────────
// Cari pasangan: item yang namanya sama persis kecuali keyword Badan/Tutup
const pairSets = computed(() => {
    const PAIR_KEYWORDS = ['badan', 'tutup'];
    const sets: { label: string; unit_price: number; inventory_item_ids: number[] }[] = [];
    const seen = new Set<string>();

    for (const inv of inventoryItems.value) {
        const nameLower = inv.name.toLowerCase();
        const matchedKw = PAIR_KEYWORDS.find(kw => nameLower.includes(kw));
        if (!matchedKw) continue;

        const baseName = inv.name
            .replace(new RegExp(matchedKw, 'gi'), '')
            .replace(/\s{2,}/g, ' ')
            .trim();

        if (seen.has(baseName.toLowerCase())) continue;

        const otherKw = PAIR_KEYWORDS.find(kw => kw !== matchedKw)!;
        const pair = inventoryItems.value.find(other => {
            const ol = other.name.toLowerCase();
            return ol.includes(otherKw) &&
                   ol.replace(new RegExp(otherKw, 'gi'), '').replace(/\s{2,}/g, ' ').trim().toLowerCase() === baseName.toLowerCase();
        });

        if (!pair) continue;

        seen.add(baseName.toLowerCase());
        sets.push({
            label:               baseName,
            unit_price:          (inv.harga_jual ?? 0) + (pair.harga_jual ?? 0),
            inventory_item_ids:  [inv.id, pair.id],
        });
    }

    return sets;
});

// Filtered suggestions per baris — set ditampilkan di atas dengan tag "Set"
function suggestions(idx: number): { type: 'set' | 'item' | 'new'; label: string; unit_price: number; inventory_item_ids: number[]; data?: any }[] {
    const q = (itemSearchQueries.value[idx] ?? '').toLowerCase();

    const matchedSets = pairSets.value
        .filter(s => !q || s.label.toLowerCase().includes(q))
        .map(s => ({ type: 'set' as const, label: s.label, unit_price: s.unit_price, inventory_item_ids: s.inventory_item_ids }));

    const matchedItems = inventoryItems.value
        .filter(inv =>
            !q ||
            inv.name.toLowerCase().includes(q) ||
            (inv.category?.name ?? '').toLowerCase().includes(q)
        )
        .slice(0, 8)
        .map(inv => ({ type: 'item' as const, label: inv.name, unit_price: inv.harga_jual ?? 0, inventory_item_ids: [inv.id], data: inv }));

    const results: { type: 'set' | 'item' | 'new'; label: string; unit_price: number; inventory_item_ids: number[]; data?: any }[] =
        [...matchedSets, ...matchedItems].slice(0, 10);

    if (q.trim() && !matchedItems.some(i => i.label.toLowerCase() === q)) {
        results.push({ type: 'new', label: (itemSearchQueries.value[idx] ?? '').trim(), unit_price: 0, inventory_item_ids: [] });
    }

    return results;
}

// ── Helpers ────────────────────────────────────────────────
function fmt(n: number): string {
    return 'Rp ' + n.toLocaleString('id-ID');
}

// Display value untuk input harga (format ribuan, tanpa prefix)
const priceDisplays = ref<string[]>(['']);

function onPriceInput(idx: number, e: Event) {
    const raw = (e.target as HTMLInputElement).value.replace(/\./g, '').replace(/[^\d]/g, '');
    const num = parseInt(raw) || 0;
    items.value[idx].unit_price = num;
    priceDisplays.value[idx] = num > 0 ? num.toLocaleString('id-ID') : '';
    // Update input display
    (e.target as HTMLInputElement).value = priceDisplays.value[idx];
}

function onPriceFocus(idx: number, e: Event) {
    const raw = String(items.value[idx].unit_price || '').replace(/\./g, '');
    (e.target as HTMLInputElement).value = raw === '0' ? '' : raw;
}

function onPriceBlur(idx: number, e: Event) {
    const num = Number(items.value[idx].unit_price) || 0;
    priceDisplays.value[idx] = num > 0 ? num.toLocaleString('id-ID') : '';
    (e.target as HTMLInputElement).value = priceDisplays.value[idx];
}

function addItem() {
    items.value.push(emptyItem());
    itemSearchQueries.value.push('');
    itemDropdownOpen.value.push(false);
    priceDisplays.value.push('');
}

function removeItem(index: number) {
    if (items.value.length > 1) {
        items.value.splice(index, 1);
        itemSearchQueries.value.splice(index, 1);
        itemDropdownOpen.value.splice(index, 1);
        priceDisplays.value.splice(index, 1);
    }
}

function onSearchInput(idx: number) {
    // Jika user mengetik manual, clear item_name agar validasi tidak lolos sampai dipilih
    items.value[idx].item_name = '';
    itemDropdownOpen.value[idx] = true;
}

function selectSuggestion(idx: number, suggestion: { type: 'set' | 'item' | 'new'; label: string; unit_price: number; inventory_item_ids: number[]; data?: any }) {
    items.value[idx].item_name           = suggestion.label;
    items.value[idx].unit_price          = suggestion.unit_price;
    items.value[idx].inventory_item_ids  = suggestion.inventory_item_ids;
    items.value[idx].is_new_item         = suggestion.type === 'new';
    itemSearchQueries.value[idx]         = suggestion.label;
    itemDropdownOpen.value[idx]          = false;
    priceDisplays.value[idx]             = suggestion.unit_price > 0 ? suggestion.unit_price.toLocaleString('id-ID') : '';
}

function closeDropdown(idx: number) {
    // Delay agar click pada opsi sempat terpanggil
    setTimeout(() => {
        itemDropdownOpen.value[idx] = false;
        // Kalau tidak ada pilihan yang cocok, kembalikan ke nama yang sudah terpilih
        if (!items.value[idx].item_name) {
            itemSearchQueries.value[idx] = '';
        }
    }, 150);
}

function onImportItemsFile(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (!file) return;
    importItemsError.value   = '';
    importItemsPreview.value = [];

    const reader = new FileReader();
    reader.onload = (ev) => {
        try {
            const wb    = XLSX.read(ev.target?.result, { type: 'array' });
            const ws    = wb.Sheets[wb.SheetNames[0]];
            const rows  = XLSX.utils.sheet_to_json<any>(ws, { defval: '' });

            if (rows.length === 0) {
                importItemsError.value = 'File kosong atau format tidak dikenali.';
                return;
            }

            // Normalisasi header: lowercase, trim, hapus spasi
            const normalize = (s: string) => String(s).toLowerCase().replace(/\s+/g, '_');
            const firstRow  = rows[0];
            const keyMap: Record<string, string> = {};
            for (const k of Object.keys(firstRow)) {
                keyMap[normalize(k)] = k;
            }

            const nameKey  = keyMap['nama_barang'] ?? keyMap['nama'] ?? keyMap['item_name'] ?? keyMap['barang'];
            const qtyKey   = keyMap['qty'] ?? keyMap['kts'] ?? keyMap['kuantitas'] ?? keyMap['jumlah'];
            const priceKey = keyMap['harga_satuan'] ?? keyMap['@harga'] ?? keyMap['harga'] ?? keyMap['unit_price'] ?? keyMap['price'];
            const descKey  = keyMap['keterangan'] ?? keyMap['description'] ?? keyMap['deskripsi'] ?? keyMap['ket'];

            if (!nameKey || !qtyKey || !priceKey) {
                importItemsError.value = 'Kolom wajib tidak ditemukan. Pastikan ada: nama_barang, qty, harga_satuan.';
                return;
            }

            const parsed = rows
                .map(r => ({
                    item_name:   String(r[nameKey] ?? '').trim(),
                    qty:         parseInt(String(r[qtyKey]).replace(/\D/g, '')) || 0,
                    unit_price:  parseInt(String(r[priceKey]).replace(/[^\d]/g, '')) || 0,
                    description: descKey ? String(r[descKey] ?? '').trim() : '',
                }))
                .filter(r => r.item_name && r.qty > 0 && r.unit_price >= 0);

            if (parsed.length === 0) {
                importItemsError.value = 'Tidak ada baris valid. Pastikan qty > 0 dan nama barang tidak kosong.';
                return;
            }

            importItemsPreview.value = parsed;
            importItemsModal.value   = true;
        } catch {
            importItemsError.value = 'Gagal membaca file. Pastikan format Excel (.xlsx) atau CSV.';
        }
    };
    reader.readAsArrayBuffer(file);
    (e.target as HTMLInputElement).value = '';
}

function downloadImportTemplate() {
    const ws = XLSX.utils.aoa_to_sheet([
        ['nama_barang', 'qty', 'harga_satuan', 'keterangan'],
        ['Contoh Produk A', 10, 150000, 'Keterangan opsional'],
        ['Contoh Produk B', 5, 75000, ''],
    ]);
    ws['!cols'] = [{ wch: 30 }, { wch: 8 }, { wch: 16 }, { wch: 30 }];
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Items');
    XLSX.writeFile(wb, 'template_import_item.xlsx');
}

function confirmImportItems() {
    const existing = items.value.filter(i => i.item_name.trim());
    const incoming = importItemsPreview.value.map(r => ({
        item_name:   r.item_name,
        description: r.description,
        qty:         r.qty,
        unit_price:  r.unit_price,
    }));

    items.value = existing.length ? [...existing, ...incoming] : incoming;
    itemSearchQueries.value = items.value.map(i => i.item_name);
    itemDropdownOpen.value  = items.value.map(() => false);
    priceDisplays.value     = items.value.map(i => Number(i.unit_price) > 0 ? Number(i.unit_price).toLocaleString('id-ID') : '');

    importItemsModal.value   = false;
    importItemsPreview.value = [];
}

function resetForm() {
    form.value = {
        recipient_name:    '',
        recipient_address: '',
        invoice_date:      new Date().toISOString().slice(0, 10),
        shipped_at:        '',
        notes:             '',
    };
    items.value             = [emptyItem()];
    itemSearchQueries.value = [''];
    itemDropdownOpen.value  = [false];
    priceDisplays.value     = [''];
}

// ── API ────────────────────────────────────────────────────
async function loadInventory() {
    const res = await inventoryApi.getItems();
    inventoryItems.value = (res.data.data as any[]).filter(
        (i: any) => (i.location ?? '').toLowerCase() === 'ruko'
    );
}

async function loadSales() {
    loading.value = true;
    try {
        const res = await salesApi.getAll();
        sales.value = res.data.data;
        window.dispatchEvent(new CustomEvent('sales-updated'));
    } finally {
        loading.value = false;
    }
}

async function submitSale() {
    if (!formValid.value) return;
    submitting.value = true;
    errorMsg.value   = '';
    successMsg.value = '';
    try {
        const payload = {
            ...form.value,
            paid_amount: parsedPayment.value,
            items: items.value.map(i => ({ ...i, total_price: i.qty * i.unit_price })),
        };
        const res = await salesApi.create(payload);
        successMsg.value = `Invoice ${res.data.data.invoice_number} berhasil dibuat.`;
        await loadSales();
        printInvoice(res.data.data);
        resetForm();
    } catch (e: any) {
        errorMsg.value = e?.response?.data?.message || 'Terjadi kesalahan.';
    } finally {
        submitting.value = false;
    }
}

// ── Modal Konfirmasi Kirim ─────────────────────────────────
const shipModal = ref<{ open: boolean; sale: Sale | null; inputRaw: string; display: string; shippedAt: string }>({
    open: false, sale: null, inputRaw: '', display: '', shippedAt: '',
});

const shipKekurangan = computed(() => {
    if (!shipModal.value.sale) return 0;
    const paid    = shipModal.value.sale.paid_amount;
    const total   = shipModal.value.sale.grand_total;
    const bayarKini = parseInt(shipModal.value.inputRaw) || 0;
    return Math.max(0, total - paid - bayarKini);
});

const shipKeteranganAuto = computed(() =>
    shipKekurangan.value > 0
        ? `Kekurangan sejumlah Rp ${shipKekurangan.value.toLocaleString('id-ID')}`
        : ''
);

function openShipModal(sale: Sale) {
    const total = sale.grand_total;
    const defaultDate = sale.shipped_at
        ? sale.shipped_at.slice(0, 10)
        : new Date().toISOString().slice(0, 10);
    shipModal.value = {
        open: true, sale,
        inputRaw: String(total),
        display:  total.toLocaleString('id-ID'),
        shippedAt: defaultDate,
    };
}

function onShipPayInput(e: Event) {
    const raw = (e.target as HTMLInputElement).value.replace(/\./g, '').replace(/\D/g, '');
    const num = parseInt(raw) || 0;
    shipModal.value.inputRaw = raw;
    shipModal.value.display  = num > 0 ? num.toLocaleString('id-ID') : '';
    (e.target as HTMLInputElement).value = shipModal.value.display;
}

async function confirmShip() {
    if (!shipModal.value.sale) return;
    const id     = shipModal.value.sale.id;
    const amount = parseInt(shipModal.value.inputRaw) || 0;
    try {
        await salesApi.ship(id, {
            shipped_at: shipModal.value.shippedAt || undefined,
            notes: shipKeteranganAuto.value || undefined,
        });
        if (amount > 0) await salesApi.setPayment(id, amount);
        await loadSales();
        shipModal.value.open = false;
    } catch (e: any) {
        showToast(e?.response?.data?.message || 'Gagal memperbarui status.');
    }
}

async function deleteSale(id: number, isShipped = false) {
    const msg = isShipped
        ? 'Stok barang yang sudah dipotong akan dikembalikan ke inventaris.'
        : 'Invoice akan dihapus permanen.';
    showConfirm('Hapus Invoice', msg, async () => {
        try {
            await salesApi.remove(id);
            await loadSales();
        } catch (e: any) {
            showToast(e?.response?.data?.message || 'Gagal menghapus invoice.');
        }
    });
}

async function revertStock(id: number) {
    showConfirm('Kembalikan Stok', 'Status invoice akan kembali ke "Belum Dikirim" dan stok barang akan dipulihkan.', async () => {
        try {
            await salesApi.revertStock(id);
            await loadSales();
        } catch (e: any) {
            showToast(e?.response?.data?.message || 'Gagal mengembalikan stok.');
        }
    });
}

// ── Invoice PDF ────────────────────────────────────────────
async function printInvoice(sale: Sale) {
    const doc  = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });
    const NAVY      = [29, 53, 87]  as [number,number,number];
    const NAVY_HEX  = '#1D3557';
    const WHITE     = [255,255,255] as [number,number,number];
    const GRAY_LINE = [170,170,170] as [number,number,number];
    const BG_PO     = [238,242,247] as [number,number,number];

    const LM   = 20;
    const RM   = 20;
    const pageW = 210;
    const CW   = pageW - LM - RM;   // 170 mm
    let y = 15;

    // ── 1. HEADER ──────────────────────────────────────────
    doc.setFontSize(20);
    doc.setFont('times', 'bold');
    doc.setTextColor(...NAVY);
    const senderName = sale.sender_name?.trim() || 'PT Indo Pangan';
    doc.text(senderName, pageW / 2, y, { align: 'center' });
    y += 5;

    doc.setFontSize(8);
    doc.setFont('times', 'normal');
    doc.setTextColor(51, 51, 51);
    const defaultAddr = [
        'Ruko Puncak CBD',
        'Jalan Dukuh Kramat I No.36 Blok 7E PSR',
        'Jajar Tunggal, Kec. Wiyung, Kota Surabaya Jawa Timur',
    ];
    const addrLines = sale.sender_address?.trim()
        ? sale.sender_address.split('\n').map(s => s.trim()).filter(Boolean)
        : defaultAddr;
    for (const line of addrLines) {
        doc.text(line, pageW / 2, y, { align: 'center' });
        y += 3.5;
    }
    y += 1;

    doc.setDrawColor(...NAVY);
    doc.setLineWidth(0.6);
    doc.line(LM, y, pageW - RM, y);
    y += 5;

    // ── 2. KEPADA / DIKIRIM KE  |  PO BOX ─────────────────
    const leftW  = CW * 0.58;
    const rightW = CW * 0.40;
    const gapW   = CW - leftW - rightW;
    const rightX = LM + leftW + gapW;

    // PO Box — border + background
    const boxH   = 28;
    const halfL  = (leftW - 3) / 2;  // lebar tiap kotak Kepada & Dikirim ke

    // Kotak Kepada (kiri)
    doc.setDrawColor(...NAVY);
    doc.setLineWidth(0.2);
    doc.rect(LM, y, halfL, boxH);
    doc.setFontSize(9);
    doc.setFont('times', 'bold');
    doc.setTextColor(0, 0, 0);
    doc.text('Kepada', LM + 3, y + 5);
    doc.setFont('times', 'normal');
    const recipientLines = doc.splitTextToSize(sale.recipient_name, halfL - 6);
    doc.text(recipientLines.slice(0, 2), LM + 3, y + 12);

    // Kotak Dikirim ke (tengah)
    const dikX = LM + halfL + 3;
    doc.setDrawColor(...NAVY);
    doc.setLineWidth(0.2);
    doc.rect(dikX, y, halfL, boxH);
    doc.setFontSize(9);
    doc.setFont('times', 'bold');
    doc.setTextColor(0, 0, 0);
    doc.text('Dikirim ke', dikX + 3, y + 5);
    doc.setFont('times', 'normal');
    const addrWrapped = doc.splitTextToSize(sale.recipient_address || '-', halfL - 6);
    doc.text(addrWrapped.slice(0, 3), dikX + 3, y + 12);

    // PO Box — border + background (kanan)
    doc.setFillColor(...BG_PO);
    doc.rect(rightX, y, rightW, boxH, 'F');
    doc.setDrawColor(...NAVY);
    doc.setLineWidth(0.2);
    doc.rect(rightX, y, rightW, boxH);

    // PO Box title
    doc.setFontSize(13);
    doc.setFont('times', 'bold');
    doc.setTextColor(...NAVY);
    doc.text('Pesanan Pembelian', rightX + 4, y + 7);

    // PO Box rows
    const poRows = [
        ['Nomor',         sale.invoice_number],
        ['Tanggal',       formatDate(sale.invoice_date)],
        ['Tanggal Kirim', sale.shipped_at ? formatDate(sale.shipped_at) : '-'],
    ];
    let py = y + 12;
    for (const [lbl, val] of poRows) {
        doc.setFontSize(8);
        doc.setFont('times', 'normal');
        doc.setTextColor(0, 0, 0);
        doc.text(lbl, rightX + 4, py);
        doc.text(':', rightX + 24, py);
        doc.text(val, rightX + 27, py);
        py += 5;
    }

    y += boxH + 5;

    // ── 3. ITEM TABLE ──────────────────────────────────────
    const colW = [
        CW * 0.07,   // No
        CW * 0.28,   // Nama Barang
        CW * 0.10,   // Qty
        CW * 0.18,   // @Harga
        CW * 0.18,   // Keterangan
        CW * 0.19,   // Total
    ];

    const tableRows = sale.items.map((item, i) => [
        String(i + 1),
        item.item_name,
        Number(item.qty).toLocaleString('id-ID'),
        'Rp ' + Number(item.unit_price).toLocaleString('id-ID'),
        item.description || '-',
        'Rp ' + (Number(item.qty) * Number(item.unit_price)).toLocaleString('id-ID'),
    ]);

    autoTable(doc, {
        startY: y,
        margin: { left: LM, right: RM },
        head: [['No', 'Nama Barang', 'Kts.', '@Harga', 'Keterangan', 'Total']],
        body: tableRows,
        styles: {
            font: 'times',
            fontStyle: 'bold',
            fontSize: 9,
            cellPadding: { top: 4, bottom: 4, left: 3, right: 3 },
            lineColor: [187, 187, 187],
            lineWidth: 0.3,
        },
        headStyles: {
            font: 'times',
            fillColor: NAVY,
            textColor: WHITE,
            fontStyle: 'bold',
            halign: 'center',
            valign: 'middle',
        },
        columnStyles: {
            0: { halign: 'center',  cellWidth: colW[0] },
            1: { halign: 'left',    cellWidth: colW[1] },
            2: { halign: 'center',  cellWidth: colW[2] },
            3: { halign: 'right',   cellWidth: colW[3] },
            4: { halign: 'center',  cellWidth: colW[4] },
            5: { halign: 'right',   cellWidth: colW[5] },
        },
        alternateRowStyles: { fillColor: [248, 250, 252] },
    });

    y = (doc as any).lastAutoTable.finalY + 6;

    // ── 4. BOTTOM: NOTES kiri | SUMMARY kanan ──────────────
    const noteW    = CW * 0.52;
    const summaryW = CW * 0.48;
    const summaryX = LM + noteW;

    // ── Notes / bank info (kiri) ───────────────────────────
    doc.setFontSize(8);
    doc.setFontSize(9);
    doc.setFont('times', 'normal');
    doc.setTextColor(0, 0, 0);
    doc.text('Keterangan :', LM, y);

    const drawHR = (yPos: number, w: number) => {
        doc.setDrawColor(...GRAY_LINE);
        doc.setLineWidth(0.3);
        doc.line(LM, yPos, LM + w, yPos);
    };

    y += 7;
    drawHR(y, noteW - 4);
    y += 5;
    doc.setFontSize(9);
    doc.setFont('times', 'normal');
    doc.setTextColor(0, 0, 0);
    doc.text('Top :', LM, y);
    y += 7;
    drawHR(y, noteW - 4);
    y += 5;
    doc.setFontSize(9);
    doc.setFont('times', 'bold');
    doc.text('RONALDO CHANDRA SUSANTO', LM, y);
    y += 5;
    doc.setFont('times', 'normal');
    doc.text('Bank Mandiri', LM, y);
    y += 5;
    doc.setFont('times', 'bold');
    doc.text('1430033951870', LM, y);
    y += 5;
    drawHR(y, noteW - 4);

    // ── Summary box (kanan) ────────────────────────────────
    // Hitung posisi Y awal summary (sejajar dengan notes)
    const summaryStartY = (doc as any).lastAutoTable.finalY + 6;
    const subTotal = sale.grand_total;
    const summaryRows = [
        ['Sub Total', 'IDR ' + subTotal.toLocaleString('id-ID')],
        ['Diskon',    ''],
        ['PPN (0%)',  ''],
    ];

    const colHalf = summaryW / 2;
    let sy = summaryStartY;

    // Border box summary rows
    const summaryBoxH = summaryRows.length * 8 + 2;
    doc.setDrawColor(...NAVY);
    doc.setLineWidth(0.4);
    doc.rect(summaryX, sy, summaryW, summaryBoxH);

    for (const [lbl, val] of summaryRows) {
        doc.setFontSize(8);
        doc.setFont('times', 'bold');
        doc.setTextColor(0, 0, 0);
        doc.text(lbl, summaryX + 4, sy + 5);
        doc.text(val, summaryX + summaryW - 4, sy + 5, { align: 'right' });
        sy += 8;
    }

    // Total box (navy)
    sy += 1;
    doc.setFillColor(...NAVY);
    doc.rect(summaryX, sy, summaryW, 10, 'F');
    doc.setFontSize(9);
    doc.setFont('times', 'bold');
    doc.setTextColor(...WHITE);
    doc.text('Total :', summaryX + 4, sy + 6.5);
    doc.text('IDR ' + sale.grand_total.toLocaleString('id-ID'), summaryX + summaryW - 4, sy + 6.5, { align: 'right' });

    // Ambil posisi Y terbawah dari dua kolom
    y = Math.max(y, sy + 10) + 8;

    // ── 5. DP NOTE ─────────────────────────────────────────
    const notesText  = sale.notes?.trim() || 'DP 50% PELUNASAN';
    const dpAmount   = parseDPAmount(sale.notes, sale.grand_total);
    const kekurangan = dpAmount > 0 ? Math.max(0, sale.grand_total - dpAmount) : 0;

    doc.setFontSize(9);
    doc.setFont('times', 'bold');
    doc.setTextColor(0, 0, 0);
    doc.text(notesText, LM, y);
    y += 5;

    if (kekurangan > 0) {
        doc.setFont('times', 'bold');
        doc.setTextColor(180, 0, 0);
        doc.text('Kekurangan Rp ' + kekurangan.toLocaleString('id-ID'), LM, y);
        doc.setTextColor(0, 0, 0);
        y += 5;
    }

    doc.setFont('times', 'normal');
    doc.text('GARANSI SUS 304 ANTI MAGNET LOLOS UJI LAB DAN VERIFIKASI MBG', LM, y);
    y += 8;

    // ── 6. ORDERED BY (signature) ──────────────────────────
    const sigW = 50;
    doc.setFontSize(8);
    doc.setFont('times', 'normal');
    doc.setTextColor(0, 0, 0);
    doc.text('Ordered by :', LM + 10, y);
    y += 16;
    doc.setDrawColor(0, 0, 0);
    doc.setLineWidth(0.4);
    doc.line(LM + 10, y, LM + 10 + sigW, y);
    y += 4;
    doc.setFont('times', 'bold');
    doc.text(sale.recipient_name, LM + 10, y);

    // ── 7. PAGE NUMBER ─────────────────────────────────────
    const pageCount = (doc as any).internal.getNumberOfPages();
    for (let i = 1; i <= pageCount; i++) {
        doc.setPage(i);
        doc.setFontSize(8);
        doc.setFont('times', 'normal');
        doc.setTextColor(136, 136, 136);
        doc.text(`${i} / ${pageCount}`, pageW / 2, 287, { align: 'center' });
    }

    const filename = `${sale.invoice_number.replace(/\//g, '-')}.pdf`;

    if (sale.attachment_path) {
        const invoiceBytes     = doc.output('arraybuffer');
        const attachRes        = await fetch(`/storage/${sale.attachment_path}`);
        const attachmentBytes  = await attachRes.arrayBuffer();

        const merged       = await PDFDocument.create();
        const invoicePdf   = await PDFDocument.load(invoiceBytes);
        const attachPdf    = await PDFDocument.load(attachmentBytes);

        const invoicePages = await merged.copyPages(invoicePdf, invoicePdf.getPageIndices());
        invoicePages.forEach(p => merged.addPage(p));

        const attachPages  = await merged.copyPages(attachPdf, attachPdf.getPageIndices());
        attachPages.forEach(p => merged.addPage(p));

        const mergedBytes = await merged.save();
        const blob        = new Blob([mergedBytes], { type: 'application/pdf' });
        const url         = URL.createObjectURL(blob);
        const a           = document.createElement('a');
        a.href     = url;
        a.download = filename;
        a.click();
        URL.revokeObjectURL(url);
    } else {
        doc.save(filename);
    }
}

function formatDate(dateStr: string): string {
    const d = new Date(dateStr);
    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' });
}

function parseDPAmount(notes: string | null | undefined, grandTotal: number): number {
    if (!notes) return 0;
    const text = notes.trim().toLowerCase();

    // Pola: DP diikuti angka + satuan (juta/jt/ribu/rb/k) atau persen
    const match = text.match(/dp\s*([\d.,]+)\s*(juta|jt|ribu|rb|k|%)?/i);
    if (!match) return 0;

    const rawNum = parseFloat(match[1].replace(/\./g, '').replace(',', '.'));
    if (isNaN(rawNum)) return 0;

    const satuan = (match[2] ?? '').toLowerCase();
    if (satuan === '%') return Math.round(grandTotal * rawNum / 100);
    if (satuan === 'juta' || satuan === 'jt') return Math.round(rawNum * 1_000_000);
    if (satuan === 'ribu' || satuan === 'rb' || satuan === 'k') return Math.round(rawNum * 1_000);

    // Tanpa satuan — angka mentah (misal "DP 10000000")
    return Math.round(rawNum);
}

// ── Lifecycle ──────────────────────────────────────────────
onMounted(async () => {
    await Promise.all([loadInventory(), loadSales()]);
});
</script>

<template>
    <div class="min-h-screen bg-slate-50 p-4 sm:p-6">
        <div class="mb-5">
            <h1 class="text-xl sm:text-2xl font-bold text-slate-800">Penjualan</h1>
            <p class="text-sm text-slate-500 mt-1">Buat dan kelola invoice penjualan</p>
        </div>

        <div class="flex flex-col sm:flex-row gap-2 mb-5">
            <!-- Buat Invoice: full width di mobile, auto di desktop -->
            <button
                @click="activeTab = 'new'"
                :class="activeTab === 'new'
                    ? 'bg-[#1D3557] text-white shadow-lg'
                    : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200'"
                class="w-full sm:w-auto sm:px-5 py-2.5 rounded-xl text-sm font-bold transition-all"
            >
                Buat Invoice
            </button>
            <!-- Belum + Sudah: berdampingan di mobile -->
            <div class="grid grid-cols-2 sm:contents gap-2">
                <button
                    @click="activeTab = 'belum_dikirim'; historySubTab = 'belum_dikirim'"
                    :class="activeTab === 'belum_dikirim'
                        ? 'bg-amber-500 text-white shadow-lg'
                        : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200'"
                    class="py-2.5 sm:px-5 rounded-xl text-sm font-bold transition-all flex items-center justify-center gap-1.5"
                >
                    Belum Kirim
                    <span v-if="salesBelumDikirim.length"
                        :class="activeTab === 'belum_dikirim' ? 'bg-white/30 text-white' : 'bg-amber-100 text-amber-700'"
                        class="text-xs font-bold px-1.5 py-0.5 rounded-full"
                    >{{ salesBelumDikirim.length }}</span>
                </button>
                <button
                    @click="activeTab = 'sudah_dikirim'; historySubTab = 'sudah_dikirim'"
                    :class="activeTab === 'sudah_dikirim'
                        ? 'bg-emerald-600 text-white shadow-lg'
                        : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200'"
                    class="py-2.5 sm:px-5 rounded-xl text-sm font-bold transition-all"
                >
                    Sudah Kirim
                </button>
            </div>
        </div>

        <!-- ─── TAB: Buat Invoice ─── -->
        <div v-if="activeTab === 'new'" class="flex flex-col gap-4 sm:gap-6">

            <div v-if="successMsg" class="bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm px-4 py-3 rounded-xl">
                {{ successMsg }}
            </div>
            <div v-if="errorMsg" class="bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-xl">
                {{ errorMsg }}
            </div>

            <!-- Info Penerima -->
            <div class="bg-white border border-slate-200 rounded-2xl p-4 sm:p-6 shadow-sm">
                <h2 class="text-base font-bold text-slate-700 mb-4">Informasi Penerima</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1.5">Nama Penerima <span class="text-red-500">*</span></label>
                        <input
                            v-model="form.recipient_name"
                            type="text"
                            placeholder="Nama pelanggan"
                            class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1D3557]/30 focus:border-[#1D3557]"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1.5">Dikirim ke (Alamat)</label>
                        <input
                            v-model="form.recipient_address"
                            type="text"
                            placeholder="Kota / Alamat tujuan"
                            class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1D3557]/30 focus:border-[#1D3557]"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1.5">Tanggal Invoice <span class="text-red-500">*</span></label>
                        <input
                            v-model="form.invoice_date"
                            type="date"
                            class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1D3557]/30 focus:border-[#1D3557]"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1.5">Tanggal Kirim</label>
                        <input
                            v-model="form.shipped_at"
                            type="date"
                            class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1D3557]/30 focus:border-[#1D3557]"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1.5">Catatan</label>
                        <input
                            v-model="form.notes"
                            type="text"
                            placeholder="Contoh: DP 10 Juta / Bayar 50%"
                            class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1D3557]/30 focus:border-[#1D3557]"
                        />
                        <p v-if="parsedPayment > 0" class="mt-1.5 text-[11px] font-semibold text-emerald-700 flex items-center gap-1">
                            <i class="pi pi-check-circle text-[11px]"></i>
                            Terdeteksi pembayaran: {{ fmt(parsedPayment) }}
                            <span class="text-slate-400 font-normal">· sisa {{ fmt(grandTotal - parsedPayment) }}</span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Tabel Item -->
            <div class="bg-white border border-slate-200 rounded-2xl p-4 sm:p-6 shadow-sm">
                <div class="flex items-center justify-between mb-4 gap-2">
                    <h2 class="text-base font-bold text-slate-700 shrink-0">Daftar Item</h2>
                    <div class="flex items-center gap-2">
                        <button @click="downloadImportTemplate" class="flex items-center gap-1.5 border border-slate-300 text-slate-600 text-xs font-bold px-3 py-2 rounded-xl hover:bg-slate-100 transition-colors">
                            <i class="pi pi-download text-xs"></i>
                            <span class="hidden sm:inline">Template</span>
                        </button>
                        <label class="flex items-center gap-1.5 border border-slate-300 text-slate-600 text-xs font-bold px-3 py-2 rounded-xl hover:bg-slate-100 transition-colors cursor-pointer">
                            <i class="pi pi-file-excel text-xs"></i>
                            <span class="hidden sm:inline">Import</span>
                            <input type="file" accept=".xlsx,.xls,.csv" class="hidden" @change="onImportItemsFile" />
                        </label>
                        <button
                            @click="addItem"
                            class="flex items-center gap-1.5 bg-[#1D3557] text-white text-xs font-bold px-3 py-2 rounded-xl hover:bg-[#162840] transition-colors"
                        >
                            <i class="pi pi-plus text-xs"></i>
                            <span class="hidden sm:inline">Tambah Item</span>
                        </button>
                    </div>
                </div>
                <div v-if="importItemsError" class="mb-3 bg-red-50 border border-red-200 text-red-600 text-xs px-4 py-2.5 rounded-xl">
                    {{ importItemsError }}
                </div>

                <!-- Header kolom -->
                <div class="hidden md:grid grid-cols-[2rem_3fr_1fr_7rem_11rem_10rem_2.5rem] gap-3 px-3 mb-2">
                    <div></div>
                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">Nama Barang</div>
                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">Keterangan</div>
                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wide text-right">Qty</div>
                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wide text-right">Harga Satuan</div>
                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wide text-right">Total</div>
                    <div></div>
                </div>

                <!-- Baris item -->
                <div class="flex flex-col gap-3">
                    <div
                        v-for="(item, idx) in items"
                        :key="idx"
                        class="bg-slate-50/60 border border-slate-200 rounded-xl px-3 py-3"
                    >
                        <!-- Mobile header -->
                        <div class="flex items-center justify-between mb-3 md:hidden">
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-wide">Item {{ idx + 1 }}</span>
                            <button
                                @click="removeItem(idx)"
                                :disabled="items.length === 1"
                                class="text-red-400 hover:text-red-600 disabled:opacity-25 disabled:cursor-not-allowed transition-colors p-1"
                            ><i class="pi pi-trash text-sm"></i></button>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-[2rem_3fr_1fr_7rem_11rem_10rem_2.5rem] gap-3 items-center">

                        <!-- Nomor -->
                        <div class="hidden md:flex items-center justify-center w-7 h-7 rounded-full bg-slate-200 text-slate-500 text-xs font-bold flex-shrink-0">
                            {{ idx + 1 }}
                        </div>

                        <!-- Nama Barang (search) -->
                        <div class="relative">
                        <label class="block md:hidden text-[10px] font-bold text-slate-400 uppercase mb-1">Nama Barang</label>
                            <div
                                class="flex items-center border rounded-lg px-3 py-2 gap-2 bg-white focus-within:ring-1 focus-within:ring-[#1D3557]/30 focus-within:border-[#1D3557] transition-colors"
                                :class="item.item_name ? 'border-[#1D3557]/40' : 'border-slate-200'"
                            >
                                <i class="pi pi-search text-slate-400 text-xs flex-shrink-0"></i>
                                <input
                                    v-model="itemSearchQueries[idx]"
                                    @input="onSearchInput(idx)"
                                    @focus="itemDropdownOpen[idx] = true"
                                    @blur="closeDropdown(idx)"
                                    type="text"
                                    placeholder="Cari barang..."
                                    class="w-full text-sm bg-transparent focus:outline-none min-w-0"
                                />
                                <span v-if="item.is_new_item" class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-blue-100 text-blue-700 uppercase tracking-wide flex-shrink-0">Baru</span>
                                <i v-else-if="item.item_name" class="pi pi-check-circle text-emerald-500 text-xs flex-shrink-0"></i>
                            </div>

                            <!-- Dropdown suggestions -->
                            <div
                                v-if="itemDropdownOpen[idx] && suggestions(idx).length > 0"
                                class="absolute top-full left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-xl z-50 overflow-y-auto max-h-52"
                            >
                                <div
                                    v-for="(sug, si) in suggestions(idx)"
                                    :key="si"
                                    @mousedown.prevent="selectSuggestion(idx, sug)"
                                    class="flex items-center justify-between px-3 py-2.5 hover:bg-slate-50 cursor-pointer group border-b border-slate-100 last:border-0"
                                    :class="sug.type === 'new' ? 'bg-blue-50/60 hover:bg-blue-50' : ''"
                                >
                                    <div class="flex items-center gap-2 min-w-0">
                                        <span
                                            v-if="sug.type === 'set'"
                                            class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-amber-100 text-amber-700 uppercase tracking-wide flex-shrink-0"
                                        >Set</span>
                                        <span
                                            v-if="sug.type === 'new'"
                                            class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-blue-100 text-blue-700 uppercase tracking-wide flex-shrink-0"
                                        >Baru</span>
                                        <div class="min-w-0">
                                            <p class="text-sm font-semibold text-slate-700 group-hover:text-[#1D3557] truncate">
                                                <template v-if="sug.type === 'new'">Tambah "{{ sug.label }}" ke stok Ruko</template>
                                                <template v-else>{{ sug.label }}</template>
                                            </p>
                                            <p v-if="sug.type === 'set'" class="text-[10px] text-amber-600">Badan + Tutup (1 pasang)</p>
                                            <p v-else-if="sug.type === 'new'" class="text-[10px] text-blue-500">Barang baru — otomatis masuk ke daftar stok</p>
                                            <p v-else class="text-xs text-slate-400">{{ sug.data?.category?.name ?? '-' }}</p>
                                        </div>
                                    </div>
                                    <div class="text-right flex-shrink-0 ml-3">
                                        <p v-if="sug.type !== 'new'" class="text-xs font-bold text-[#1D3557]">{{ fmt(sug.unit_price) }}</p>
                                        <p v-if="sug.type === 'item'" class="text-[10px] text-slate-400">Stok: {{ sug.data?.quantity }} {{ sug.data?.unit }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- No result -->
                            <div
                                v-if="itemDropdownOpen[idx] && suggestions(idx).length === 0"
                                class="absolute top-full left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-xl z-50 px-4 py-3 text-xs text-slate-400 text-center"
                            >
                                Barang tidak ditemukan
                            </div>
                        </div>

                        <!-- Keterangan -->
                        <div class="md:contents">
                        <label class="block md:hidden text-[10px] font-bold text-slate-400 uppercase mb-1">Keterangan</label>
                        <input
                            v-model="item.description"
                            type="text"
                            placeholder="Keterangan (opsional)"
                            class="w-full border border-slate-200 bg-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#1D3557]/30 focus:border-[#1D3557]"
                        /></div>

                        <!-- Qty + Harga (berdampingan di mobile) -->
                        <div class="grid grid-cols-2 gap-2 md:contents">
                            <div>
                                <label class="block md:hidden text-[10px] font-bold text-slate-400 uppercase mb-1">Qty</label>
                                <input
                                    v-model.number="item.qty"
                                    type="number" min="1" placeholder="0"
                                    class="w-full border border-slate-200 bg-white rounded-lg px-3 py-2 text-sm text-right focus:outline-none focus:ring-1 focus:ring-[#1D3557]/30 focus:border-[#1D3557]"
                                />
                            </div>
                            <div>
                                <label class="block md:hidden text-[10px] font-bold text-slate-400 uppercase mb-1">Harga Satuan</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm pointer-events-none">Rp</span>
                                    <input
                                        :value="priceDisplays[idx]"
                                        @input="onPriceInput(idx, $event)"
                                        @focus="onPriceFocus(idx, $event)"
                                        @blur="onPriceBlur(idx, $event)"
                                        type="text" inputmode="numeric" placeholder="0"
                                        class="w-full border border-slate-200 bg-white rounded-lg pl-8 pr-3 py-2 text-sm text-right focus:outline-none focus:ring-1 focus:ring-[#1D3557]/30 focus:border-[#1D3557]"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Total -->
                        <div class="md:contents">
                        <label class="block md:hidden text-[10px] font-bold text-slate-400 uppercase mb-1">Total</label>
                        <div class="text-right">
                            <span class="text-sm font-bold text-slate-700 whitespace-nowrap">
                                {{ (Number(item.qty) > 0 && Number(item.unit_price) > 0) ? fmt(Number(item.qty) * Number(item.unit_price)) : '—' }}
                            </span>
                        </div></div>

                        <!-- Hapus (desktop only, mobile ada di header card) -->
                        <div class="hidden md:flex justify-center">
                            <button
                                @click="removeItem(idx)"
                                :disabled="items.length === 1"
                                class="text-red-400 hover:text-red-600 disabled:opacity-25 disabled:cursor-not-allowed transition-colors p-1"
                            >
                                <i class="pi pi-trash text-sm"></i>
                            </button>
                        </div>

                        </div><!-- end grid -->
                    </div>
                </div>

                <!-- Grand Total -->
                <div class="flex justify-end mt-4 pt-4 border-t border-slate-100">
                    <div class="flex items-center gap-4">
                        <span class="text-sm font-bold text-slate-600">Grand Total</span>
                        <span class="bg-[#1D3557] text-white text-sm font-bold px-5 py-2 rounded-xl">
                            {{ fmt(grandTotal) }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <button
                    @click="resetForm"
                    class="px-5 py-2.5 rounded-xl text-sm font-bold border border-slate-300 text-slate-600 hover:bg-slate-100 transition-colors"
                >
                    Reset
                </button>
                <button
                    @click="submitSale"
                    :disabled="!formValid || submitting"
                    class="px-6 py-2.5 rounded-xl text-sm font-bold bg-[#1D3557] text-white hover:bg-[#162840] disabled:opacity-40 disabled:cursor-not-allowed transition-colors flex items-center gap-2"
                >
                    <i v-if="submitting" class="pi pi-spin pi-spinner text-xs"></i>
                    <i v-else class="pi pi-file-pdf text-xs"></i>
                    {{ submitting ? 'Menyimpan...' : 'Simpan & Cetak Invoice' }}
                </button>
            </div>
        </div>

        <!-- ─── TAB: Belum Dikirim / Sudah Dikirim ─── -->
        <div v-else class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
            <div class="p-4 sm:p-5 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-bold text-slate-700">
                        {{ activeTab === 'belum_dikirim' ? 'Invoice Belum Dikirim' : 'Invoice Sudah Dikirim' }}
                    </h2>
                    <p v-if="activeTab === 'belum_dikirim'" class="text-xs text-amber-600 mt-0.5">Stok belum dipotong. Tandai terkirim untuk memotong stok.</p>
                    <p v-else class="text-xs text-emerald-600 mt-0.5">Stok sudah dipotong saat status diubah menjadi terkirim.</p>
                </div>
            </div>

            <div v-if="loading" class="flex items-center justify-center py-16 text-slate-400 text-sm gap-2">
                <i class="pi pi-spin pi-spinner"></i> Memuat data...
            </div>

            <template v-else>
                <!-- Belum Dikirim -->
                <div v-if="activeTab === 'belum_dikirim'">
                    <div v-if="salesBelumDikirim.length === 0" class="flex flex-col items-center justify-center py-16 text-slate-400">
                        <i class="pi pi-inbox text-3xl mb-2"></i>
                        <p class="text-sm">Tidak ada invoice yang menunggu pengiriman</p>
                    </div>
                    <div v-else>
                        <!-- Mobile cards -->
                        <div class="md:hidden divide-y divide-slate-100">
                            <div v-for="sale in salesBelumDikirim" :key="sale.id" class="p-4 hover:bg-amber-50/30 transition-colors">
                                <div class="flex items-start justify-between gap-3 mb-2">
                                    <div class="min-w-0">
                                        <p class="font-mono text-xs font-bold text-[#1D3557]">{{ sale.invoice_number }}</p>
                                        <p class="font-semibold text-slate-800 mt-0.5">{{ sale.recipient_name }}</p>
                                        <p v-if="sale.recipient_address" class="text-xs text-slate-400">{{ sale.recipient_address }}</p>
                                        <p class="text-xs text-slate-400 mt-0.5">{{ formatDate(sale.invoice_date) }}</p>
                                    </div>
                                    <div class="text-right flex-shrink-0">
                                        <p class="font-bold text-slate-800">{{ fmt(sale.grand_total) }}</p>
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full" :class="paymentStatus(sale).color">{{ paymentStatus(sale).label }}</span>
                                        <p v-if="sisaTagihan(sale) > 0" class="text-xs text-red-500 font-semibold mt-0.5">Sisa: {{ fmt(sisaTagihan(sale)) }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 mt-2 flex-wrap">
                                    <button @click="openShipModal(sale)" class="flex items-center gap-1 text-xs font-bold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 px-3 py-1.5 rounded-lg transition-colors">
                                        <i class="pi pi-send text-xs"></i> Kirim
                                    </button>
                                    <button v-if="sisaTagihan(sale) > 0" @click="openPayModal(sale)" class="text-xs font-bold text-[#1D3557] bg-slate-100 hover:bg-slate-200 px-3 py-1.5 rounded-lg transition-colors">
                                        + Catat Bayar
                                    </button>
                                    <button @click="openEditModal(sale)" class="text-xs font-bold text-slate-500 bg-slate-100 hover:bg-slate-200 px-3 py-1.5 rounded-lg transition-colors">
                                        <i class="pi pi-pencil text-xs"></i> Edit
                                    </button>
                                    <button @click="printInvoice(sale)" class="text-xs font-bold text-[#1D3557] bg-slate-100 hover:bg-slate-200 px-3 py-1.5 rounded-lg transition-colors">
                                        <i class="pi pi-file-pdf text-xs"></i>
                                    </button>
                                    <button @click="deleteSale(sale.id)" class="text-xs font-bold text-red-500 bg-red-50 hover:bg-red-100 px-3 py-1.5 rounded-lg transition-colors">
                                        <i class="pi pi-trash text-xs"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <!-- Desktop table -->
                        <div class="hidden md:block overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 border-b border-slate-200">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wide">No. Invoice</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wide">Penerima</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wide">Tanggal</th>
                                    <th class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase tracking-wide">Grand Total</th>
                                    <th class="px-4 py-3 text-center text-xs font-bold text-slate-500 uppercase tracking-wide">Pembayaran</th>
                                    <th class="px-4 py-3 text-center text-xs font-bold text-slate-500 uppercase tracking-wide">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="sale in salesBelumDikirim" :key="sale.id" class="hover:bg-amber-50/40 transition-colors">
                                    <td class="px-4 py-3 font-mono text-xs font-semibold text-[#1D3557]">{{ sale.invoice_number }}</td>
                                    <td class="px-4 py-3">
                                        <div class="font-semibold text-slate-700">{{ sale.recipient_name }}</div>
                                        <div v-if="sale.recipient_address" class="text-xs text-slate-400">{{ sale.recipient_address }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-slate-600">{{ formatDate(sale.invoice_date) }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="font-bold text-slate-800">{{ fmt(sale.grand_total) }}</div>
                                        <div v-if="sale.paid_amount > 0" class="text-xs text-slate-400">Dibayar: {{ fmt(sale.paid_amount) }}</div>
                                        <div v-if="sisaTagihan(sale) > 0" class="text-xs text-red-500 font-semibold">Sisa: {{ fmt(sisaTagihan(sale)) }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <div class="flex flex-col items-center gap-1">
                                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full" :class="paymentStatus(sale).color">
                                                {{ paymentStatus(sale).label }}
                                            </span>
                                            <button
                                                v-if="sisaTagihan(sale) > 0"
                                                @click="openPayModal(sale)"
                                                class="text-[10px] font-bold text-[#1D3557] hover:underline"
                                            >+ Catat Bayar</button>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <div class="flex items-center justify-center gap-3">
                                            <button
                                                @click="openShipModal(sale)"
                                                class="flex items-center gap-1 text-xs font-bold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 px-2.5 py-1 rounded-lg transition-colors"
                                                title="Tandai Terkirim"
                                            >
                                                <i class="pi pi-send text-xs"></i>
                                                Kirim
                                            </button>
                                            <button @click="openEditModal(sale)" class="text-slate-500 hover:text-[#1D3557] transition-colors" title="Edit">
                                                <i class="pi pi-pencil"></i>
                                            </button>
                                            <button @click="printInvoice(sale)" class="text-[#1D3557] hover:text-[#162840] transition-colors relative" title="Cetak Invoice">
                                                <i class="pi pi-file-pdf"></i>
                                                <span v-if="sale.attachment_path" class="absolute -top-1 -right-1 w-2 h-2 rounded-full bg-emerald-500"></span>
                                            </button>
                                            <button @click="deleteSale(sale.id)" class="text-red-400 hover:text-red-600 transition-colors" title="Hapus">
                                                <i class="pi pi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        </div>
                    </div>
                </div>

                <!-- Sudah Dikirim -->
                <div v-else>
                    <div v-if="salesSudahDikirim.length === 0" class="flex flex-col items-center justify-center py-16 text-slate-400">
                        <i class="pi pi-inbox text-3xl mb-2"></i>
                        <p class="text-sm">Belum ada invoice yang terkirim</p>
                    </div>
                    <div v-else>
                        <!-- Mobile cards -->
                        <div class="md:hidden divide-y divide-slate-100">
                            <div v-for="sale in salesSudahDikirim" :key="sale.id" class="p-4 hover:bg-emerald-50/20 transition-colors">
                                <div class="flex items-start justify-between gap-3 mb-2">
                                    <div class="min-w-0">
                                        <p class="font-mono text-xs font-bold text-[#1D3557]">{{ sale.invoice_number }}</p>
                                        <p class="font-semibold text-slate-800 mt-0.5">{{ sale.recipient_name }}</p>
                                        <p v-if="sale.recipient_address" class="text-xs text-slate-400">{{ sale.recipient_address }}</p>
                                        <p class="text-xs text-slate-400 mt-0.5">Dikirim: {{ sale.shipped_at ? formatDate(sale.shipped_at) : '-' }}</p>
                                    </div>
                                    <div class="text-right flex-shrink-0">
                                        <p class="font-bold text-slate-800">{{ fmt(sale.grand_total) }}</p>
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full" :class="paymentStatus(sale).color">{{ paymentStatus(sale).label }}</span>
                                        <p v-if="sisaTagihan(sale) > 0" class="text-xs text-red-500 font-semibold mt-0.5">Sisa: {{ fmt(sisaTagihan(sale)) }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 mt-2 flex-wrap">
                                    <button v-if="sisaTagihan(sale) > 0" @click="openPayModal(sale)" class="text-xs font-bold text-[#1D3557] bg-slate-100 hover:bg-slate-200 px-3 py-1.5 rounded-lg transition-colors">
                                        + Catat Bayar
                                    </button>
                                    <button @click="openEditModal(sale)" class="text-xs font-bold text-slate-500 bg-slate-100 hover:bg-slate-200 px-3 py-1.5 rounded-lg transition-colors">
                                        <i class="pi pi-pencil text-xs"></i> Edit
                                    </button>
                                    <button @click="printInvoice(sale)" class="text-xs font-bold text-[#1D3557] bg-slate-100 hover:bg-slate-200 px-3 py-1.5 rounded-lg transition-colors">
                                        <i class="pi pi-file-pdf text-xs"></i>
                                    </button>
                                    <button @click="revertStock(sale.id)" class="text-xs font-bold text-amber-600 bg-amber-50 hover:bg-amber-100 px-3 py-1.5 rounded-lg transition-colors">
                                        <i class="pi pi-history text-xs"></i>
                                    </button>
                                    <button @click="deleteSale(sale.id, true)" class="text-xs font-bold text-red-500 bg-red-50 hover:bg-red-100 px-3 py-1.5 rounded-lg transition-colors">
                                        <i class="pi pi-trash text-xs"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <!-- Desktop table -->
                        <div class="hidden md:block overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 border-b border-slate-200">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wide">No. Invoice</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wide">Penerima</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wide">Tgl. Dikirim</th>
                                    <th class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase tracking-wide">Grand Total</th>
                                    <th class="px-4 py-3 text-center text-xs font-bold text-slate-500 uppercase tracking-wide">Pembayaran</th>
                                    <th class="px-4 py-3 text-center text-xs font-bold text-slate-500 uppercase tracking-wide">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="sale in salesSudahDikirim" :key="sale.id" class="hover:bg-emerald-50/30 transition-colors">
                                    <td class="px-4 py-3">
                                        <div class="font-mono text-xs font-semibold text-[#1D3557]">{{ sale.invoice_number }}</div>
                                        <div class="text-[10px] text-slate-400 mt-0.5">Invoice: {{ formatDate(sale.invoice_date) }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-semibold text-slate-700">{{ sale.recipient_name }}</div>
                                        <div v-if="sale.recipient_address" class="text-xs text-slate-400">{{ sale.recipient_address }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-slate-600">{{ sale.shipped_at ? formatDate(sale.shipped_at) : '-' }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="font-bold text-slate-800">{{ fmt(sale.grand_total) }}</div>
                                        <div v-if="sale.paid_amount > 0" class="text-xs text-slate-400">Dibayar: {{ fmt(sale.paid_amount) }}</div>
                                        <div v-if="sisaTagihan(sale) > 0" class="text-xs text-red-500 font-semibold">Sisa: {{ fmt(sisaTagihan(sale)) }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <div class="flex flex-col items-center gap-1">
                                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full" :class="paymentStatus(sale).color">
                                                {{ paymentStatus(sale).label }}
                                            </span>
                                            <button
                                                v-if="sisaTagihan(sale) > 0"
                                                @click="openPayModal(sale)"
                                                class="text-[10px] font-bold text-[#1D3557] hover:underline"
                                            >+ Catat Bayar</button>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <div class="flex items-center justify-center gap-3">
                                            <button @click="openEditModal(sale)" class="text-slate-500 hover:text-[#1D3557] transition-colors" title="Edit">
                                                <i class="pi pi-pencil"></i>
                                            </button>
                                            <button @click="printInvoice(sale)" class="text-[#1D3557] hover:text-[#162840] transition-colors relative" title="Cetak Invoice">
                                                <i class="pi pi-file-pdf"></i>
                                                <span v-if="sale.attachment_path" class="absolute -top-1 -right-1 w-2 h-2 rounded-full bg-emerald-500"></span>
                                            </button>
                                            <button @click="revertStock(sale.id)" class="text-amber-500 hover:text-amber-700 transition-colors" title="Revert Stok (kembalikan ke Belum Dikirim)">
                                                <i class="pi pi-history"></i>
                                            </button>
                                            <button @click="deleteSale(sale.id, true)" class="text-red-400 hover:text-red-600 transition-colors" title="Hapus (stok akan dikembalikan)">
                                                <i class="pi pi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- ─── Modal Edit Invoice ─── -->
        <div v-if="editModal.open" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/40 p-0 sm:p-4">
            <div class="bg-white rounded-t-2xl sm:rounded-2xl shadow-2xl w-full sm:max-w-3xl max-h-[92vh] flex flex-col">
                <!-- Header -->
                <div class="flex items-center justify-between p-5 border-b border-slate-100">
                    <div>
                        <h3 class="text-base font-bold text-slate-800">Edit Invoice</h3>
                        <p class="text-xs text-slate-400 mt-0.5">{{ editModal.sale?.invoice_number }}</p>
                    </div>
                    <button @click="editModal.open = false" class="text-slate-400 hover:text-slate-600 transition-colors">
                        <i class="pi pi-times text-lg"></i>
                    </button>
                </div>

                <!-- Body -->
                <div class="overflow-y-auto p-5 flex flex-col gap-6">

                    <!-- Seksi: Info Penerima -->
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3">Penerima</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 mb-1.5">Nama Penerima <span class="text-red-500">*</span></label>
                                <input v-model="editForm.recipient_name" type="text" class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1D3557]/30 focus:border-[#1D3557]" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 mb-1.5">Dikirim ke (Alamat)</label>
                                <input v-model="editForm.recipient_address" type="text" class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1D3557]/30 focus:border-[#1D3557]" />
                            </div>
                        </div>
                    </div>

                    <!-- Seksi: Tanggal & Catatan -->
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3">Detail Invoice</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 mb-1.5">Tanggal Invoice <span class="text-red-500">*</span></label>
                                <input v-model="editForm.invoice_date" type="date" class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1D3557]/30 focus:border-[#1D3557]" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 mb-1.5">Tanggal Kirim</label>
                                <input v-model="editForm.shipped_at" type="date" class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1D3557]/30 focus:border-[#1D3557]" />
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-semibold text-slate-500 mb-1.5">Catatan</label>
                                <input v-model="editForm.notes" type="text" placeholder="Contoh: DP 10 Juta / Bayar 50%" class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1D3557]/30 focus:border-[#1D3557]" />
                                <p v-if="editParsedPayment > 0" class="mt-1.5 text-[11px] font-semibold text-emerald-700 flex items-center gap-1">
                                    <i class="pi pi-check-circle text-[11px]"></i>
                                    Terdeteksi pembayaran: {{ fmt(editParsedPayment) }}
                                    <span v-if="editModal.sale" class="text-slate-400 font-normal">· sisa {{ fmt((editModal.sale.status === 'belum_dikirim' ? editItems.reduce((s,i) => s + Number(i.qty)*Number(i.unit_price), 0) : editModal.sale.grand_total) - editParsedPayment) }}</span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Seksi: Kop PDF -->
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3">Kop PDF <span class="text-slate-300 font-normal normal-case tracking-normal">— kosongkan untuk pakai PT Indo Pangan</span></p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 mb-1.5">Nama Pengirim</label>
                                <input v-model="editForm.sender_name" type="text" placeholder="PT Indo Pangan" class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1D3557]/30 focus:border-[#1D3557]" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 mb-1.5">Alamat Pengirim</label>
                                <input v-model="editForm.sender_address" type="text" placeholder="Alamat pengirim" class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1D3557]/30 focus:border-[#1D3557]" />
                            </div>
                        </div>
                    </div>

                    <!-- Seksi: Lampiran -->
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3">Lampiran PDF</p>
                        <div v-if="editModal.sale?.attachment_path" class="flex items-center gap-3 mb-2 p-2.5 bg-slate-50 rounded-xl border border-slate-200">
                            <a :href="`/storage/${editModal.sale.attachment_path}`" target="_blank" class="flex items-center gap-1.5 text-xs font-semibold text-[#1D3557] hover:underline flex-1 min-w-0">
                                <i class="pi pi-file-pdf text-red-500 text-sm flex-shrink-0"></i>
                                <span class="truncate">{{ editModal.sale.attachment_path.split('/').pop() }}</span>
                            </a>
                            <button @click="removeAttachment(editModal.sale!)" class="text-red-400 hover:text-red-600 transition-colors flex-shrink-0" title="Hapus lampiran">
                                <i class="pi pi-times text-xs"></i>
                            </button>
                        </div>
                        <div class="flex gap-2">
                            <input
                                type="file"
                                accept="application/pdf"
                                @change="(e) => { attachmentFile = (e.target as HTMLInputElement).files?.[0] ?? null }"
                                class="flex-1 border border-slate-300 rounded-xl px-4 py-2.5 text-sm file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200 cursor-pointer"
                            />
                            <button
                                v-if="attachmentFile"
                                @click="uploadAttachment(editModal.sale!)"
                                :disabled="attachmentUploading"
                                class="flex items-center gap-1.5 bg-[#1D3557] text-white text-xs font-bold px-4 py-2.5 rounded-xl hover:bg-[#162840] disabled:opacity-40 transition-colors whitespace-nowrap"
                            >
                                <i v-if="attachmentUploading" class="pi pi-spin pi-spinner text-xs"></i>
                                <i v-else class="pi pi-upload text-xs"></i>
                                {{ attachmentUploading ? 'Mengunggah...' : 'Upload' }}
                            </button>
                        </div>
                        <p class="text-[11px] text-slate-400 mt-1 pl-1">{{ editModal.sale?.attachment_path ? 'Pilih file baru untuk mengganti.' : 'Hanya file PDF. Maks. 10 MB.' }}</p>
                    </div>

                    <!-- Item (hanya belum dikirim) -->
                    <div v-if="editModal.sale?.status === 'belum_dikirim'">
                        <div class="flex items-center mb-3">
                            <p class="text-sm font-bold text-slate-700">Daftar Item</p>
                        </div>

                        <div class="hidden md:grid grid-cols-[2fr_1fr_6rem_11rem_10rem_2rem] gap-2 px-1 mb-1">
                            <div class="text-[10px] font-bold text-slate-400 uppercase">Nama Barang</div>
                            <div class="text-[10px] font-bold text-slate-400 uppercase">Keterangan</div>
                            <div class="text-[10px] font-bold text-slate-400 uppercase text-right">Qty</div>
                            <div class="text-[10px] font-bold text-slate-400 uppercase text-right">Harga Satuan</div>
                            <div class="text-[10px] font-bold text-slate-400 uppercase text-right">Total</div>
                            <div></div>
                        </div>

                        <div class="flex flex-col gap-2">
                            <div v-for="(item, idx) in editItems" :key="idx"
                                class="bg-slate-50/60 border border-slate-200 rounded-xl px-3 py-3"
                            >
                                <!-- Mobile header -->
                                <div class="flex items-center justify-between mb-2 md:hidden">
                                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wide">Item {{ idx + 1 }}</span>
                                    <button @click="removeEditItem(idx)" :disabled="editItems.length === 1"
                                        class="text-red-400 hover:text-red-600 disabled:opacity-25 disabled:cursor-not-allowed transition-colors p-1"
                                    ><i class="pi pi-trash text-sm"></i></button>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-[2fr_1fr_6rem_11rem_10rem_2rem] gap-2 items-center">
                                <!-- Nama Barang -->
                                <div class="relative">
                                    <label class="block md:hidden text-[10px] font-bold text-slate-400 uppercase mb-1">Nama Barang</label>
                                    <div class="flex items-center border rounded-lg px-3 py-2 gap-2 bg-white focus-within:ring-1 focus-within:ring-[#1D3557]/30 focus-within:border-[#1D3557]"
                                        :class="item.item_name ? 'border-[#1D3557]/40' : 'border-slate-200'"
                                    >
                                        <i class="pi pi-search text-slate-400 text-xs flex-shrink-0"></i>
                                        <input
                                            v-model="editSearchQueries[idx]"
                                            @input="onEditSearchInput(idx)"
                                            @focus="editDropdownOpen[idx] = true"
                                            @blur="closeEditDropdown(idx)"
                                            type="text" placeholder="Cari barang..."
                                            class="w-full text-sm bg-transparent focus:outline-none min-w-0"
                                        />
                                        <i v-if="item.item_name" class="pi pi-check-circle text-emerald-500 text-xs flex-shrink-0"></i>
                                    </div>
                                    <div v-if="editDropdownOpen[idx] && suggestions(idx).length > 0"
                                        class="absolute top-full left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-xl z-50 overflow-y-auto max-h-44"
                                    >
                                        <div v-for="(sug, si) in suggestions(idx)" :key="si"
                                            @mousedown.prevent="selectEditSuggestion(idx, sug)"
                                            class="flex items-center justify-between px-3 py-2 hover:bg-slate-50 cursor-pointer border-b border-slate-100 last:border-0"
                                        >
                                            <div class="flex items-center gap-2 min-w-0">
                                                <span v-if="sug.type === 'set'" class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-amber-100 text-amber-700 uppercase flex-shrink-0">Set</span>
                                                <p class="text-sm font-semibold text-slate-700 truncate">{{ sug.label }}</p>
                                            </div>
                                            <p class="text-xs font-bold text-[#1D3557] flex-shrink-0 ml-2">{{ fmt(sug.unit_price) }}</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Keterangan -->
                                <div>
                                    <label class="block md:hidden text-[10px] font-bold text-slate-400 uppercase mb-1">Keterangan</label>
                                    <input v-model="item.description" type="text" placeholder="Keterangan"
                                        class="w-full border border-slate-200 bg-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#1D3557]/30 focus:border-[#1D3557]"
                                    />
                                </div>

                                <!-- Qty + Harga berdampingan di mobile -->
                                <div class="grid grid-cols-2 gap-2 md:contents">
                                    <div>
                                        <label class="block md:hidden text-[10px] font-bold text-slate-400 uppercase mb-1">Qty</label>
                                        <input v-model.number="item.qty" type="number" min="1" placeholder="0"
                                            class="w-full border border-slate-200 bg-white rounded-lg px-3 py-2 text-sm text-right focus:outline-none focus:ring-1 focus:ring-[#1D3557]/30 focus:border-[#1D3557]"
                                        />
                                    </div>
                                    <div>
                                        <label class="block md:hidden text-[10px] font-bold text-slate-400 uppercase mb-1">Harga Satuan</label>
                                        <div class="relative">
                                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm pointer-events-none">Rp</span>
                                            <input
                                                :value="editPriceDisplays[idx]"
                                                @input="onEditPriceInput(idx, $event)"
                                                @focus="onEditPriceFocus(idx, $event)"
                                                @blur="onEditPriceBlur(idx, $event)"
                                                type="text" inputmode="numeric" placeholder="0"
                                                class="w-full border border-slate-200 bg-white rounded-lg pl-8 pr-3 py-2 text-sm text-right focus:outline-none focus:ring-1 focus:ring-[#1D3557]/30 focus:border-[#1D3557]"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <!-- Total -->
                                <div>
                                    <label class="block md:hidden text-[10px] font-bold text-slate-400 uppercase mb-1">Total</label>
                                    <div class="text-right text-sm font-bold text-slate-700 whitespace-nowrap">
                                        {{ (Number(item.qty) > 0 && Number(item.unit_price) > 0) ? fmt(Number(item.qty) * Number(item.unit_price)) : '—' }}
                                    </div>
                                </div>

                                <!-- Hapus (desktop) -->
                                <button @click="removeEditItem(idx)" :disabled="editItems.length === 1"
                                    class="hidden md:flex text-red-400 hover:text-red-600 disabled:opacity-25 disabled:cursor-not-allowed transition-colors p-1 justify-center"
                                ><i class="pi pi-trash text-sm"></i></button>
                                </div><!-- end grid -->
                            </div>
                        </div>

                        <div class="mt-2">
                            <button @click="addEditItem" class="w-full flex items-center justify-center gap-1.5 border border-dashed border-slate-300 text-slate-500 hover:border-[#1D3557] hover:text-[#1D3557] text-xs font-bold px-3 py-2.5 rounded-xl transition-colors">
                                <i class="pi pi-plus text-xs"></i> Tambah Item
                            </button>
                        </div>

                        <div class="flex justify-end mt-3 pt-3 border-t border-slate-100">
                            <div class="flex items-center gap-4">
                                <span class="text-sm font-bold text-slate-600">Grand Total</span>
                                <span class="bg-[#1D3557] text-white text-sm font-bold px-5 py-2 rounded-xl">{{ fmt(editGrandTotal) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Info untuk sudah dikirim -->
                    <div v-else class="text-xs text-slate-400 bg-slate-50 rounded-xl px-4 py-3">
                        Item tidak bisa diubah karena stok sudah dipotong.
                    </div>
                </div>

                <!-- Footer -->
                <div class="flex gap-3 p-5 border-t border-slate-100">
                    <button @click="editModal.open = false" class="flex-1 border border-slate-300 text-slate-600 text-sm font-bold py-2.5 rounded-xl hover:bg-slate-50 transition-colors">
                        Batal
                    </button>
                    <button
                        v-if="editModal.sale && editModal.sale.paid_amount > 0"
                        @click="() => { editModal.open = false; openPayModal(editModal.sale!, 'edit'); }"
                        class="flex-1 border border-amber-400 text-amber-600 text-sm font-bold py-2.5 rounded-xl hover:bg-amber-50 transition-colors"
                    >
                        Koreksi Bayar
                    </button>
                    <button @click="submitEdit" :disabled="editSubmitting || !editForm.recipient_name || !editForm.invoice_date"
                        class="flex-1 bg-[#1D3557] text-white text-sm font-bold py-2.5 rounded-xl hover:bg-[#162840] disabled:opacity-40 transition-colors flex items-center justify-center gap-2"
                    >
                        <i v-if="editSubmitting" class="pi pi-spin pi-spinner text-xs"></i>
                        {{ editSubmitting ? 'Menyimpan...' : 'Simpan Perubahan' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- ─── Modal Konfirmasi Kirim ─── -->
        <div v-if="shipModal.open" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/40 p-0 sm:p-4">
            <div class="bg-white rounded-t-2xl sm:rounded-2xl shadow-2xl p-6 w-full sm:max-w-sm">
                <h3 class="text-base font-bold text-slate-800 mb-1">Konfirmasi Pengiriman</h3>
                <p class="text-xs text-slate-500 mb-4">{{ shipModal.sale?.invoice_number }} — {{ shipModal.sale?.recipient_name }}</p>

                <div class="bg-slate-50 rounded-xl p-3 mb-4 space-y-1.5">
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-500">Grand Total</span>
                        <span class="font-bold text-slate-800">{{ fmt(shipModal.sale?.grand_total ?? 0) }}</span>
                    </div>
                    <div v-if="(shipModal.sale?.paid_amount ?? 0) > 0" class="flex justify-between text-sm">
                        <span class="text-slate-500">Sudah Dibayar</span>
                        <span class="font-bold text-emerald-700">{{ fmt(shipModal.sale?.paid_amount ?? 0) }}</span>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-xs font-semibold text-slate-500 mb-1.5">Tanggal Kirim</label>
                    <input
                        v-model="shipModal.shippedAt"
                        type="date"
                        class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1D3557]/30 focus:border-[#1D3557]"
                    />
                </div>

                <div class="flex items-center justify-between mb-1.5">
                    <label class="text-xs font-semibold text-slate-500">Jumlah Pembayaran</label>
                    <button
                        @click="() => { const sisa = (shipModal.sale!.grand_total - shipModal.sale!.paid_amount); shipModal.inputRaw = String(sisa); shipModal.display = sisa.toLocaleString('id-ID'); }"
                        class="text-[10px] font-bold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 px-2.5 py-1 rounded-lg transition-colors"
                    >Lunas Penuh</button>
                </div>
                <div class="relative mb-3">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm pointer-events-none">Rp</span>
                    <input
                        :value="shipModal.display"
                        @input="onShipPayInput($event)"
                        type="text"
                        inputmode="numeric"
                        placeholder="0"
                        class="w-full border border-slate-300 rounded-xl pl-8 pr-3 py-2.5 text-sm text-right focus:outline-none focus:ring-2 focus:ring-[#1D3557]/30 focus:border-[#1D3557]"
                    />
                </div>

                <div v-if="shipKeteranganAuto" class="bg-red-50 border border-red-200 rounded-xl px-4 py-2.5 mb-4">
                    <p class="text-xs font-semibold text-red-600">Keterangan otomatis:</p>
                    <p class="text-sm font-bold text-red-700 mt-0.5">{{ shipKeteranganAuto }}</p>
                </div>

                <div class="flex gap-3">
                    <button @click="shipModal.open = false" class="flex-1 border border-slate-300 text-slate-600 text-sm font-bold py-2.5 rounded-xl hover:bg-slate-50 transition-colors">
                        Batal
                    </button>
                    <button
                        @click="confirmShip"
                        class="flex-1 bg-emerald-600 text-white text-sm font-bold py-2.5 rounded-xl hover:bg-emerald-700 transition-colors"
                    >
                        Kirim
                    </button>
                </div>
            </div>
        </div>

        <!-- ─── Modal Catat Pembayaran ─── -->
        <div v-if="payModal.open" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/40 p-0 sm:p-4">
            <div class="bg-white rounded-t-2xl sm:rounded-2xl shadow-2xl p-6 w-full sm:max-w-sm">
                <h3 class="text-base font-bold text-slate-800 mb-1">{{ payModal.mode === 'edit' ? 'Koreksi Pembayaran' : 'Catat Pembayaran' }}</h3>
                <p class="text-xs text-slate-500 mb-4">{{ payModal.sale?.invoice_number }} — {{ payModal.sale?.recipient_name }}</p>

                <div class="space-y-3 mb-5">
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-500">Grand Total</span>
                        <span class="font-bold text-slate-800">{{ fmt(payModal.sale?.grand_total ?? 0) }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-500">Sudah Dibayar</span>
                        <span class="font-bold text-emerald-700">{{ fmt(payModal.sale?.paid_amount ?? 0) }}</span>
                    </div>
                    <div class="flex justify-between text-sm border-t pt-2">
                        <span class="text-slate-500 font-semibold">Sisa Tagihan</span>
                        <span class="font-bold text-red-600">{{ fmt(sisaTagihan(payModal.sale!)) }}</span>
                    </div>
                </div>

                <div class="flex items-center justify-between mb-1.5">
                    <label class="text-xs font-semibold text-slate-500">Jumlah Pembayaran</label>
                    <button
                        @click="() => { const sisa = sisaTagihan(payModal.sale!); payModal.inputRaw = String(sisa); payModal.display = sisa.toLocaleString('id-ID'); }"
                        class="text-[10px] font-bold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 px-2.5 py-1 rounded-lg transition-colors"
                    >Lunas</button>
                </div>
                <div class="relative mb-5">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm pointer-events-none">Rp</span>
                    <input
                        :value="payModal.display"
                        @input="onPayInput($event)"
                        type="text"
                        inputmode="numeric"
                        placeholder="0"
                        class="w-full border border-slate-300 rounded-xl pl-8 pr-3 py-2.5 text-sm text-right focus:outline-none focus:ring-2 focus:ring-[#1D3557]/30 focus:border-[#1D3557]"
                    />
                </div>

                <div class="flex gap-3">
                    <button @click="payModal.open = false" class="flex-1 border border-slate-300 text-slate-600 text-sm font-bold py-2.5 rounded-xl hover:bg-slate-50 transition-colors">
                        Batal
                    </button>
                    <button
                        @click="submitPayment"
                        :disabled="!payModal.inputRaw"
                        class="flex-1 bg-[#1D3557] text-white text-sm font-bold py-2.5 rounded-xl hover:bg-[#162840] disabled:opacity-40 transition-colors"
                    >
                        Simpan
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirm Modal -->
    <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100" leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
        <div v-if="confirmModal.show" class="fixed inset-0 z-[70] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40" @click="confirmModal.show = false"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl p-6 w-full max-w-sm">
                <div class="flex items-start gap-4 mb-5">
                    <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center shrink-0">
                        <i class="pi pi-exclamation-triangle text-red-600"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-800 text-sm">{{ confirmModal.title }}</h3>
                        <p class="text-xs text-slate-500 mt-1 leading-relaxed">{{ confirmModal.message }}</p>
                    </div>
                </div>
                <div class="flex gap-3">
                    <button @click="confirmModal.show = false" class="flex-1 border border-slate-300 text-slate-600 text-sm font-bold py-2.5 rounded-xl hover:bg-slate-50 transition-colors">
                        Batal
                    </button>
                    <button @click="() => { confirmModal.onConfirm(); confirmModal.show = false; }" class="flex-1 bg-red-600 text-white text-sm font-bold py-2.5 rounded-xl hover:bg-red-700 transition-colors">
                        Ya, Hapus
                    </button>
                </div>
            </div>
        </div>
    </Transition>

    <!-- Toast Notification -->
    <Transition enter-active-class="transition ease-out duration-300" enter-from-class="opacity-0 translate-y-4" enter-to-class="opacity-100 translate-y-0" leave-active-class="transition ease-in duration-200" leave-from-class="opacity-100 translate-y-0" leave-to-class="opacity-0 translate-y-4">
        <div v-if="toast.show" class="fixed bottom-6 left-1/2 -translate-x-1/2 z-[60] flex items-center gap-3 px-5 py-3.5 rounded-2xl shadow-2xl text-sm font-semibold max-w-sm w-full mx-4"
            :class="toast.type === 'error' ? 'bg-red-600 text-white' : 'bg-emerald-600 text-white'">
            <i :class="toast.type === 'error' ? 'pi pi-exclamation-circle' : 'pi pi-check-circle'" class="text-base shrink-0"></i>
            <span class="flex-1">{{ toast.message }}</span>
            <button @click="toast.show = false" class="opacity-60 hover:opacity-100 transition-opacity shrink-0">
                <i class="pi pi-times text-xs"></i>
            </button>
        </div>
    </Transition>

    <!-- Import Items Preview Modal -->
    <div v-if="importItemsModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[90vh] flex flex-col">
            <div class="flex items-center justify-between p-6 border-b border-slate-100">
                <h3 class="text-lg font-bold text-[#1D3557]">Preview Import Item</h3>
                <button @click="importItemsModal = false" class="text-slate-400 hover:text-slate-600 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="overflow-auto flex-1 p-6">
                <p v-if="importItemsError" class="text-red-600 text-sm mb-4">{{ importItemsError }}</p>
                <p class="text-sm text-slate-500 mb-4">{{ importItemsPreview.length }} item ditemukan. Klik Konfirmasi untuk menambahkan ke daftar item.</p>
                <table class="w-full text-sm border-collapse">
                    <thead>
                        <tr class="bg-slate-50">
                            <th class="text-left px-3 py-2 border border-slate-200 font-semibold text-slate-600">#</th>
                            <th class="text-left px-3 py-2 border border-slate-200 font-semibold text-slate-600">Nama Barang</th>
                            <th class="text-left px-3 py-2 border border-slate-200 font-semibold text-slate-600">Keterangan</th>
                            <th class="text-right px-3 py-2 border border-slate-200 font-semibold text-slate-600">Qty</th>
                            <th class="text-right px-3 py-2 border border-slate-200 font-semibold text-slate-600">Harga Satuan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(item, i) in importItemsPreview" :key="i" class="hover:bg-slate-50">
                            <td class="px-3 py-2 border border-slate-200 text-slate-500">{{ i + 1 }}</td>
                            <td class="px-3 py-2 border border-slate-200">{{ item.item_name }}</td>
                            <td class="px-3 py-2 border border-slate-200 text-slate-500">{{ item.description }}</td>
                            <td class="px-3 py-2 border border-slate-200 text-right">{{ item.qty }}</td>
                            <td class="px-3 py-2 border border-slate-200 text-right">{{ Number(item.unit_price).toLocaleString('id-ID') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="flex gap-3 p-6 border-t border-slate-100">
                <button @click="importItemsModal = false" class="flex-1 border border-slate-300 text-slate-600 text-sm font-bold py-2.5 rounded-xl hover:bg-slate-50 transition-colors">
                    Batal
                </button>
                <button @click="confirmImportItems" :disabled="importItemsPreview.length === 0" class="flex-1 bg-[#1D3557] text-white text-sm font-bold py-2.5 rounded-xl hover:bg-[#162840] disabled:opacity-40 transition-colors">
                    Konfirmasi ({{ importItemsPreview.length }} item)
                </button>
            </div>
        </div>
    </div>
</template>
