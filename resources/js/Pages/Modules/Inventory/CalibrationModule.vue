<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { calibrationApi } from '@/api/calibration.api'
import LoadingState from '@/components/shared/LoadingState.vue'

type CalItem = {
  id: number
  name: string
  category: string
  qty_system: number
  qty_physical: number
  unit: string
  location: string
}

type HistoryRecord = {
  id: number
  calibrated_at: string
  calibrated_by: string
  total_items: number
  total_adjusted: number
  notes: string | null
}

const tab          = ref<'form' | 'history'>('form')
const isLoading    = ref(false)
const isSubmitting = ref(false)
const items        = ref<CalItem[]>([])
const history      = ref<HistoryRecord[]>([])
const notes        = ref('')
const searchQuery  = ref('')
const toast        = ref({ show: false, message: '', type: 'success' })
const result       = ref<{ total_adjusted: number } | null>(null)

const filteredItems = computed(() => {
  const q = searchQuery.value.toLowerCase()
  if (!q) return items.value
  return items.value.filter(i =>
    i.name.toLowerCase().includes(q) || i.category?.toLowerCase().includes(q)
  )
})

const changedCount = computed(() =>
  items.value.filter(i => i.qty_physical !== i.qty_system).length
)

function showToast(message: string, type = 'success') {
  toast.value = { show: true, message, type }
  setTimeout(() => (toast.value.show = false), 4000)
}

async function loadItems() {
  isLoading.value = true
  try {
    const res = await calibrationApi.getItems()
    items.value = res.data.data
  } finally {
    isLoading.value = false
  }
}

async function loadHistory() {
  const res = await calibrationApi.getHistory()
  history.value = res.data.data
}

async function applyCalibration() {
  if (!confirm(`Terapkan kalibrasi? ${changedCount.value} item akan disesuaikan.`)) return
  isSubmitting.value = true
  try {
    const res = await calibrationApi.apply({
      notes: notes.value || undefined,
      items: items.value.map(i => ({ id: i.id, qty_physical: i.qty_physical })),
    })
    result.value = { total_adjusted: res.data.total_adjusted }
    showToast(res.data.message)
    await loadItems()
    notes.value = ''
    if (tab.value === 'history') await loadHistory()
  } catch {
    showToast('Gagal menerapkan kalibrasi', 'error')
  } finally {
    isSubmitting.value = false
  }
}

function fmtDate(d: string) {
  return new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' })
}

onMounted(async () => {
  await loadItems()
  await loadHistory()
})
</script>

<template>
  <div class="space-y-6 animate-in fade-in slide-in-from-bottom-4 duration-700">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <h2 class="text-xl sm:text-3xl font-display font-bold text-primary">Kalibrasi Stok</h2>
        <p class="text-slate-500 text-sm mt-1">Cocokkan jumlah fisik barang dengan data sistem setiap minggu.</p>
      </div>
    </div>

    <!-- Tabs -->
    <div class="border-b border-slate-200">
      <nav class="flex gap-6">
        <button @click="tab = 'form'"
          :class="['pb-3 text-sm font-medium border-b-2 transition-colors', tab === 'form' ? 'border-primary text-primary' : 'border-transparent text-slate-500 hover:text-slate-700']">
          Form Kalibrasi
          <span v-if="changedCount > 0" class="ml-1.5 bg-amber-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full">{{ changedCount }}</span>
        </button>
        <button @click="tab = 'history'; loadHistory()"
          :class="['pb-3 text-sm font-medium border-b-2 transition-colors', tab === 'history' ? 'border-primary text-primary' : 'border-transparent text-slate-500 hover:text-slate-700']">
          Riwayat Kalibrasi
        </button>
      </nav>
    </div>

    <!-- Form Tab -->
    <div v-if="tab === 'form'" class="space-y-4">

      <!-- Result banner -->
      <div v-if="result" class="bg-green-50 border border-green-200 rounded-2xl px-5 py-4 flex items-center justify-between">
        <div>
          <p class="text-sm font-bold text-green-800">Kalibrasi berhasil diterapkan</p>
          <p class="text-xs text-green-600 mt-0.5">{{ result.total_adjusted }} item disesuaikan</p>
        </div>
        <button @click="result = null" class="text-green-400 hover:text-green-600">
          <i class="pi pi-times text-xs"></i>
        </button>
      </div>

      <!-- Toolbar -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="relative">
          <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
          <input v-model="searchQuery" type="text" placeholder="Cari barang..."
            class="bg-white border border-slate-200 rounded-xl pl-8 pr-3 py-2.5 text-xs outline-none focus:ring-2 focus:ring-accent/20 focus:border-accent shadow-sm w-full sm:w-56">
        </div>
        <p class="text-xs text-slate-400">
          <span v-if="changedCount > 0" class="text-amber-600 font-bold">{{ changedCount }} item berubah</span>
          <span v-else class="text-slate-400">Belum ada perubahan</span>
        </p>
      </div>

      <LoadingState v-if="isLoading" label="Memuat data stok..." />

      <template v-else>
        <div class="bg-white rounded-2xl shadow overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="bg-slate-50 text-[10px] text-slate-400 uppercase font-bold tracking-widest">
                <tr>
                  <th class="px-4 py-3 text-left">Nama Barang</th>
                  <th class="px-4 py-3 text-left">Kategori</th>
                  <th class="px-4 py-3 text-left">Lokasi</th>
                  <th class="px-4 py-3 text-center">Stok Sistem</th>
                  <th class="px-4 py-3 text-center">Stok Fisik</th>
                  <th class="px-4 py-3 text-center">Selisih</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-50">
                <tr v-for="item in filteredItems" :key="item.id"
                  :class="item.qty_physical !== item.qty_system ? 'bg-amber-50/40' : 'hover:bg-slate-50/30'"
                  class="transition-colors">
                  <td class="px-4 py-3 font-medium text-slate-800 text-sm">{{ item.name }}</td>
                  <td class="px-4 py-3">
                    <span class="bg-slate-100 text-slate-600 text-[10px] font-bold px-2 py-0.5 rounded-lg uppercase">{{ item.category }}</span>
                  </td>
                  <td class="px-4 py-3 text-xs text-slate-400">{{ item.location ?? '-' }}</td>
                  <td class="px-4 py-3 text-center font-bold text-slate-600">{{ item.qty_system }} <span class="text-[10px] text-slate-400 font-normal">{{ item.unit }}</span></td>
                  <td class="px-4 py-3 text-center">
                    <input
                      v-model.number="item.qty_physical"
                      type="number"
                      min="0"
                      class="w-20 text-center border rounded-lg px-2 py-1.5 text-sm font-bold outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all"
                      :class="item.qty_physical !== item.qty_system ? 'border-amber-400 bg-amber-50 text-amber-800' : 'border-slate-200'"
                    >
                  </td>
                  <td class="px-4 py-3 text-center">
                    <span v-if="item.qty_physical - item.qty_system === 0" class="text-slate-300 text-xs font-bold">—</span>
                    <span v-else
                      :class="item.qty_physical - item.qty_system > 0 ? 'text-emerald-600 bg-emerald-50' : 'text-red-600 bg-red-50'"
                      class="text-xs font-bold px-2 py-0.5 rounded-lg">
                      {{ item.qty_physical - item.qty_system > 0 ? '+' : '' }}{{ item.qty_physical - item.qty_system }}
                    </span>
                  </td>
                </tr>
                <tr v-if="!filteredItems.length">
                  <td colspan="6" class="px-4 py-12 text-center text-slate-400 text-sm">Tidak ada barang ditemukan.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Notes + Submit -->
        <div class="bg-white rounded-2xl shadow p-5 flex flex-col sm:flex-row gap-4 items-start sm:items-end">
          <div class="flex-1">
            <label class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-1.5 block">Catatan (opsional)</label>
            <input v-model="notes" type="text" placeholder="Contoh: Kalibrasi mingguan 14 Juni 2026"
              class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
          </div>
          <button
            @click="applyCalibration"
            :disabled="isSubmitting"
            class="shrink-0 bg-primary text-white text-xs font-bold px-6 py-3 rounded-xl hover:bg-primary/90 transition-all disabled:opacity-50 disabled:cursor-not-allowed shadow-lg shadow-primary/20">
            {{ isSubmitting ? 'Menerapkan...' : 'Terapkan Kalibrasi' }}
          </button>
        </div>
      </template>
    </div>

    <!-- History Tab -->
    <div v-if="tab === 'history'" class="space-y-4">
      <div v-if="!history.length" class="bg-white rounded-2xl shadow px-4 py-12 text-center text-slate-400 text-sm">
        Belum ada riwayat kalibrasi.
      </div>
      <div v-else class="bg-white rounded-2xl shadow overflow-hidden">
        <table class="w-full text-sm">
          <thead class="bg-slate-50 text-[10px] text-slate-400 uppercase font-bold tracking-widest">
            <tr>
              <th class="px-4 py-3 text-left">Tanggal</th>
              <th class="px-4 py-3 text-left">Dilakukan oleh</th>
              <th class="px-4 py-3 text-center">Total Item</th>
              <th class="px-4 py-3 text-center">Disesuaikan</th>
              <th class="px-4 py-3 text-left">Catatan</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50">
            <tr v-for="rec in history" :key="rec.id" class="hover:bg-slate-50/30 transition-colors">
              <td class="px-4 py-3 font-bold text-slate-800">{{ fmtDate(rec.calibrated_at) }}</td>
              <td class="px-4 py-3 text-slate-600">{{ rec.calibrated_by }}</td>
              <td class="px-4 py-3 text-center text-slate-500">{{ rec.total_items }}</td>
              <td class="px-4 py-3 text-center">
                <span :class="rec.total_adjusted > 0 ? 'bg-amber-100 text-amber-700' : 'bg-green-100 text-green-700'"
                  class="text-xs font-bold px-2.5 py-1 rounded-full">
                  {{ rec.total_adjusted }} item
                </span>
              </td>
              <td class="px-4 py-3 text-xs text-slate-400">{{ rec.notes ?? '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Toast -->
    <Transition name="toast">
      <div v-if="toast.show"
        class="fixed bottom-6 left-1/2 -translate-x-1/2 z-[60] px-5 py-3 rounded-xl shadow-lg text-sm font-medium text-white"
        :class="toast.type === 'error' ? 'bg-red-500' : 'bg-green-500'">
        {{ toast.message }}
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.toast-enter-active, .toast-leave-active { transition: all 0.3s ease; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translate(-50%, 1rem); }
</style>
