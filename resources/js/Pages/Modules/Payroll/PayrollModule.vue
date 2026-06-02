<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { payrollApi } from '@/api/payroll.api';

const selectedMonth = ref(new Date().getMonth() + 1);
const selectedYear = ref(new Date().getFullYear());
const payrolls = ref<any[]>([]);
const isLoading = ref(false);
const isGenerating = ref(false);

const months = [
  { val: 1, name: 'Januari' }, { val: 2, name: 'Februari' }, { val: 3, name: 'Maret' },
  { val: 4, name: 'April' }, { val: 5, name: 'Mei' }, { val: 6, name: 'Juni' },
  { val: 7, name: 'Juli' }, { val: 8, name: 'Agustus' }, { val: 9, name: 'September' },
  { val: 10, name: 'Oktober' }, { val: 11, name: 'November' }, { val: 12, name: 'Desember' }
];

const fetchPayrolls = async () => {
  isLoading.value = true;
  try {
    const res = await payrollApi.getPayrolls(selectedMonth.value, selectedYear.value);
    payrolls.value = res.data.data;
  } catch (error) {
    console.error('Gagal mengambil data payroll:', error);
  } finally {
    isLoading.value = false;
  }
};

const generatePayroll = async () => {
  if (!confirm(`Generate payroll untuk periode ${selectedMonth.value}/${selectedYear.value}?`)) return;
  isGenerating.value = true;
  try {
    await payrollApi.generatePayroll(selectedMonth.value, selectedYear.value);
    alert('Payroll berhasil digenerate');
    fetchPayrolls();
  } catch (error) {
    alert('Gagal generate payroll');
  } finally {
    isGenerating.value = false;
  }
};

const markAsPaid = async (id: number) => {
  try {
    await payrollApi.markAsPaid(id);
    fetchPayrolls();
  } catch (error) {
    alert('Gagal memproses pembayaran');
  }
};

const totalPayout = computed(() => {
  return payrolls.value.reduce((acc, curr) => acc + parseFloat(curr.net_salary), 0);
});

const formatCurrency = (val: number) => {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(val);
};

onMounted(fetchPayrolls);
</script>

<template>
  <div class="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-700">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
      <div>
        <h2 class="text-3xl font-display font-bold text-primary">Penggajian</h2>
        <p class="text-slate-500 text-sm mt-1">Manajemen gaji, tunjangan, dan slip gaji karyawan.</p>
      </div>
      <div class="flex flex-col sm:flex-row sm:items-center gap-3">
        <div class="grid grid-cols-2 sm:flex sm:items-center gap-3">
          <select v-model="selectedMonth" @change="fetchPayrolls" class="bg-white border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-bold text-primary outline-none focus:ring-2 focus:ring-accent/20 focus:border-accent shadow-sm">
            <option v-for="m in months" :key="m.val" :value="m.val">{{ m.name }}</option>
          </select>
          <select v-model="selectedYear" @change="fetchPayrolls" class="bg-white border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-bold text-primary outline-none focus:ring-2 focus:ring-accent/20 focus:border-accent shadow-sm">
            <option v-for="y in [2024, 2025, 2026]" :key="y" :value="y">{{ y }}</option>
          </select>
        </div>
        <button
          @click="generatePayroll"
          :disabled="isGenerating"
          class="btn-primary shadow-xl shadow-primary/20 justify-center"
        >
          <i class="pi pi-sync" :class="{ 'animate-spin': isGenerating }"></i>
          Generate Payroll
        </button>
      </div>
    </div>

    <!-- Stats Summary -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <div class="premium-card bg-primary text-white overflow-hidden relative group">
        <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-white/10 rounded-full blur-3xl group-hover:scale-150 transition-transform duration-700"></div>
        <p class="text-[10px] font-bold text-white/60 uppercase tracking-widest mb-4">Total Pengeluaran Gaji</p>
        <h3 class="text-3xl font-display font-bold">{{ formatCurrency(totalPayout) }}</h3>
        <p class="text-[10px] text-white/40 mt-2 font-bold uppercase tracking-widest">Periode: {{ months.find(m => m.val === selectedMonth)?.name }} {{ selectedYear }}</p>
      </div>
      
      <div class="premium-card bg-white border border-slate-100 group">
        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-4">Status Pembayaran</p>
        <div class="flex items-center justify-between">
          <div>
            <h3 class="text-3xl font-display font-bold text-primary">
              {{ payrolls.filter(p => p.status === 'PAID').length }} / {{ payrolls.length }}
            </h3>
            <p class="text-[10px] text-slate-500 mt-1 font-bold uppercase tracking-widest">Telah Dibayar</p>
          </div>
          <div class="w-12 h-12 rounded-2xl bg-emerald-50 flex items-center justify-center text-emerald-600 text-xl">
            <i class="pi pi-verified"></i>
          </div>
        </div>
      </div>

      <div class="premium-card bg-white border border-slate-100 group">
        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-4">Potongan Absensi</p>
        <div class="flex items-center justify-between">
          <div>
            <h3 class="text-3xl font-display font-bold text-red-600">
              {{ formatCurrency(payrolls.reduce((acc, curr) => acc + parseFloat(curr.deduction), 0)) }}
            </h3>
            <p class="text-[10px] text-slate-500 mt-1 font-bold uppercase tracking-widest">Total Potongan</p>
          </div>
          <div class="w-12 h-12 rounded-2xl bg-red-50 flex items-center justify-center text-red-600 text-xl">
            <i class="pi pi-exclamation-triangle"></i>
          </div>
        </div>
      </div>
    </div>

    <!-- Payroll Table -->
    <div class="premium-card bg-white p-0 overflow-hidden shadow-2xl shadow-primary/5">

      <!-- Loading -->
      <div v-if="isLoading" class="divide-y divide-slate-50">
        <div v-for="i in 5" :key="i" class="px-4 py-4 animate-pulse h-16 bg-slate-50/10"></div>
      </div>

      <!-- Empty state -->
      <div v-else-if="payrolls.length === 0" class="px-6 py-20 text-center">
        <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-6">
          <i class="pi pi-wallet text-4xl text-slate-200"></i>
        </div>
        <h4 class="text-lg font-display font-bold text-slate-400">Belum ada data payroll</h4>
        <p class="text-sm text-slate-400 mt-1">Klik tombol Generate Payroll untuk memproses gaji bulan ini.</p>
      </div>

      <template v-else>
        <!-- Mobile cards -->
        <div class="md:hidden divide-y divide-slate-100">
          <div v-for="p in payrolls" :key="p.id" class="px-4 py-4">
            <div class="flex items-start justify-between gap-3">
              <div class="flex items-center gap-3 min-w-0">
                <div class="w-9 h-9 rounded-full bg-accent/10 border border-accent/20 flex items-center justify-center font-bold text-accent text-sm flex-shrink-0">
                  {{ p.employee.first_name[0] }}
                </div>
                <div class="min-w-0">
                  <p class="text-sm font-bold text-slate-900 truncate">{{ p.employee.first_name }} {{ p.employee.last_name }}</p>
                  <p class="text-[10px] text-slate-400 font-medium uppercase tracking-tight">{{ p.employee.position?.name }}</p>
                </div>
              </div>
              <span
                :class="p.status === 'PAID' ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : 'bg-amber-50 text-amber-600 border-amber-100'"
                class="text-[9px] font-bold px-2.5 py-1 rounded-full border tracking-widest uppercase flex-shrink-0"
              >{{ p.status }}</span>
            </div>
            <div class="mt-3 grid grid-cols-3 gap-2 text-center">
              <div class="bg-slate-50 rounded-xl p-2">
                <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">Pokok</p>
                <p class="text-xs font-bold text-slate-600 mt-0.5">{{ formatCurrency(p.base_salary) }}</p>
              </div>
              <div class="bg-red-50 rounded-xl p-2">
                <p class="text-[9px] font-bold text-red-400 uppercase tracking-widest">Potongan</p>
                <p class="text-xs font-bold text-red-500 mt-0.5">-{{ formatCurrency(p.deduction) }}</p>
              </div>
              <div class="bg-primary/5 rounded-xl p-2">
                <p class="text-[9px] font-bold text-primary/60 uppercase tracking-widest">Bersih</p>
                <p class="text-xs font-bold text-primary mt-0.5">{{ formatCurrency(p.net_salary) }}</p>
              </div>
            </div>
            <div class="mt-3 flex items-center justify-end gap-2">
              <button
                v-if="p.status === 'PENDING'"
                @click="markAsPaid(p.id)"
                class="h-8 px-3 rounded-lg bg-emerald-600 text-white text-[10px] font-bold uppercase tracking-widest hover:bg-emerald-700 transition-all"
              >Bayar</button>
              <button class="w-8 h-8 rounded-lg border border-slate-100 flex items-center justify-center text-slate-400 hover:text-primary hover:border-primary/30 transition-all" title="Print Slip">
                <i class="pi pi-print text-xs"></i>
              </button>
            </div>
          </div>
        </div>

        <!-- Desktop table -->
        <div class="hidden md:block overflow-x-auto">
          <table class="w-full text-left border-collapse">
            <thead>
              <tr class="bg-slate-50/50 border-b border-slate-100">
                <th class="px-6 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Karyawan</th>
                <th class="px-6 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest text-center">Gaji Pokok</th>
                <th class="px-6 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest text-center">Potongan</th>
                <th class="px-6 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest text-center">Gaji Bersih</th>
                <th class="px-6 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest text-center">Status</th>
                <th class="px-6 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest text-right">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
              <tr v-for="p in payrolls" :key="p.id" class="hover:bg-slate-50/30 transition-colors group">
                <td class="px-6 py-5">
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-accent/10 border border-accent/20 flex items-center justify-center font-bold text-accent">
                      {{ p.employee.first_name[0] }}
                    </div>
                    <div>
                      <p class="text-sm font-bold text-slate-900">{{ p.employee.first_name }} {{ p.employee.last_name }}</p>
                      <p class="text-[10px] text-slate-400 font-medium uppercase tracking-tight">{{ p.employee.position?.name }}</p>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-5 text-center text-xs font-medium text-slate-500">{{ formatCurrency(p.base_salary) }}</td>
                <td class="px-6 py-5 text-center text-xs font-bold text-red-500">-{{ formatCurrency(p.deduction) }}</td>
                <td class="px-6 py-5 text-center text-sm font-bold text-primary">{{ formatCurrency(p.net_salary) }}</td>
                <td class="px-6 py-5 text-center">
                  <span
                    :class="p.status === 'PAID' ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : 'bg-amber-50 text-amber-600 border-amber-100'"
                    class="text-[9px] font-bold px-2.5 py-1 rounded-full border tracking-widest uppercase"
                  >{{ p.status }}</span>
                </td>
                <td class="px-6 py-5 text-right">
                  <div class="flex items-center justify-end gap-2">
                    <button
                      v-if="p.status === 'PENDING'"
                      @click="markAsPaid(p.id)"
                      class="h-8 px-3 rounded-lg bg-emerald-600 text-white text-[10px] font-bold uppercase tracking-widest hover:bg-emerald-700 transition-all shadow-lg shadow-emerald-600/20"
                    >Bayar</button>
                    <button class="w-8 h-8 rounded-lg border border-slate-100 flex items-center justify-center text-slate-400 hover:text-primary hover:border-primary/30 transition-all" title="Print Slip">
                      <i class="pi pi-print text-xs"></i>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
    </div>
  </div>
</template>
