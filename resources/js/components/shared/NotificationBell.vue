<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import axios from 'axios'
import { registerPush, isPushSupported } from '@/composables/usePush'

type Notif = {
  id: number
  title: string
  body: string
  url: string
  type: string
  read_at: string | null
  created_at: string
}

const open        = ref(false)
const notifs      = ref<Notif[]>([])
const unreadCount = ref(0)
const bellRef     = ref<HTMLElement | null>(null)
const pushActive  = ref(false)
const pushLoading = ref(false)

async function checkPushState() {
  if (!isPushSupported()) return
  pushActive.value = Notification.permission === 'granted'
}

async function load() {
  try {
    const res     = await axios.get('/notifications')
    notifs.value  = res.data.data
    unreadCount.value = res.data.unread_count
  } catch {}
}

async function markRead(notif: Notif) {
  if (!notif.read_at) {
    await axios.post(`/notifications/${notif.id}/read`)
    notif.read_at = new Date().toISOString()
    unreadCount.value = Math.max(0, unreadCount.value - 1)
  }
  open.value = false
  window.location.href = notif.url
}

async function markAllRead() {
  await axios.post('/notifications/read-all')
  notifs.value.forEach(n => { if (!n.read_at) n.read_at = new Date().toISOString() })
  unreadCount.value = 0
}

async function togglePush() {
  if (!isPushSupported()) return
  pushLoading.value = true
  try {
    const permission = await Notification.requestPermission()
    if (permission === 'granted') {
      pushActive.value = true
      registerPush().catch(() => {})
    } else {
      pushActive.value = false
    }
  } finally {
    pushLoading.value = false
  }
}

function fmtTime(d: string) {
  const diff = Math.floor((Date.now() - new Date(d).getTime()) / 1000)
  if (diff < 60)  return 'Baru saja'
  if (diff < 3600) return Math.floor(diff / 60) + ' menit lalu'
  if (diff < 86400) return Math.floor(diff / 3600) + ' jam lalu'
  return Math.floor(diff / 86400) + ' hari lalu'
}

function typeIcon(type: string) {
  if (type === 'user')    return 'pi pi-user text-blue-500'
  if (type === 'warning') return 'pi pi-exclamation-triangle text-amber-500'
  return 'pi pi-info-circle text-primary'
}

function onClickOutside(e: MouseEvent) {
  if (bellRef.value && !bellRef.value.contains(e.target as Node)) {
    open.value = false
  }
}

let pollInterval: ReturnType<typeof setInterval>

onMounted(async () => {
  await Promise.all([load(), checkPushState()])
  document.addEventListener('click', onClickOutside)
  pollInterval = setInterval(load, 30000)
})

onUnmounted(() => {
  document.removeEventListener('click', onClickOutside)
  clearInterval(pollInterval)
})
</script>

<template>
  <div ref="bellRef" class="relative">
    <!-- Bell button -->
    <button
      @click="open = !open"
      class="relative p-2 rounded-xl text-slate-400 hover:text-primary hover:bg-slate-100 transition-colors"
    >
      <i class="pi pi-bell text-lg"></i>
      <span
        v-if="unreadCount > 0"
        class="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center px-1 leading-none"
      >
        {{ unreadCount > 99 ? '99+' : unreadCount }}
      </span>
    </button>

    <!-- Dropdown -->
    <Transition name="dropdown">
      <div
        v-if="open"
        class="absolute right-0 top-full mt-2 w-80 bg-white rounded-2xl shadow-2xl shadow-slate-200/80 border border-slate-100 z-[100] overflow-hidden"
      >
        <!-- Header -->
        <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100">
          <div class="flex items-center gap-2">
            <p class="text-sm font-bold text-slate-800">Notifikasi</p>
            <span v-if="unreadCount > 0" class="bg-red-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full">{{ unreadCount }}</span>
          </div>
          <div class="flex items-center gap-2">
            <button
              v-if="isPushSupported()"
              @click="togglePush"
              :disabled="pushLoading"
              :title="pushActive ? 'Push aktif' : 'Aktifkan push notif ke HP'"
              class="text-xs text-slate-400 hover:text-primary transition-colors disabled:opacity-40 flex items-center gap-1"
            >
              <i :class="pushActive ? 'pi pi-bell text-primary' : 'pi pi-bell-slash'"></i>
              <span class="text-[10px]">{{ pushActive ? 'Aktif' : 'Aktifkan' }}</span>
            </button>
            <button
              v-if="unreadCount > 0"
              @click="markAllRead"
              class="text-[10px] text-primary hover:underline font-medium"
            >
              Tandai semua dibaca
            </button>
          </div>
        </div>

        <!-- List -->
        <div class="max-h-80 overflow-y-auto divide-y divide-slate-50">
          <div v-if="!notifs.length" class="px-4 py-10 text-center text-sm text-slate-400">
            Tidak ada notifikasi.
          </div>
          <button
            v-for="n in notifs"
            :key="n.id"
            @click="markRead(n)"
            class="w-full flex items-start gap-3 px-4 py-3.5 hover:bg-slate-50 transition-colors text-left"
            :class="!n.read_at ? 'bg-blue-50/40' : ''"
          >
            <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center shrink-0 mt-0.5">
              <i :class="typeIcon(n.type)" class="text-sm"></i>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-xs font-bold text-slate-800 leading-snug" :class="!n.read_at ? 'text-slate-900' : ''">{{ n.title }}</p>
              <p class="text-[11px] text-slate-500 mt-0.5 leading-snug line-clamp-2">{{ n.body }}</p>
              <p class="text-[10px] text-slate-300 mt-1 font-medium">{{ fmtTime(n.created_at) }}</p>
            </div>
            <div v-if="!n.read_at" class="w-2 h-2 bg-primary rounded-full shrink-0 mt-1.5"></div>
          </button>
        </div>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.dropdown-enter-active, .dropdown-leave-active { transition: all 0.15s ease; }
.dropdown-enter-from, .dropdown-leave-to { opacity: 0; transform: translateY(-6px) scale(0.97); }
</style>
