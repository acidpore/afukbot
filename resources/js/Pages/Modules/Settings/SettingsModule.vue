<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-gray-900">Pengaturan</h1>
      <p class="text-sm text-gray-500 mt-0.5">Kelola akses dan konfigurasi sistem</p>
    </div>

    <!-- Sub-tabs -->
    <div class="border-b border-gray-200">
      <nav class="flex gap-6">
        <button @click="tab = 'users'"
          :class="['pb-3 text-sm font-medium border-b-2 transition-colors flex items-center gap-2', tab === 'users'
            ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700']">
          Manajemen User
          <span v-if="pendingCount > 0"
            class="bg-red-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full leading-none">
            {{ pendingCount }}
          </span>
        </button>
        <button v-if="isSuperAdmin" @click="tab = 'permissions'"
          :class="['pb-3 text-sm font-medium border-b-2 transition-colors', tab === 'permissions'
            ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700']">
          Hak Akses Admin
        </button>
      </nav>
    </div>

    <!-- Tab: Users -->
    <div v-if="tab === 'users'" class="space-y-4">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-gray-500">{{ users.length }} user terdaftar</p>
        <div class="flex flex-wrap gap-2">
          <button v-for="f in ['all','pending','active','rejected']" :key="f"
            @click="filter = f"
            :class="['text-xs px-3 py-1.5 rounded-lg font-medium transition-colors', filter === f
              ? 'bg-primary text-white'
              : 'bg-gray-100 text-gray-600 hover:bg-gray-200']">
            {{ { all: 'Semua', pending: 'Pending', active: 'Aktif', rejected: 'Ditolak' }[f] }}
            <span v-if="f === 'pending' && pendingCount > 0" class="ml-1 bg-red-500 text-white text-[9px] px-1 rounded-full">{{ pendingCount }}</span>
          </button>
        </div>
      </div>

      <!-- Desktop table -->
      <div class="hidden sm:block bg-white rounded-2xl shadow overflow-hidden">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
            <tr>
              <th class="px-4 py-3 text-left">Nama</th>
              <th class="px-4 py-3 text-left">Email</th>
              <th class="px-4 py-3 text-center">Role</th>
              <th class="px-4 py-3 text-center">Status</th>
              <th class="px-4 py-3 text-center">Daftar</th>
              <th class="px-4 py-3 text-center">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="u in filteredUsers" :key="u.id" class="hover:bg-gray-50">
              <td class="px-4 py-3 font-medium text-gray-800">{{ u.name }}</td>
              <td class="px-4 py-3 text-gray-500">{{ u.email }}</td>
              <td class="px-4 py-3 text-center">
                <span :class="u.role === 'super_admin' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700'"
                  class="text-xs px-2.5 py-1 rounded-full font-semibold">
                  {{ u.role === 'super_admin' ? 'Super Admin' : 'Admin' }}
                </span>
              </td>
              <td class="px-4 py-3 text-center">
                <span :class="userStatusClass(u.status)" class="text-xs px-2.5 py-1 rounded-full font-semibold">
                  {{ userStatusLabel(u.status) }}
                </span>
              </td>
              <td class="px-4 py-3 text-center text-xs text-gray-400">{{ fmtDate(u.created_at) }}</td>
              <td class="px-4 py-3 text-center">
                <div v-if="u.status === 'pending'" class="flex justify-center gap-2">
                  <button @click="approve(u.id)" class="text-xs bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded-lg transition-colors">Approve</button>
                  <button @click="reject(u.id)"  class="text-xs bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded-lg transition-colors">Reject</button>
                </div>
                <div v-else-if="u.status === 'active' && u.role !== 'super_admin'" class="flex justify-center">
                  <button @click="deleteUser(u.id, u.name)" class="text-xs bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded-lg transition-colors">Hapus Akun</button>
                </div>
                <span v-else class="text-xs text-gray-300">—</span>
              </td>
            </tr>
            <tr v-if="!filteredUsers.length">
              <td colspan="6" class="px-4 py-10 text-center text-gray-400 text-sm">Tidak ada data</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Mobile cards -->
      <div class="sm:hidden space-y-3">
        <div v-if="!filteredUsers.length" class="bg-white rounded-2xl shadow px-4 py-10 text-center text-gray-400 text-sm">Tidak ada data</div>
        <div v-for="u in filteredUsers" :key="u.id"
          class="bg-white rounded-2xl shadow p-4 flex items-start justify-between gap-3">
          <div class="flex-1 min-w-0 space-y-1">
            <p class="font-semibold text-gray-800 text-sm">{{ u.name }}</p>
            <p class="text-xs text-gray-500 truncate">{{ u.email }}</p>
            <div class="flex items-center gap-2 pt-0.5">
              <span :class="u.role === 'super_admin' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700'"
                class="text-[11px] px-2 py-0.5 rounded-full font-semibold">
                {{ u.role === 'super_admin' ? 'Super Admin' : 'Admin' }}
              </span>
              <span :class="userStatusClass(u.status)" class="text-[11px] px-2 py-0.5 rounded-full font-semibold">
                {{ userStatusLabel(u.status) }}
              </span>
              <span class="text-[11px] text-gray-400">{{ fmtDate(u.created_at) }}</span>
            </div>
          </div>
          <div v-if="u.status === 'pending'" class="flex flex-col gap-1.5 shrink-0">
            <button @click="approve(u.id)"
              class="text-xs bg-green-500 hover:bg-green-600 text-white px-3 py-1.5 rounded-lg transition-colors">
              Approve
            </button>
            <button @click="reject(u.id)"
              class="text-xs bg-red-500 hover:bg-red-600 text-white px-3 py-1.5 rounded-lg transition-colors">
              Reject
            </button>
          </div>
          <div v-else-if="u.status === 'active' && u.role !== 'super_admin'" class="shrink-0">
            <button @click="deleteUser(u.id, u.name)"
              class="text-xs bg-red-500 hover:bg-red-600 text-white px-3 py-1.5 rounded-lg transition-colors">
              Hapus Akun
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Tab: Hak Akses Admin -->
    <div v-if="tab === 'permissions' && isSuperAdmin" class="space-y-5">
      <!-- Pilih admin -->
      <div class="flex items-center gap-3">
        <label class="text-sm font-medium text-gray-700 shrink-0">Pilih Admin:</label>
        <select v-model="selectedAdminId" @change="loadPermissions"
          class="border border-gray-200 rounded-xl px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary bg-white">
          <option value="">— Pilih user —</option>
          <option v-for="u in adminUsers" :key="u.id" :value="u.id">{{ u.name }} ({{ u.email }})</option>
        </select>
      </div>

      <!-- Tabel permissions -->
      <div v-if="selectedAdminId && permissions.length" class="bg-white rounded-2xl shadow overflow-hidden">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
            <tr>
              <th class="px-4 py-3 text-left">Fitur</th>
              <th class="px-4 py-3 text-center">
                <div class="flex flex-col items-center gap-1">
                  <span>Lihat</span>
                  <button @click="selectAll('can_view')" class="text-[9px] font-bold text-primary hover:underline normal-case">Semua</button>
                </div>
              </th>
              <th class="px-4 py-3 text-center">
                <div class="flex flex-col items-center gap-1">
                  <span>Tambah Item</span>
                  <button @click="selectAll('can_create')" class="text-[9px] font-bold text-primary hover:underline normal-case">Semua</button>
                </div>
              </th>
              <th class="px-4 py-3 text-center">
                <div class="flex flex-col items-center gap-1">
                  <span>Edit Item</span>
                  <button @click="selectAll('can_edit')" class="text-[9px] font-bold text-primary hover:underline normal-case">Semua</button>
                </div>
              </th>
              <th class="px-4 py-3 text-center">
                <div class="flex flex-col items-center gap-1">
                  <span>Hapus Item</span>
                  <button @click="selectAll('can_delete')" class="text-[9px] font-bold text-primary hover:underline normal-case">Semua</button>
                </div>
              </th>
              <th class="px-4 py-3 text-center">
                <div class="flex flex-col items-center gap-1">
                  <span>Sesuaikan Stok</span>
                  <button @click="selectAll('can_adjust')" class="text-[9px] font-bold text-primary hover:underline normal-case">Semua</button>
                </div>
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="perm in permissions" :key="perm.feature" class="hover:bg-gray-50">
              <td class="px-4 py-3 font-medium text-gray-700 capitalize">{{ featureLabel(perm.feature) }}</td>
              <td class="px-4 py-3 text-center">
                <input type="checkbox" v-model="perm.can_view" class="accent-primary w-4 h-4">
              </td>
              <td class="px-4 py-3 text-center">
                <input type="checkbox" v-model="perm.can_create" class="accent-primary w-4 h-4">
              </td>
              <td class="px-4 py-3 text-center">
                <input type="checkbox" v-model="perm.can_edit" class="accent-primary w-4 h-4">
              </td>
              <td class="px-4 py-3 text-center">
                <input type="checkbox" v-model="perm.can_delete" class="accent-primary w-4 h-4">
              </td>
              <td class="px-4 py-3 text-center">
                <input v-if="perm.feature === 'inventory'" type="checkbox" v-model="perm.can_adjust" class="accent-primary w-4 h-4">
                <span v-else class="text-xs text-gray-200">—</span>
              </td>
            </tr>
          </tbody>
        </table>
        <div class="px-4 py-3 border-t border-gray-100 flex justify-end">
          <button @click="savePermissions"
            class="bg-primary text-white text-xs font-bold px-5 py-2 rounded-xl hover:bg-primary/90 transition-colors">
            Simpan Hak Akses
          </button>
        </div>
      </div>

      <p v-else-if="selectedAdminId && !permissions.length" class="text-sm text-gray-400">Memuat...</p>
      <p v-else class="text-sm text-gray-400">Pilih admin untuk mengatur hak aksesnya.</p>
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

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { useAuth } from '@/composables/useAuth'

const { isSuperAdmin, loadUser } = useAuth()

const tab    = ref('users')
const filter = ref('all')
const users  = ref<any[]>([])
const toast  = ref({ show: false, message: '', type: 'success' })

const selectedAdminId = ref<number | ''>('')
const permissions     = ref<any[]>([])

const FEATURE_LABELS: Record<string, string> = {
  inventory:   'Inventory Stok',
  sales:       'Penjualan',
  expenses:    'Pengeluaran',
  incomes:     'Pemasukan',
  rab:         'RAB Tracking',
  employees:   'Data Karyawan',
  attendance:  'Absensi',
  payroll:     'Penggajian',
  mbg:         'MBG Admin',
  surat_jalan: 'Surat Jalan',
}

function featureLabel(feature: string) {
  return FEATURE_LABELS[feature] ?? feature
}

const pendingCount = computed(() => users.value.filter(u => u.status === 'pending').length)

const filteredUsers = computed(() =>
  filter.value === 'all' ? users.value : users.value.filter(u => u.status === filter.value)
)

const adminUsers = computed(() =>
  users.value.filter(u => u.role === 'admin' && u.status === 'active')
)

function showToast(message: string, type = 'success') {
  toast.value = { show: true, message, type }
  setTimeout(() => (toast.value.show = false), 3500)
}

function userStatusClass(s: string) {
  if (s === 'pending')  return 'bg-amber-100 text-amber-700'
  if (s === 'active')   return 'bg-green-100 text-green-700'
  return 'bg-red-100 text-red-600'
}
function userStatusLabel(s: string) {
  return { pending: 'Menunggu', active: 'Aktif', rejected: 'Ditolak' }[s] ?? s
}
function fmtDate(d: string) {
  return new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
}

async function loadUsers() {
  const res = await axios.get('/auth/users')
  users.value = res.data
}

async function approve(id: number) {
  try {
    await axios.post(`/auth/users/${id}/approve`)
    await loadUsers()
    showToast('User disetujui')
  } catch {
    showToast('Gagal menyetujui user', 'error')
  }
}

async function reject(id: number) {
  try {
    await axios.post(`/auth/users/${id}/reject`)
    await loadUsers()
    showToast('User ditolak')
  } catch {
    showToast('Gagal menolak user', 'error')
  }
}

async function deleteUser(id: number, name: string) {
  if (!confirm(`Hapus akun "${name}"? Tindakan ini tidak bisa dibatalkan.`)) return
  try {
    await axios.delete(`/auth/users/${id}`)
    await loadUsers()
    showToast(`Akun ${name} dihapus`)
  } catch {
    showToast('Gagal menghapus akun', 'error')
  }
}

async function loadPermissions() {
  if (!selectedAdminId.value) return
  permissions.value = []
  const res = await axios.get(`/auth/users/${selectedAdminId.value}/permissions`)
  permissions.value = res.data.data
}

function selectAll(field: 'can_view' | 'can_create' | 'can_edit' | 'can_delete' | 'can_adjust') {
  const targets = field === 'can_adjust'
    ? permissions.value.filter(p => p.feature === 'inventory')
    : permissions.value
  const allChecked = targets.every(p => p[field])
  targets.forEach(p => (p[field] = !allChecked))
}

async function savePermissions() {
  if (!selectedAdminId.value) return
  try {
    await axios.put(`/auth/users/${selectedAdminId.value}/permissions`, {
      permissions: permissions.value,
    })
    showToast('Hak akses berhasil disimpan')
  } catch {
    showToast('Gagal menyimpan hak akses', 'error')
  }
}

onMounted(async () => {
  await loadUser()
  await loadUsers()
})
</script>

<style scoped>
.toast-enter-active, .toast-leave-active { transition: all 0.3s ease; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translate(-50%, 1rem); }
</style>
