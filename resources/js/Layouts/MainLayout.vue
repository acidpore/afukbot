<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import axios from 'axios';
import { useAuth } from '@/composables/useAuth';
import { useDarkMode } from '@/composables/useDarkMode';
import NotificationBell from '@/components/shared/NotificationBell.vue';

const { isDark, toggle: toggleDark } = useDarkMode();

const isSidebarOpen = ref(false);
const isSearchOpen  = ref(false);
const searchQuery   = ref('');
const searchInput   = ref<HTMLInputElement | null>(null);
const selectedIndex = ref(0);

const PAGES = [
  { id: 'overview',             label: 'Dashboard',                  desc: 'Ringkasan & statistik utama',          icon: 'pi-chart-bar'           },
  { id: 'inventory-ruko',       label: 'Stok Barang — Ruko',          desc: 'Inventory barang lokasi ruko',         icon: 'pi-building'            },
  { id: 'inventory-margomulyo', label: 'Stok Barang — Margomulyo',    desc: 'Inventory barang lokasi margomulyo',   icon: 'pi-warehouse'           },
  { id: 'inventory-history',    label: 'Mutasi Stok',                 desc: 'Riwayat keluar masuk barang',          icon: 'pi-history'             },
  { id: 'sales',                label: 'Penjualan',                   desc: 'Invoice & transaksi penjualan',        icon: 'pi-receipt'             },
  { id: 'expenses',             label: 'Pengeluaran',                 desc: 'Catat & lihat pengeluaran ruko',       icon: 'pi-wallet'              },
  { id: 'incomes',              label: 'Pemasukan',                   desc: 'Catat & lihat pemasukan ruko',         icon: 'pi-arrow-circle-down'   },
  { id: 'rab',                  label: 'RAB Tracking',                desc: 'Rencana anggaran & realisasi',         icon: 'pi-chart-bar'           },
  { id: 'employees',            label: 'Data Karyawan',               desc: 'Manajemen staff & departemen',         icon: 'pi-users'               },
  { id: 'attendance',           label: 'Absensi Harian',              desc: 'Rekap kehadiran karyawan',             icon: 'pi-calendar-clock'      },
  { id: 'payroll',              label: 'Penggajian',                  desc: 'Slip gaji & generate payroll',         icon: 'pi-credit-card'         },
  { id: 'mbg',                  label: 'MBG Admin',                   desc: 'Sinkronisasi data MBG API',            icon: 'pi-globe'               },
  { id: 'settings',             label: 'Pengaturan',                  desc: 'Kelola akun & konfigurasi sistem',     icon: 'pi-cog'                 },
];

const searchResults = computed(() => {
  const q = searchQuery.value.trim().toLowerCase();
  if (!q) return PAGES;
  return PAGES.filter(p =>
    p.label.toLowerCase().includes(q) ||
    p.desc.toLowerCase().includes(q) ||
    p.id.toLowerCase().includes(q)
  );
});

function openSearch() {
  isSearchOpen.value = true;
  searchQuery.value  = '';
  selectedIndex.value = 0;
  nextTick(() => searchInput.value?.focus());
}

function closeSearch() {
  isSearchOpen.value = false;
  searchQuery.value  = '';
}

function navigateTo(id: string) {
  const url = new URL(window.location.href);
  url.searchParams.set('tab', id);
  window.history.pushState({}, '', url);
  window.dispatchEvent(new CustomEvent('tab-navigate', { detail: id }));
  closeSearch();
}

function onKeydown(e: KeyboardEvent) {
  if (!isSearchOpen.value) return;
  if (e.key === 'ArrowDown') {
    e.preventDefault();
    selectedIndex.value = Math.min(selectedIndex.value + 1, searchResults.value.length - 1);
  } else if (e.key === 'ArrowUp') {
    e.preventDefault();
    selectedIndex.value = Math.max(selectedIndex.value - 1, 0);
  } else if (e.key === 'Enter') {
    const item = searchResults.value[selectedIndex.value];
    if (item) navigateTo(item.id);
  } else if (e.key === 'Escape') {
    closeSearch();
  }
}

function onGlobalKey(e: KeyboardEvent) {
  if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
    e.preventDefault();
    openSearch();
  }
}

// reset selectedIndex saat query berubah
import { watch } from 'vue';
watch(searchQuery, () => { selectedIndex.value = 0; });

const { user, isSuperAdmin, loadUser } = useAuth();


onMounted(() => {
  window.addEventListener('keydown', onGlobalKey);
  window.addEventListener('keydown', onKeydown);
  loadUser();
});
onUnmounted(() => {
  window.removeEventListener('keydown', onGlobalKey);
  window.removeEventListener('keydown', onKeydown);
});

function closeSidebar() {
  isSidebarOpen.value = false;
}

async function logout() {
  try {
    await axios.post('/auth/logout');
  } finally {
    window.location.href = '/login';
  }
}
</script>

<template>
  <div class="min-h-screen flex" style="background-color: var(--bg-base);">

    <!-- Overlay (mobile only) -->
    <div
      v-if="isSidebarOpen"
      class="fixed inset-0 z-40 bg-black/40 lg:hidden"
      @click="closeSidebar"
    ></div>

    <!-- Persistent Sidebar -->
    <aside
      class="fixed inset-y-0 left-0 z-50 w-72 border-r transition-transform duration-300 lg:static lg:translate-x-0"
      style="background-color: var(--bg-card); border-color: var(--border);"
      :class="isSidebarOpen ? 'translate-x-0' : '-translate-x-full'"
    >
      <div class="flex flex-col h-full">
        <!-- Sidebar Header -->
        <div class="p-6">
          <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 bg-primary rounded-xl flex items-center justify-center shadow-lg shadow-primary/20">
                <span class="text-accent font-bold text-xl">M</span>
              </div>
              <div>
                <h1 class="text-lg font-display font-bold text-primary leading-tight">MBG Admin</h1>
                <p class="text-[10px] text-accent uppercase tracking-widest font-bold">Internal System</p>
              </div>
            </div>
            <button @click="closeSidebar" class="lg:hidden p-1.5 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition-colors">
              <i class="pi pi-times"></i>
            </button>
          </div>
        </div>

        <div class="h-[1px] w-full mb-6" style="background-color: var(--border-soft);"></div>

        <!-- Sidebar Navigation (Slot for links) -->
        <div class="flex-1 px-4 space-y-2 overflow-y-auto">
          <slot name="sidebar" :closeSidebar="closeSidebar" />
        </div>

        <!-- Sidebar Footer -->
        <div class="p-4 border-t" style="border-color: var(--border-soft);">
          <div class="p-4 rounded-2xl flex items-center gap-3" style="background-color: var(--bg-subtle);">
            <div class="w-10 h-10 rounded-full bg-accent/20 border-2 border-white shadow-sm flex items-center justify-center font-bold text-accent">
              {{ user?.name?.charAt(0)?.toUpperCase() ?? 'A' }}
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-xs font-bold truncate" style="color: var(--text-base);">{{ user?.name ?? 'Administrator' }}</p>
              <p class="text-[10px] truncate" style="color: var(--text-muted);">{{ isSuperAdmin ? 'Super Admin' : 'Admin' }}</p>
            </div>
            <button @click="logout" title="Logout" class="text-slate-400 hover:text-red-500 transition-colors">
              <i class="pi pi-sign-out"></i>
            </button>
          </div>
        </div>
      </div>
    </aside>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col min-h-screen overflow-hidden">
      <!-- Top Bar -->
      <header class="h-20 backdrop-blur-md border-b sticky top-0 z-40 px-8 flex items-center justify-between transition-colors" style="background-color: var(--bg-surface); border-color: var(--border);">
        <div class="flex items-center gap-4">
          <button @click="isSidebarOpen = !isSidebarOpen" class="lg:hidden p-2 hover:bg-slate-100 rounded-lg">
            <i class="pi pi-bars"></i>
          </button>
          <div class="text-xs font-bold uppercase tracking-widest flex items-center gap-2" style="color: var(--text-muted);">
            System
            <i class="pi pi-chevron-right text-[8px]"></i>
            <span style="color: var(--text-base);">Dashboard</span>
          </div>
        </div>

        <div class="flex items-center gap-1">
          <button
            @click="openSearch"
            class="hidden sm:flex items-center gap-2 px-3 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-400 hover:text-slate-600 transition-colors text-xs"
          >
            <i class="pi pi-search text-xs"></i>
            <span class="font-medium">Cari fitur...</span>
            <span class="ml-1 bg-white border border-slate-200 text-slate-400 text-[10px] font-bold px-1.5 py-0.5 rounded-md">Ctrl K</span>
          </button>
          <button
            @click="openSearch"
            class="sm:hidden p-2 text-slate-400 hover:text-primary transition-colors rounded-lg hover:bg-slate-100"
          >
            <i class="pi pi-search"></i>
          </button>
          <!-- Dark mode toggle -->
          <button
            @click="toggleDark"
            class="p-2 rounded-xl transition-colors"
            :style="isDark ? 'background-color: var(--bg-subtle); color: #c9a84c;' : 'color: var(--text-muted);'"
            :title="isDark ? 'Mode Terang' : 'Mode Gelap'"
          >
            <i class="pi" :class="isDark ? 'pi-sun' : 'pi-moon'"></i>
          </button>
          <NotificationBell v-if="isSuperAdmin" />
        </div>
      </header>

      <!-- Page Content -->
      <main class="flex-1 overflow-y-auto p-8 lg:p-12 islamic-pattern">
        <slot />
      </main>
    </div>
  </div>

  <!-- Command Palette -->
  <Transition name="palette">
    <div v-if="isSearchOpen" class="fixed inset-0 z-[200] flex items-start justify-center pt-24 px-4">
      <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="closeSearch"></div>
      <div class="relative w-full max-w-lg rounded-2xl shadow-2xl overflow-hidden transition-colors" style="background-color: var(--bg-card);">
        <!-- Input -->
        <div class="flex items-center gap-3 px-4 py-3.5 border-b" style="border-color: var(--border-soft);">
          <i class="pi pi-search flex-shrink-0" style="color: var(--text-soft);"></i>
          <input
            ref="searchInput"
            v-model="searchQuery"
            type="text"
            placeholder="Cari fitur atau halaman..."
            class="flex-1 text-sm outline-none bg-transparent"
            style="color: var(--text-base);"
          >
          <button @click="closeSearch" class="text-xs border px-1.5 py-0.5 rounded-md font-bold transition-colors" style="color: var(--text-soft); border-color: var(--border);">Esc</button>
        </div>

        <!-- Results -->
        <div class="max-h-80 overflow-y-auto py-2">
          <div v-if="searchResults.length === 0" class="px-4 py-8 text-center text-sm" style="color: var(--text-soft);">
            Tidak ada fitur yang cocok.
          </div>
          <button
            v-for="(item, i) in searchResults"
            :key="item.id"
            @click="navigateTo(item.id)"
            @mouseenter="selectedIndex = i"
            class="w-full flex items-center gap-3 px-4 py-3 text-left transition-colors group"
            :style="selectedIndex === i ? 'background-color: var(--bg-subtle);' : ''"
          >
            <div
              :class="selectedIndex === i ? 'bg-primary text-white' : 'group-hover:text-primary'"
              class="w-8 h-8 rounded-xl flex items-center justify-center flex-shrink-0 transition-colors"
              :style="selectedIndex === i ? '' : 'background-color: var(--bg-subtle); color: var(--text-muted);'"
            >
              <i :class="'pi ' + item.icon" class="text-xs"></i>
            </div>
            <div class="min-w-0 flex-1">
              <p :class="selectedIndex === i ? 'text-primary' : ''" class="text-sm font-bold truncate" :style="selectedIndex === i ? '' : 'color: var(--text-base);'">{{ item.label }}</p>
              <p class="text-[10px] truncate" style="color: var(--text-soft);">{{ item.desc }}</p>
            </div>
            <i v-if="selectedIndex === i" class="pi pi-arrow-right text-[10px] text-primary/40 flex-shrink-0"></i>
          </button>
        </div>

        <!-- Footer hint -->
        <div class="px-4 py-2.5 border-t flex items-center gap-4 text-[10px]" style="border-color: var(--border-soft); color: var(--text-soft);">
          <span><kbd class="px-1.5 py-0.5 rounded font-bold" style="background-color: var(--bg-subtle);">↑↓</kbd> navigasi</span>
          <span><kbd class="px-1.5 py-0.5 rounded font-bold" style="background-color: var(--bg-subtle);">Enter</kbd> buka</span>
          <span><kbd class="px-1.5 py-0.5 rounded font-bold" style="background-color: var(--bg-subtle);">Esc</kbd> tutup</span>
        </div>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.palette-enter-active, .palette-leave-active { transition: opacity 0.15s ease; }
.palette-enter-from, .palette-leave-to { opacity: 0; }
.palette-enter-active .relative, .palette-leave-active .relative { transition: transform 0.15s ease; }
.palette-enter-from .relative { transform: scale(0.97) translateY(-8px); }
.palette-leave-to .relative { transform: scale(0.97) translateY(-8px); }
</style>
