<script setup lang="ts">
import { ref, onMounted, onUnmounted, watch, computed } from 'vue';
import MainLayout from '@/Layouts/MainLayout.vue';
import { inventoryApi } from '@/api/inventory.api';
import { salesApi } from '@/api/sales.api';
import { piutangApi, type ManualPiutang } from '@/api/piutang.api';
import EmployeeList from './Modules/Employee/EmployeeList.vue';
import AttendanceModule from './Modules/Attendance/AttendanceModule.vue';
import PayrollModule from './Modules/Payroll/PayrollModule.vue';
import InventoryModule from './Modules/Inventory/InventoryModule.vue';
import SalesModule from './Modules/Sales/SalesModule.vue';
import ExpenseModule from './Modules/Expense/ExpenseModule.vue';
import IncomeModule from './Modules/Income/IncomeModule.vue';
import BudgetModule from './Modules/Budget/BudgetModule.vue';

const urlParams = new URLSearchParams(window.location.search);
const activeTab = ref(urlParams.get('tab') || 'overview');

watch(activeTab, (newTab) => {
  const url = new URL(window.location.href);
  url.searchParams.set('tab', newTab);
  window.history.pushState({}, '', url);
});
const stats = ref({
  totalItems:       0,
  totalInvoice:     0,
  invoicePending:   0,
  totalOmzet:       0,
});

const kekurangan = ref<{ invoice_number: string; recipient_name: string; sisa: number }[]>([]);
const totalKekurangan = computed(() => kekurangan.value.reduce((s, k) => s + k.sisa, 0));

const top10Items = ref<{ item_name: string; total_qty: number; total_revenue: number }[]>([]);

const manualPiutang    = ref<ManualPiutang[]>([]);
const piutangForm      = ref({ name: '', amount: '', notes: '' });

function onPiutangAmountInput(e: Event) {
  const raw = (e.target as HTMLInputElement).value.replace(/\D/g, '');
  const num = parseInt(raw) || 0;
  const formatted = num > 0 ? num.toLocaleString('id-ID') : '';
  piutangForm.value.amount = raw;
  (e.target as HTMLInputElement).value = formatted;
}
const piutangFormOpen  = ref(false);
const piutangSubmitting = ref(false);

const totalManualPiutang = computed(() => manualPiutang.value.reduce((s, p) => s + p.amount, 0));
const totalAllPiutang    = computed(() => totalKekurangan.value + totalManualPiutang.value);

async function fetchManualPiutang() {
    const res = await piutangApi.getAll();
    manualPiutang.value = res.data.data;
}

async function addManualPiutang() {
    const amount = parseInt(piutangForm.value.amount.replace(/\D/g, ''));
    if (!piutangForm.value.name.trim() || !amount) return;
    piutangSubmitting.value = true;
    try {
        const res = await piutangApi.create({ name: piutangForm.value.name.trim(), amount, notes: piutangForm.value.notes || undefined });
        manualPiutang.value.unshift(res.data.data);
        piutangForm.value = { name: '', amount: '', notes: '' };
        piutangFormOpen.value = false;
    } finally {
        piutangSubmitting.value = false;
    }
}

async function deleteManualPiutang(id: number) {
    if (!confirm('Hapus entri piutang ini?')) return;
    await piutangApi.remove(id);
    manualPiutang.value = manualPiutang.value.filter(p => p.id !== id);
}

const valuasi = ref({
  total_valuasi: 0,
  total_item_jenis: 0,
  total_stok: 0,
});

const formatRupiah = (val: number) =>
  'Rp ' + val.toLocaleString('id-ID');

const menuItems = [
  { id: 'overview', name: 'Dashboard', icon: 'pi pi-chart-bar' },
  {
    id: 'inventory',
    name: 'Inventory Dashboard',
    icon: 'pi pi-box',
    children: [
      { id: 'inventory-ruko', name: 'Stok Barang di Ruko', icon: 'pi pi-building' },
      { id: 'inventory-margomulyo', name: 'Stok Barang di Margomulyo', icon: 'pi pi-warehouse' },
      { id: 'inventory-history', name: 'Mutasi Stok', icon: 'pi pi-history' },
    ],
  },
  { id: 'sales', name: 'Penjualan', icon: 'pi pi-receipt' },
  {
    id: 'keuangan',
    name: 'Keuangan Ruko',
    icon: 'pi pi-chart-line',
    children: [
      { id: 'expenses', name: 'Pengeluaran', icon: 'pi pi-wallet' },
      { id: 'incomes',  name: 'Pemasukan',  icon: 'pi pi-arrow-circle-down' },
      { id: 'rab',      name: 'RAB Tracking', icon: 'pi pi-chart-bar' },
    ],
  },
];

const inventoryExpanded = computed(() =>
  activeTab.value === 'inventory' || activeTab.value.startsWith('inventory-')
);

const keuanganExpanded = computed(() =>
  activeTab.value === 'keuangan' || activeTab.value === 'expenses' || activeTab.value === 'incomes'
);

function processSales(sales: any[]) {
  stats.value.totalInvoice   = sales.length;
  stats.value.invoicePending = sales.filter((s: any) => s.status === 'belum_dikirim').length;
  stats.value.totalOmzet     = sales
    .filter((s: any) => s.status === 'sudah_dikirim')
    .reduce((sum: number, s: any) => sum + s.grand_total, 0);

  kekurangan.value = sales
    .filter((s: any) => (s.grand_total - (s.paid_amount ?? 0)) > 0)
    .map((s: any) => ({
      invoice_number: s.invoice_number,
      recipient_name: s.recipient_name,
      sisa: s.grand_total - (s.paid_amount ?? 0),
    }))
    .sort((a: any, b: any) => b.sisa - a.sisa);

  // Top 10 barang terlaris dari semua invoice
  const itemMap = new Map<string, { total_qty: number; total_revenue: number }>();
  for (const sale of sales) {
    for (const item of (sale.items ?? [])) {
      const key = item.item_name;
      const prev = itemMap.get(key) ?? { total_qty: 0, total_revenue: 0 };
      itemMap.set(key, {
        total_qty:     prev.total_qty + (item.qty ?? 0),
        total_revenue: prev.total_revenue + (item.total_price ?? item.qty * item.unit_price ?? 0),
      });
    }
  }
  top10Items.value = [...itemMap.entries()]
    .map(([item_name, v]) => ({ item_name, ...v }))
    .sort((a, b) => b.total_qty - a.total_qty)
    .slice(0, 10);
}

async function fetchSales() {
  try {
    const salesRes = await salesApi.getAll();
    processSales(salesRes.data.data);
  } catch {}
}

async function fetchValuasi() {
  try {
    const res = await inventoryApi.getValuasi();
    valuasi.value = res.data.data;
  } catch {}
}

async function fetchAll() {
  await Promise.all([fetchSales(), fetchValuasi()]);
}

watch(activeTab, (tab) => {
  if (tab === 'sales' || tab === 'overview') fetchSales();
});

function onVisibilityChange() {
  if (document.visibilityState === 'visible') fetchAll();
}

onMounted(async () => {
  try {
    const [itemsRes, valuasiRes, salesRes] = await Promise.all([
      inventoryApi.getItems(),
      inventoryApi.getValuasi(),
      salesApi.getAll(),
      fetchManualPiutang(),
    ]);

    stats.value.totalItems = itemsRes.data.data.length;
    processSales(salesRes.data.data);
    valuasi.value = valuasiRes.data.data;
  } catch (error) {
    console.error('Gagal mengambil data dashboard:', error);
  }

  // Refetch saat user kembali ke tab browser
  document.addEventListener('visibilitychange', onVisibilityChange);
  // Refetch instan saat ada perubahan data sales
  window.addEventListener('sales-updated', fetchSales);
});

onUnmounted(() => {
  document.removeEventListener('visibilitychange', onVisibilityChange);
  window.removeEventListener('sales-updated', fetchSales);
});
</script>

<template>
  <MainLayout>
    <!-- Sidebar Links Slot -->
    <template #sidebar="{ closeSidebar }">
      <div class="flex flex-col gap-1">
        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em] px-4 py-4">Main Menu</p>
        <template v-for="item in menuItems" :key="item.id">
          <!-- Menu item dengan children (tree) -->
          <template v-if="item.children">
            <button
              @click="activeTab = item.id"
              class="w-full flex items-center gap-3 px-4 py-3.5 rounded-xl text-[13px] font-bold transition-all duration-300 group"
              :class="(item.id === 'inventory' ? inventoryExpanded : keuanganExpanded) ? 'text-primary' : 'text-slate-500 hover:bg-slate-50 hover:text-primary'"
            >
              <i :class="item.icon" class="text-lg transition-transform"></i>
              <span class="tracking-wide flex-1 text-left">{{ item.name }}</span>
              <i :class="(item.id === 'inventory' ? inventoryExpanded : keuanganExpanded) ? 'pi pi-chevron-down' : 'pi pi-chevron-right'" class="text-[10px] opacity-50"></i>
            </button>
            <div v-if="item.id === 'inventory' ? inventoryExpanded : keuanganExpanded" class="ml-4 flex flex-col gap-0.5 border-l-2 border-slate-100 pl-3">
              <button
                v-for="child in item.children"
                :key="child.id"
                @click="activeTab = child.id; closeSidebar()"
                class="w-full flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-[12px] font-bold transition-all duration-200 group"
                :class="activeTab === child.id
                  ? 'bg-primary text-white shadow-md shadow-primary/20'
                  : 'text-slate-500 hover:bg-slate-50 hover:text-primary'"
              >
                <i :class="child.icon" class="text-sm"></i>
                <span>{{ child.name }}</span>
              </button>
            </div>
          </template>
          <!-- Menu item biasa -->
          <button
            v-else
            @click="activeTab = item.id; closeSidebar()"
            class="w-full flex items-center gap-3 px-4 py-3.5 rounded-xl text-[13px] font-bold transition-all duration-300 group"
            :class="activeTab === item.id
              ? 'bg-primary text-white shadow-lg shadow-primary/20'
              : 'text-slate-500 hover:bg-slate-50 hover:text-primary'"
          >
            <i :class="item.icon" class="text-lg group-hover:scale-110 transition-transform"></i>
            <span class="tracking-wide">{{ item.name }}</span>
          </button>
        </template>
      </div>

      <div class="mt-8 flex flex-col gap-1">
        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em] px-4 py-4">Settings</p>
        <button class="w-full flex items-center gap-3 px-4 py-3.5 rounded-xl text-[13px] font-bold text-slate-500 hover:bg-slate-50 hover:text-primary transition-all">
          <i class="pi pi-cog"></i>
          <span class="tracking-wide">Pengaturan</span>
        </button>
      </div>
    </template>

    <!-- Main Workspace Content -->
    <div class="max-w-6xl mx-auto space-y-10 animate-in fade-in slide-in-from-bottom-6 duration-1000">
      <!-- Welcome Header -->
      <header v-if="activeTab === 'overview'" class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-12">
        <div>
          <div class="flex items-center gap-2 text-accent font-semibold text-[10px] uppercase tracking-[0.3em] mb-3">
            <span class="w-10 h-[1px] bg-accent/30"></span>
            Administrator Portal
          </div>
          <h2 class="text-4xl font-display font-bold text-primary">Overview Dashboard</h2>
          <p class="text-slate-500 mt-2 text-sm max-w-lg leading-relaxed">
            Pantau seluruh operasional internal MBG mulai dari inventaris hingga payroll dalam satu kendali.
          </p>
        </div>
        <div class="flex gap-3">
          <button class="btn-primary text-xs py-3 px-6">
            Generate Report
          </button>
        </div>
      </header>

      <!-- Content for Overview -->
      <div v-if="activeTab === 'overview'" class="space-y-10">
        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
          <div class="premium-card group relative overflow-hidden bg-white">
            <div class="absolute -right-6 -top-6 w-24 h-24 bg-primary/5 rounded-full blur-2xl group-hover:bg-accent/10 transition-colors duration-500"></div>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-4">Total Inventaris</p>
            <div class="flex items-end justify-between">
              <div>
                <h3 class="text-4xl font-display font-bold text-primary">{{ stats.totalItems }}</h3>
                <p class="text-[10px] text-slate-500 font-bold uppercase mt-1">Items</p>
              </div>
              <span class="text-[10px] font-bold text-emerald-600 bg-emerald-50 px-2 py-1 rounded-lg">Optimal</span>
            </div>
          </div>

          <div class="premium-card group relative overflow-hidden bg-white">
            <div class="absolute -right-6 -top-6 w-24 h-24 bg-primary/5 rounded-full blur-2xl group-hover:bg-accent/10 transition-colors duration-500"></div>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-4">Total Invoice</p>
            <div class="flex items-end justify-between">
              <div>
                <h3 class="text-4xl font-display font-bold text-primary">{{ stats.totalInvoice }}</h3>
                <p class="text-[10px] text-slate-500 font-bold uppercase mt-1">Invoice</p>
              </div>
              <span v-if="stats.invoicePending" class="text-[10px] font-bold text-amber-600 bg-amber-50 px-2 py-1 rounded-lg">{{ stats.invoicePending }} pending</span>
              <span v-else class="text-[10px] font-bold text-emerald-600 bg-emerald-50 px-2 py-1 rounded-lg">Semua terkirim</span>
            </div>
          </div>

          <div class="premium-card group relative overflow-hidden bg-gradient-to-br from-primary to-primary-light text-white">
            <div class="absolute -right-6 -top-6 w-24 h-24 bg-white/10 rounded-full blur-2xl"></div>
            <p class="text-[10px] font-bold text-white/60 uppercase tracking-widest mb-3">Valuasi Stok + Piutang</p>
            <div class="flex items-end justify-between">
              <div class="space-y-1.5">
                <h3 class="text-2xl font-display font-bold text-white leading-tight">{{ formatRupiah(valuasi.total_valuasi + totalKekurangan) }}</h3>
                <div class="flex items-center gap-2 flex-wrap">
                  <span class="text-[10px] text-white/60 font-bold uppercase">Stok {{ formatRupiah(valuasi.total_valuasi) }}</span>
                  <span v-if="totalKekurangan > 0" class="text-[10px] text-amber-300 font-bold">+ Piutang {{ formatRupiah(totalKekurangan) }}</span>
                </div>
              </div>
              <i class="pi pi-box text-3xl text-white/20"></i>
            </div>
          </div>

          <div class="premium-card group relative overflow-hidden bg-white">
            <div class="absolute -right-6 -top-6 w-24 h-24 bg-primary/5 rounded-full blur-2xl group-hover:bg-accent/10 transition-colors duration-500"></div>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-4">Omzet Terkirim</p>
            <div class="flex items-end justify-between">
              <div>
                <h3 class="text-2xl font-display font-bold text-primary leading-tight">{{ formatRupiah(stats.totalOmzet) }}</h3>
                <p class="text-[10px] text-slate-500 font-bold uppercase mt-1">Sudah dikirim</p>
              </div>
              <i class="pi pi-send text-2xl text-slate-200"></i>
            </div>
          </div>
        </div>

        <div class="section-divider">
          <div class="w-1.5 h-1.5 rounded-full bg-accent/30"></div>
        </div>

        <!-- Top 10 Barang Laris + Hutang Invoice -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <!-- Top 10 Barang Terlaris -->
          <div class="premium-card bg-white">
            <h2 class="text-xl font-display font-bold text-primary mb-5">10 Barang Terlaris</h2>
            <div v-if="top10Items.length === 0" class="text-sm text-slate-400 text-center py-6">Belum ada data penjualan</div>
            <div v-else class="space-y-2">
              <div v-for="(item, i) in top10Items" :key="item.item_name" class="flex items-center gap-3">
                <span class="w-5 text-[10px] font-bold text-slate-300 text-right shrink-0">{{ i + 1 }}</span>
                <div class="flex-1 min-w-0">
                  <p class="text-xs font-semibold text-slate-700 truncate">{{ item.item_name }}</p>
                  <p class="text-[10px] text-slate-400">{{ item.total_qty.toLocaleString('id-ID') }} unit &middot; {{ formatRupiah(item.total_revenue) }}</p>
                </div>
                <div class="w-16 bg-slate-100 rounded-full h-1.5 shrink-0">
                  <div class="bg-primary h-1.5 rounded-full" :style="{ width: (item.total_qty / top10Items[0].total_qty * 100) + '%' }"></div>
                </div>
              </div>
            </div>
          </div>

          <!-- Piutang Invoice + Manual -->
          <div class="premium-card bg-white flex flex-col gap-4">
            <div class="flex items-center justify-between">
              <h2 class="text-xl font-display font-bold text-primary">Piutang</h2>
              <span v-if="totalAllPiutang > 0" class="text-sm font-bold text-amber-600 bg-amber-50 px-3 py-1.5 rounded-lg">{{ formatRupiah(totalAllPiutang) }}</span>
            </div>

            <!-- Dari invoice -->
            <div v-if="kekurangan.length > 0">
              <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Dari Invoice</p>
              <div class="space-y-1.5 max-h-40 overflow-y-auto pr-1">
                <div v-for="k in kekurangan" :key="k.invoice_number" class="flex items-center justify-between gap-3 py-1.5 border-b border-slate-50 last:border-0">
                  <div class="min-w-0">
                    <p class="text-xs font-semibold text-slate-700 truncate">{{ k.recipient_name }}</p>
                    <p class="text-[10px] text-slate-400 font-mono">{{ k.invoice_number }}</p>
                  </div>
                  <p class="text-xs font-bold text-amber-600 shrink-0">{{ formatRupiah(k.sisa) }}</p>
                </div>
              </div>
            </div>

            <!-- Manual -->
            <div>
              <div class="flex items-center justify-between mb-2">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Manual</p>
                <button @click="piutangFormOpen = !piutangFormOpen" class="flex items-center gap-1 text-xs font-bold px-3 py-1.5 rounded-lg border border-slate-200 hover:bg-slate-50 transition-colors text-slate-600">
                  <i :class="piutangFormOpen ? 'pi pi-times' : 'pi pi-plus'" class="text-[10px]"></i>
                  {{ piutangFormOpen ? 'Batal' : 'Tambah' }}
                </button>
              </div>

              <!-- Form tambah -->
              <div v-if="piutangFormOpen" class="bg-slate-50 rounded-xl p-3 mb-3 space-y-2">
                <input v-model="piutangForm.name" type="text" placeholder="Nama (mis: Invoice Pak Abdul)" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[#1D3557]/20 bg-white" />
                <input v-model="piutangForm.amount" type="text" inputmode="numeric" placeholder="Nominal (mis: 58131000)" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[#1D3557]/20 bg-white" />
                <input v-model="piutangForm.notes" type="text" placeholder="Keterangan (opsional)" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[#1D3557]/20 bg-white" />
                <button @click="addManualPiutang" :disabled="piutangSubmitting" class="w-full bg-[#1D3557] text-white text-xs font-bold py-2 rounded-lg hover:bg-[#162840] disabled:opacity-40 transition-colors">
                  Simpan
                </button>
              </div>

              <div v-if="manualPiutang.length === 0 && !piutangFormOpen" class="text-xs text-slate-400 py-2">Belum ada entri manual.</div>
              <div v-else class="space-y-1.5 max-h-48 overflow-y-auto pr-1">
                <div v-for="p in manualPiutang" :key="p.id" class="flex items-center justify-between gap-3 py-1.5 border-b border-slate-50 last:border-0 group">
                  <div class="min-w-0">
                    <p class="text-xs font-semibold text-slate-700 truncate">{{ p.name }}</p>
                    <p v-if="p.notes" class="text-[10px] text-slate-400 truncate">{{ p.notes }}</p>
                  </div>
                  <div class="flex items-center gap-2 shrink-0">
                    <p class="text-xs font-bold text-amber-600">{{ formatRupiah(p.amount) }}</p>
                    <button @click="deleteManualPiutang(p.id)" class="text-slate-300 hover:text-red-500 transition-colors opacity-0 group-hover:opacity-100">
                      <i class="pi pi-times text-[10px]"></i>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="section-divider">
          <div class="w-1.5 h-1.5 rounded-full bg-accent/30"></div>
        </div>

      </div>

      <!-- Module: Inventory -->
      <div v-else-if="activeTab === 'inventory-ruko'">
        <InventoryModule view="ruko" />
      </div>
      <div v-else-if="activeTab === 'inventory-margomulyo'">
        <InventoryModule view="margomulyo" />
      </div>
      <div v-else-if="activeTab === 'inventory-history'">
        <InventoryModule view="history" />
      </div>

      <!-- Module: Employees -->
      <div v-else-if="activeTab === 'employees'">
        <EmployeeList />
      </div>

      <!-- Module: Attendance -->
      <div v-else-if="activeTab === 'attendance'">
        <AttendanceModule />
      </div>

      <!-- Module: Payroll -->
      <div v-else-if="activeTab === 'payroll'">
        <PayrollModule />
      </div>

      <!-- Module: Sales -->
      <div v-else-if="activeTab === 'sales'">
        <!-- Card Piutang di tab Sales -->
        <div class="premium-card bg-white mb-6 border border-amber-100">
          <div class="flex items-center justify-between mb-4">
            <div>
              <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Total Piutang</p>
              <h3 class="text-2xl font-display font-bold text-amber-600 leading-tight mt-1">Rp{{ totalAllPiutang.toLocaleString('id-ID') }}</h3>
            </div>
            <div class="text-right">
              <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">{{ kekurangan.length }} invoice · {{ manualPiutang.length }} manual</p>
              <i class="pi pi-exclamation-circle text-2xl text-amber-300 mt-1"></i>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Dari Invoice -->
            <div>
              <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Dari Invoice</p>
              <div v-if="kekurangan.length === 0" class="text-xs text-slate-400 py-2">Semua invoice lunas.</div>
              <div v-else class="space-y-2 max-h-56 overflow-y-auto">
                <div v-for="k in kekurangan" :key="k.invoice_number" class="flex items-center justify-between bg-amber-50 rounded-xl px-4 py-2.5">
                  <div>
                    <p class="text-xs font-bold text-slate-700">{{ k.recipient_name }}</p>
                    <p class="text-[10px] text-slate-400 font-mono">{{ k.invoice_number }}</p>
                  </div>
                  <p class="text-xs font-bold text-amber-600">{{ formatRupiah(k.sisa) }}</p>
                </div>
              </div>
            </div>

            <!-- Manual -->
            <div>
              <div class="flex items-center justify-between mb-2">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Manual</p>
                <button @click="piutangFormOpen = !piutangFormOpen" class="flex items-center gap-1 text-xs font-bold px-3 py-1.5 rounded-lg border border-slate-200 hover:bg-slate-50 transition-colors text-slate-600">
                  <i :class="piutangFormOpen ? 'pi pi-times' : 'pi pi-plus'" class="text-[10px]"></i>
                  {{ piutangFormOpen ? 'Batal' : 'Tambah' }}
                </button>
              </div>
              <div v-if="piutangFormOpen" class="bg-amber-50 border border-amber-100 rounded-xl px-4 py-3 mb-2 space-y-2">
                <input v-model="piutangForm.name" type="text" placeholder="Nama" class="w-full border border-amber-200 rounded-xl px-3 py-2 text-xs bg-white focus:outline-none focus:ring-2 focus:ring-amber-300/40 focus:border-amber-300" />
                <div class="relative">
                  <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none">Rp</span>
                  <input @input="onPiutangAmountInput" type="text" inputmode="numeric" placeholder="0" class="w-full border border-amber-200 rounded-xl pl-8 pr-3 py-2 text-xs bg-white focus:outline-none focus:ring-2 focus:ring-amber-300/40 focus:border-amber-300" />
                </div>
                <input v-model="piutangForm.notes" type="text" placeholder="Keterangan (opsional)" class="w-full border border-amber-200 rounded-xl px-3 py-2 text-xs bg-white focus:outline-none focus:ring-2 focus:ring-amber-300/40 focus:border-amber-300" />
                <button @click="addManualPiutang" :disabled="piutangSubmitting" class="w-full bg-amber-500 text-white text-xs font-bold py-2 rounded-xl hover:bg-amber-600 disabled:opacity-40 transition-colors">Simpan</button>
              </div>
              <div v-if="manualPiutang.length === 0 && !piutangFormOpen" class="text-xs text-slate-400 py-2">Belum ada entri manual.</div>
              <div v-else class="space-y-2 max-h-56 overflow-y-auto">
                <div v-for="p in manualPiutang" :key="p.id" class="flex items-center justify-between bg-amber-50 rounded-xl px-4 py-2.5 group">
                  <div>
                    <p class="text-xs font-bold text-slate-700">{{ p.name }}</p>
                    <p v-if="p.notes" class="text-[10px] text-slate-400">{{ p.notes }}</p>
                  </div>
                  <div class="flex items-center gap-2">
                    <p class="text-xs font-bold text-amber-600">{{ formatRupiah(p.amount) }}</p>
                    <button @click="deleteManualPiutang(p.id)" class="text-slate-300 hover:text-red-500 transition-colors opacity-0 group-hover:opacity-100">
                      <i class="pi pi-times text-[10px]"></i>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <SalesModule />
      </div>

      <!-- Module: Expenses -->
      <div v-else-if="activeTab === 'expenses'">
        <ExpenseModule />
      </div>

      <!-- Module: Incomes -->
      <div v-else-if="activeTab === 'incomes'">
        <IncomeModule />
      </div>

      <!-- Module: RAB Budget -->
      <div v-else-if="activeTab === 'rab'">
        <BudgetModule />
      </div>

      <!-- Module Placeholder -->
      <div v-else class="flex flex-col items-center justify-center py-32 premium-card bg-white/40 border-dashed">
        <div class="w-24 h-24 bg-primary text-white rounded-3xl flex items-center justify-center text-4xl mb-8 shadow-2xl shadow-primary/20 animate-bounce">
          <i class="pi pi-box"></i>
        </div>
        <h3 class="font-display font-bold text-3xl text-primary capitalize">Pilih sub-menu Inventory</h3>
        <p class="text-slate-500 mt-3 text-sm max-w-xs text-center leading-relaxed">
          Sistem sedang mempersiapkan antarmuka untuk modul ini sesuai dengan alur kerja di <span class="text-accent font-bold">plan.md</span>.
        </p>
        <div class="mt-10 flex gap-4">
          <div class="w-2 h-2 rounded-full bg-accent animate-ping"></div>
          <div class="w-2 h-2 rounded-full bg-accent animate-ping delay-75"></div>
          <div class="w-2 h-2 rounded-full bg-accent animate-ping delay-150"></div>
        </div>
      </div>
    </div>
  </MainLayout>
</template>
