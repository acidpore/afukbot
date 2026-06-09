<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { suratJalanApi, type InvoiceWithProgress, type SuratJalan, type SaleProgress, type CompletedInvoice } from '../../../api/surat-jalan.api';
import { inventoryApi } from '../../../api/inventory.api';

// ── State ──────────────────────────────────────────────────
const activeTab      = ref<'aktif' | 'riwayat'>('aktif');
const invoices       = ref<InvoiceWithProgress[]>([]);
const completed      = ref<CompletedInvoice[]>([]);
const inventoryStock = ref<Record<number, number>>({});
const loading        = ref(false);
const loadingRiwayat = ref(false);
const expandedSJ     = ref<Record<number, boolean>>({});  // invoice_id → expanded

// ── Toast ──────────────────────────────────────────────────
const toast = ref<{ show: boolean; message: string; type: 'success' | 'error' }>({ show: false, message: '', type: 'success' });
let toastTimer: ReturnType<typeof setTimeout>;
function showToast(message: string, type: 'success' | 'error' = 'success') {
    clearTimeout(toastTimer);
    toast.value = { show: true, message, type };
    toastTimer = setTimeout(() => { toast.value.show = false; }, 4000);
}

// ── Confirm Modal ─────────────────────────────────────────
const confirmModal = ref<{ show: boolean; message: string; onConfirm: () => void }>({ show: false, message: '', onConfirm: () => {} });
function showConfirm(message: string, onConfirm: () => void) {
    confirmModal.value = { show: true, message, onConfirm };
}

// ── Detail Panel ──────────────────────────────────────────
const detailInvoice = ref<InvoiceWithProgress | null>(null);
const detailSjs     = ref<SuratJalan[]>([]);
const detailLoading = ref(false);

// ── Create SJ Modal ───────────────────────────────────────
const createModal = ref(false);
const createForm  = ref({
    sale_id:       0,
    tanggal_kirim: new Date().toISOString().slice(0, 10),
    catatan:       '',
    items:         [] as { sale_item_id: number; item_name: string; qty_order: number; qty_sisa: number; qty_kirim: number }[],
});
const creating = ref(false);

// ── Helpers ───────────────────────────────────────────────
const formatRp = (v: number) => 'Rp ' + v.toLocaleString('id-ID');
const formatDateTime = (s: string) => {
    const d = new Date(s);
    const dd  = String(d.getDate()).padStart(2, '0');
    const mm  = String(d.getMonth() + 1).padStart(2, '0');
    const hh  = String(d.getHours()).padStart(2, '0');
    const min = String(d.getMinutes()).padStart(2, '0');
    return `${dd}/${mm}/${d.getFullYear()} ${hh}.${min}`;
};

function progressPct(p: SaleProgress) {
    if (p.qty_total_order === 0) return 0;
    return Math.round((p.qty_total_kirim / p.qty_total_order) * 100);
}

// ── Filter ────────────────────────────────────────────────
const activeFilter = ref<'semua' | 'belum' | 'prioritas'>('prioritas');
const searchQuery  = ref('');

const filteredInvoices = computed(() => {
    let list = invoices.value;
    if (activeFilter.value === 'belum')     list = list.filter(i => i.progress.qty_total_kirim === 0);
    if (activeFilter.value === 'prioritas') list = list.filter(i => i.paid_amount > 0 || i.progress.qty_total_kirim > 0);
    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase();
        list = list.filter(i => i.recipient_name.toLowerCase().includes(q) || i.invoice_number.toLowerCase().includes(q));
    }
    return list;
});

// ── Summary panel ─────────────────────────────────────────
const pickingExpanded = ref(false);

const summary = computed(() => {
    const today = new Date(); today.setHours(0,0,0,0);
    const urgent = invoices.value.filter(i => {
        const d = new Date(i.invoice_date); d.setHours(0,0,0,0);
        return Math.floor((today.getTime() - d.getTime()) / 86400000) >= 7 && i.progress.qty_total_sisa > 0;
    });
    const nilaiSisa = invoices.value.filter(i => i.paid_amount > 0).reduce((s, i) => {
        const pct = i.progress.qty_total_order > 0 ? i.progress.qty_total_sisa / i.progress.qty_total_order : 0;
        return s + Math.round(i.grand_total * pct);
    }, 0);

    // Picking list: gabung semua item sisa, group by nama barang
    const itemMap: Record<string, {
        item_name: string;
        total_qty: number;
        stok: number | null;   // null = tidak terhubung ke inventory
        inventory_item_ids: number[];
        invoices: { recipient_name: string; invoice_number: string; qty_sisa: number }[];
    }> = {};
    for (const inv of invoices.value.filter(i => i.paid_amount > 0)) {
        for (const item of inv.items) {
            const sisa = inv.progress.items[item.id]?.qty_sisa ?? 0;
            if (sisa <= 0) continue;
            const key = item.item_name.toLowerCase().trim();
            if (!itemMap[key]) itemMap[key] = { item_name: item.item_name, total_qty: 0, stok: null, inventory_item_ids: [], invoices: [] };
            itemMap[key].total_qty += sisa;
            itemMap[key].invoices.push({ recipient_name: inv.recipient_name, invoice_number: inv.invoice_number, qty_sisa: sisa });
            for (const id of (item.inventory_item_ids ?? [])) {
                if (!itemMap[key].inventory_item_ids.includes(id)) itemMap[key].inventory_item_ids.push(id);
            }
        }
    }
    // Resolve stok dari inventoryStock lookup
    for (const entry of Object.values(itemMap)) {
        if (entry.inventory_item_ids.length > 0) {
            entry.stok = entry.inventory_item_ids.reduce((s, id) => s + (inventoryStock.value[id] ?? 0), 0);
        }
    }
    const pickingList = Object.values(itemMap).sort((a, b) => b.total_qty - a.total_qty);
    const totalItemJenis = pickingList.length;
    const totalItemQty   = pickingList.reduce((s, i) => s + i.total_qty, 0);

    return { urgent, nilaiSisa, pickingList, totalItemJenis, totalItemQty };
});

function daysSince(dateStr: string) {
    const d = new Date(dateStr); d.setHours(0,0,0,0);
    const today = new Date(); today.setHours(0,0,0,0);
    return Math.floor((today.getTime() - d.getTime()) / 86400000);
}

const createInvoice = computed(() => invoices.value.find(i => i.id === createForm.value.sale_id));
const createTotalKirim = computed(() => createForm.value.items.reduce((s, i) => s + (i.qty_kirim || 0), 0));

// ── Fetch ─────────────────────────────────────────────────
async function fetchRiwayat() {
    loadingRiwayat.value = true;
    try {
        const res = await suratJalanApi.getCompletedInvoices();
        completed.value = res.data.data;
    } finally {
        loadingRiwayat.value = false;
    }
}

async function switchTab(tab: 'aktif' | 'riwayat') {
    activeTab.value = tab;
    if (tab === 'riwayat' && completed.value.length === 0) fetchRiwayat();
}

async function fetchInvoices() {
    loading.value = true;
    try {
        const [sjRes, invRes] = await Promise.all([
            suratJalanApi.getInvoicesProgress(),
            inventoryApi.getItems(),
        ]);
        invoices.value = sjRes.data.data;
        inventoryStock.value = Object.fromEntries(
            invRes.data.data.map((item: any) => [item.id, item.quantity])
        );
        // Refresh detail panel jika terbuka
        if (detailInvoice.value) {
            const updated = invoices.value.find(i => i.id === detailInvoice.value!.id);
            if (updated) detailInvoice.value = updated;
            else detailInvoice.value = null;
        }
    } catch {
        showToast('Gagal memuat data.', 'error');
    } finally {
        loading.value = false;
    }
}

async function openDetail(inv: InvoiceWithProgress) {
    detailInvoice.value = inv;
    detailLoading.value = true;
    try {
        const res = await suratJalanApi.getBySale(inv.id);
        detailSjs.value = res.data.data.surat_jalans;
        detailInvoice.value = { ...inv, progress: res.data.data.progress };
    } finally {
        detailLoading.value = false;
    }
}

function closeDetail() {
    detailInvoice.value = null;
    detailSjs.value = [];
}

// ── Create ────────────────────────────────────────────────
function openCreate(inv: InvoiceWithProgress) {
    createForm.value = {
        sale_id:       inv.id,
        tanggal_kirim: new Date().toISOString().slice(0, 10),
        catatan:       '',
        items: inv.items
            .filter(i => (inv.progress.items[i.id]?.qty_sisa ?? 0) > 0)
            .map(i => ({
                sale_item_id: i.id,
                item_name:    i.item_name,
                qty_order:    i.qty,
                qty_sisa:     inv.progress.items[i.id]?.qty_sisa ?? 0,
                qty_kirim:    inv.progress.items[i.id]?.qty_sisa ?? 0,
            })),
    };
    createModal.value = true;
}

function setAllQty(val: 'all' | 'none') {
    createForm.value.items.forEach(i => { i.qty_kirim = val === 'all' ? i.qty_sisa : 0; });
}

function clampQty(item: typeof createForm.value.items[0]) {
    if (item.qty_kirim < 0 || isNaN(item.qty_kirim)) item.qty_kirim = 0;
    if (item.qty_kirim > item.qty_sisa) item.qty_kirim = item.qty_sisa;
}

async function submitCreate() {
    const validItems = createForm.value.items.filter(i => i.qty_kirim > 0);
    if (validItems.length === 0) { showToast('Tidak ada item yang akan dikirim.', 'error'); return; }

    creating.value = true;
    try {
        await suratJalanApi.create({
            sale_id:       createForm.value.sale_id,
            tanggal_kirim: createForm.value.tanggal_kirim,
            catatan:       createForm.value.catatan || undefined,
            items:         validItems.map(i => ({ sale_item_id: i.sale_item_id, qty_kirim: i.qty_kirim })),
        });
        createModal.value = false;
        showToast('Surat Jalan berhasil dibuat!');
        await fetchInvoices();
        if (detailInvoice.value?.id === createForm.value.sale_id) {
            const updated = invoices.value.find(i => i.id === createForm.value.sale_id);
            if (updated) await openDetail(updated);
            else closeDetail();
        }
    } catch (e: any) {
        showToast(e?.response?.data?.message || 'Gagal membuat Surat Jalan.', 'error');
    } finally {
        creating.value = false;
    }
}

async function deleteSJ(sj: SuratJalan) {
    showConfirm(`Hapus ${sj.nomor_sj}? Stok akan otomatis dikembalikan.`, async () => {
        confirmModal.value.show = false;
        try {
            await suratJalanApi.remove(sj.id);
            showToast(`${sj.nomor_sj} berhasil dihapus.`);
            await Promise.all([fetchInvoices(), fetchRiwayat()]);
            if (detailInvoice.value) {
                const updated = invoices.value.find(i => i.id === detailInvoice.value!.id);
                if (updated) await openDetail(updated);
                else closeDetail();
            }
        } catch (e: any) {
            showToast(e?.response?.data?.message || 'Gagal menghapus.', 'error');
        }
    });
}

onMounted(fetchInvoices);
</script>

<template>
  <div class="space-y-6">

    <!-- ── Toast ──────────────────────────────────────────── -->
    <Transition name="toast">
      <div
        v-if="toast.show"
        class="fixed top-4 right-4 z-[100] flex items-center gap-3 px-5 py-3.5 rounded-2xl shadow-2xl text-sm font-semibold max-w-xs"
        :class="toast.type === 'success' ? 'bg-[#1D3557] text-white' : 'bg-red-600 text-white'"
      >
        <i :class="toast.type === 'success' ? 'pi pi-check-circle' : 'pi pi-exclamation-triangle'" class="text-base"></i>
        {{ toast.message }}
      </div>
    </Transition>

    <!-- ── Confirm Modal ──────────────────────────────────── -->
    <Transition name="fade">
      <div v-if="confirmModal.show" class="fixed inset-0 z-[90] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="confirmModal.show = false"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6 space-y-4">
          <div class="flex items-start gap-3">
            <div class="w-10 h-10 rounded-full bg-red-50 flex items-center justify-center shrink-0">
              <i class="pi pi-trash text-red-500"></i>
            </div>
            <div>
              <p class="font-bold text-slate-800">Konfirmasi Hapus</p>
              <p class="text-sm text-slate-500 mt-1">{{ confirmModal.message }}</p>
            </div>
          </div>
          <div class="flex gap-2 justify-end">
            <button @click="confirmModal.show = false" class="px-4 py-2 rounded-xl text-sm font-semibold bg-slate-100 text-slate-600 hover:bg-slate-200 transition-colors">Batal</button>
            <button @click="confirmModal.onConfirm()" class="px-4 py-2 rounded-xl text-sm font-semibold bg-red-600 text-white hover:bg-red-700 transition-colors">Ya, Hapus</button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- ── Header ─────────────────────────────────────────── -->
    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 text-[#457B9D] font-semibold text-[10px] uppercase tracking-[0.3em] mb-2">
          <span class="w-8 h-[1px] bg-[#457B9D]/30"></span>
          Logistik
        </div>
        <h2 class="text-3xl font-display font-bold text-[#1D3557]">Surat Jalan</h2>
        <p class="text-slate-500 mt-1 text-sm">Kelola pengiriman parsial per invoice.</p>
      </div>
      <!-- Tab switcher -->
      <div class="flex bg-slate-100 rounded-xl p-1 gap-1 self-start sm:self-auto">
        <button
          @click="switchTab('aktif')"
          class="px-4 py-2 rounded-lg text-sm font-bold transition-all"
          :class="activeTab === 'aktif' ? 'bg-white text-[#1D3557] shadow-sm' : 'text-slate-500 hover:text-slate-700'"
        >
          <i class="pi pi-truck mr-1.5 text-xs"></i>Aktif
          <span v-if="invoices.length" class="ml-1.5 text-[10px] font-bold bg-[#1D3557] text-white px-1.5 py-0.5 rounded-full">{{ invoices.length }}</span>
        </button>
        <button
          @click="switchTab('riwayat')"
          class="px-4 py-2 rounded-lg text-sm font-bold transition-all"
          :class="activeTab === 'riwayat' ? 'bg-white text-[#1D3557] shadow-sm' : 'text-slate-500 hover:text-slate-700'"
        >
          <i class="pi pi-history mr-1.5 text-xs"></i>Riwayat
        </button>
      </div>
    </div>

    <!-- ── Summary Panel ─────────────────────────────────── -->
    <div v-if="!loading && invoices.length > 0" class="space-y-3">

      <!-- Alert urgent (invoice >= 7 hari belum selesai) -->
      <div
        v-if="summary.urgent.length > 0"
        class="flex items-start gap-3 bg-red-50 border border-red-200 rounded-2xl px-4 py-3.5"
      >
        <div class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center shrink-0 mt-0.5">
          <i class="pi pi-clock text-red-500 text-sm"></i>
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-bold text-red-700">{{ summary.urgent.length }} invoice sudah menunggu lebih dari 7 hari</p>
          <div class="flex flex-wrap gap-x-3 gap-y-0.5 mt-1">
            <button
              v-for="inv in summary.urgent.slice(0, 3)"
              :key="inv.id"
              @click="openDetail(inv)"
              class="text-xs text-red-500 hover:text-red-700 font-medium underline underline-offset-2"
            >
              {{ inv.recipient_name }} ({{ daysSince(inv.invoice_date) }}h)
            </button>
            <span v-if="summary.urgent.length > 3" class="text-xs text-red-400">+{{ summary.urgent.length - 3 }} lainnya</span>
          </div>
        </div>
      </div>

      <!-- Ringkasan nilai + invoice terlama -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <!-- Nilai barang belum dikirim -->
        <div class="flex items-center gap-4 bg-white border border-slate-100 rounded-2xl px-5 py-4 shadow-sm">
          <div class="w-10 h-10 rounded-xl bg-[#1D3557]/8 flex items-center justify-center shrink-0">
            <i class="pi pi-box text-[#1D3557]"></i>
          </div>
          <div>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Estimasi Nilai Belum Kirim</p>
            <p class="text-lg font-bold text-[#1D3557] mt-0.5">{{ formatRp(summary.nilaiSisa) }}</p>
            <p class="text-[10px] text-slate-400">dari {{ invoices.filter(i => i.paid_amount > 0).length }} invoice sudah DP</p>
          </div>
        </div>

        <!-- Picking list — buka modal -->
        <button
          @click="pickingExpanded = true"
          class="flex items-center gap-4 bg-white border border-slate-100 rounded-2xl px-5 py-4 shadow-sm hover:border-[#1D3557]/20 hover:shadow-md transition-all text-left group w-full"
        >
          <div class="w-10 h-10 rounded-xl bg-[#1D3557]/8 flex items-center justify-center shrink-0">
            <i class="pi pi-list-check text-[#1D3557]"></i>
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Barang yang Harus Disiapkan</p>
            <p class="text-sm font-bold text-[#1D3557] mt-0.5">
              {{ summary.totalItemQty }} pcs
              <span class="text-slate-400 font-normal">· {{ summary.totalItemJenis }} jenis barang</span>
            </p>
          </div>
          <i class="pi pi-arrow-up-right text-slate-300 group-hover:text-[#1D3557] transition-colors shrink-0"></i>
        </button>
      </div>
    </div>

    <!-- ── Search + Filter ────────────────────────────────── -->
    <div v-if="!loading && invoices.length > 0" class="flex flex-col gap-2">
      <!-- Search -->
      <div class="flex items-center gap-2 bg-white border border-slate-200 rounded-xl px-3.5 py-2.5 focus-within:border-[#1D3557] focus-within:ring-2 focus-within:ring-[#1D3557]/10 transition-all">
        <i class="pi pi-search text-slate-400 text-sm shrink-0"></i>
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Cari nama customer atau nomor invoice..."
          class="flex-1 text-sm bg-transparent focus:outline-none text-slate-700 placeholder-slate-400"
        />
        <button v-if="searchQuery" @click="searchQuery = ''" class="text-slate-300 hover:text-slate-500 transition-colors">
          <i class="pi pi-times text-sm"></i>
        </button>
      </div>
      <!-- Filter chips -->
      <div class="flex gap-1.5 flex-wrap">
        <button
          v-for="f in ([{ key: 'semua', label: 'Semua' }, { key: 'prioritas', label: 'Prioritas' }, { key: 'belum', label: 'Belum Kirim' }] as const)"
          :key="f.key"
          @click="activeFilter = f.key"
          class="px-3 py-1.5 rounded-lg text-[11px] font-bold transition-all whitespace-nowrap"
          :class="activeFilter === f.key ? 'bg-[#1D3557] text-white shadow-sm' : 'bg-white border border-slate-200 text-slate-500 hover:border-slate-300'"
        >{{ f.label }}</button>
      </div>
    </div>

    <!-- ════ TAB: AKTIF ════ -->
    <template v-if="activeTab === 'aktif'">

    <!-- ── Loading ────────────────────────────────────────── -->
    <div v-if="loading" class="flex items-center justify-center py-20 gap-3 text-slate-400">
      <i class="pi pi-spin pi-spinner text-2xl"></i>
      <span class="text-sm font-medium">Memuat data...</span>
    </div>

    <!-- ── Empty ──────────────────────────────────────────── -->
    <div v-else-if="invoices.length === 0" class="premium-card bg-white text-center py-16">
      <div class="w-16 h-16 rounded-full bg-emerald-50 flex items-center justify-center mx-auto mb-4">
        <i class="pi pi-check-circle text-3xl text-emerald-400"></i>
      </div>
      <p class="font-bold text-slate-700 text-lg">Semua Terkirim!</p>
      <p class="text-slate-400 text-sm mt-2">Tidak ada invoice yang masih memiliki sisa pengiriman.</p>
    </div>

    <!-- ── Invoice List ───────────────────────────────────── -->
    <div v-else-if="filteredInvoices.length === 0" class="text-center py-10 text-slate-400">
      <i class="pi pi-filter-slash text-3xl text-slate-200 mb-2 block"></i>
      <p class="text-sm">Tidak ada invoice yang cocok dengan filter.</p>
    </div>
    <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-4">
      <div
        v-for="inv in filteredInvoices"
        :key="inv.id"
        class="premium-card bg-white group hover:shadow-xl transition-all duration-300 cursor-pointer border border-transparent hover:border-[#1D3557]/10"
        @click="openDetail(inv)"
      >
        <!-- Top row: nomor + badge status -->
        <div class="flex items-start justify-between gap-3 mb-3">
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 mb-1 flex-wrap">
              <span class="text-[10px] font-bold text-slate-400 font-mono tracking-wide">{{ inv.invoice_number }}</span>
              <span
                class="text-[10px] font-bold px-2 py-0.5 rounded-full"
                :class="{
                  'bg-slate-100 text-slate-500': inv.progress.qty_total_kirim === 0,
                  'bg-amber-100 text-amber-600': inv.progress.qty_total_kirim > 0 && inv.progress.qty_total_sisa > 0,
                  'bg-emerald-100 text-emerald-600': inv.progress.qty_total_sisa === 0,
                }"
              >
                {{ inv.progress.qty_total_kirim === 0 ? 'Belum Kirim' : inv.progress.qty_total_sisa === 0 ? '✓ Selesai' : 'Parsial' }}
              </span>
            </div>
            <h3 class="font-bold text-[#1D3557] text-base truncate">{{ inv.recipient_name }}</h3>
            <p class="text-xs text-slate-400 mt-0.5 flex items-center gap-1.5">
              {{ new Date(inv.invoice_date).toLocaleDateString('id-ID', { day:'numeric', month:'short', year:'numeric' }) }}
              <span
                class="font-bold px-1.5 py-0.5 rounded text-[10px]"
                :class="daysSince(inv.invoice_date) >= 7 ? 'bg-red-100 text-red-500' : daysSince(inv.invoice_date) >= 3 ? 'bg-amber-100 text-amber-600' : 'bg-slate-100 text-slate-400'"
              >{{ daysSince(inv.invoice_date) }}h lalu</span>
            </p>
          </div>
          <div class="text-right shrink-0">
            <p class="text-xs font-bold text-slate-600">{{ formatRp(inv.grand_total) }}</p>
            <p class="text-[10px] text-slate-400 mt-0.5">{{ inv.items.length }} jenis barang</p>
          </div>
        </div>

        <!-- Progress bar -->
        <div class="mb-4">
          <div class="flex justify-between text-[10px] font-bold text-slate-400 mb-1.5">
            <span>{{ inv.progress.qty_total_kirim }} terkirim</span>
            <span class="text-red-400" v-if="inv.progress.qty_total_sisa > 0">{{ inv.progress.qty_total_sisa }} sisa</span>
            <span class="text-emerald-500" v-else>Selesai</span>
          </div>
          <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
            <div
              class="h-full rounded-full transition-all duration-700"
              :class="{
                'bg-slate-300': inv.progress.qty_total_kirim === 0,
                'bg-amber-400': inv.progress.qty_total_kirim > 0 && progressPct(inv.progress) < 100,
                'bg-emerald-500': progressPct(inv.progress) === 100,
              }"
              :style="{ width: Math.max(progressPct(inv.progress), 2) + '%' }"
            ></div>
          </div>
          <div class="text-right text-[10px] text-slate-400 mt-1">{{ progressPct(inv.progress) }}% dari {{ inv.progress.qty_total_order }} item</div>
        </div>

        <!-- Action buttons -->
        <div class="flex gap-2" @click.stop>
          <button
            v-if="inv.progress.qty_total_sisa > 0"
            @click="openCreate(inv)"
            class="flex-1 flex items-center justify-center gap-2 py-2.5 px-4 rounded-xl bg-[#1D3557] text-white text-xs font-bold hover:bg-[#1D3557]/90 active:scale-[0.98] transition-all shadow-md shadow-[#1D3557]/20"
          >
            <i class="pi pi-plus"></i>
            Buat Surat Jalan
          </button>
          <button
            @click="openDetail(inv)"
            class="flex items-center justify-center gap-2 py-2.5 px-4 rounded-xl bg-slate-100 text-slate-600 text-xs font-bold hover:bg-slate-200 active:scale-[0.98] transition-all"
          >
            <i class="pi pi-list"></i>
            <span class="hidden sm:inline">Riwayat SJ</span>
          </button>
        </div>
      </div>
    </div>

    </template><!-- end aktif -->

    <!-- ════ TAB: RIWAYAT ════ -->
    <template v-if="activeTab === 'riwayat'">
      <div v-if="loadingRiwayat" class="flex items-center justify-center py-20 gap-3 text-slate-400">
        <i class="pi pi-spin pi-spinner text-2xl"></i>
        <span class="text-sm font-medium">Memuat riwayat...</span>
      </div>
      <div v-else-if="completed.length === 0" class="premium-card bg-white text-center py-16">
        <div class="w-16 h-16 rounded-full bg-slate-50 flex items-center justify-center mx-auto mb-4">
          <i class="pi pi-history text-3xl text-slate-300"></i>
        </div>
        <p class="font-bold text-slate-700 text-lg">Belum ada riwayat</p>
        <p class="text-slate-400 text-sm mt-2">Invoice yang sudah selesai dikirim akan muncul di sini.</p>
      </div>
      <div v-else class="space-y-3">
        <div
          v-for="inv in completed"
          :key="inv.id"
          class="premium-card bg-white"
        >
          <!-- Header invoice -->
          <button
            class="w-full flex items-start justify-between gap-3 text-left"
            @click="expandedSJ[inv.id] = !expandedSJ[inv.id]"
          >
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 flex-wrap mb-1">
                <span class="text-[10px] font-bold text-slate-400 font-mono">{{ inv.invoice_number }}</span>
                <span class="text-[10px] font-bold bg-emerald-100 text-emerald-600 px-2 py-0.5 rounded-full">
                  <i class="pi pi-check mr-1 text-[9px]"></i>Selesai
                </span>
              </div>
              <p class="font-bold text-[#1D3557]">{{ inv.recipient_name }}</p>
              <p class="text-xs text-slate-400 mt-0.5 flex items-center gap-2">
                <span>{{ new Date(inv.invoice_date).toLocaleDateString('id-ID', { day:'numeric', month:'short', year:'numeric' }) }}</span>
                <span v-if="inv.shipped_at" class="flex items-center gap-1">
                  <i class="pi pi-truck text-emerald-400 text-[10px]"></i>
                  Dikirim {{ formatDateTime(inv.shipped_at) }}
                </span>
              </p>
            </div>
            <div class="flex items-center gap-3 shrink-0">
              <div class="text-right">
                <p class="text-xs font-bold text-slate-600">{{ formatRp(inv.grand_total) }}</p>
                <p class="text-[10px] text-slate-400">{{ inv.surat_jalans.length }} Surat Jalan</p>
              </div>
              <i class="pi text-slate-400 transition-transform duration-200 text-sm"
                :class="expandedSJ[inv.id] ? 'pi-chevron-up' : 'pi-chevron-down'"
              ></i>
            </div>
          </button>

          <!-- List SJ (expandable) -->
          <Transition name="expand">
            <div v-if="expandedSJ[inv.id]" class="mt-4 pt-4 border-t border-slate-100 space-y-3">
              <div
                v-for="sj in inv.surat_jalans"
                :key="sj.id"
                class="border border-slate-100 rounded-xl overflow-hidden"
              >
                <div class="flex items-center justify-between px-4 py-2.5 bg-slate-50 border-b border-slate-100">
                  <p class="text-sm font-bold text-[#1D3557] font-mono">{{ sj.nomor_sj }}</p>
                  <p class="text-[10px] text-slate-400">
                    {{ new Date(sj.tanggal_kirim).toLocaleDateString('id-ID', { day:'numeric', month:'short', year:'numeric' }) }}
                  </p>
                </div>
                <div class="px-4 py-3 space-y-1">
                  <div v-for="sjItem in sj.items" :key="sjItem.id" class="flex justify-between text-sm">
                    <span class="text-slate-600 truncate flex-1">{{ sjItem.sale_item?.item_name }}</span>
                    <span class="font-bold text-[#1D3557] ml-3 shrink-0">{{ sjItem.qty_kirim }} pcs</span>
                  </div>
                  <p v-if="sj.catatan" class="text-xs text-slate-400 italic pt-1.5 border-t border-slate-50 mt-1.5">
                    <i class="pi pi-comment mr-1"></i>{{ sj.catatan }}
                  </p>
                </div>
              </div>
            </div>
          </Transition>
        </div>
      </div>
    </template><!-- end riwayat -->


    <!-- ════════════════════════════════════════════════════ -->
    <!-- ── Detail Modal ──────────────────────────────────── -->
    <!-- ════════════════════════════════════════════════════ -->
    <Transition name="fade">
      <div v-if="detailInvoice" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="closeDetail"></div>

        <div class="relative bg-white w-full sm:max-w-lg sm:rounded-2xl rounded-t-3xl shadow-2xl flex flex-col max-h-[90vh]">

          <!-- Handle bar mobile -->
          <div class="flex justify-center pt-3 pb-1 sm:hidden shrink-0">
            <div class="w-10 h-1 rounded-full bg-slate-200"></div>
          </div>

          <!-- Header -->
          <div class="px-5 sm:px-6 pt-3 sm:pt-5 pb-4 border-b border-slate-100 shrink-0">
            <div class="flex items-start justify-between gap-3 mb-4">
              <div class="flex-1 min-w-0">
                <p class="text-[10px] font-bold text-slate-400 font-mono tracking-wider mb-1">{{ detailInvoice.invoice_number }}</p>
                <h3 class="font-bold text-[#1D3557] text-xl leading-tight">{{ detailInvoice.recipient_name }}</h3>
                <p class="text-xs text-slate-400 mt-1">
                  {{ new Date(detailInvoice.invoice_date).toLocaleDateString('id-ID', { day:'numeric', month:'long', year:'numeric' }) }}
                  · {{ formatRp(detailInvoice.grand_total) }}
                </p>
              </div>
              <button @click="closeDetail" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition-colors shrink-0 mt-0.5">
                <i class="pi pi-times text-sm text-slate-500"></i>
              </button>
            </div>

            <!-- Progress ringkas -->
            <div class="grid grid-cols-3 gap-2 text-center">
              <div class="bg-slate-50 rounded-xl py-3">
                <p class="text-xl font-bold text-slate-700">{{ detailInvoice.items.length }}</p>
                <p class="text-[9px] text-slate-400 font-bold uppercase tracking-wider mt-0.5">Jenis Item</p>
              </div>
              <div class="bg-emerald-50 rounded-xl py-3">
                <p class="text-xl font-bold text-emerald-600">
                  {{ detailInvoice.items.filter(i => (detailInvoice!.progress.items[i.id]?.qty_sisa ?? 0) === 0).length }}
                </p>
                <p class="text-[9px] text-emerald-500 font-bold uppercase tracking-wider mt-0.5">Selesai</p>
              </div>
              <div class="rounded-xl py-3" :class="detailInvoice.progress.qty_total_sisa > 0 ? 'bg-red-50' : 'bg-slate-50'">
                <p class="text-xl font-bold" :class="detailInvoice.progress.qty_total_sisa > 0 ? 'text-red-500' : 'text-slate-400'">
                  {{ detailInvoice.items.filter(i => (detailInvoice!.progress.items[i.id]?.qty_sisa ?? 0) > 0).length }}
                </p>
                <p class="text-[9px] font-bold uppercase tracking-wider mt-0.5" :class="detailInvoice.progress.qty_total_sisa > 0 ? 'text-red-400' : 'text-slate-400'">Sisa Item</p>
              </div>
            </div>

            <!-- Progress bar -->
            <div class="mt-3 h-1.5 bg-slate-100 rounded-full overflow-hidden">
              <div
                class="h-full rounded-full transition-all duration-700"
                :class="progressPct(detailInvoice.progress) === 100 ? 'bg-emerald-500' : 'bg-amber-400'"
                :style="{ width: Math.max(progressPct(detailInvoice.progress), 1) + '%' }"
              ></div>
            </div>
          </div>

          <!-- Scrollable body -->
          <div class="flex-1 overflow-y-auto px-5 sm:px-6 py-4 space-y-5">

            <!-- Buat SJ button -->
            <button
              v-if="detailInvoice.progress.qty_total_sisa > 0"
              @click="openCreate(detailInvoice)"
              class="w-full flex items-center justify-center gap-2 py-3 rounded-xl bg-[#1D3557] text-white text-sm font-bold hover:bg-[#1D3557]/90 active:scale-[0.99] transition-all shadow-lg shadow-[#1D3557]/20"
            >
              <i class="pi pi-plus text-base"></i>
              Buat Surat Jalan Baru
            </button>

            <!-- Riwayat SJ -->
            <div>
              <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3">Riwayat Surat Jalan</p>

              <div v-if="detailLoading" class="flex items-center justify-center py-8 gap-2 text-slate-400">
                <i class="pi pi-spin pi-spinner"></i>
                <span class="text-sm">Memuat...</span>
              </div>
              <div v-else-if="detailSjs.length === 0" class="text-center py-8 text-slate-400">
                <i class="pi pi-inbox text-3xl text-slate-200 mb-2 block"></i>
                <p class="text-sm">Belum ada Surat Jalan untuk invoice ini.</p>
              </div>
              <div v-else class="space-y-3">
                <div v-for="sj in detailSjs" :key="sj.id" class="border border-slate-100 rounded-xl overflow-hidden">
                  <div class="flex items-center justify-between px-4 py-3 bg-slate-50 border-b border-slate-100">
                    <div>
                      <p class="text-sm font-bold text-[#1D3557] font-mono">{{ sj.nomor_sj }}</p>
                      <p class="text-[10px] text-slate-400 mt-0.5">
                        {{ new Date(sj.tanggal_kirim).toLocaleDateString('id-ID', { day:'numeric', month:'long', year:'numeric' }) }}
                      </p>
                    </div>
                    <button
                      @click="deleteSJ(sj)"
                      class="w-7 h-7 rounded-lg bg-white border border-slate-200 flex items-center justify-center text-slate-400 hover:text-red-500 hover:border-red-200 hover:bg-red-50 transition-all"
                    >
                      <i class="pi pi-trash text-xs"></i>
                    </button>
                  </div>
                  <div class="px-4 py-3 space-y-1.5">
                    <div v-for="sjItem in sj.items" :key="sjItem.id" class="flex justify-between items-center text-sm">
                      <span class="text-slate-600 truncate flex-1">{{ sjItem.sale_item?.item_name }}</span>
                      <span class="font-bold text-[#1D3557] ml-3 shrink-0">{{ sjItem.qty_kirim }} pcs</span>
                    </div>
                    <p v-if="sj.catatan" class="text-xs text-slate-400 italic pt-1 border-t border-slate-100 mt-2">
                      <i class="pi pi-comment mr-1"></i>{{ sj.catatan }}
                    </p>
                  </div>
                </div>
              </div>
            </div>

            <!-- Detail per item -->
            <div>
              <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3">Detail Item</p>
              <div class="space-y-2">
                <div
                  v-for="item in [...detailInvoice.items].sort((a, b) => {
                    const sisaA = detailInvoice!.progress.items[a.id]?.qty_sisa ?? 0;
                    const sisaB = detailInvoice!.progress.items[b.id]?.qty_sisa ?? 0;
                    return (sisaA === 0 ? 1 : 0) - (sisaB === 0 ? 1 : 0);
                  })"
                  :key="item.id"
                  class="flex items-center gap-3 py-3 px-4 rounded-xl border"
                  :class="(detailInvoice.progress.items[item.id]?.qty_sisa ?? 0) > 0 ? 'bg-white border-slate-100' : 'bg-emerald-50/50 border-emerald-100'"
                >
                  <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-slate-700 truncate">{{ item.item_name }}</p>
                    <div class="flex items-center gap-3 mt-0.5">
                      <span class="text-[10px] text-slate-400">Order: <strong>{{ item.qty }}</strong></span>
                      <span class="text-[10px] text-emerald-600">Kirim: <strong>{{ detailInvoice.progress.items[item.id]?.qty_kirim ?? 0 }}</strong></span>
                    </div>
                  </div>
                  <div class="shrink-0">
                    <div v-if="(detailInvoice.progress.items[item.id]?.qty_sisa ?? 0) > 0" class="text-xs font-bold text-red-500 bg-red-50 px-2 py-1 rounded-lg">
                      Sisa {{ detailInvoice.progress.items[item.id]?.qty_sisa }}
                    </div>
                    <div v-else class="text-xs font-bold text-emerald-600 bg-emerald-50 px-2 py-1 rounded-lg">
                      <i class="pi pi-check mr-1"></i>Selesai
                    </div>
                  </div>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>
    </Transition>


    <!-- ════════════════════════════════════════════════════ -->
    <!-- ── Create SJ Modal ───────────────────────────────── -->
    <!-- ════════════════════════════════════════════════════ -->
    <Transition name="fade">
      <div v-if="createModal" class="fixed inset-0 z-[60] flex items-end sm:items-center justify-center">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="createModal = false"></div>

        <div class="relative bg-white w-full sm:max-w-lg sm:rounded-2xl rounded-t-3xl shadow-2xl flex flex-col max-h-[92vh]">

          <!-- Handle bar (mobile) -->
          <div class="flex justify-center pt-3 pb-1 sm:hidden">
            <div class="w-10 h-1 rounded-full bg-slate-200"></div>
          </div>

          <!-- Modal header -->
          <div class="px-5 sm:px-6 pt-3 sm:pt-5 pb-4 border-b border-slate-100 flex items-start justify-between gap-3 shrink-0">
            <div>
              <h3 class="font-bold text-[#1D3557] text-lg">Buat Surat Jalan</h3>
              <p class="text-xs text-slate-400 mt-0.5" v-if="createInvoice">
                <i class="pi pi-user mr-1"></i>{{ createInvoice.recipient_name }}
                · <span class="font-mono">{{ createInvoice.invoice_number }}</span>
              </p>
            </div>
            <button @click="createModal = false" class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center text-slate-400 hover:bg-slate-200 transition-colors shrink-0">
              <i class="pi pi-times text-sm"></i>
            </button>
          </div>

          <!-- Body -->
          <div class="overflow-y-auto flex-1 px-5 sm:px-6 py-4 space-y-5">

            <!-- Tanggal -->
            <div>
              <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest block mb-1.5">Tanggal Pengiriman</label>
              <input
                v-model="createForm.tanggal_kirim"
                type="date"
                class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-[#1D3557]/20 focus:border-[#1D3557] transition-colors"
              />
            </div>

            <!-- Item section -->
            <div>
              <div class="flex items-center justify-between mb-2">
                <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Item yang Dikirim</label>
                <div class="flex gap-1.5">
                  <button @click="setAllQty('all')" class="text-[10px] font-bold px-2.5 py-1 rounded-lg bg-[#1D3557]/10 text-[#1D3557] hover:bg-[#1D3557]/15 transition-colors">Kirim Semua</button>
                  <button @click="setAllQty('none')" class="text-[10px] font-bold px-2.5 py-1 rounded-lg bg-slate-100 text-slate-500 hover:bg-slate-200 transition-colors">Reset</button>
                </div>
              </div>

              <div class="space-y-2.5">
                <div
                  v-for="item in createForm.items"
                  :key="item.sale_item_id"
                  class="rounded-xl border transition-colors"
                  :class="item.qty_kirim > 0 ? 'border-[#1D3557]/20 bg-[#1D3557]/[0.02]' : 'border-slate-100 bg-slate-50/60'"
                >
                  <div class="px-4 py-3 flex items-center gap-3">
                    <!-- Nama -->
                    <div class="flex-1 min-w-0">
                      <p class="text-sm font-semibold text-slate-700 truncate">{{ item.item_name }}</p>
                      <p class="text-[10px] text-slate-400 mt-0.5">
                        Sisa: <strong>{{ item.qty_sisa }}</strong>
                        <span class="mx-1">·</span>
                        Total order: {{ item.qty_order }}
                      </p>
                    </div>

                    <!-- Qty stepper -->
                    <div class="flex items-center gap-2 shrink-0">
                      <button
                        @click="item.qty_kirim = Math.max(0, item.qty_kirim - 1)"
                        class="w-8 h-8 rounded-lg flex items-center justify-center text-lg font-bold transition-all"
                        :class="item.qty_kirim > 0 ? 'bg-slate-200 text-slate-700 hover:bg-slate-300 active:scale-90' : 'bg-slate-100 text-slate-300 cursor-not-allowed'"
                      >−</button>
                      <input
                        v-model.number="item.qty_kirim"
                        @change="clampQty(item)"
                        type="number"
                        :min="0"
                        :max="item.qty_sisa"
                        class="w-14 text-center border rounded-lg py-1.5 text-sm font-bold focus:outline-none focus:ring-2 focus:ring-[#1D3557]/20 focus:border-[#1D3557] transition-colors"
                        :class="item.qty_kirim > 0 ? 'border-[#1D3557]/30 text-[#1D3557]' : 'border-slate-200 text-slate-400'"
                      />
                      <button
                        @click="item.qty_kirim = Math.min(item.qty_sisa, item.qty_kirim + 1)"
                        class="w-8 h-8 rounded-lg flex items-center justify-center text-lg font-bold transition-all"
                        :class="item.qty_kirim < item.qty_sisa ? 'bg-[#1D3557] text-white hover:bg-[#1D3557]/90 active:scale-90' : 'bg-slate-100 text-slate-300 cursor-not-allowed'"
                      >+</button>
                    </div>
                  </div>

                  <!-- Mini progress bar per item -->
                  <div class="px-4 pb-2.5" v-if="item.qty_sisa > 0">
                    <div class="h-1 bg-slate-100 rounded-full overflow-hidden">
                      <div
                        class="h-full rounded-full transition-all duration-300"
                        :class="item.qty_kirim === item.qty_sisa ? 'bg-emerald-500' : item.qty_kirim > 0 ? 'bg-[#457B9D]' : 'bg-slate-200'"
                        :style="{ width: (item.qty_sisa > 0 ? (item.qty_kirim / item.qty_sisa) * 100 : 0) + '%' }"
                      ></div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Catatan -->
            <div>
              <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest block mb-1.5">Catatan <span class="normal-case font-normal">(opsional)</span></label>
              <textarea
                v-model="createForm.catatan"
                rows="2"
                placeholder="cth: via JNE, resi 12345..."
                class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-[#1D3557]/20 focus:border-[#1D3557] transition-colors"
              ></textarea>
            </div>
          </div>

          <!-- Footer -->
          <div class="px-5 sm:px-6 py-4 border-t border-slate-100 shrink-0 bg-white">
            <!-- Summary -->
            <div class="flex items-center justify-between mb-3 px-1">
              <span class="text-xs text-slate-500">Total item yang akan dikirim</span>
              <span class="text-sm font-bold" :class="createTotalKirim > 0 ? 'text-[#1D3557]' : 'text-slate-300'">{{ createTotalKirim }} pcs</span>
            </div>
            <div class="flex gap-2">
              <button @click="createModal = false" class="flex-none px-5 py-3 rounded-xl bg-slate-100 text-slate-600 text-sm font-bold hover:bg-slate-200 transition-colors">
                Batal
              </button>
              <button
                @click="submitCreate"
                :disabled="creating || createTotalKirim === 0"
                class="flex-1 flex items-center justify-center gap-2 py-3 rounded-xl text-sm font-bold transition-all"
                :class="createTotalKirim > 0 && !creating ? 'bg-[#1D3557] text-white hover:bg-[#1D3557]/90 active:scale-[0.99] shadow-lg shadow-[#1D3557]/20' : 'bg-slate-100 text-slate-400 cursor-not-allowed'"
              >
                <i v-if="creating" class="pi pi-spin pi-spinner"></i>
                <i v-else class="pi pi-truck"></i>
                {{ creating ? 'Menyimpan...' : 'Buat Surat Jalan' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>

    <!-- ════════════════════════════════════════════════════ -->
    <!-- ── Picking List Modal ────────────────────────────── -->
    <!-- ════════════════════════════════════════════════════ -->
    <Transition name="fade">
      <div v-if="pickingExpanded" class="fixed inset-0 z-[60] flex items-end sm:items-center justify-center">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="pickingExpanded = false"></div>

        <div class="relative bg-white w-full sm:max-w-lg sm:rounded-2xl rounded-t-3xl shadow-2xl flex flex-col max-h-[85vh]">

          <!-- Handle bar mobile -->
          <div class="flex justify-center pt-3 pb-1 sm:hidden">
            <div class="w-10 h-1 rounded-full bg-slate-200"></div>
          </div>

          <!-- Header -->
          <div class="px-5 sm:px-6 pt-3 sm:pt-5 pb-4 border-b border-slate-100 flex items-center justify-between shrink-0">
            <div>
              <h3 class="font-bold text-[#1D3557] text-lg">Barang yang Harus Disiapkan</h3>
              <p class="text-xs text-slate-400 mt-0.5">
                {{ summary.totalItemQty }} pcs · {{ summary.totalItemJenis }} jenis barang
              </p>
            </div>
            <button @click="pickingExpanded = false" class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center text-slate-400 hover:bg-slate-200 transition-colors">
              <i class="pi pi-times text-sm"></i>
            </button>
          </div>

          <!-- Legend -->
          <div class="px-5 sm:px-6 py-2.5 flex items-center gap-4 border-b border-slate-50 bg-slate-50/50 shrink-0">
            <span class="flex items-center gap-1.5 text-[10px] font-bold text-emerald-600"><span class="w-2 h-2 rounded-full bg-emerald-400 inline-block"></span>Stok cukup</span>
            <span class="flex items-center gap-1.5 text-[10px] font-bold text-amber-600"><span class="w-2 h-2 rounded-full bg-amber-400 inline-block"></span>Stok kurang</span>
            <span class="flex items-center gap-1.5 text-[10px] font-bold text-red-500"><span class="w-2 h-2 rounded-full bg-red-400 inline-block"></span>Stok habis</span>
            <span class="flex items-center gap-1.5 text-[10px] font-bold text-slate-400"><span class="w-2 h-2 rounded-full bg-slate-300 inline-block"></span>Tanpa stok</span>
          </div>

          <!-- List -->
          <div class="overflow-y-auto flex-1 divide-y divide-slate-50">
            <div
              v-for="(item, idx) in summary.pickingList"
              :key="item.item_name"
              class="px-5 sm:px-6 py-3.5"
            >
              <div class="flex items-start justify-between gap-3 mb-1.5">
                <!-- Nomor + nama -->
                <div class="flex items-start gap-2.5 flex-1 min-w-0">
                  <span class="text-[10px] font-bold text-slate-300 mt-0.5 w-4 shrink-0 text-right">{{ idx + 1 }}</span>
                  <p class="text-sm font-semibold text-slate-700 leading-snug">{{ item.item_name }}</p>
                </div>
                <!-- Badges -->
                <div class="flex items-center gap-1.5 shrink-0">
                  <template v-if="item.stok !== null">
                    <span
                      class="text-[10px] font-bold px-2 py-0.5 rounded-lg flex items-center gap-1"
                      :class="{
                        'bg-emerald-100 text-emerald-700': item.stok >= item.total_qty,
                        'bg-amber-100 text-amber-600':    item.stok > 0 && item.stok < item.total_qty,
                        'bg-red-100 text-red-600':        item.stok === 0,
                      }"
                    >
                      <i class="pi text-[9px]"
                        :class="{
                          'pi-check-circle':        item.stok >= item.total_qty,
                          'pi-exclamation-triangle': item.stok > 0 && item.stok < item.total_qty,
                          'pi-times-circle':        item.stok === 0,
                        }"
                      ></i>
                      Stok {{ item.stok }}
                    </span>
                  </template>
                  <span class="text-[10px] font-bold text-[#1D3557] bg-[#1D3557]/8 px-2 py-0.5 rounded-lg">
                    Butuh {{ item.total_qty }}
                  </span>
                </div>
              </div>
              <!-- Per invoice breakdown -->
              <div class="flex flex-wrap gap-x-3 gap-y-0.5 pl-6">
                <span
                  v-for="inv in item.invoices"
                  :key="inv.invoice_number"
                  class="text-[10px] text-slate-400"
                >
                  {{ inv.recipient_name }} <strong class="text-slate-500">×{{ inv.qty_sisa }}</strong>
                </span>
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div class="px-5 sm:px-6 py-4 border-t border-slate-100 shrink-0">
            <button
              @click="pickingExpanded = false"
              class="w-full py-3 rounded-xl bg-slate-100 text-slate-600 text-sm font-bold hover:bg-slate-200 transition-colors"
            >Tutup</button>
          </div>
        </div>
      </div>
    </Transition>

  </div>
</template>

<style scoped>
.toast-enter-active, .toast-leave-active { transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1); }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateY(-12px) scale(0.95); }

.fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }


.expand-enter-active, .expand-leave-active { transition: all 0.25s ease; overflow: hidden; }
.expand-enter-from, .expand-leave-to { opacity: 0; max-height: 0; }
.expand-enter-to, .expand-leave-from { opacity: 1; max-height: 300px; }

input[type=number]::-webkit-outer-spin-button,
input[type=number]::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
input[type=number] { -moz-appearance: textfield; }
</style>
