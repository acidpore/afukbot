<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-start justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Invoice Eksternal</h1>
        <p class="text-sm text-gray-500 mt-0.5">Invoice multi-perusahaan untuk pembelian dari luar (audit). PPN 11%.</p>
      </div>
      <button @click="companyMgr.open = true"
        class="shrink-0 text-sm border border-gray-300 hover:border-primary text-gray-600 hover:text-primary px-3 py-2 rounded-xl transition-colors flex items-center gap-1.5">
        <i class="pi pi-building text-xs"></i> <span class="hidden sm:inline">Kelola</span> Perusahaan
      </button>
    </div>

    <!-- Toolbar -->
    <div class="flex flex-col sm:flex-row sm:items-end gap-3">
      <div class="grid grid-cols-2 sm:flex gap-3 flex-1">
        <div>
          <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest block mb-1">Perusahaan</label>
          <select v-model="filterCompany" @change="loadInvoices"
            class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm bg-white">
            <option :value="null">Semua</option>
            <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </div>
        <div>
          <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest block mb-1">Status</label>
          <select v-model="filterStatus" @change="loadInvoices"
            class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm bg-white">
            <option :value="null">Semua</option>
            <option value="draft">Draft</option>
            <option value="sent">Terkirim</option>
            <option value="paid">Lunas</option>
            <option value="overdue">Jatuh Tempo</option>
          </select>
        </div>
      </div>
      <button @click="openCreate"
        class="w-full sm:w-auto shrink-0 bg-primary hover:bg-primary-light text-white text-sm font-bold px-4 py-2.5 rounded-xl transition-colors flex items-center justify-center gap-1.5">
        <i class="pi pi-plus text-[10px]"></i> Invoice
      </button>
    </div>

    <!-- List -->
    <div v-if="!invoices.length" class="bg-white rounded-2xl shadow p-12 text-center text-gray-400">
      <i class="pi pi-file text-4xl mb-3 block"></i>
      <p class="text-sm">Belum ada invoice.</p>
    </div>

    <div v-else class="bg-white rounded-2xl shadow overflow-hidden">
      <!-- Desktop -->
      <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
            <tr>
              <th class="px-4 py-2 text-left">Nomor</th>
              <th class="px-4 py-2 text-left">Perusahaan</th>
              <th class="px-4 py-2 text-left">Kepada</th>
              <th class="px-4 py-2 text-left">Tanggal</th>
              <th class="px-4 py-2 text-right">Total</th>
              <th class="px-4 py-2 text-center">Status</th>
              <th class="px-4 py-2"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="inv in invoices" :key="inv.id" class="hover:bg-gray-50">
              <td class="px-4 py-3 font-medium text-gray-800 whitespace-nowrap">{{ inv.invoice_number }}</td>
              <td class="px-4 py-3 text-gray-600">{{ inv.company?.name }}</td>
              <td class="px-4 py-3 text-gray-600">{{ inv.customer?.name ?? '-' }}</td>
              <td class="px-4 py-3 text-gray-500 whitespace-nowrap">{{ fmtDate(inv.issue_date) }}</td>
              <td class="px-4 py-3 text-right font-semibold text-gray-800">{{ fmt(inv.total) }}</td>
              <td class="px-4 py-3 text-center"><span :class="statusBadge(inv.status)" class="text-[10px] font-bold px-2 py-0.5 rounded-full uppercase">{{ statusLabel(inv.status) }}</span></td>
              <td class="px-4 py-3 text-right whitespace-nowrap">
                <div class="flex justify-end gap-1.5">
                  <a :href="api.pdfUrl(inv.id)" target="_blank" title="Download PDF" class="w-8 h-8 inline-flex items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 hover:bg-emerald-100"><i class="pi pi-file-pdf text-xs"></i></a>
                  <button @click="openPreview(inv.id)" title="Preview" class="w-8 h-8 inline-flex items-center justify-center rounded-lg bg-amber-50 text-amber-600 hover:bg-amber-100"><i class="pi pi-eye text-xs"></i></button>
                  <button @click="openEdit(inv.id)" title="Edit" class="w-8 h-8 inline-flex items-center justify-center rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100"><i class="pi pi-pencil text-xs"></i></button>
                  <button @click="confirmDelete(inv.id)" title="Hapus" class="w-8 h-8 inline-flex items-center justify-center rounded-lg bg-red-50 text-red-500 hover:bg-red-100"><i class="pi pi-trash text-xs"></i></button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <!-- Mobile -->
      <div class="md:hidden divide-y divide-gray-100">
        <div v-for="inv in invoices" :key="inv.id" class="p-4">
          <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
              <p class="font-semibold text-gray-800 text-sm">{{ inv.invoice_number }}</p>
              <p class="text-xs text-gray-500">{{ inv.company?.name }} → {{ inv.customer?.name ?? '-' }}</p>
              <p class="text-xs text-gray-400 mt-0.5">{{ fmtDate(inv.issue_date) }}</p>
            </div>
            <div class="text-right shrink-0">
              <p class="font-bold text-gray-800">{{ fmt(inv.total) }}</p>
              <span :class="statusBadge(inv.status)" class="text-[10px] font-bold px-2 py-0.5 rounded-full uppercase">{{ statusLabel(inv.status) }}</span>
            </div>
          </div>
          <div class="flex gap-2 mt-3">
            <a :href="api.pdfUrl(inv.id)" target="_blank" title="PDF" class="w-9 h-9 inline-flex items-center justify-center rounded-lg bg-emerald-50 text-emerald-600"><i class="pi pi-file-pdf text-sm"></i></a>
            <button @click="openPreview(inv.id)" title="Preview" class="w-9 h-9 inline-flex items-center justify-center rounded-lg bg-amber-50 text-amber-600"><i class="pi pi-eye text-sm"></i></button>
            <button @click="openEdit(inv.id)" title="Edit" class="w-9 h-9 inline-flex items-center justify-center rounded-lg bg-blue-50 text-blue-600"><i class="pi pi-pencil text-sm"></i></button>
            <button @click="confirmDelete(inv.id)" title="Hapus" class="w-9 h-9 inline-flex items-center justify-center rounded-lg bg-red-50 text-red-500"><i class="pi pi-trash text-sm"></i></button>
          </div>
        </div>
      </div>
    </div>

    <!-- Form Modal -->
    <Transition name="modal">
      <div v-if="modal.open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="modal.open = false" />
        <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-2xl overflow-hidden max-h-[92vh] flex flex-col">
          <div class="px-6 py-4 bg-gradient-to-r from-slate-50 to-slate-100 border-b flex items-center justify-between">
            <h2 class="text-base font-bold text-gray-800">{{ modal.id ? 'Edit Invoice' : 'Invoice Baru' }}</h2>
            <button @click="modal.open = false" class="w-8 h-8 flex items-center justify-center rounded-full bg-slate-200/60 hover:bg-slate-200 text-slate-600">
              <i class="pi pi-times text-xs"></i>
            </button>
          </div>

          <div class="px-6 py-5 space-y-4 overflow-y-auto">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label class="lbl">Perusahaan (penerbit)</label>
                <select v-model.number="form.company_id" class="inp">
                  <option :value="0" disabled>Pilih perusahaan...</option>
                  <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
              </div>
              <div>
                <label class="lbl">Status</label>
                <select v-model="form.status" class="inp">
                  <option value="draft">Draft</option>
                  <option value="sent">Terkirim</option>
                  <option value="paid">Lunas</option>
                  <option value="overdue">Jatuh Tempo</option>
                </select>
              </div>
            </div>

            <div v-if="modal.id">
              <label class="lbl">Nomor Invoice</label>
              <input v-model="form.invoice_number" class="inp font-mono" placeholder="INV/.../2026/001" />
            </div>

            <!-- Customer inline -->
            <div class="border border-slate-200 rounded-xl p-3 space-y-2 bg-slate-50/50">
              <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Ditagihkan Kepada</p>
              <input v-model="form.customer.name" class="inp" placeholder="Nama / perusahaan customer" />
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                <input v-model="form.customer.company_address" class="inp" placeholder="Alamat" />
                <input v-model="form.customer.npwp" class="inp" placeholder="NPWP (opsional)" />
              </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="lbl">Tanggal Invoice</label>
                <input type="date" v-model="form.issue_date" class="inp" />
              </div>
              <div>
                <label class="lbl">Jatuh Tempo</label>
                <input type="date" v-model="form.due_date" class="inp" />
              </div>
            </div>

            <!-- Items -->
            <div>
              <div class="flex items-center justify-between mb-1.5">
                <label class="lbl mb-0">Item</label>
                <button @click="addItem" class="text-xs font-bold text-primary hover:underline"><i class="pi pi-plus text-[9px]"></i> Tambah</button>
              </div>
              <div v-for="(it, i) in form.items" :key="i" class="grid grid-cols-12 gap-2 mb-2 items-center">
                <input v-model="it.description" class="inp col-span-12 sm:col-span-6" placeholder="Deskripsi" />
                <input v-model.number="it.qty" type="number" min="0" step="any" class="inp col-span-3 sm:col-span-2" placeholder="Qty" />
                <input v-model.number="it.unit_price" type="number" min="0" class="inp col-span-7 sm:col-span-3" placeholder="Harga" />
                <button @click="removeItem(i)" class="col-span-2 sm:col-span-1 w-8 h-8 flex items-center justify-center rounded-lg bg-red-50 text-red-500 hover:bg-red-100 justify-self-end">
                  <i class="pi pi-times text-[10px]"></i>
                </button>
              </div>
            </div>

            <!-- Tax controls -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
              <div>
                <label class="lbl">Mode Harga</label>
                <select v-model="form.price_mode" class="inp">
                  <option value="exclusive">PPN ditambahkan</option>
                  <option value="inclusive">Sudah termasuk PPN</option>
                </select>
              </div>
              <div>
                <label class="lbl">PPN (%)</label>
                <input v-model.number="form.tax_percent" type="number" min="0" max="100" step="any" class="inp" />
              </div>
              <div>
                <label class="lbl">Diskon (Rp)</label>
                <input v-model.number="form.discount" type="number" min="0" class="inp" />
              </div>
            </div>
            <p v-if="form.price_mode === 'inclusive'" class="text-[11px] text-amber-600 -mt-1">
              Mode ini: total = jumlah item. DPP &amp; PPN dihitung mundur. Mau total pas 350jt? Isi 1 item harga 350.000.000.
            </p>

            <textarea v-model="form.notes" rows="2" class="inp resize-none" placeholder="Catatan (opsional)" />

            <!-- Live totals -->
            <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-sm space-y-1">
              <div class="flex justify-between text-gray-600"><span>DPP</span><span>{{ fmt(totals.subtotal) }}</span></div>
              <div v-if="form.discount > 0" class="flex justify-between text-gray-600"><span>Diskon</span><span>-{{ fmt(form.discount) }}</span></div>
              <div class="flex justify-between text-gray-600"><span>PPN {{ form.tax_percent }}%</span><span>{{ fmt(totals.tax_amount) }}</span></div>
              <div class="flex justify-between font-bold text-gray-900 text-base border-t border-slate-200 pt-1.5 mt-1"><span>TOTAL</span><span>{{ fmt(totals.total) }}</span></div>
            </div>
          </div>

          <div class="px-6 py-4 bg-slate-50 border-t flex justify-between gap-3">
            <button @click="previewDraft" class="px-4 py-2.5 text-sm font-bold text-amber-600 hover:bg-amber-50 rounded-xl flex items-center gap-1.5">
              <i class="pi pi-eye text-xs"></i> Preview
            </button>
            <div class="flex gap-3">
              <button @click="modal.open = false" class="px-5 py-2.5 text-sm font-semibold text-slate-500 hover:bg-slate-100 rounded-xl">Batal</button>
              <button @click="save" :disabled="saving || !form.company_id || !form.items.length"
                class="px-6 py-2.5 text-sm font-bold bg-primary hover:bg-primary-light text-white rounded-xl disabled:opacity-50">
                {{ saving ? 'Menyimpan...' : 'Simpan' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Preview Modal -->
    <Transition name="modal">
      <div v-if="preview.open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/60" @click="preview.open = false" />
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-3xl h-[90vh] flex flex-col overflow-hidden">
          <div class="px-5 py-3 border-b flex items-center justify-between">
            <h2 class="text-sm font-bold text-gray-800">Preview Invoice <span v-if="!preview.id" class="text-amber-600">(draft)</span></h2>
            <div class="flex gap-3 items-center">
              <a v-if="preview.id" :href="api.pdfUrl(preview.id)" target="_blank" class="text-xs font-bold text-emerald-600">Download PDF</a>
              <button @click="preview.open = false" class="w-7 h-7 flex items-center justify-center rounded-full bg-slate-100 hover:bg-slate-200"><i class="pi pi-times text-xs"></i></button>
            </div>
          </div>
          <iframe v-if="preview.id" :src="api.previewUrl(preview.id)" class="flex-1 w-full" />
          <iframe v-else :srcdoc="preview.html" class="flex-1 w-full" />
        </div>
      </div>
    </Transition>

    <!-- Company Manager -->
    <Transition name="modal">
      <div v-if="companyMgr.open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="companyMgr.open = false" />
        <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-xl overflow-hidden max-h-[90vh] flex flex-col">
          <div class="px-6 py-4 border-b flex items-center justify-between">
            <h2 class="text-base font-bold text-gray-800">Perusahaan Penerbit</h2>
            <div class="flex gap-2 items-center">
              <button @click="openCompanyCreate" class="text-xs font-bold bg-primary text-white px-3 py-1.5 rounded-lg">+ Tambah</button>
              <button @click="companyMgr.open = false" class="w-8 h-8 flex items-center justify-center rounded-full bg-slate-100 hover:bg-slate-200"><i class="pi pi-times text-xs"></i></button>
            </div>
          </div>
          <div class="p-4 overflow-y-auto divide-y divide-gray-100">
            <div v-for="c in companies" :key="c.id" class="py-3 flex items-center gap-3">
              <img v-if="c.logo_url" :src="c.logo_url" class="w-10 h-10 object-contain rounded" />
              <span v-else class="w-10 h-10 rounded flex items-center justify-center text-white text-xs font-bold" :style="{ background: c.brand_primary }">{{ c.name.slice(0,2).toUpperCase() }}</span>
              <div class="min-w-0 flex-1">
                <p class="font-semibold text-gray-800 text-sm truncate">{{ c.name }}</p>
                <p class="text-xs text-gray-400">{{ c.invoice_prefix }} &middot; {{ c.template_variant }}</p>
              </div>
              <button @click="openCompanyEdit(c)" title="Edit" class="w-8 h-8 inline-flex items-center justify-center rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 shrink-0"><i class="pi pi-pencil text-xs"></i></button>
              <button @click="confirmDeleteCompany(c.id)" title="Hapus" class="w-8 h-8 inline-flex items-center justify-center rounded-lg bg-red-50 text-red-500 hover:bg-red-100 shrink-0"><i class="pi pi-trash text-xs"></i></button>
            </div>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Company Form -->
    <Transition name="modal">
      <div v-if="companyModal.open" class="fixed inset-0 z-[55] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="companyModal.open = false" />
        <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-lg overflow-hidden max-h-[92vh] flex flex-col">
          <div class="px-6 py-4 border-b flex items-center justify-between">
            <h2 class="text-base font-bold text-gray-800">{{ companyModal.id ? 'Edit' : 'Tambah' }} Perusahaan</h2>
            <button @click="companyModal.open = false" class="w-8 h-8 flex items-center justify-center rounded-full bg-slate-100 hover:bg-slate-200"><i class="pi pi-times text-xs"></i></button>
          </div>
          <div class="px-6 py-5 space-y-3 overflow-y-auto">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div><label class="lbl">Nama</label><input v-model="companyForm.name" class="inp" /></div>
              <div><label class="lbl">Nama Legal (PT)</label><input v-model="companyForm.legal_name" class="inp" /></div>
              <div><label class="lbl">NPWP</label><input v-model="companyForm.npwp" class="inp" /></div>
              <div><label class="lbl">Prefix Nomor</label><input v-model="companyForm.invoice_prefix" class="inp font-mono" placeholder="INV/ABC" /></div>
            </div>
            <div><label class="lbl">Alamat</label><input v-model="companyForm.address" class="inp" /></div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div><label class="lbl">Telepon</label><input v-model="companyForm.phone" class="inp" /></div>
              <div><label class="lbl">Email</label><input v-model="companyForm.email" class="inp" /></div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
              <div><label class="lbl">Bank</label><input v-model="companyForm.bank_name" class="inp" /></div>
              <div><label class="lbl">No. Rekening</label><input v-model="companyForm.bank_account" class="inp" /></div>
              <div><label class="lbl">a.n.</label><input v-model="companyForm.bank_holder" class="inp" /></div>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 items-end">
              <div><label class="lbl">Warna 1</label><input type="color" v-model="companyForm.brand_primary" class="w-full h-10 rounded-lg border border-slate-200" /></div>
              <div><label class="lbl">Warna 2</label><input type="color" v-model="companyForm.brand_secondary" class="w-full h-10 rounded-lg border border-slate-200" /></div>
              <div class="col-span-2"><label class="lbl">Layout</label>
                <select v-model="companyForm.template_variant" class="inp">
                  <option value="modern">Modern</option><option value="classic">Classic</option>
                  <option value="minimal">Minimal</option><option value="bold">Bold</option>
                </select>
              </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div><label class="lbl">Logo</label><input type="file" accept="image/*" class="inp py-1.5" @change="e => logoFile = (e.target as HTMLInputElement).files?.[0] ?? null" /></div>
              <div><label class="lbl">Tanda Tangan</label><input type="file" accept="image/*" class="inp py-1.5" @change="e => signFile = (e.target as HTMLInputElement).files?.[0] ?? null" /></div>
            </div>
          </div>
          <div class="px-6 py-4 bg-slate-50 border-t flex justify-end gap-3">
            <button @click="companyModal.open = false" class="px-5 py-2.5 text-sm font-semibold text-slate-500 hover:bg-slate-100 rounded-xl">Batal</button>
            <button @click="saveCompany" :disabled="companySaving || !companyForm.name.trim() || !companyForm.invoice_prefix.trim()"
              class="px-6 py-2.5 text-sm font-bold bg-primary hover:bg-primary-light text-white rounded-xl disabled:opacity-50">
              {{ companySaving ? 'Menyimpan...' : 'Simpan' }}
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Confirm -->
    <Transition name="modal">
      <div v-if="confirm.open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" @click="confirm.open = false" />
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-sm p-6 space-y-4">
          <h2 class="text-lg font-bold text-gray-800">{{ confirm.title }}</h2>
          <p class="text-sm text-gray-600">{{ confirm.message }}</p>
          <div class="flex justify-end gap-3">
            <button @click="confirm.open = false" class="px-4 py-2 text-sm text-gray-600">Batal</button>
            <button @click="confirm.action(); confirm.open = false" class="px-5 py-2 text-sm bg-red-500 hover:bg-red-600 text-white rounded-lg">Hapus</button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Toast -->
    <Transition name="toast">
      <div v-if="toast.show" class="fixed bottom-6 left-1/2 -translate-x-1/2 z-[60] px-5 py-3 rounded-xl shadow-lg text-sm font-medium text-white"
        :class="toast.type === 'error' ? 'bg-red-500' : 'bg-green-500'">{{ toast.message }}</div>
    </Transition>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { invoicingApi as api } from '../../../api/invoicing.api'

const companies = ref<any[]>([])
const invoices  = ref<any[]>([])
const filterCompany = ref<number | null>(null)
const filterStatus  = ref<string | null>(null)
const saving = ref(false)

const blankForm = () => ({
  company_id: 0,
  invoice_number: '',
  status: 'draft',
  customer: { name: '', company_address: '', npwp: '' },
  issue_date: new Date().toISOString().slice(0, 10),
  due_date: '',
  price_mode: 'exclusive',
  tax_percent: 11,
  discount: 0,
  notes: '',
  items: [{ description: '', qty: 1, unit_price: 0 }] as any[],
})

const modal   = reactive({ open: false, id: null as number | null })
const form    = ref(blankForm())
const preview = reactive({ open: false, id: 0, html: '' })
const confirm = reactive({ open: false, title: 'Hapus', message: '', action: () => {} })
const toast   = reactive({ show: false, message: '', type: 'success' })

// Company manager
const companyMgr    = reactive({ open: false })
const companyModal  = reactive({ open: false, id: null as number | null })
const blankCompany  = () => ({
  name: '', legal_name: '', npwp: '', address: '', phone: '', email: '',
  bank_name: '', bank_account: '', bank_holder: '',
  brand_primary: '#0f172a', brand_secondary: '#f59e0b',
  template_variant: 'modern', invoice_prefix: 'INV',
})
const companyForm   = ref(blankCompany())
const companySaving = ref(false)
const logoFile      = ref<File | null>(null)
const signFile      = ref<File | null>(null)

function fmt(n: number | string) { return 'Rp ' + Number(n || 0).toLocaleString('id-ID') }
function fmtDate(d: string) { return d ? new Date(d + 'T00:00:00').toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) : '-' }
function statusBadge(s: string) {
  return { draft: 'bg-gray-100 text-gray-600', sent: 'bg-blue-100 text-blue-700', paid: 'bg-green-100 text-green-700', overdue: 'bg-red-100 text-red-700' }[s] ?? 'bg-gray-100'
}
function statusLabel(s: string) {
  return { draft: 'Draft', sent: 'Terkirim', paid: 'Lunas', overdue: 'Jatuh Tempo' }[s] ?? s
}
function showToast(message: string, type = 'success') {
  toast.show = true; toast.message = message; toast.type = type
  setTimeout(() => (toast.show = false), 3000)
}

// Mirror perhitungan backend untuk live preview
const totals = computed(() => {
  const sumLines = form.value.items.reduce((s, it) => s + Math.round((Number(it.qty) || 0) * (Number(it.unit_price) || 0)), 0)
  const rate = (Number(form.value.tax_percent) || 0) / 100
  const discount = Number(form.value.discount) || 0
  if (form.value.price_mode === 'inclusive') {
    const total = Math.max(0, sumLines - discount)
    const subtotal = Math.round(total / (1 + rate))
    return { subtotal, tax_amount: total - subtotal, total }
  }
  const subtotal = sumLines
  const after = Math.max(0, subtotal - discount)
  const tax_amount = Math.round(after * rate)
  return { subtotal, tax_amount, total: after + tax_amount }
})

function addItem() { form.value.items.push({ description: '', qty: 1, unit_price: 0 }) }
function removeItem(i: number) { form.value.items.splice(i, 1) }

async function loadCompanies() { companies.value = (await api.getCompanies()).data }
async function loadInvoices() {
  invoices.value = (await api.getInvoices({
    company_id: filterCompany.value ?? undefined,
    status: filterStatus.value ?? undefined,
  })).data
}

function openCreate() { modal.id = null; form.value = blankForm(); modal.open = true }

async function openEdit(id: number) {
  const inv = (await api.getInvoice(id)).data
  modal.id = id
  form.value = {
    company_id: inv.company_id,
    invoice_number: inv.invoice_number,
    status: inv.status,
    customer: { name: inv.customer?.name ?? '', company_address: inv.customer?.company_address ?? '', npwp: inv.customer?.npwp ?? '' },
    issue_date: inv.issue_date?.slice(0, 10) ?? '',
    due_date: inv.due_date?.slice(0, 10) ?? '',
    price_mode: inv.price_mode,
    tax_percent: Number(inv.tax_percent),
    discount: Number(inv.discount),
    notes: inv.notes ?? '',
    items: inv.items.map((it: any) => ({ description: it.description, qty: Number(it.qty), unit_price: Number(it.unit_price) })),
  }
  modal.open = true
}

function buildPayload(): any {
  const payload: any = {
    company_id: form.value.company_id,
    status: form.value.status,
    issue_date: form.value.issue_date,
    due_date: form.value.due_date || null,
    price_mode: form.value.price_mode,
    tax_percent: form.value.tax_percent,
    discount: Math.round(Number(form.value.discount) || 0),
    notes: form.value.notes || null,
    items: form.value.items
      .filter(it => it.description.trim())
      .map(it => ({ description: it.description, qty: Number(it.qty) || 1, unit_price: Math.round(Number(it.unit_price) || 0) })),
  }
  if (form.value.customer.name.trim()) payload.customer = form.value.customer
  if (modal.id && form.value.invoice_number.trim()) payload.invoice_number = form.value.invoice_number.trim()
  return payload
}

async function save() {
  saving.value = true
  try {
    const payload = buildPayload()
    let id: number
    if (modal.id) { await api.updateInvoice(modal.id, payload); id = modal.id }
    else { id = (await api.createInvoice(payload)).data.id }

    modal.open = false
    await loadInvoices()
    showToast('Invoice disimpan')
    openPreview(id)
  } catch (e: any) {
    showToast(e?.response?.data?.message ?? 'Gagal menyimpan invoice', 'error')
  } finally {
    saving.value = false
  }
}

async function previewDraft() {
  if (!form.value.company_id || !form.value.items.some(it => it.description.trim())) {
    showToast('Pilih perusahaan & isi minimal 1 item', 'error'); return
  }
  try {
    const html = (await api.previewDraft(buildPayload())).data
    preview.id = 0; preview.html = html; preview.open = true
  } catch (e: any) {
    showToast(e?.response?.data?.message ?? 'Gagal membuat preview', 'error')
  }
}

function openPreview(id: number) { preview.id = id; preview.html = ''; preview.open = true }

function confirmDelete(id: number) {
  confirm.title = 'Hapus Invoice'; confirm.message = 'Yakin hapus invoice ini?'
  confirm.action = () => doDelete(id); confirm.open = true
}
async function doDelete(id: number) {
  try { await api.deleteInvoice(id); await loadInvoices(); showToast('Invoice dihapus') }
  catch { showToast('Gagal menghapus', 'error') }
}

// ── Company manager ────────────────────────────────────────────

function openCompanyCreate() {
  companyModal.id = null; companyForm.value = blankCompany()
  logoFile.value = null; signFile.value = null; companyModal.open = true
}

function openCompanyEdit(c: any) {
  companyModal.id = c.id
  companyForm.value = {
    name: c.name, legal_name: c.legal_name ?? '', npwp: c.npwp ?? '', address: c.address ?? '',
    phone: c.phone ?? '', email: c.email ?? '', bank_name: c.bank_name ?? '',
    bank_account: c.bank_account ?? '', bank_holder: c.bank_holder ?? '',
    brand_primary: c.brand_primary ?? '#0f172a', brand_secondary: c.brand_secondary ?? '#f59e0b',
    template_variant: c.template_variant ?? 'modern', invoice_prefix: c.invoice_prefix ?? 'INV',
  }
  logoFile.value = null; signFile.value = null; companyModal.open = true
}

async function saveCompany() {
  companySaving.value = true
  try {
    const fd = new FormData()
    Object.entries(companyForm.value).forEach(([k, v]) => { if (v !== '' && v != null) fd.append(k, String(v)) })
    if (logoFile.value) fd.append('logo', logoFile.value)
    if (signFile.value) fd.append('signature', signFile.value)
    if (companyModal.id) { fd.append('_method', 'PUT'); await api.updateCompany(companyModal.id, fd) }
    else await api.createCompany(fd)
    companyModal.open = false
    await loadCompanies()
    showToast('Perusahaan disimpan')
  } catch (e: any) {
    showToast(e?.response?.data?.message ?? 'Gagal menyimpan perusahaan', 'error')
  } finally {
    companySaving.value = false
  }
}

function confirmDeleteCompany(id: number) {
  confirm.title = 'Hapus Perusahaan'
  confirm.message = 'Hapus perusahaan ini? Semua invoice perusahaan ini juga ikut terhapus.'
  confirm.action = () => doDeleteCompany(id); confirm.open = true
}
async function doDeleteCompany(id: number) {
  try {
    await api.deleteCompany(id)
    if (filterCompany.value === id) filterCompany.value = null
    await Promise.all([loadCompanies(), loadInvoices()])
    showToast('Perusahaan dihapus')
  } catch (e: any) {
    showToast(e?.response?.data?.message ?? 'Gagal menghapus perusahaan', 'error')
  }
}

onMounted(async () => { await loadCompanies(); await loadInvoices() })
</script>

<style scoped>
@reference "../../../../css/app.css";
.lbl { @apply text-[10px] font-bold text-slate-400 uppercase tracking-widest block mb-1; }
.inp { @apply w-full border border-slate-200 rounded-xl px-3 py-2 text-sm bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary/40 outline-none; }
.modal-enter-active, .modal-leave-active { transition: opacity 0.2s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.toast-enter-active, .toast-leave-active { transition: all 0.3s ease; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translate(-50%, 1rem); }
</style>
