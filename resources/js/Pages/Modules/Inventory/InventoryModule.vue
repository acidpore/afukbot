<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed, watch } from 'vue';
import { inventoryApi } from '@/api/inventory.api';

const activeSubTab = ref<'items' | 'history'>('items');
const items = ref<any[]>([]);
const categories = ref<any[]>([]);
const transactions = ref<any[]>([]);
const isLoading = ref(false);
const searchQuery = ref('');

// Modal States
const isItemModalOpen = ref(false);
const isAdjustModalOpen = ref(false);
const isCategoryModalOpen = ref(false);
const modalMode = ref<'create' | 'edit'>('create');
const selectedItem = ref<any>(null);
const categoryForm = ref({ name: '' });

// Bulk Import
const isBulkModalOpen = ref(false);
const bulkStep = ref<'upload' | 'preview' | 'result'>('upload');
const bulkRows = ref<any[]>([]);
const bulkResult = ref<{ created: number; errors: any[] } | null>(null);
const bulkFileError = ref('');

const BULK_HEADERS = ['name', 'category', 'quantity', 'unit', 'harga_jual'];

const parseCsvFile = (file: File) => {
  return new Promise<any[]>((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = (e) => {
      const text = e.target?.result as string;
      const lines = text.trim().split('\n').filter(l => l.trim());

      // detect delimiter: tab, semicolon, or comma
      const firstLine = lines[0];
      const delimiter = firstLine.includes('\t') ? '\t' : firstLine.includes(';') ? ';' : ',';

      // skip header row if first cell matches known header
      const startIndex = firstLine.toLowerCase().includes('name') || firstLine.toLowerCase().includes('nama') ? 1 : 0;

      const rows = lines.slice(startIndex).map((line, i) => {
        const cols = line.split(delimiter).map(c => c.trim().replace(/^"|"$/g, ''));
        const isEmpty = cols.every(c => !c);
        if (isEmpty) return null;
        return {
          name:       cols[0] || '',
          category:   cols[1] || '',
          quantity:   parseInt(cols[2]) || 0,
          unit:       cols[3] || 'pcs',
          harga_jual: cols[4] ? parseInt(cols[4].replace(/[^0-9]/g, '')) : 0,
          _row:       startIndex + i + 1,
          _error:     !cols[0] || !cols[1] ? 'Nama/Kategori kosong' : '',
        };
      }).filter(Boolean);

      resolve(rows);
    };
    reader.onerror = reject;
    reader.readAsText(file);
  });
};

const onBulkFileChange = async (e: Event) => {
  const file = (e.target as HTMLInputElement).files?.[0];
  bulkFileError.value = '';
  if (!file) return;

  const ext = file.name.split('.').pop()?.toLowerCase();
  if (!['csv', 'txt'].includes(ext ?? '')) {
    bulkFileError.value = 'Hanya file .csv yang didukung. Simpan Excel kamu sebagai CSV dulu.';
    return;
  }

  try {
    bulkRows.value = await parseCsvFile(file);
    bulkStep.value = 'preview';
  } catch {
    bulkFileError.value = 'Gagal membaca file.';
  }
};

const handleBulkSubmit = async () => {
  const validRows = bulkRows.value.filter(r => !r._error);
  try {
    const res = await inventoryApi.bulkCreateItems(validRows.map(({ _row, _error, ...rest }) => rest));
    bulkResult.value = res.data.data;
    bulkStep.value = 'result';
    fetchData();
  } catch {
    bulkFileError.value = 'Gagal menyimpan data.';
  }
};

const downloadCsvTemplate = () => {
  const rows = [
    'Nama Barang,Kategori,Stok Awal,Satuan,Harga Jual',
    'Foodtray Badan,Foodtray,3500,pcs,1500',
    'Foodtray Tutup,Foodtray,2000,pcs,1200',
    'Sendok Plastik,Alat Makan,500,lusin,',
  ];
  const blob = new Blob([rows.join('\n')], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'template_import_barang.csv';
  a.click();
  URL.revokeObjectURL(url);
};

const resetBulkModal = () => {
  isBulkModalOpen.value = false;
  bulkStep.value = 'upload';
  bulkRows.value = [];
  bulkResult.value = null;
  bulkFileError.value = '';
};

const itemForm = ref({
  id: null as number | null,
  name: '',
  category_id: '',
  quantity: 0,
  unit: 'pcs',
  description: '',
  harga_jual: 0
});

const hargaJualDisplay = ref('');

const onHargaJualInput = (e: Event) => {
  const raw = (e.target as HTMLInputElement).value.replace(/\D/g, '');
  itemForm.value.harga_jual = raw ? parseInt(raw) : 0;
  hargaJualDisplay.value = raw ? parseInt(raw).toLocaleString('id-ID') : '';
};

const syncHargaJualDisplay = () => {
  hargaJualDisplay.value = itemForm.value.harga_jual
    ? itemForm.value.harga_jual.toLocaleString('id-ID')
    : '';
};

const adjustForm = ref({
  item_id: null as number | null,
  type: 'IN' as 'IN' | 'OUT',
  quantity: 1,
  notes: ''
});

const fetchData = async () => {
  isLoading.value = true;
  try {
    const [itemsRes, catRes, transRes] = await Promise.all([
      inventoryApi.getItems(),
      inventoryApi.getCategories(),
      inventoryApi.getTransactions()
    ]);
    items.value = itemsRes.data.data;
    categories.value = catRes.data.data;
    transactions.value = transRes.data.data;
  } catch (error) {
    console.error('Gagal mengambil data inventory:', error);
  } finally {
    isLoading.value = false;
  }
};

let pollInterval: ReturnType<typeof setInterval>;

watch(searchQuery, () => { currentPage.value = 1; });

onMounted(() => {
  fetchData();
  pollInterval = setInterval(async () => {
    try {
      const [itemsRes, transRes] = await Promise.all([
        inventoryApi.getItems(),
        inventoryApi.getTransactions(),
      ]);
      items.value = itemsRes.data.data;
      transactions.value = transRes.data.data;
    } catch {}
  }, 8000);
});

onUnmounted(() => clearInterval(pollInterval));

const currentPage = ref(1);
const perPage = 10;
const sortKey = ref<'name' | 'category' | 'quantity' | 'unit' | 'harga_jual'>('quantity');
const sortDir = ref<'asc' | 'desc'>('desc');

const toggleSort = (key: typeof sortKey.value) => {
  if (sortKey.value === key) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
  } else {
    sortKey.value = key;
    sortDir.value = key === 'quantity' || key === 'harga_jual' ? 'desc' : 'asc';
  }
  currentPage.value = 1;
};

const filteredItems = computed(() => {
  const q = searchQuery.value.toLowerCase();
  return items.value
    .filter(item =>
      item.name.toLowerCase().includes(q) ||
      item.category?.name.toLowerCase().includes(q)
    )
    .sort((a, b) => {
      let valA: any, valB: any;
      if (sortKey.value === 'category') {
        valA = a.category?.name ?? '';
        valB = b.category?.name ?? '';
      } else {
        valA = a[sortKey.value] ?? '';
        valB = b[sortKey.value] ?? '';
      }
      if (typeof valA === 'string') {
        return sortDir.value === 'asc' ? valA.localeCompare(valB) : valB.localeCompare(valA);
      }
      return sortDir.value === 'asc' ? valA - valB : valB - valA;
    });
});

const totalPages = computed(() => Math.ceil(filteredItems.value.length / perPage));

const paginatedItems = computed(() => {
  const start = (currentPage.value - 1) * perPage;
  return filteredItems.value.slice(start, start + perPage);
});

const openItemModal = (item: any = null) => {
  if (item) {
    modalMode.value = 'edit';
    itemForm.value = { ...item, category_id: item.category_id.toString() };
    syncHargaJualDisplay();
  } else {
    modalMode.value = 'create';
    itemForm.value = { id: null, name: '', category_id: '', quantity: 0, unit: 'pcs', description: '', harga_jual: 0 };
    hargaJualDisplay.value = '';
  }
  isItemModalOpen.value = true;
};

const openAdjustModal = (item: any) => {
  selectedItem.value = item;
  adjustForm.value = { item_id: item.id, type: 'IN', quantity: 1, notes: '' };
  isAdjustModalOpen.value = true;
};

const handleSaveItem = async () => {
  try {
    if (modalMode.value === 'create') {
      await inventoryApi.createItem(itemForm.value);
    } else {
      await inventoryApi.updateItem(itemForm.value.id!, itemForm.value);
    }
    isItemModalOpen.value = false;
    fetchData();
  } catch (error) {
    alert('Gagal menyimpan barang');
  }
};

const handleAdjustStock = async () => {
  try {
    await inventoryApi.adjustStock(adjustForm.value as any);
    isAdjustModalOpen.value = false;
    fetchData();
  } catch (error) {
    alert('Gagal menyesuaikan stok');
  }
};

const handleDeleteItem = async (id: number) => {
  if (!confirm('Hapus barang ini?')) return;
  try {
    await inventoryApi.deleteItem(id);
    fetchData();
  } catch (error) {
    alert('Gagal menghapus barang');
  }
};

const handleSaveCategory = async () => {
  if (!categoryForm.value.name.trim()) return;
  try {
    await inventoryApi.createCategory({ name: categoryForm.value.name.trim() });
    isCategoryModalOpen.value = false;
    categoryForm.value.name = '';
    fetchData();
  } catch (error) {
    alert('Gagal menyimpan kategori');
  }
};
</script>

<template>
  <div class="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-700">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
      <div>
        <h2 class="text-3xl font-display font-bold text-primary">Inventory Stok</h2>
        <p class="text-slate-500 text-sm mt-1">Manajemen bahan baku, aset, dan perlengkapan MBG.</p>
      </div>
      <div class="flex items-center gap-2">
        <div class="relative">
          <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Cari barang..."
            class="bg-white border border-slate-200 rounded-lg pl-8 pr-3 py-2 text-xs outline-none focus:ring-2 focus:ring-accent/20 focus:border-accent shadow-sm w-52"
          >
        </div>
        <button @click="isBulkModalOpen = true" class="h-10 px-4 rounded-xl border border-slate-200 bg-white text-slate-500 hover:text-primary hover:border-primary/30 text-xs font-bold uppercase tracking-widest flex items-center gap-2 transition-all">
          <i class="pi pi-upload text-xs"></i>
          Import
        </button>
        <button @click="isCategoryModalOpen = true" class="h-10 px-4 rounded-xl border border-slate-200 bg-white text-slate-500 hover:text-primary hover:border-primary/30 text-xs font-bold uppercase tracking-widest flex items-center gap-2 transition-all">
          <i class="pi pi-tag text-xs"></i>
          Kategori
        </button>
        <button @click="openItemModal()" class="h-10 px-5 rounded-xl bg-primary text-white text-xs font-bold uppercase tracking-widest flex items-center gap-2 hover:bg-primary-light transition-all shadow-lg shadow-primary/20">
          <i class="pi pi-plus text-xs"></i>
          Tambah
        </button>
      </div>
    </div>

    <!-- Sub Navigation Tabs -->
    <div class="flex gap-1 p-1 bg-slate-100 rounded-2xl w-fit">
      <button 
        @click="activeSubTab = 'items'"
        :class="activeSubTab === 'items' ? 'bg-white text-primary shadow-sm' : 'text-slate-500 hover:text-primary'"
        class="px-6 py-2 rounded-xl text-xs font-bold transition-all"
      >
        Stok Barang
      </button>
      <button 
        @click="activeSubTab = 'history'"
        :class="activeSubTab === 'history' ? 'bg-white text-primary shadow-sm' : 'text-slate-500 hover:text-primary'"
        class="px-6 py-2 rounded-xl text-xs font-bold transition-all"
      >
        Riwayat Transaksi
      </button>
    </div>

    <!-- Main Content -->
    <div v-if="activeSubTab === 'items'" class="premium-card bg-white p-0 overflow-hidden shadow-2xl shadow-primary/5">
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-slate-50/50 border-b border-slate-100">
              <th @click="toggleSort('name')" class="px-6 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest cursor-pointer hover:text-primary select-none">
                Nama Barang <i :class="sortKey === 'name' ? (sortDir === 'asc' ? 'pi pi-sort-amount-up-alt' : 'pi pi-sort-amount-down') : 'pi pi-sort-alt'" class="ml-1 opacity-40"></i>
              </th>
              <th @click="toggleSort('category')" class="px-6 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest cursor-pointer hover:text-primary select-none">
                Kategori <i :class="sortKey === 'category' ? (sortDir === 'asc' ? 'pi pi-sort-amount-up-alt' : 'pi pi-sort-amount-down') : 'pi pi-sort-alt'" class="ml-1 opacity-40"></i>
              </th>
              <th @click="toggleSort('quantity')" class="px-6 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest text-center cursor-pointer hover:text-primary select-none">
                Stok <i :class="sortKey === 'quantity' ? (sortDir === 'asc' ? 'pi pi-sort-amount-up-alt' : 'pi pi-sort-amount-down') : 'pi pi-sort-alt'" class="ml-1 opacity-40"></i>
              </th>
              <th @click="toggleSort('unit')" class="px-6 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest text-center cursor-pointer hover:text-primary select-none">
                Satuan <i :class="sortKey === 'unit' ? (sortDir === 'asc' ? 'pi pi-sort-amount-up-alt' : 'pi pi-sort-amount-down') : 'pi pi-sort-alt'" class="ml-1 opacity-40"></i>
              </th>
              <th @click="toggleSort('harga_jual')" class="px-6 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest text-right cursor-pointer hover:text-primary select-none">
                Harga Jual <i :class="sortKey === 'harga_jual' ? (sortDir === 'asc' ? 'pi pi-sort-amount-up-alt' : 'pi pi-sort-amount-down') : 'pi pi-sort-alt'" class="ml-1 opacity-40"></i>
              </th>
              <th class="px-6 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50">
            <tr v-if="isLoading" v-for="i in 5" :key="i" class="animate-pulse">
              <td colspan="5" class="px-6 py-8 h-16 bg-slate-50/10"></td>
            </tr>

            <tr v-else v-for="item in paginatedItems" :key="item.id" class="hover:bg-slate-50/30 transition-colors group">
              <td class="px-6 py-5">
                <p class="text-sm font-bold text-slate-900">{{ item.name }}</p>
                <p class="text-[10px] text-slate-400 font-medium truncate max-w-[200px]">{{ item.description || 'Tidak ada deskripsi' }}</p>
              </td>
              <td class="px-6 py-5 text-center">
                <span class="bg-slate-100 text-slate-600 text-[10px] font-bold px-2.5 py-1 rounded-lg uppercase tracking-wider">
                  {{ item.category?.name }}
                </span>
              </td>
              <td class="px-6 py-5 text-center">
                <span 
                  :class="item.quantity <= 5 ? 'text-red-600' : 'text-primary'"
                  class="text-lg font-display font-bold"
                >
                  {{ item.quantity }}
                </span>
              </td>
              <td class="px-6 py-5 text-center text-xs font-bold text-slate-400 uppercase tracking-widest">
                {{ item.unit }}
              </td>
              <td class="px-6 py-5 text-center text-sm font-bold text-slate-700">
                {{ item.harga_jual ? 'Rp ' + item.harga_jual.toLocaleString('id-ID') : '-' }}
              </td>
              <td class="px-6 py-5 text-right">
                <div class="flex items-center justify-end gap-2">
                  <button @click="openAdjustModal(item)" class="h-8 px-3 rounded-lg border border-primary/20 text-primary text-[10px] font-bold uppercase tracking-widest hover:bg-primary hover:text-white transition-all">
                    Update Stok
                  </button>
                  <button @click="openItemModal(item)" class="w-8 h-8 rounded-lg border border-slate-100 flex items-center justify-center text-slate-400 hover:text-primary hover:border-primary/30 transition-all">
                    <i class="pi pi-pencil text-xs"></i>
                  </button>
                  <button @click="handleDeleteItem(item.id)" class="w-8 h-8 rounded-lg border border-slate-100 flex items-center justify-center text-slate-400 hover:text-red-500 hover:border-red-100 transition-all">
                    <i class="pi pi-trash text-xs"></i>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="totalPages > 1" class="flex items-center justify-between px-6 py-4 border-t border-slate-100">
        <p class="text-xs text-slate-400">
          Menampilkan {{ (currentPage - 1) * perPage + 1 }}–{{ Math.min(currentPage * perPage, filteredItems.length) }} dari {{ filteredItems.length }} barang
        </p>
        <div class="flex items-center gap-1">
          <button
            @click="currentPage--"
            :disabled="currentPage === 1"
            class="w-8 h-8 rounded-lg border border-slate-100 flex items-center justify-center text-slate-400 hover:text-primary hover:border-primary/30 disabled:opacity-30 disabled:cursor-not-allowed transition-all"
          ><i class="pi pi-chevron-left text-xs"></i></button>
          <button
            v-for="p in totalPages" :key="p"
            @click="currentPage = p"
            :class="p === currentPage ? 'bg-primary text-white border-primary' : 'border-slate-100 text-slate-500 hover:border-primary/30 hover:text-primary'"
            class="w-8 h-8 rounded-lg border text-xs font-bold transition-all"
          >{{ p }}</button>
          <button
            @click="currentPage++"
            :disabled="currentPage === totalPages"
            class="w-8 h-8 rounded-lg border border-slate-100 flex items-center justify-center text-slate-400 hover:text-primary hover:border-primary/30 disabled:opacity-30 disabled:cursor-not-allowed transition-all"
          ><i class="pi pi-chevron-right text-xs"></i></button>
        </div>
      </div>
    </div>

    <!-- History View -->
    <div v-else class="space-y-6">
      <div v-for="t in transactions" :key="t.id" class="premium-card bg-white flex items-center justify-between hover:border-accent/30 transition-all group">
        <div class="flex items-center gap-6">
          <div :class="t.type === 'IN' ? 'bg-emerald-50 text-emerald-600' : 'bg-red-50 text-red-600'" class="w-12 h-12 rounded-2xl flex items-center justify-center text-xl shadow-sm group-hover:scale-110 transition-transform">
            <i :class="t.type === 'IN' ? 'pi pi-arrow-down-left' : 'pi pi-arrow-up-right'"></i>
          </div>
          <div>
            <div class="flex items-center gap-3">
              <p class="text-sm font-bold text-slate-900">{{ t.item?.name }}</p>
              <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">{{ t.item?.category?.name }}</span>
            </div>
            <p class="text-[10px] text-slate-400 mt-1">
              <span class="font-bold text-slate-500">{{ t.date }}</span> • {{ t.notes || 'Tanpa catatan' }}
            </p>
          </div>
        </div>
        <div class="text-right">
          <p :class="t.type === 'IN' ? 'text-emerald-600' : 'text-red-600'" class="text-xl font-display font-bold">
            {{ t.type === 'IN' ? '+' : '-' }}{{ t.quantity }}
          </p>
          <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">{{ t.item?.unit }}</p>
        </div>
      </div>
    </div>

    <!-- Item Modal -->
    <div v-if="isItemModalOpen" class="fixed inset-0 z-[100] flex items-center justify-center p-6">
      <div @click="isItemModalOpen = false" class="absolute inset-0 bg-primary/40 backdrop-blur-sm"></div>
      <div class="relative w-full max-w-xl bg-white rounded-3xl shadow-2xl overflow-hidden animate-in zoom-in-95 duration-300">
        <div class="p-8 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
          <h3 class="text-xl font-display font-bold text-primary">{{ modalMode === 'create' ? 'Tambah' : 'Edit' }} Barang</h3>
          <button @click="isItemModalOpen = false" class="text-slate-400 hover:text-primary"><i class="pi pi-times"></i></button>
        </div>
        <div class="p-8 space-y-6">
          <div>
            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Nama Barang</label>
            <input v-model="itemForm.name" type="text" class="w-full bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-accent/20 outline-none">
          </div>
          <div class="grid grid-cols-2 gap-6">
            <div>
              <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Kategori</label>
              <select v-model="itemForm.category_id" class="w-full bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-accent/20 outline-none">
                <option value="" disabled>Pilih Kategori</option>
                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Satuan (Unit)</label>
              <input v-model="itemForm.unit" type="text" placeholder="pcs, kg, liter..." class="w-full bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-accent/20 outline-none">
            </div>
          </div>
          <div class="grid grid-cols-2 gap-6">
            <div v-if="modalMode === 'create'">
              <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Stok Awal</label>
              <input v-model.number="itemForm.quantity" type="number" min="0" placeholder="0" class="w-full bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-accent/20 outline-none">
            </div>
            <div :class="modalMode === 'create' ? '' : 'col-span-2'">
              <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Harga Jual (Rp)</label>
              <div class="relative">
                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-sm font-bold text-slate-400">Rp</span>
                <input
                  :value="hargaJualDisplay"
                  @input="onHargaJualInput"
                  type="text"
                  inputmode="numeric"
                  placeholder="0"
                  class="w-full bg-slate-50 border border-slate-100 rounded-xl pl-10 pr-4 py-3 text-sm focus:ring-2 focus:ring-accent/20 outline-none"
                >
              </div>
            </div>
          </div>
          <div>
            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Deskripsi</label>
            <textarea v-model="itemForm.description" rows="3" class="w-full bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-accent/20 outline-none"></textarea>
          </div>
          <div class="pt-6 flex justify-end gap-3">
            <button @click="isItemModalOpen = false" class="px-6 py-3 text-sm font-bold text-slate-400 hover:text-slate-600">Batal</button>
            <button @click="handleSaveItem" class="btn-primary px-10">Simpan</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Bulk Import Modal -->
    <div v-if="isBulkModalOpen" class="fixed inset-0 z-[100] flex items-center justify-center p-6">
      <div @click="resetBulkModal" class="absolute inset-0 bg-primary/40 backdrop-blur-sm"></div>
      <div class="relative w-full max-w-4xl bg-white rounded-3xl shadow-2xl overflow-hidden animate-in zoom-in-95 duration-300">
        <div class="p-8 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
          <div>
            <h3 class="text-xl font-display font-bold text-primary">Import Barang</h3>
            <p class="text-xs text-slate-400 mt-0.5">
              <span v-if="bulkStep === 'upload'">Upload file CSV untuk import massal</span>
              <span v-else-if="bulkStep === 'preview'">Preview {{ bulkRows.length }} baris data</span>
              <span v-else>Import selesai</span>
            </p>
          </div>
          <button @click="resetBulkModal" class="text-slate-400 hover:text-primary"><i class="pi pi-times"></i></button>
        </div>

        <!-- Step 1: Upload -->
        <div v-if="bulkStep === 'upload'" class="p-8 space-y-6">
          <div class="bg-slate-50 border-2 border-dashed border-slate-200 rounded-2xl p-10 text-center">
            <i class="pi pi-file-excel text-4xl text-slate-300 mb-4 block"></i>
            <p class="text-sm font-bold text-slate-500 mb-1">Pilih file CSV</p>
            <p class="text-xs text-slate-400 mb-6">Simpan Excel/Sheets kamu sebagai <strong>.csv</strong> terlebih dahulu</p>
            <label class="btn-primary cursor-pointer inline-flex">
              <i class="pi pi-upload"></i>
              Pilih File
              <input type="file" accept=".csv,.txt" class="hidden" @change="onBulkFileChange">
            </label>
            <p v-if="bulkFileError" class="text-red-500 text-xs mt-4 font-bold">{{ bulkFileError }}</p>
          </div>
          <div class="bg-amber-50 border border-amber-100 rounded-xl p-4 text-xs text-amber-700 flex items-center justify-between gap-4">
            <div class="space-y-1">
              <p class="font-bold">Format kolom (urutan harus sesuai):</p>
              <p class="font-mono">Nama Barang, Kategori, Stok Awal, Satuan, Harga Jual</p>
              <p class="text-amber-500">Harga Jual boleh kosong.</p>
            </div>
            <button @click="downloadCsvTemplate" class="shrink-0 flex items-center gap-2 bg-white border border-amber-200 text-amber-700 hover:bg-amber-100 transition-colors px-4 py-2 rounded-xl font-bold text-xs">
              <i class="pi pi-download"></i>
              Download Template
            </button>
          </div>
        </div>

        <!-- Step 2: Preview -->
        <div v-else-if="bulkStep === 'preview'" class="flex flex-col max-h-[70vh]">
          <div class="overflow-auto flex-1">
            <table class="w-full text-left text-sm border-collapse">
              <thead class="sticky top-0 bg-slate-50 border-b border-slate-100">
                <tr>
                  <th class="px-4 py-3 text-[10px] font-bold text-slate-400 uppercase tracking-widest">#</th>
                  <th class="px-4 py-3 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Nama Barang</th>
                  <th class="px-4 py-3 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Kategori</th>
                  <th class="px-4 py-3 text-[10px] font-bold text-slate-400 uppercase tracking-widest text-center">Stok</th>
                  <th class="px-4 py-3 text-[10px] font-bold text-slate-400 uppercase tracking-widest text-center">Satuan</th>
                  <th class="px-4 py-3 text-[10px] font-bold text-slate-400 uppercase tracking-widest text-right">Harga Jual</th>
                  <th class="px-4 py-3 text-[10px] font-bold text-slate-400 uppercase tracking-widest text-center">Status</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-50">
                <tr v-for="row in bulkRows" :key="row._row" :class="row._error ? 'bg-red-50' : 'hover:bg-slate-50/50'">
                  <td class="px-4 py-3 text-xs text-slate-400">{{ row._row }}</td>
                  <td class="px-4 py-3 font-bold text-slate-800">{{ row.name || '-' }}</td>
                  <td class="px-4 py-3 text-slate-500">{{ row.category || '-' }}</td>
                  <td class="px-4 py-3 text-center text-slate-700">{{ row.quantity }}</td>
                  <td class="px-4 py-3 text-center text-slate-400 uppercase text-[10px] tracking-widest">{{ row.unit }}</td>
                  <td class="px-4 py-3 text-right text-slate-700">{{ row.harga_jual ? 'Rp ' + row.harga_jual.toLocaleString('id-ID') : '-' }}</td>
                  <td class="px-4 py-3 text-center">
                    <span v-if="row._error" class="text-[10px] font-bold text-red-500 bg-red-50 px-2 py-0.5 rounded-lg">{{ row._error }}</span>
                    <span v-else class="text-[10px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-lg">OK</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="p-6 border-t border-slate-100 flex items-center justify-between bg-white">
            <p class="text-xs text-slate-400">
              <span class="font-bold text-emerald-600">{{ bulkRows.filter(r => !r._error).length }} valid</span>
              <span v-if="bulkRows.filter(r => r._error).length" class="ml-3 font-bold text-red-500">{{ bulkRows.filter(r => r._error).length }} error (akan dilewati)</span>
            </p>
            <div class="flex gap-3">
              <button @click="bulkStep = 'upload'" class="px-6 py-3 text-sm font-bold text-slate-400 hover:text-slate-600">Ganti File</button>
              <button @click="handleBulkSubmit" class="btn-primary px-10" :disabled="!bulkRows.filter(r => !r._error).length">Simpan Semua</button>
            </div>
          </div>
        </div>

        <!-- Step 3: Result -->
        <div v-else class="p-8 text-center space-y-4">
          <div class="w-16 h-16 bg-emerald-50 rounded-full flex items-center justify-center mx-auto">
            <i class="pi pi-check text-2xl text-emerald-600"></i>
          </div>
          <p class="text-lg font-display font-bold text-slate-900">Import Selesai</p>
          <p class="text-sm text-slate-500"><span class="font-bold text-emerald-600">{{ bulkResult?.created }} barang</span> berhasil disimpan.</p>
          <p v-if="bulkResult?.skipped" class="text-sm text-slate-400"><span class="font-bold text-amber-500">{{ bulkResult.skipped }} dilewati</span> karena nama sudah ada.</p>
          <div v-if="bulkResult?.errors?.length" class="text-left bg-red-50 rounded-xl p-4 space-y-1">
            <p class="text-xs font-bold text-red-600 mb-2">Gagal ({{ bulkResult.errors.length }} baris):</p>
            <p v-for="e in bulkResult.errors" :key="e.row" class="text-xs text-red-500">Baris {{ e.row }} — {{ e.name }}: {{ e.reason }}</p>
          </div>
          <button @click="resetBulkModal" class="btn-primary mx-auto mt-4">Tutup</button>
        </div>
      </div>
    </div>

    <!-- Category Modal -->
    <div v-if="isCategoryModalOpen" class="fixed inset-0 z-[100] flex items-center justify-center p-6">
      <div @click="isCategoryModalOpen = false" class="absolute inset-0 bg-primary/40 backdrop-blur-sm"></div>
      <div class="relative w-full max-w-sm bg-white rounded-3xl shadow-2xl overflow-hidden animate-in zoom-in-95 duration-300">
        <div class="p-8 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
          <h3 class="text-xl font-display font-bold text-primary">Tambah Kategori</h3>
          <button @click="isCategoryModalOpen = false" class="text-slate-400 hover:text-primary"><i class="pi pi-times"></i></button>
        </div>
        <div class="p-8 space-y-6">
          <div>
            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Nama Kategori</label>
            <input v-model="categoryForm.name" type="text" placeholder="Misal: Foodtray, Alat Masak..." class="w-full bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-accent/20 outline-none">
          </div>
          <div class="flex justify-end gap-3">
            <button @click="isCategoryModalOpen = false" class="px-6 py-3 text-sm font-bold text-slate-400 hover:text-slate-600">Batal</button>
            <button @click="handleSaveCategory" class="btn-primary px-10">Simpan</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Adjust Stock Modal -->
    <div v-if="isAdjustModalOpen" class="fixed inset-0 z-[100] flex items-center justify-center p-6">
      <div @click="isAdjustModalOpen = false" class="absolute inset-0 bg-primary/40 backdrop-blur-sm"></div>
      <div class="relative w-full max-w-md bg-white rounded-3xl shadow-2xl overflow-hidden animate-in zoom-in-95 duration-300">
        <div class="p-8 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
          <h3 class="text-xl font-display font-bold text-primary">Update Stok</h3>
          <button @click="isAdjustModalOpen = false" class="text-slate-400 hover:text-primary"><i class="pi pi-times"></i></button>
        </div>
        <div class="p-8 space-y-6 text-center">
          <p class="text-xs font-bold text-slate-500 uppercase tracking-widest">{{ selectedItem?.name }}</p>
          <div class="flex items-center justify-center gap-4">
            <button 
              @click="adjustForm.type = 'OUT'"
              :class="adjustForm.type === 'OUT' ? 'bg-red-600 text-white' : 'bg-slate-100 text-slate-400'"
              class="w-20 py-2 rounded-xl text-[10px] font-bold uppercase tracking-widest transition-all"
            >Keluar</button>
            <button 
              @click="adjustForm.type = 'IN'"
              :class="adjustForm.type === 'IN' ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-400'"
              class="w-20 py-2 rounded-xl text-[10px] font-bold uppercase tracking-widest transition-all"
            >Masuk</button>
          </div>
          <div>
            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Jumlah ({{ selectedItem?.unit }})</label>
            <input v-model.number="adjustForm.quantity" type="number" min="1" class="w-full bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 text-center text-2xl font-display font-bold text-primary focus:ring-2 focus:ring-accent/20 outline-none">
          </div>
          <div>
            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Catatan</label>
            <input v-model="adjustForm.notes" type="text" placeholder="Misal: Restock bulanan..." class="w-full bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-accent/20 outline-none">
          </div>
          <div class="pt-6 flex flex-col gap-3">
            <button @click="handleAdjustStock" class="btn-primary w-full justify-center py-4">Konfirmasi Perubahan</button>
            <button @click="isAdjustModalOpen = false" class="text-sm font-bold text-slate-400 hover:text-slate-600">Batal</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
