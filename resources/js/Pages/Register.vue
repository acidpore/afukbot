<script setup lang="ts">
import { ref } from 'vue';
import axios from 'axios';

const form    = ref({ name: '', email: '', password: '', password_confirmation: '' });
const error   = ref('');
const success  = ref(false);
const loading = ref(false);

async function handleRegister() {
  error.value   = '';
  loading.value = true;
  try {
    await axios.post('/auth/register', form.value);
    success.value = true;
  } catch (e: any) {
    const errors = e?.response?.data?.errors;
    if (errors) {
      error.value = Object.values(errors).flat().join(' ');
    } else {
      error.value = e?.response?.data?.message ?? 'Gagal mendaftar, coba lagi.';
    }
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center islamic-pattern p-6">
    <div class="max-w-md w-full">
      <!-- Logo -->
      <div class="text-center mb-10">
        <div class="w-20 h-20 bg-primary rounded-3xl flex items-center justify-center shadow-2xl shadow-primary/30 mx-auto mb-6 rotate-12 hover:rotate-0 transition-transform duration-500">
          <span class="text-accent font-bold text-3xl">M</span>
        </div>
        <h1 class="text-3xl font-display font-bold text-primary">Daftar Akun</h1>
        <p class="text-slate-500 mt-2 text-sm">Permintaan akan diverifikasi oleh admin</p>
      </div>

      <!-- Success state -->
      <div v-if="success" class="premium-card text-center space-y-4">
        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto">
          <i class="pi pi-check text-2xl text-green-600"></i>
        </div>
        <h2 class="text-lg font-bold text-slate-800">Pendaftaran Terkirim!</h2>
        <p class="text-sm text-slate-500">Admin akan mereview dan menyetujui akun kamu. Kamu akan diberi tahu setelah disetujui.</p>
        <a href="/login" class="inline-block mt-2 text-sm font-bold text-accent hover:underline">
          Kembali ke Login →
        </a>
      </div>

      <!-- Form -->
      <div v-else class="premium-card">
        <form @submit.prevent="handleRegister" class="space-y-5">
          <div>
            <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Nama Lengkap</label>
            <input v-model="form.name" type="text" placeholder="Ahmad Subardjo" required
              class="w-full bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-accent/20 focus:border-accent transition-all" />
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Email Address</label>
            <input v-model="form.email" type="email" placeholder="kamu@mbg.com" required
              class="w-full bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-accent/20 focus:border-accent transition-all" />
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Password</label>
            <input v-model="form.password" type="password" placeholder="Min. 8 karakter" required
              class="w-full bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-accent/20 focus:border-accent transition-all" />
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Konfirmasi Password</label>
            <input v-model="form.password_confirmation" type="password" placeholder="Ulangi password" required
              class="w-full bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-accent/20 focus:border-accent transition-all" />
          </div>

          <div v-if="error" class="bg-red-50 border border-red-200 text-red-600 text-xs rounded-xl px-4 py-3 flex items-center gap-2">
            <i class="pi pi-exclamation-circle text-sm"></i>
            {{ error }}
          </div>

          <button type="submit" :disabled="loading"
            class="w-full btn-primary justify-center py-4 shadow-xl shadow-primary/20 disabled:opacity-60 disabled:cursor-not-allowed">
            <span v-if="loading" class="flex items-center justify-center gap-2">
              <i class="pi pi-spin pi-spinner text-sm"></i> Mendaftar...
            </span>
            <span v-else>Kirim Permintaan</span>
          </button>

          <p class="text-center text-xs text-slate-400">
            Sudah punya akun?
            <a href="/login" class="font-bold text-accent hover:underline">Masuk di sini</a>
          </p>
        </form>
      </div>

      <p class="text-center text-[10px] text-slate-400 mt-8 uppercase tracking-[0.3em]">
        &copy; 2024 MBG Management &bull; Exclusive Access Only
      </p>
    </div>
  </div>
</template>
