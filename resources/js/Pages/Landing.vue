<script setup lang="ts">
import { ref, onMounted } from 'vue';

const features = [
  { title: 'Inventory MBG', desc: 'Tracking stok alat dapur secara real-time dengan sistem cascade.', icon: 'pi pi-shopping-cart' },
  { title: 'Manajemen Karyawan', desc: 'Database karyawan lengkap dengan penyimpanan dokumen digital.', icon: 'pi pi-users' },
  { title: 'Sistem Absensi', desc: 'Pencatatan kehadiran harian yang terintegrasi dengan payroll.', icon: 'pi pi-calendar-plus' },
  { title: 'Penggajian Otomatis', desc: 'Kalkulasi gaji akurat berdasarkan performa dan kehadiran.', icon: 'pi pi-money-bill' },
];

const stats = [
  { value: 120, suffix: '+', label: 'Karyawan Terkelola' },
  { value: 100, suffix: '%', label: 'Data Terpusat' },
  { value: 4, suffix: '', label: 'Modul Terintegrasi' },
  { value: 24, suffix: '/7', label: 'Akses Kapan Saja' },
];

const marqueeItems = [
  'INVENTORY MBG', 'ABSENSI HARIAN', 'PENGGAJIAN OTOMATIS',
  'DATA AKURAT', 'MANAJEMEN KARYAWAN', 'SISTEM PREMIUM',
  'REAL-TIME TRACKING', 'LAPORAN INSTAN',
];

const statValues = ref(stats.map(() => 0));
const statsVisible = ref(false);
const statsRef = ref<HTMLElement | null>(null);
const featureRefs = ref<HTMLElement[]>([]);
const visibleFeatures = ref<boolean[]>(stats.map(() => false));
const heroVisible = ref(false);

function animateCount(index: number, target: number, duration = 1800) {
  const start = performance.now();
  function step(now: number) {
    const elapsed = now - start;
    const progress = Math.min(elapsed / duration, 1);
    const eased = 1 - Math.pow(1 - progress, 3);
    statValues.value[index] = Math.round(eased * target);
    if (progress < 1) requestAnimationFrame(step);
    else statValues.value[index] = target;
  }
  requestAnimationFrame(step);
}

onMounted(() => {
  setTimeout(() => { heroVisible.value = true; }, 100);

  const statsObserver = new IntersectionObserver(
    ([entry]) => {
      if (entry.isIntersecting && !statsVisible.value) {
        statsVisible.value = true;
        stats.forEach((s, i) => animateCount(i, s.value));
      }
    },
    { threshold: 0.3 }
  );
  if (statsRef.value) statsObserver.observe(statsRef.value);

  const cardObserver = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        const idx = parseInt((entry.target as HTMLElement).dataset.index ?? '0');
        if (entry.isIntersecting) visibleFeatures.value[idx] = true;
      });
    },
    { threshold: 0.15 }
  );
  featureRefs.value.forEach((el) => { if (el) cardObserver.observe(el); });
});

function setFeatureRef(el: any, index: number) {
  if (el) featureRefs.value[index] = el;
}
</script>

<template>
  <div class="min-h-screen islamic-pattern overflow-hidden">

    <!-- Navbar -->
    <nav class="p-6 flex justify-between items-center max-w-7xl mx-auto">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 bg-primary rounded-xl flex items-center justify-center shadow-lg">
          <span class="text-accent font-bold text-xl">M</span>
        </div>
        <span class="font-display font-bold text-primary text-xl">MBG System</span>
      </div>
      <a href="/login" class="btn-primary text-xs py-2 px-4">
        Portal Internal
      </a>
    </nav>

    <!-- Hero -->
    <main class="max-w-7xl mx-auto px-6 pt-16 pb-8 flex flex-col items-center text-center">

      <div
        class="hero-badge flex items-center gap-2 text-accent font-semibold text-xs uppercase tracking-[0.3em] mb-6"
        :class="{ 'hero-badge--visible': heroVisible }"
      >
        <span class="w-12 h-[1px] bg-accent/50"></span>
        Eksklusif Untuk MBG
        <span class="w-12 h-[1px] bg-accent/50"></span>
      </div>

      <div class="overflow-hidden mb-4">
        <h1
          class="hero-line text-5xl md:text-7xl font-display font-bold text-primary leading-tight"
          :class="{ 'hero-line--visible': heroVisible }"
          style="transition-delay: 0.1s"
        >
          Manajemen Internal
        </h1>
      </div>
      <div class="overflow-hidden mb-8">
        <h1
          class="hero-line text-5xl md:text-7xl font-display font-bold text-accent leading-tight"
          :class="{ 'hero-line--visible': heroVisible }"
          style="transition-delay: 0.28s"
        >
          Tanpa Batas
        </h1>
      </div>

      <p
        class="hero-fade max-w-2xl text-slate-500 text-lg mb-12"
        :class="{ 'hero-fade--visible': heroVisible }"
        style="transition-delay: 0.5s"
      >
        Sistem manajemen terintegrasi untuk mengelola inventaris dapur, data karyawan,
        absensi harian, hingga penggajian dalam satu platform premium.
      </p>

      <div
        class="hero-fade flex gap-4 flex-wrap justify-center"
        :class="{ 'hero-fade--visible': heroVisible }"
        style="transition-delay: 0.65s"
      >
        <a href="/login" class="btn-primary py-4 px-8 text-sm">
          <i class="pi pi-sign-in"></i>
          Mulai Sekarang
        </a>
        <a href="#fitur" class="px-8 py-4 glass rounded-xl text-sm font-bold text-primary hover:bg-white/90 transition-all flex items-center gap-2">
          <i class="pi pi-th-large"></i>
          Pelajari Fitur
        </a>
      </div>
    </main>

    <!-- Marquee Strip -->
    <div class="marquee-wrapper my-16 py-4 border-y border-accent/10 overflow-hidden" aria-hidden="true">
      <div class="marquee-track">
        <span
          v-for="(item, i) in [...marqueeItems, ...marqueeItems]"
          :key="i"
          class="marquee-item text-xs font-bold tracking-[0.2em] text-slate-400 uppercase"
        >
          {{ item }} <span class="text-accent mx-3">·</span>
        </span>
      </div>
    </div>

    <!-- Stats Section -->
    <section ref="statsRef" class="max-w-7xl mx-auto px-6 mb-24">
      <div class="glass rounded-2xl py-10 px-8 grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
        <div
          v-for="(s, i) in stats"
          :key="i"
          class="stat-item"
          :class="{ 'stat-item--visible': statsVisible }"
          :style="{ transitionDelay: `${i * 0.12}s` }"
        >
          <div class="text-4xl font-display font-bold text-primary mb-1">
            {{ statValues[i] }}{{ s.suffix }}
          </div>
          <div class="text-xs text-slate-500 uppercase tracking-widest">{{ s.label }}</div>
        </div>
      </div>
    </section>

    <!-- Feature Grid -->
    <section id="fitur" class="max-w-7xl mx-auto px-6 mb-24">
      <div class="text-center mb-12">
        <p class="text-xs font-semibold text-accent uppercase tracking-[0.3em] mb-3">Fitur Unggulan</p>
        <h2 class="text-3xl md:text-4xl font-display font-bold text-primary">Semua yang Anda Butuhkan</h2>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
        <div
          v-for="(f, i) in features"
          :key="f.title"
          :ref="el => setFeatureRef(el, i)"
          :data-index="i"
          class="premium-card text-left card-reveal"
          :class="{ 'card-reveal--visible': visibleFeatures[i] }"
          :style="{ transitionDelay: `${i * 0.1}s` }"
        >
          <div class="w-12 h-12 rounded-xl bg-primary/5 flex items-center justify-center mb-4">
            <i :class="[f.icon, 'text-2xl text-accent']"></i>
          </div>
          <h3 class="font-display font-bold text-primary text-lg mb-2">{{ f.title }}</h3>
          <p class="text-xs text-slate-500 leading-relaxed">{{ f.desc }}</p>
        </div>
      </div>
    </section>

    <!-- CTA Section -->
    <section class="max-w-7xl mx-auto px-6 mb-24">
      <div class="bg-primary rounded-2xl px-8 py-16 text-center relative overflow-hidden">
        <div class="absolute inset-0 opacity-10 islamic-pattern"></div>
        <div class="relative z-10">
          <p class="text-accent text-xs font-semibold uppercase tracking-[0.3em] mb-4">Siap Memulai?</p>
          <h2 class="text-3xl md:text-4xl font-display font-bold text-white mb-4">
            Kelola Operasional MBG<br>dengan Lebih Efisien
          </h2>
          <p class="text-white/60 text-sm max-w-lg mx-auto mb-8">
            Login ke portal internal dan mulai pantau inventaris, karyawan, absensi, serta penggajian dalam satu dashboard.
          </p>
          <a href="/login" class="inline-flex items-center gap-2 bg-accent text-primary font-bold py-4 px-10 rounded-xl hover:brightness-110 active:scale-95 transition-all text-sm shadow-lg">
            <i class="pi pi-sign-in"></i>
            Masuk ke Portal
          </a>
        </div>
      </div>
    </section>

    <!-- Footer -->
    <footer class="py-10 text-center">
      <div class="section-divider"></div>
      <p class="text-[10px] text-slate-400 uppercase tracking-widest mt-4">MBG Internal System &bull; 2024</p>
    </footer>

  </div>
</template>

<style scoped>
/* Hero animations */
.hero-badge {
  opacity: 0;
  transform: translateY(8px);
  transition: opacity 0.6s ease, transform 0.6s ease;
}
.hero-badge--visible {
  opacity: 1;
  transform: translateY(0);
}

.hero-line {
  display: block;
  transform: translateY(110%);
  opacity: 0;
  transition: transform 1s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.8s ease;
}
.hero-line--visible {
  transform: translateY(0);
  opacity: 1;
}

.hero-fade {
  opacity: 0;
  transform: translateY(20px);
  transition: opacity 0.8s ease, transform 0.8s cubic-bezier(0.16, 1, 0.3, 1);
}
.hero-fade--visible {
  opacity: 1;
  transform: translateY(0);
}

/* Marquee */
.marquee-wrapper {
  background: linear-gradient(to right, transparent, rgba(201,168,76,0.03), transparent);
}
.marquee-track {
  display: flex;
  width: max-content;
  animation: marquee 28s linear infinite;
}
.marquee-item {
  white-space: nowrap;
  padding: 0 0.5rem;
}
@keyframes marquee {
  from { transform: translateX(0); }
  to { transform: translateX(-50%); }
}

/* Stats */
.stat-item {
  opacity: 0;
  transform: translateY(24px);
  transition: opacity 0.7s ease, transform 0.7s cubic-bezier(0.16, 1, 0.3, 1);
}
.stat-item--visible {
  opacity: 1;
  transform: translateY(0);
}

/* Card reveal */
.card-reveal {
  opacity: 0;
  transform: translateY(32px);
  transition: opacity 0.7s ease, transform 0.7s cubic-bezier(0.16, 1, 0.3, 1);
}
.card-reveal--visible {
  opacity: 1;
  transform: translateY(0);
}
</style>
