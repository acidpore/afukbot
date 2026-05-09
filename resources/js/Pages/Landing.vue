<script setup lang="ts">
import { ref } from 'vue';

const features = [
  { title: 'Inventory MBG', desc: 'Tracking stok alat dapur secara real-time dengan sistem cascade.', icon: 'pi pi-shopping-cart' },
  { title: 'Manajemen Karyawan', desc: 'Database karyawan lengkap dengan penyimpanan dokumen digital.', icon: 'pi pi-users' },
  { title: 'Sistem Absensi', desc: 'Pencatatan kehadiran harian yang terintegrasi dengan payroll.', icon: 'pi pi-calendar-plus' },
  { title: 'Penggajian Otomatis', desc: 'Kalkulasi gaji akurat berdasarkan performa dan kehadiran.', icon: 'pi pi-money-bill' },
];
</script>

<template>
  <div class="min-h-screen islamic-pattern overflow-hidden">
    <!-- Hero Section -->
    <nav class="p-6 flex justify-between items-center max-w-7xl mx-auto">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 bg-primary rounded-xl flex items-center justify-center">
          <span class="text-accent font-bold text-xl">M</span>
        </div>
        <span class="font-display font-bold text-primary text-xl">MBG System</span>
      </div>
      <a href="/login" class="btn-primary text-xs py-2 px-4">
        Portal Internal
      </a>
    </nav>

    <main class="max-w-7xl mx-auto px-6 py-20 flex flex-col items-center text-center">
      <div class="flex items-center gap-2 text-accent font-semibold text-xs uppercase tracking-[0.3em] mb-6">
        <span class="w-12 h-[1px] bg-accent/50"></span>
        Eksklusif Untuk MBG
        <span class="w-12 h-[1px] bg-accent/50"></span>
      </div>
      
      <h1 class="text-5xl md:text-7xl font-display font-bold text-primary leading-tight mb-8">
        Manajemen Internal <br>
        <span class="text-accent">Tanpa Batas</span>
      </h1>
      
      <p class="max-w-2xl text-slate-500 text-lg mb-12">
        Sistem manajemen terintegrasi untuk mengelola inventaris dapur, data karyawan, 
        absensi harian, hingga penggajian dalam satu platform premium.
      </p>

      <div class="flex gap-4">
        <a href="/login" class="btn-primary py-4 px-8 text-sm">Mulai Sekarang</a>
        <button class="px-8 py-4 glass rounded-xl text-sm font-bold text-primary hover:bg-white/90 transition-all">Pelajari Fitur</button>
      </div>

      <!-- Feature Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 mt-32 w-full">
        <div v-for="f in features" :key="f.title" class="premium-card text-left">
          <div class="text-3xl mb-4 text-accent">
            <i :class="f.icon"></i>
          </div>
          <h3 class="font-display font-bold text-primary text-lg mb-2">{{ f.title }}</h3>
          <p class="text-xs text-slate-500 leading-relaxed">{{ f.desc }}</p>
        </div>
      </div>
    </main>

    <!-- Footer Ornament -->
    <div class="py-10 text-center opacity-30">
      <div class="section-divider"></div>
      <p class="text-[10px] text-slate-400 uppercase tracking-widest mt-4">MBG Internal System &bull; 2024</p>
    </div>
  </div>
</template>
