<script setup lang="ts">
import { ref } from 'vue';
import axios from 'axios';

const email   = ref('');
const message = ref('');
const error   = ref('');
const loading = ref(false);

async function handleSubmit() {
  error.value   = '';
  message.value = '';
  loading.value = true;
  try {
    const res = await axios.post('/auth/forgot-password', { email: email.value });
    message.value = res.data.message;
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? 'Terjadi kesalahan, coba lagi.';
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
        <h1 class="text-3xl font-display font-bold text-primary">Lupa Password</h1>
        <p class="text-slate-500 mt-2 text-sm">Masukkan email kamu dan kami akan kirim link reset</p>
      </div>

      <div class="premium-card">
        <div v-if="message" class="bg-green-50 border border-green-200 text-green-700 text-sm rounded-xl px-4 py-3 mb-4">
          {{ message }}
        </div>

        <form v-else @submit.prevent="handleSubmit" class="space-y-5">
          <div>
            <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Email Address</label>
            <input v-model="email" type="email" placeholder="kamu@mbg.com" required
              class="w-full bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-accent/20 focus:border-accent transition-all" />
          </div>

          <div v-if="error" class="bg-red-50 border border-red-200 text-red-600 text-xs rounded-xl px-4 py-3 flex items-center gap-2">
            <i class="pi pi-exclamation-circle text-sm"></i>
            {{ error }}
          </div>

          <button type="submit" :disabled="loading"
            class="w-full btn-primary justify-center py-4 shadow-xl shadow-primary/20 disabled:opacity-60 disabled:cursor-not-allowed">
            <span v-if="loading" class="flex items-center justify-center gap-2">
              <i class="pi pi-spin pi-spinner text-sm"></i> Mengirim...
            </span>
            <span v-else>Kirim Link Reset</span>
          </button>
        </form>

        <p class="text-center text-xs text-slate-400 mt-4">
          <a href="/login" class="font-bold text-accent hover:underline">Kembali ke Login</a>
        </p>
      </div>
    </div>
  </div>
</template>
