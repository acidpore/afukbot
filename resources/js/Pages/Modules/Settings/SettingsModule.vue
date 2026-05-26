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
      </nav>
    </div>

    <!-- Tab: Users -->
    <div v-if="tab === 'users'" class="space-y-4">
      <!-- Filter bar -->
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
                <span v-else class="text-xs text-gray-300">—</span>
              </td>
            </tr>
            <tr v-if="!filteredUsers.length">
              <td colspan="5" class="px-4 py-10 text-center text-gray-400 text-sm">Tidak ada data</td>
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
        </div>
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

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'


const tab    = ref('users')
const filter = ref('all')
const users  = ref<any[]>([])
const toast  = ref({ show: false, message: '', type: 'success' })

const pendingCount = computed(() => users.value.filter(u => u.status === 'pending').length)

const filteredUsers = computed(() =>
  filter.value === 'all' ? users.value : users.value.filter(u => u.status === filter.value)
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

onMounted(loadUsers)
</script>

<style scoped>
.toast-enter-active, .toast-leave-active { transition: all 0.3s ease; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translate(-50%, 1rem); }
</style>
