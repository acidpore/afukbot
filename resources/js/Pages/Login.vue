<script setup lang="ts">
import { ref } from 'vue';
import axios from 'axios';

const email    = ref('');
const password = ref('');
const error    = ref('');
const loading  = ref(false);

async function handleLogin() {
  error.value   = '';
  loading.value = true;
  try {
    // Ambil CSRF cookie dulu (diperlukan untuk session auth)
    await axios.get('/sanctum/csrf-cookie').catch(() => {});
    await axios.post('/auth/login', { email: email.value, password: password.value });
    window.location.href = '/dashboard';
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? 'Gagal login, coba lagi.';
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center islamic-pattern p-6">
    <div class="max-w-md w-full">
      <!-- Logo Section -->
      <div class="text-center mb-10">
        <div class="w-20 h-20 bg-primary rounded-3xl flex items-center justify-center shadow-2xl shadow-primary/30 mx-auto mb-6 rotate-12 hover:rotate-0 transition-transform duration-500">
          <span class="text-accent font-bold text-3xl">M</span>
        </div>
        <h1 class="text-3xl font-display font-bold text-primary">Selamat Datang</h1>
        <p class="text-slate-500 mt-2 text-sm">Masuk ke MBG Internal Management System</p>
      </div>

      <!-- Login Card -->
      <div class="premium-card">
        <form @submit.prevent="handleLogin" class="space-y-6">
          <div>
            <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Email Address</label>
            <div class="relative group">
              <input 
                v-model="email"
                type="email" 
                placeholder="admin@mbg.com"
                class="w-full bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-accent/20 focus:border-accent transition-all"
              >
            </div>
          </div>

          <div>
            <div class="flex justify-between items-center mb-2">
              <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest">Password</label>
              <a href="#" class="text-[10px] font-bold text-accent hover:underline">Lupa Password?</a>
            </div>
            <input 
              v-model="password"
              type="password" 
              placeholder="••••••••"
              class="w-full bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-accent/20 focus:border-accent transition-all"
            >
          </div>

          <div class="flex items-center gap-3 py-2">
            <input type="checkbox" id="remember" class="w-4 h-4 rounded border-slate-200 text-primary focus:ring-primary">
            <label for="remember" class="text-xs text-slate-500">Ingat saya di perangkat ini</label>
          </div>

          <!-- Error message -->
          <div v-if="error" class="bg-red-50 border border-red-200 text-red-600 text-xs rounded-xl px-4 py-3 flex items-center gap-2">
            <i class="pi pi-exclamation-circle text-sm"></i>
            {{ error }}
          </div>

          <p class="text-center text-xs text-slate-400">
            Belum punya akun?
            <a href="/register" class="font-bold text-accent hover:underline">Daftar di sini</a>
          </p>

          <button type="submit" :disabled="loading" class="w-full btn-primary justify-center py-4 shadow-xl shadow-primary/20 disabled:opacity-60 disabled:cursor-not-allowed">
            <span v-if="loading" class="flex items-center justify-center gap-2">
              <i class="pi pi-spin pi-spinner text-sm"></i> Masuk...
            </span>
            <span v-else>Sign In to System</span>
          </button>
        </form>
      </div>

      <!-- Footer Info -->
      <p class="text-center text-[10px] text-slate-400 mt-8 uppercase tracking-[0.3em]">
        &copy; 2024 MBG Management &bull; Exclusive Access Only
      </p>
    </div>
  </div>
</template>
