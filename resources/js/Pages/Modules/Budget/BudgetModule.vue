<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">RAB Keuangan Ruko</h1>
        <p class="text-sm text-gray-500 mt-0.5">Rencana Anggaran Biaya — tracking realisasi pengeluaran</p>
      </div>
    </div>

    <!-- Sub-tabs -->
    <div class="border-b border-gray-200">
      <nav class="flex gap-6">
        <button v-for="t in tabs" :key="t.id" @click="activeTab = t.id"
          :class="['pb-3 text-sm font-medium border-b-2 transition-colors', activeTab === t.id
            ? 'border-amber-500 text-amber-600'
            : 'border-transparent text-gray-500 hover:text-gray-700']">
          {{ t.label }}
        </button>
      </nav>
    </div>

    <!-- Tab: Dashboard -->
    <div v-if="activeTab === 'dashboard'" class="space-y-6">
      <!-- Period Setting -->
      <div class="bg-amber-50 border border-amber-100 rounded-2xl px-5 py-4 flex flex-wrap items-center justify-between gap-3">
        <div>
          <p class="text-[10px] font-bold text-amber-600 uppercase tracking-widest mb-0.5">Periode Aktif</p>
          <p class="text-sm font-semibold text-gray-800">
            {{ period.start_date ? fmtDate(period.start_date) : '—' }}
            &nbsp;–&nbsp;
            {{ period.end_date ? fmtDate(period.end_date) : '—' }}
          </p>
        </div>
        <button @click="periodEdit = !periodEdit"
          class="text-xs font-bold px-3 py-1.5 rounded-lg border border-amber-300 text-amber-700 hover:bg-amber-100 transition-colors">
          {{ periodEdit ? 'Batal' : 'Ubah Periode' }}
        </button>
      </div>
      <div v-if="periodEdit" class="flex flex-wrap gap-3 items-end bg-white border border-amber-100 rounded-2xl px-5 py-4">
        <div>
          <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest block mb-1">Mulai</label>
          <input type="date" v-model="periodForm.start_date"
            class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-300" />
        </div>
        <div>
          <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest block mb-1">Sampai</label>
          <input type="date" v-model="periodForm.end_date"
            class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-300" />
        </div>
        <button @click="savePeriodSetting"
          class="bg-amber-500 hover:bg-amber-600 text-white text-sm font-bold px-4 py-2 rounded-xl transition-colors">
          Simpan
        </button>
      </div>

      <!-- Summary Cards -->
      <div v-if="summary" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="premium-card p-5 rounded-2xl shadow">
          <p class="text-xs text-amber-600 font-semibold uppercase tracking-wide">Total Direncanakan</p>
          <p class="text-2xl font-bold text-gray-900 mt-1">{{ fmt(summary.total_planned) }}</p>
        </div>
        <div class="premium-card p-5 rounded-2xl shadow">
          <p class="text-xs font-semibold uppercase tracking-wide"
            :class="summary.total_actual > summary.total_planned ? 'text-red-500' : 'text-green-600'">
            Total Realisasi
          </p>
          <p class="text-2xl font-bold text-gray-900 mt-1">{{ fmt(summary.total_actual) }}</p>
          <p class="text-xs text-gray-500 mt-0.5">{{ summary.total_pct }}% dari rencana</p>
        </div>
        <div class="premium-card p-5 rounded-2xl shadow">
          <p class="text-xs text-blue-600 font-semibold uppercase tracking-wide">Sisa Anggaran</p>
          <p class="text-2xl font-bold mt-1"
            :class="summary.total_sisa < 0 ? 'text-red-600' : 'text-blue-700'">
            {{ fmt(summary.total_sisa) }}
          </p>
        </div>
      </div>

      <!-- Per-category -->
      <div v-if="summary" class="space-y-4">
        <div v-for="cat in summary.categories" :key="cat.category_id"
          class="bg-white rounded-2xl shadow p-5 space-y-3">
          <div class="flex items-center justify-between">
            <h3 class="font-semibold text-gray-800">{{ cat.category }}</h3>
            <span :class="statusBadge(cat.status)" class="text-xs px-2 py-0.5 rounded-full font-medium">
              {{ statusLabel(cat.status) }}
            </span>
          </div>
          <div class="flex justify-between text-sm text-gray-600">
            <span>Rencana: <strong>{{ fmt(cat.planned) }}</strong></span>
            <span>Realisasi: <strong>{{ fmt(cat.actual) }}</strong></span>
            <span>{{ cat.pct }}%</span>
          </div>
          <!-- Progress bar -->
          <div class="w-full bg-gray-100 rounded-full h-2">
            <div class="h-2 rounded-full transition-all"
              :style="{ width: Math.min(cat.pct, 100) + '%' }"
              :class="cat.status === 'over_budget' ? 'bg-red-500' : cat.status === 'warning' ? 'bg-amber-400' : 'bg-green-500'" />
          </div>
          <!-- Unpaid items -->
          <div v-if="cat.unpaid.length" class="mt-2">
            <p class="text-xs text-gray-500 font-medium mb-1">Belum/Kurang Dibayar:</p>
            <div class="space-y-1">
              <div v-for="u in cat.unpaid" :key="u.id"
                class="flex justify-between text-xs bg-amber-50 border border-amber-100 rounded-lg px-3 py-1.5">
                <span class="text-gray-700">{{ u.name }}</span>
                <span class="text-amber-700 font-semibold">Kurang {{ fmt(u.remaining) }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Trend chart (simple text table) -->
      <div class="bg-white rounded-2xl shadow p-5">
        <h3 class="font-semibold text-gray-800 mb-3">Tren 6 Bulan Terakhir</h3>
        <div class="space-y-2">
          <div v-for="t in trend" :key="t.month" class="flex items-center gap-3">
            <span class="text-sm text-gray-500 w-20">{{ t.month }}</span>
            <div class="flex-1 bg-gray-100 rounded-full h-3">
              <div class="h-3 rounded-full bg-amber-400 transition-all"
                :style="{ width: trendPct(t.actual) + '%' }" />
            </div>
            <span class="text-sm font-medium text-gray-700 w-32 text-right">{{ fmt(t.actual) }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Tab: Master RAB -->
    <div v-if="activeTab === 'master'" class="space-y-6">
      <!-- Toolbar -->
      <div class="flex items-center justify-between gap-3 flex-wrap">
        <div class="flex gap-2">
          <button @click="downloadTemplate"
            class="text-sm border border-gray-300 hover:border-amber-400 text-gray-600 hover:text-amber-600 px-4 py-2 rounded-lg transition-colors flex items-center gap-2">
            <i class="pi pi-download text-xs"></i> Template
          </button>
          <label class="cursor-pointer text-sm border border-gray-300 hover:border-amber-400 text-gray-600 hover:text-amber-600 px-4 py-2 rounded-lg transition-colors flex items-center gap-2">
            <i class="pi pi-upload text-xs"></i> Import Excel
            <input type="file" accept=".xlsx,.xls,.csv" class="hidden" @change="onImportFile" />
          </label>
        </div>
        <button @click="openAddCategory"
          class="bg-amber-500 hover:bg-amber-600 text-white text-sm px-4 py-2 rounded-lg transition-colors">
          + Kategori
        </button>
      </div>

      <!-- Empty state -->
      <div v-if="!categories.length" class="bg-white rounded-2xl shadow p-12 text-center text-gray-400">
        <i class="pi pi-folder-open text-4xl mb-3 block"></i>
        <p class="text-sm">Belum ada kategori. Klik <strong>+ Kategori</strong> untuk mulai.</p>
      </div>

      <div v-for="cat in categories" :key="cat.id" class="bg-white rounded-2xl shadow overflow-hidden">
        <!-- Category header -->
        <div class="flex items-center justify-between px-5 py-4 bg-amber-50 border-b border-amber-100">
          <div>
            <h3 class="font-semibold text-amber-800">{{ cat.name }}</h3>
            <p class="text-xs text-amber-600 mt-0.5">
              Total rencana: {{ fmt(cat.items.reduce((s: number, i: any) => s + i.total_monthly_budget, 0)) }}/bulan
            </p>
          </div>
          <div class="flex gap-2">
            <button @click="openEditCategory(cat)"
              class="text-xs text-amber-600 hover:text-amber-800 px-2 py-1.5 transition-colors">Edit</button>
            <button @click="confirmDeleteCategory(cat.id)"
              class="text-xs text-red-400 hover:text-red-600 px-2 py-1.5 transition-colors">Hapus</button>
            <button @click="openAddItem(cat.id)"
              class="text-xs bg-amber-500 hover:bg-amber-600 text-white px-3 py-1.5 rounded-lg transition-colors">
              + Item
            </button>
          </div>
        </div>

        <!-- Desktop table -->
        <div class="hidden sm:block overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
              <tr>
                <th class="px-4 py-2 text-center">Nama</th>
                <th class="px-4 py-2 text-center">Satuan</th>
                <th class="px-4 py-2 text-center">Frekuensi</th>
                <th class="px-4 py-2 text-center">Budget/Bulan</th>
                <th class="px-4 py-2 text-center">Status</th>
                <th class="px-4 py-2 text-center">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="item in cat.items" :key="item.id" class="hover:bg-gray-50">
                <td class="px-4 py-3 text-center font-medium text-gray-800">{{ item.name }}</td>
                <td class="px-4 py-3 text-center text-gray-600">{{ fmt(item.unit_cost) }}</td>
                <td class="px-4 py-3 text-center text-gray-500">{{ rateLabel(item.rate) }} ×{{ item.multiplier }}</td>
                <td class="px-4 py-3 text-center font-semibold text-gray-800">{{ fmt(item.total_monthly_budget) }}</td>
                <td class="px-4 py-3 text-center">
                  <span :class="item.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                    class="text-xs px-2 py-0.5 rounded-full">
                    {{ item.is_active ? 'Aktif' : 'Non-aktif' }}
                  </span>
                </td>
                <td class="px-4 py-3 text-center">
                  <div class="flex justify-center gap-2">
                    <button @click="openEditItem(item)" class="text-xs text-blue-600 hover:text-blue-800 transition-colors">Edit</button>
                    <button @click="confirmDeleteItem(item.id)" class="text-xs text-red-500 hover:text-red-700 transition-colors">Hapus</button>
                  </div>
                </td>
              </tr>
              <tr v-if="!cat.items.length">
                <td colspan="6" class="px-4 py-6 text-center text-gray-400 text-sm">Belum ada item</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile cards -->
        <div class="sm:hidden divide-y divide-gray-100">
          <div v-if="!cat.items.length" class="px-4 py-6 text-center text-gray-400 text-sm">Belum ada item</div>
          <div v-for="item in cat.items" :key="item.id" class="px-4 py-3 flex items-center justify-between gap-3">
            <div class="flex-1 min-w-0">
              <p class="font-semibold text-gray-800 text-sm truncate">{{ item.name }}</p>
              <p class="text-xs text-gray-500 mt-0.5">{{ fmt(item.unit_cost) }} · {{ rateLabel(item.rate) }} ×{{ item.multiplier }}</p>
              <div class="flex items-center gap-2 mt-1">
                <span class="text-xs font-bold text-amber-700">{{ fmt(item.total_monthly_budget) }}/bln</span>
                <span :class="item.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                  class="text-[10px] px-1.5 py-0.5 rounded-full font-medium">
                  {{ item.is_active ? 'Aktif' : 'Non-aktif' }}
                </span>
              </div>
            </div>
            <div class="flex gap-2 shrink-0">
              <button @click="openEditItem(item)"
                class="w-8 h-8 flex items-center justify-center rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 transition-colors">
                <i class="pi pi-pencil text-xs"></i>
              </button>
              <button @click="confirmDeleteItem(item.id)"
                class="w-8 h-8 flex items-center justify-center rounded-lg bg-red-50 text-red-500 hover:bg-red-100 transition-colors">
                <i class="pi pi-trash text-xs"></i>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Tab: Realisasi -->
    <div v-if="activeTab === 'realisasi'" class="space-y-4">
      <!-- Filters -->
      <div class="flex flex-wrap gap-3 items-end">
        <div>
          <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest block mb-1.5">Tanggal</label>
          <input type="date" v-model="txDate" @change="loadTransactions"
            class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/40 bg-white" />
        </div>
        <div>
          <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest block mb-1.5">Bulan</label>
          <input type="month" v-model="txMonth" @change="txDate = ''; loadTransactions()"
            :disabled="!!txDate"
            class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/40 bg-white disabled:opacity-40" />
        </div>
        <div>
          <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest block mb-1.5">Kategori</label>
          <div class="relative">
            <select v-model="txCatId" @change="txItemId = null; loadTransactions()"
              class="appearance-none border border-slate-200 rounded-xl px-3 py-2 pr-8 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/40 bg-white cursor-pointer">
              <option :value="null">Semua Kategori</option>
              <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
            </select>
            <i class="pi pi-chevron-down absolute right-2.5 top-1/2 -translate-y-1/2 text-[10px] text-slate-400 pointer-events-none"></i>
          </div>
        </div>
        <div>
          <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest block mb-1.5">Item</label>
          <div class="relative">
            <select v-model="txItemId" @change="loadTransactions"
              class="appearance-none border border-slate-200 rounded-xl px-3 py-2 pr-8 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/40 bg-white cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
              :disabled="!txCatId">
              <option :value="null">Semua Item</option>
              <option v-for="item in filteredTxItems" :key="item.id" :value="item.id">{{ item.name }}</option>
            </select>
            <i class="pi pi-chevron-down absolute right-2.5 top-1/2 -translate-y-1/2 text-[10px] text-slate-400 pointer-events-none"></i>
          </div>
        </div>
        <button @click="openAddTransaction"
          class="flex items-center gap-2 bg-primary hover:bg-primary-light text-white text-sm font-bold px-4 py-2 rounded-xl transition-colors">
          <i class="pi pi-plus text-[10px]"></i>
          Transaksi
        </button>
      </div>

      <!-- Transactions table -->
      <div class="bg-white rounded-2xl shadow overflow-hidden">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
            <tr>
              <th class="px-4 py-2 text-left">Tanggal</th>
              <th class="px-4 py-2 text-left">Item</th>
              <th class="px-4 py-2 text-left">Kategori</th>
              <th class="px-4 py-2 text-right">Jumlah</th>
              <th class="px-4 py-2 text-left">Keterangan</th>
              <th class="px-4 py-2 text-center">Bukti</th>
              <th class="px-4 py-2"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="tx in transactions" :key="tx.id" class="hover:bg-gray-50">
              <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ tx.transaction_date?.slice(0, 10).split('-').reverse().join('/') }}</td>
              <td class="px-4 py-3 font-medium text-gray-800">{{ tx.budget_item?.name }}</td>
              <td class="px-4 py-3 text-gray-500 text-xs">{{ tx.budget_item?.category?.name }}</td>
              <td class="px-4 py-3 text-right font-semibold text-gray-800">{{ fmt(tx.amount) }}</td>
              <td class="px-4 py-3 text-gray-500 text-xs max-w-[180px] truncate">{{ tx.note }}</td>
              <td class="px-4 py-3 text-center">
                <label v-if="!tx.receipt_path" :for="'receipt-' + tx.id"
                  class="cursor-pointer text-xs text-amber-600 hover:text-amber-800">Upload</label>
                <a v-else :href="'/storage/' + tx.receipt_path" target="_blank"
                  class="text-xs text-blue-600 hover:underline">Lihat</a>
                <input :id="'receipt-' + tx.id" type="file" class="hidden" accept="image/*,application/pdf"
                  @change="(e) => uploadReceipt(tx.id, e)" />
              </td>
              <td class="px-4 py-3 text-right">
                <div class="flex justify-end gap-2">
                  <button @click="openEditTransaction(tx)"
                    class="text-xs text-blue-600 hover:text-blue-800">Edit</button>
                  <button @click="confirmDeleteTransaction(tx.id)"
                    class="text-xs text-red-500 hover:text-red-700">Hapus</button>
                </div>
              </td>
            </tr>
            <tr v-if="!transactions.length">
              <td colspan="7" class="px-4 py-10 text-center text-gray-400">Belum ada transaksi</td>
            </tr>
          </tbody>
        </table>
        <div v-if="transactions.length" class="px-4 py-3 bg-gray-50 border-t border-gray-100 flex justify-end">
          <span class="text-sm font-semibold text-gray-700">
            Total: {{ fmt(transactions.reduce((s: number, t: any) => s + Number(t.amount), 0)) }}
          </span>
        </div>
      </div>
    </div>

    <!-- ── Modals ─────────────────────────────────────────────── -->

    <!-- Import Preview Modal -->
    <Transition name="modal">
      <div v-if="importModal.open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" @click="importModal.open = false" />
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-2xl p-6 space-y-4 max-h-[90vh] flex flex-col">
          <h2 class="text-lg font-bold text-gray-800">Preview Import — {{ importRows.length }} item</h2>

          <!-- Pilih kategori -->
          <div>
            <label class="text-xs text-gray-500 mb-1 block">Import ke Kategori</label>
            <select v-model.number="importCatId"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400 focus:border-amber-400">
              <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
            </select>
          </div>

          <!-- Tabel preview -->
          <div class="overflow-y-auto flex-1 border border-gray-100 rounded-xl">
            <table class="w-full text-sm">
              <thead class="bg-gray-50 text-xs text-gray-500 uppercase sticky top-0">
                <tr>
                  <th class="px-3 py-2 text-left">Nama Item</th>
                  <th class="px-3 py-2 text-right">Biaya Satuan</th>
                  <th class="px-3 py-2 text-center">Frekuensi</th>
                  <th class="px-3 py-2 text-right">Budget/Bulan</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-50">
                <tr v-for="(row, i) in importRows" :key="i" class="hover:bg-amber-50">
                  <td class="px-3 py-2 font-medium text-gray-800">{{ row.name }}</td>
                  <td class="px-3 py-2 text-right text-gray-600">{{ fmt(row.unit_cost) }}</td>
                  <td class="px-3 py-2 text-center text-gray-500 text-xs">{{ row._rateLabel }}</td>
                  <td class="px-3 py-2 text-right font-semibold text-amber-700">{{ fmt(row.unit_cost * row.multiplier) }}</td>
                </tr>
              </tbody>
              <tfoot class="bg-amber-50 border-t border-amber-100">
                <tr>
                  <td colspan="3" class="px-3 py-2 text-sm font-semibold text-gray-700">Total Budget/Bulan</td>
                  <td class="px-3 py-2 text-right font-bold text-amber-700">
                    {{ fmt(importRows.reduce((s, r) => s + r.unit_cost * r.multiplier, 0)) }}
                  </td>
                </tr>
              </tfoot>
            </table>
          </div>

          <div class="flex justify-end gap-3 pt-2">
            <button @click="importModal.open = false"
              class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition-colors">Batal</button>
            <button @click="doImport" :disabled="importLoading || !importCatId"
              class="px-5 py-2 text-sm bg-amber-500 hover:bg-amber-600 text-white rounded-lg transition-colors disabled:opacity-50">
              {{ importLoading ? 'Mengimport...' : `Import ${importRows.length} Item` }}
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Add/Edit Category Modal -->
    <Transition name="modal">
      <div v-if="catModal.open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" @click="catModal.open = false" />
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-sm p-6 space-y-4">
          <h2 class="text-lg font-bold text-gray-800">{{ catModal.id ? 'Edit Kategori' : 'Tambah Kategori' }}</h2>
          <div>
            <label class="text-xs text-gray-500 mb-1 block">Nama Kategori</label>
            <input v-model="catForm.name" type="text" placeholder="e.g. Fixed Cost"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400 focus:border-amber-400" />
          </div>
          <div class="flex justify-end gap-3 pt-2">
            <button @click="catModal.open = false"
              class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition-colors">Batal</button>
            <button @click="saveCategory" :disabled="saving"
              class="px-5 py-2 text-sm bg-amber-500 hover:bg-amber-600 text-white rounded-lg transition-colors disabled:opacity-50">
              {{ saving ? 'Menyimpan...' : 'Simpan' }}
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Add/Edit Item Modal -->
    <Transition name="modal">
      <div v-if="itemModal.open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="itemModal.open = false" />
        <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-md overflow-hidden">
          <!-- Header -->
          <div class="px-6 py-5 bg-gradient-to-r from-amber-50 to-amber-100 border-b border-amber-100 flex items-center justify-between">
            <div>
              <h2 class="text-base font-bold text-amber-900">{{ itemModal.id ? 'Edit Item RAB' : 'Tambah Item RAB' }}</h2>
              <p class="text-xs text-amber-600 mt-0.5">{{ itemModal.id ? 'Perbarui data item anggaran' : 'Tambah item ke anggaran bulanan' }}</p>
            </div>
            <button @click="itemModal.open = false" class="w-8 h-8 flex items-center justify-center rounded-full bg-amber-200/50 hover:bg-amber-200 text-amber-700 transition-colors">
              <i class="pi pi-times text-xs"></i>
            </button>
          </div>

          <!-- Body -->
          <div class="px-6 py-5 space-y-4">
            <div>
              <label class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1.5 block">Nama Item</label>
              <input v-model="itemForm.name" type="text" placeholder="e.g. Listrik, Server Database"
                class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm bg-slate-50 focus:bg-white focus:ring-2 focus:ring-amber-400 focus:border-amber-400 transition-all outline-none" />
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1.5 block">Biaya Satuan</label>
                <div class="relative">
                  <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400 font-medium pointer-events-none">Rp</span>
                  <input v-model="itemForm.unit_cost" type="text" inputmode="numeric" @input="onCostInput" placeholder="0"
                    class="w-full border border-slate-200 rounded-xl pl-8 pr-3 py-2.5 text-sm bg-slate-50 focus:bg-white focus:ring-2 focus:ring-amber-400 focus:border-amber-400 transition-all outline-none" />
                </div>
              </div>
              <div>
                <label class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1.5 block">Frekuensi</label>
                <select v-model="itemForm.rate"
                  class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm bg-slate-50 focus:bg-white focus:ring-2 focus:ring-amber-400 focus:border-amber-400 transition-all outline-none">
                  <option value="harian">Harian</option>
                  <option value="mingguan">Mingguan</option>
                  <option value="dua_mingguan">2 Mingguan</option>
                  <option value="bulanan">Bulanan</option>
                  <option value="custom">Custom</option>
                </select>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1.5 block">Multiplier</label>
                <input v-model.number="itemForm.multiplier" type="number" min="1"
                  class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm bg-slate-50 focus:bg-white focus:ring-2 focus:ring-amber-400 focus:border-amber-400 transition-all outline-none" />
              </div>
              <div class="flex items-end pb-1">
                <label class="flex items-center gap-2.5 cursor-pointer select-none px-3 py-2.5 rounded-xl border border-slate-200 bg-slate-50 w-full hover:bg-amber-50 hover:border-amber-200 transition-colors">
                  <input type="checkbox" v-model="itemForm.is_active" class="w-4 h-4 rounded accent-amber-500" />
                  <span class="text-sm font-medium text-slate-700">Aktif</span>
                </label>
              </div>
            </div>

            <!-- Budget preview -->
            <div class="flex items-center justify-between bg-gradient-to-r from-amber-50 to-amber-100 border border-amber-200 rounded-xl px-4 py-3">
              <span class="text-xs font-semibold text-amber-700 uppercase tracking-wide">Budget / Bulan</span>
              <span class="text-base font-bold text-amber-800">{{ fmt(computedBudget) }}</span>
            </div>
          </div>

          <!-- Footer -->
          <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex justify-end gap-3">
            <button @click="itemModal.open = false"
              class="px-5 py-2.5 text-sm font-semibold text-slate-500 hover:text-slate-700 hover:bg-slate-100 rounded-xl transition-colors">
              Batal
            </button>
            <button @click="saveItem" :disabled="saving"
              class="px-6 py-2.5 text-sm font-bold bg-amber-500 hover:bg-amber-600 text-white rounded-xl shadow-lg shadow-amber-200 transition-all disabled:opacity-50 disabled:cursor-not-allowed">
              {{ saving ? 'Menyimpan...' : (itemModal.id ? 'Simpan Perubahan' : 'Tambah Item') }}
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Add/Edit Transaction Modal -->
    <Transition name="modal">
      <div v-if="txModal.open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="txModal.open = false" />
        <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-md overflow-hidden">
          <!-- Header -->
          <div class="px-6 py-5 bg-gradient-to-r from-amber-50 to-amber-100 border-b border-amber-100 flex items-center justify-between">
            <div>
              <h2 class="text-base font-bold text-amber-900">{{ txModal.id ? 'Edit Transaksi' : 'Catat Pengeluaran' }}</h2>
              <p class="text-xs text-amber-600 mt-0.5">{{ txModal.id ? 'Perbarui data transaksi' : 'Input realisasi pengeluaran' }}</p>
            </div>
            <button @click="txModal.open = false" class="w-8 h-8 flex items-center justify-center rounded-full bg-amber-200/50 hover:bg-amber-200 text-amber-700 transition-colors">
              <i class="pi pi-times text-xs"></i>
            </button>
          </div>

          <!-- Body -->
          <div class="px-6 py-5 space-y-4">
            <div>
              <label class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1.5 block">Kategori</label>
              <div class="relative">
                <select v-model.number="txModalCatId" @change="txForm.budget_item_id = 0"
                  class="appearance-none w-full border border-slate-200 rounded-xl px-4 py-2.5 pr-9 text-sm bg-slate-50 focus:bg-white focus:ring-2 focus:ring-amber-400 focus:border-amber-400 transition-all outline-none cursor-pointer">
                  <option :value="null" disabled>Pilih kategori...</option>
                  <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                </select>
                <i class="pi pi-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-[10px] text-slate-400 pointer-events-none"></i>
              </div>
            </div>
            <div>
              <label class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1.5 block">Item Anggaran</label>
              <div class="relative">
                <select v-model.number="txForm.budget_item_id"
                  :disabled="!txModalCatId"
                  class="appearance-none w-full border border-slate-200 rounded-xl px-4 py-2.5 pr-9 text-sm bg-slate-50 focus:bg-white focus:ring-2 focus:ring-amber-400 focus:border-amber-400 transition-all outline-none cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed">
                  <option :value="0" disabled>{{ txModalCatId ? 'Pilih item...' : 'Pilih kategori dulu' }}</option>
                  <option v-for="item in txModalItems" :key="item.id" :value="item.id">{{ item.name }}</option>
                </select>
                <i class="pi pi-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-[10px] text-slate-400 pointer-events-none"></i>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1.5 block">Jumlah</label>
                <div class="relative">
                  <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400 font-medium pointer-events-none">Rp</span>
                  <input v-model="txForm.amount" type="text" inputmode="numeric" @input="onAmountInput" placeholder="0"
                    class="w-full border border-slate-200 rounded-xl pl-8 pr-3 py-2.5 text-sm bg-slate-50 focus:bg-white focus:ring-2 focus:ring-amber-400 focus:border-amber-400 transition-all outline-none" />
                </div>
              </div>
              <div>
                <label class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1.5 block">Tanggal</label>
                <input v-model="txForm.transaction_date" type="date"
                  class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm bg-slate-50 focus:bg-white focus:ring-2 focus:ring-amber-400 focus:border-amber-400 transition-all outline-none" />
              </div>
            </div>

            <div>
              <label class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1.5 block">Keterangan <span class="normal-case font-normal text-slate-400">(opsional)</span></label>
              <textarea v-model="txForm.note" rows="2" placeholder="Catatan tambahan..."
                class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm bg-slate-50 focus:bg-white focus:ring-2 focus:ring-amber-400 focus:border-amber-400 transition-all outline-none resize-none" />
            </div>

            <div>
              <label class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1.5 block">Bukti <span class="normal-case font-normal text-slate-400">(opsional)</span></label>
              <label class="flex items-center gap-3 border border-dashed border-slate-200 rounded-xl px-4 py-3 cursor-pointer hover:border-amber-300 hover:bg-amber-50/50 transition-colors group">
                <i class="pi pi-upload text-slate-300 group-hover:text-amber-400 transition-colors"></i>
                <span class="text-sm text-slate-400 group-hover:text-amber-500 transition-colors truncate">
                  {{ txReceiptFile ? txReceiptFile.name : 'Upload foto / PDF struk...' }}
                </span>
                <input type="file" accept="image/*,application/pdf" class="hidden"
                  @change="e => txReceiptFile = (e.target as HTMLInputElement).files?.[0] ?? null" />
              </label>
              <button v-if="txReceiptFile" @click="txReceiptFile = null"
                class="mt-1.5 text-[10px] text-slate-400 hover:text-red-500 transition-colors">
                Hapus file
              </button>
            </div>
          </div>

          <!-- Footer -->
          <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex justify-end gap-3">
            <button @click="txModal.open = false"
              class="px-5 py-2.5 text-sm font-semibold text-slate-500 hover:text-slate-700 hover:bg-slate-100 rounded-xl transition-colors">
              Batal
            </button>
            <button @click="saveTransaction" :disabled="saving"
              class="px-6 py-2.5 text-sm font-bold bg-amber-500 hover:bg-amber-600 text-white rounded-xl shadow-lg shadow-amber-200 transition-all disabled:opacity-50 disabled:cursor-not-allowed">
              {{ saving ? 'Menyimpan...' : (txModal.id ? 'Simpan Perubahan' : 'Catat Pengeluaran') }}
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Confirm Modal -->
    <Transition name="modal">
      <div v-if="confirmModal.open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" @click="confirmModal.open = false" />
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-sm p-6 space-y-4">
          <h2 class="text-lg font-bold text-gray-800">Konfirmasi Hapus</h2>
          <p class="text-sm text-gray-600">{{ confirmModal.message }}</p>
          <div class="flex justify-end gap-3 pt-2">
            <button @click="confirmModal.open = false"
              class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Batal</button>
            <button @click="confirmModal.action(); confirmModal.open = false"
              class="px-5 py-2 text-sm bg-red-500 hover:bg-red-600 text-white rounded-lg transition-colors">
              Hapus
            </button>
          </div>
        </div>
      </div>
    </Transition>

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

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { budgetApi } from '../../../api/budget.api'

const tabs = [
  { id: 'dashboard', label: 'Dashboard' },
  { id: 'master',    label: 'Master RAB' },
  { id: 'realisasi', label: 'Realisasi' },
]
const activeTab = ref('dashboard')

// ── State ──────────────────────────────────────────────────────

const categories   = ref<any[]>([])
const summary      = ref<any>(null)
const trend        = ref<any[]>([])
const transactions = ref<any[]>([])
const saving       = ref(false)

const period        = ref({ start_date: '', end_date: '' })
const periodForm    = ref({ start_date: '', end_date: '' })
const periodEdit    = ref(false)
const txMonth       = ref('')
const txDate        = ref('')
const txCatId       = ref<number | null>(null)
const txItemId      = ref<number | null>(null)

const filteredTxItems = computed(() =>
  txCatId.value
    ? (categories.value.find((c: any) => c.id === txCatId.value)?.items ?? [])
    : []
)

const catModal = ref({ open: false, id: null as number | null })
const catForm  = ref({ name: '' })

const itemModal = ref({ open: false, id: null as number | null, categoryId: 0 })
const itemForm  = ref({ name: '', unit_cost: '0', rate: 'bulanan', multiplier: 1, is_active: true })

const txModal      = ref({ open: false, id: null as number | null })
const txForm       = ref({ budget_item_id: 0, amount: '0', transaction_date: today(), note: '' })
const txModalCatId = ref<number | null>(null)
const txReceiptFile = ref<File | null>(null)
const txModalItems = computed(() => {
  if (!txModalCatId.value) return []
  return categories.value.find(c => c.id === txModalCatId.value)?.items ?? []
})

const confirmModal = ref({ open: false, message: '', action: () => {} })
const toast        = ref({ show: false, message: '', type: 'success' })

// ── Helpers ────────────────────────────────────────────────────

function today() {
  return new Date().toISOString().slice(0, 10)
}

function fmt(n: number | string) {
  return 'Rp ' + Number(n).toLocaleString('id-ID')
}

function fmtDate(d: string) {
  return new Date(d + 'T00:00:00').toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}

function rateLabel(r: string) {
  return { harian: 'Harian', mingguan: 'Mingguan', dua_mingguan: '2 Mingguan', bulanan: 'Bulanan', custom: 'Custom' }[r] ?? r
}

function statusBadge(s: string) {
  return { over_budget: 'bg-red-100 text-red-700', warning: 'bg-amber-100 text-amber-700', on_track: 'bg-green-100 text-green-700' }[s]
}

function statusLabel(s: string) {
  return { over_budget: 'Over Budget', warning: 'Peringatan', on_track: 'On Track' }[s] ?? s
}

function showToast(message: string, type = 'success') {
  toast.value = { show: true, message, type }
  setTimeout(() => (toast.value.show = false), 3500)
}

const computedBudget = computed(() => {
  const cost = Number(String(itemForm.value.unit_cost).replace(/\./g, '').replace(/,/g, ''))
  return isNaN(cost) ? 0 : cost * itemForm.value.multiplier
})

const trendMax = computed(() => Math.max(...trend.value.map(t => t.actual), 1))
function trendPct(v: number) {
  return Math.round((v / trendMax.value) * 100)
}

function onCostInput(e: Event) {
  const raw = (e.target as HTMLInputElement).value.replace(/\D/g, '')
  itemForm.value.unit_cost = raw ? Number(raw).toLocaleString('id-ID') : '0'
}

function onAmountInput(e: Event) {
  const raw = (e.target as HTMLInputElement).value.replace(/\D/g, '')
  txForm.value.amount = raw ? Number(raw).toLocaleString('id-ID') : '0'
}

// ── Load ───────────────────────────────────────────────────────

async function loadAll() {
  await loadPeriodSetting()
  await loadCategories()
  await loadSummary()
  await loadTrend()
  await loadTransactions()
}

async function loadCategories() {
  const res = await budgetApi.getCategories()
  categories.value = res.data
}

async function loadPeriodSetting() {
  const res = await budgetApi.getPeriodSetting()
  period.value     = res.data
  periodForm.value = { ...res.data }
}

async function savePeriodSetting() {
  await budgetApi.setPeriodSetting(periodForm.value)
  period.value  = { ...periodForm.value }
  periodEdit.value = false
  await Promise.all([loadSummary(), loadTransactions()])
  showToast('Periode berhasil diubah')
}

async function loadSummary() {
  const res = await budgetApi.getSummary()
  summary.value = res.data
}

async function loadTrend() {
  const res = await budgetApi.getTrend(6)
  trend.value = res.data
}

async function loadTransactions() {
  const res = await budgetApi.getTransactions({
    date:           txDate.value || undefined,
    month:          txDate.value ? undefined : (txMonth.value || undefined),
    budget_item_id: txItemId.value ?? undefined,
  })
  transactions.value = res.data
}

// ── Category CRUD ─────────────────────────────────────────

function openAddCategory() {
  catModal.value = { open: true, id: null }
  catForm.value  = { name: '' }
}

function openEditCategory(cat: any) {
  catModal.value = { open: true, id: cat.id }
  catForm.value  = { name: cat.name }
}

async function saveCategory() {
  if (!catForm.value.name.trim()) return
  saving.value = true
  try {
    if (catModal.value.id) {
      await budgetApi.updateCategory(catModal.value.id, { name: catForm.value.name })
    } else {
      await budgetApi.createCategory({ name: catForm.value.name })
    }
    catModal.value.open = false
    await loadCategories()
    showToast('Kategori berhasil disimpan')
  } catch {
    showToast('Gagal menyimpan kategori', 'error')
  } finally {
    saving.value = false
  }
}

function confirmDeleteCategory(id: number) {
  confirmModal.value = {
    open: true,
    message: 'Hapus kategori ini? Semua item di dalamnya juga akan terhapus.',
    action: () => deleteCategory(id),
  }
}

async function deleteCategory(id: number) {
  try {
    await budgetApi.deleteCategory(id)
    await loadCategories()
    showToast('Kategori dihapus')
  } catch {
    showToast('Gagal menghapus kategori', 'error')
  }
}

// ── Item CRUD ──────────────────────────────────────────────────

function openAddItem(categoryId: number) {
  itemModal.value = { open: true, id: null, categoryId }
  itemForm.value  = { name: '', unit_cost: '0', rate: 'bulanan', multiplier: 1, is_active: true }
}

function openEditItem(item: any) {
  itemModal.value = { open: true, id: item.id, categoryId: item.category_id }
  itemForm.value  = {
    name: item.name,
    unit_cost: Number(item.unit_cost).toLocaleString('id-ID'),
    rate: item.rate,
    multiplier: item.multiplier,
    is_active: item.is_active,
  }
}

async function saveItem() {
  saving.value = true
  try {
    const rawCost = Number(String(itemForm.value.unit_cost).replace(/\./g, '').replace(/,/g, ''))
    const payload = {
      category_id: itemModal.value.categoryId,
      name: itemForm.value.name,
      unit_cost: rawCost,
      rate: itemForm.value.rate,
      multiplier: itemForm.value.multiplier,
      is_active: itemForm.value.is_active,
    }
    if (itemModal.value.id) {
      await budgetApi.updateItem(itemModal.value.id, payload)
    } else {
      await budgetApi.createItem(payload)
    }
    itemModal.value.open = false
    await loadCategories()
    showToast('Item berhasil disimpan')
  } catch (e: any) {
    showToast(e?.response?.data?.message ?? 'Gagal menyimpan item', 'error')
  } finally {
    saving.value = false
  }
}

function confirmDeleteItem(id: number) {
  confirmModal.value = {
    open: true,
    message: 'Hapus item ini? Data transaksi terkait tidak akan terhapus.',
    action: () => deleteItem(id),
  }
}

async function deleteItem(id: number) {
  try {
    await budgetApi.deleteItem(id)
    await loadCategories()
    showToast('Item dihapus')
  } catch {
    showToast('Gagal menghapus item', 'error')
  }
}

// ── Transaction CRUD ───────────────────────────────────────────

function openAddTransaction() {
  txModal.value      = { open: true, id: null }
  txForm.value       = { budget_item_id: 0, amount: '0', transaction_date: today(), note: '' }
  txModalCatId.value = null
  txReceiptFile.value = null
}

function openEditTransaction(tx: any) {
  txModal.value = { open: true, id: tx.id }
  txForm.value  = {
    budget_item_id: tx.budget_item_id,
    amount: Number(tx.amount).toLocaleString('id-ID'),
    transaction_date: tx.transaction_date?.slice(0, 10) ?? today(),
    note: tx.note ?? '',
  }
  txModalCatId.value = categories.value.find(c =>
    c.items?.some((i: any) => i.id === tx.budget_item_id)
  )?.id ?? null
}

async function saveTransaction() {
  saving.value = true
  try {
    const rawAmount = Number(String(txForm.value.amount).replace(/\./g, '').replace(/,/g, ''))
    const payload = {
      budget_item_id: txForm.value.budget_item_id,
      amount: rawAmount,
      transaction_date: txForm.value.transaction_date,
      note: txForm.value.note || null,
    }
    let savedId: number
    if (txModal.value.id) {
      await budgetApi.updateTransaction(txModal.value.id, payload)
      savedId = txModal.value.id
    } else {
      const res = await budgetApi.createTransaction(payload)
      savedId = res.data?.id
    }
    if (txReceiptFile.value && savedId) {
      try {
        await budgetApi.uploadReceipt(savedId, txReceiptFile.value)
      } catch {
        showToast('Transaksi disimpan, tapi bukti gagal diunggah.', 'error')
        txModal.value.open = false
        await Promise.all([loadTransactions(), loadSummary()])
        return
      }
    }
    txModal.value.open = false
    await Promise.all([loadTransactions(), loadSummary()])
    showToast('Transaksi berhasil disimpan')
  } catch (e: any) {
    showToast(e?.response?.data?.message ?? 'Gagal menyimpan transaksi', 'error')
  } finally {
    saving.value = false
  }
}

function confirmDeleteTransaction(id: number) {
  confirmModal.value = {
    open: true,
    message: 'Hapus transaksi ini?',
    action: () => deleteTransaction(id),
  }
}

async function deleteTransaction(id: number) {
  try {
    await budgetApi.deleteTransaction(id)
    await Promise.all([loadTransactions(), loadSummary()])
    showToast('Transaksi dihapus')
  } catch {
    showToast('Gagal menghapus transaksi', 'error')
  }
}

async function uploadReceipt(id: number, e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  try {
    const res = await budgetApi.uploadReceipt(id, file)
    const idx = transactions.value.findIndex(t => t.id === id)
    if (idx !== -1) transactions.value[idx] = res.data
    showToast('Bukti berhasil diupload')
  } catch {
    showToast('Gagal upload bukti', 'error')
  }
}

// ── Import ─────────────────────────────────────────────────

const importModal   = ref({ open: false })
const importRows    = ref<any[]>([])
const importCatId   = ref(0)
const importLoading = ref(false)

const RATE_MAP: Record<string, { rate: string; multiplier: number }> = {
  harian:          { rate: 'harian',       multiplier: 30 },
  daily:           { rate: 'harian',       multiplier: 30 },
  mingguan:        { rate: 'mingguan',     multiplier: 4  },
  weekly:          { rate: 'mingguan',     multiplier: 4  },
  'dua mingguan':  { rate: 'dua_mingguan', multiplier: 2  },
  bulanan:         { rate: 'bulanan',      multiplier: 1  },
  monthly:         { rate: 'bulanan',      multiplier: 1  },
  custom:          { rate: 'custom',       multiplier: 1  },
}

function parseRate(raw: string): { rate: string; multiplier: number } {
  const s = String(raw ?? '').toLowerCase().trim()
  // "ada 3 bulanan" / "3x bulanan" / "3 bulanan"
  const m = s.match(/(\d+)\s*(?:x|kali)?\s*(harian|mingguan|dua[\s_]mingguan|bulanan|custom)?/) ||
            s.match(/(harian|mingguan|dua[\s_]mingguan|bulanan)\s*[x×]\s*(\d+)/)
  if (m) {
    const num  = parseInt(m[1] || m[2] || '')
    const word = (m[3] || m[1] || '').replace(/[\s_]/g, ' ').toLowerCase()
    if (!isNaN(num) && num > 1) {
      const base = RATE_MAP[word] ?? { rate: 'custom', multiplier: 1 }
      return { rate: base.rate === 'bulanan' ? 'custom' : base.rate, multiplier: num }
    }
  }
  return RATE_MAP[s] ?? { rate: 'bulanan', multiplier: 1 }
}

function parseCurrency(raw: any): number {
  if (typeof raw === 'number') return Math.round(raw)
  return parseInt(String(raw ?? '0').replace(/[^\d]/g, '')) || 0
}

async function onImportFile(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  ;(e.target as HTMLInputElement).value = ''

  const { read, utils } = await import('xlsx')
  const buf  = await file.arrayBuffer()
  const wb   = read(buf)
  const ws   = wb.Sheets[wb.SheetNames[0]]
  const rows = utils.sheet_to_json(ws, { header: 1 }) as any[][]

  // Skip header jika baris pertama bukan angka
  const firstVal = String(rows[0]?.[1] ?? '').replace(/[^\d]/g, '')
  const dataRows = (!firstVal || isNaN(Number(firstVal))) ? rows.slice(1) : rows

  importRows.value = dataRows
    .filter(r => r[0])
    .map(r => {
      const parsed = parseRate(String(r[2] ?? 'bulanan'))
      const mult   = r[3] ? parseInt(r[3]) || parsed.multiplier : parsed.multiplier
      return {
        name:       String(r[0]).trim(),
        unit_cost:  parseCurrency(r[1]),
        rate:       parsed.rate,
        multiplier: mult,
        _rateLabel: rateLabel(parsed.rate) + (mult > 1 ? ` ×${mult}` : ''),
      }
    })

  importCatId.value = categories.value[0]?.id ?? 0
  importModal.value.open = true
}

async function doImport() {
  if (!importCatId.value || !importRows.value.length) return
  importLoading.value = true
  try {
    await budgetApi.bulkStoreItems({
      category_id: importCatId.value,
      items: importRows.value.map(r => ({
        name: r.name, unit_cost: r.unit_cost, rate: r.rate, multiplier: r.multiplier,
      })),
    })
    importModal.value.open = false
    await loadCategories()
    showToast(`${importRows.value.length} item berhasil diimport`)
  } catch (e: any) {
    showToast(e?.response?.data?.message ?? 'Gagal import', 'error')
  } finally {
    importLoading.value = false
  }
}

function downloadTemplate() {
  const rows = [
    ['Nama Item', 'Biaya Satuan', 'Frekuensi', 'Multiplier'],
    ['Server Database', 580000, 'bulanan', 1],
    ['Afuk', 20000, 'harian', 30],
    ['Kartu Parkir', 150000, 'custom', 3],
    ['Beras 10 kg', 76000, 'dua_mingguan', 2],
    ['Air Galon', 5000, 'custom', 24],
  ]
  import('xlsx').then(({ utils, writeFile }) => {
    const ws = utils.aoa_to_sheet(rows)
    ws['!cols'] = [{ wch: 25 }, { wch: 15 }, { wch: 15 }, { wch: 12 }]
    const wb = utils.book_new()
    utils.book_append_sheet(wb, ws, 'Template RAB')
    writeFile(wb, 'template_rab.xlsx')
  })
}

// ── Init ───────────────────────────────────────────────────────

onMounted(loadAll)
</script>

<style scoped>
.premium-card {
  background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
  border: 1px solid #fde68a;
}

.modal-enter-active, .modal-leave-active { transition: opacity 0.2s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }

.toast-enter-active, .toast-leave-active { transition: all 0.3s ease; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translate(-50%, 1rem); }
</style>
