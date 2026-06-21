<script setup lang="ts">
import { ref, onMounted } from 'vue';
import axios from 'axios';

const token    = ref('');
const email    = ref('');
const password = ref('');
const confirm  = ref('');
const error    = ref('');
const success  = ref(false);
const loading  = ref(false);

onMounted(() => {
  const params = new URLSearchParams(window.location.search);
  token.value = params.get('token') ?? '';
  email.value = params.get('email') ?? '';
});

async function handleReset() {
  error.value   = '';
  loading.value = true;
  try {
    await axios.post('/auth/reset-password', {
      token: token.value,
      email: email.value,
      password: password.value,
      password_confirmation: confirm.value,
    });
    success.value = true;
  } catch (e: any) {
    const errors = e?.response?.data?.errors;
    error.value = errors
      ? Object.values(errors).flat().join(' ')
      : (e?.response?.data?.message ?? 'Terjadi kesalahan, coba lagi.');
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center islamic-pattern p-6">
    <div class="max-w-md w-full">
      <div class="text-center mb-10">
        <div class="w-20 h-20 bg-primary rounded-3xl flex items-center justify-center shadow-2xl shadow-primary/30 mx-auto mb-6 rotate-12 hover:rotate-0 transition-transform duration-500">
          <span class="text-accent font-bold text-3xl">M</span>
        </div>
        <h1 class="text-3xl font-display font-bold text-primary">Reset Password</h1>
        <p class="text-slate-500 mt-2 text-sm">Buat password baru untuk akun kamu</p>
      </div>

      <div class="premium-card">
        <div v-if="success" class="text-center space-y-4">
          <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto">
            <i class="pi pi-check text-2xl text-green-600"></i>
          </div>
          <p class="text-sm text-slate-600">Password berhasil diubah. Silakan login dengan password baru kamu.</p>
          <a href="/login" class="inline-block text-sm font-bold text-accent hover:underline">Login Sekarang →</a>
        </div>

        <form v-else @submit.prevent="handleReset" class="space-y-5">
          <div>
            <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Password Baru</label>
            <input v-model="password" type="password" placeholder="Min. 8 karakter, 1 kapital, 1 angka" required
              class="w-full bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-accent/20 focus:border-accent transition-all" />
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Konfirmasi Password</label>
            <input v-model="confirm" type="password" placeholder="Ulangi password baru" required
              class="w-full bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-accent/20 focus:border-accent transition-all" />
          </div>

          <div v-if="error" class="bg-red-50 border border-red-200 text-red-600 text-xs rounded-xl px-4 py-3 flex items-center gap-2">
            <i class="pi pi-exclamation-circle text-sm"></i>
            {{ error }}
          </div>

          <button type="submit" :disabled="loading"
            class="w-full btn-primary justify-center py-4 shadow-xl shadow-primary/20 disabled:opacity-60 disabled:cursor-not-allowed">
            <span v-if="loading" class="flex items-center justify-center gap-2">
              <i class="pi pi-spin pi-spinner text-sm"></i> Menyimpan...
            </span>
            <span v-else>Simpan Password Baru</span>
          </button>
        </form>
      </div>
    </div>
  </div>
</template>
