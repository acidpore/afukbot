<script setup lang="ts">
import { ref } from 'vue';

const isSidebarOpen = ref(false);

function closeSidebar() {
  isSidebarOpen.value = false;
}
</script>

<template>
  <div class="min-h-screen flex bg-[#faf9f6]">

    <!-- Overlay (mobile only) -->
    <div
      v-if="isSidebarOpen"
      class="fixed inset-0 z-40 bg-black/40 lg:hidden"
      @click="closeSidebar"
    ></div>

    <!-- Persistent Sidebar -->
    <aside
      class="fixed inset-y-0 left-0 z-50 w-72 bg-white border-r border-slate-200 transition-transform duration-300 lg:static lg:translate-x-0"
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

        <div class="h-[1px] w-full bg-slate-100 mb-6"></div>

        <!-- Sidebar Navigation (Slot for links) -->
        <div class="flex-1 px-4 space-y-2 overflow-y-auto">
          <slot name="sidebar" :closeSidebar="closeSidebar" />
        </div>

        <!-- Sidebar Footer -->
        <div class="p-4 border-t border-slate-100">
          <div class="p-4 bg-slate-50 rounded-2xl flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-accent/20 border-2 border-white shadow-sm flex items-center justify-center font-bold text-accent">
              A
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-xs font-bold text-slate-900 truncate">Administrator</p>
              <p class="text-[10px] text-slate-500 truncate">admin@mbg.com</p>
            </div>
            <button class="text-slate-400 hover:text-red-500 transition-colors">
              <i class="pi pi-log-out"></i>
            </button>
          </div>
        </div>
      </div>
    </aside>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col min-h-screen overflow-hidden">
      <!-- Top Bar -->
      <header class="h-20 bg-white/80 backdrop-blur-md border-b border-slate-200 sticky top-0 z-40 px-8 flex items-center justify-between">
        <div class="flex items-center gap-4">
          <button @click="isSidebarOpen = !isSidebarOpen" class="lg:hidden p-2 hover:bg-slate-100 rounded-lg">
            <i class="pi pi-bars"></i>
          </button>
          <div class="text-xs font-bold text-slate-400 uppercase tracking-widest flex items-center gap-2">
            System
            <i class="pi pi-chevron-right text-[8px]"></i>
            <span class="text-slate-900">Dashboard</span>
          </div>
        </div>

        <div class="flex items-center gap-4">
          <button class="p-2 text-slate-400 hover:text-primary transition-colors">
            <i class="pi pi-bell"></i>
          </button>
          <button class="p-2 text-slate-400 hover:text-primary transition-colors">
            <i class="pi pi-search"></i>
          </button>
        </div>
      </header>

      <!-- Page Content -->
      <main class="flex-1 overflow-y-auto p-8 lg:p-12 islamic-pattern">
        <slot />
      </main>
    </div>
  </div>
</template>
