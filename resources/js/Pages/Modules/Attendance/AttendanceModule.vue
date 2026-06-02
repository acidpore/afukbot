<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import { attendanceApi } from '@/api/attendance.api';

const selectedDate = ref(new Date().toISOString().split('T')[0]);
const employees = ref<any[]>([]);
const isLoading = ref(false);
const isSaving = ref(false);

const fetchAttendance = async () => {
  isLoading.value = true;
  console.log('Fetching attendance for date:', selectedDate.value);
  try {
    const res = await attendanceApi.getAttendance(selectedDate.value);
    console.log('Attendance API Response:', res.data);
    
    if (res.data && res.data.data) {
      employees.value = res.data.data.map(emp => {
        // Attendance data is nested in the 'attendances' relation
        const att = emp.attendances && emp.attendances.length > 0 ? emp.attendances[0] : null;
        return {
          id: emp.id,
          first_name: emp.first_name,
          last_name: emp.last_name,
          employee_id: emp.employee_id,
          status: att ? att.status : 'PRESENT',
          check_in: att && att.check_in ? att.check_in.substring(0, 5) : '08:00',
          check_out: att && att.check_out ? att.check_out.substring(0, 5) : '17:00',
          notes: att ? att.notes : ''
        };
      });
    }
  } catch (error) {
    console.error('Gagal mengambil data absensi:', error);
  } finally {
    isLoading.value = false;
  }
};

onMounted(fetchAttendance);
watch(selectedDate, fetchAttendance);

const saveAttendance = async () => {
  isSaving.value = true;
  try {
    await attendanceApi.saveAttendance({
      date: selectedDate.value,
      attendances: employees.value.map(emp => ({
        employee_id: emp.id,
        status: emp.status,
        check_in: emp.check_in,
        check_out: emp.check_out,
        notes: emp.notes
      }))
    });
    alert('Absensi berhasil disimpan');
    fetchAttendance();
  } catch (error) {
    alert('Gagal menyimpan absensi');
  } finally {
    isSaving.value = false;
  }
};

const getStatusColor = (status: string) => {
  switch (status) {
    case 'PRESENT': return 'bg-emerald-50 text-emerald-600 border-emerald-100';
    case 'ABSENT': return 'bg-red-50 text-red-600 border-red-100';
    case 'LEAVE': return 'bg-amber-50 text-amber-600 border-amber-100';
    case 'SICK': return 'bg-blue-50 text-blue-600 border-blue-100';
    default: return 'bg-slate-50 text-slate-500 border-slate-100';
  }
};
</script>

<template>
  <div class="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-700">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
      <div>
        <h2 class="text-3xl font-display font-bold text-primary">Absensi Harian</h2>
        <p class="text-slate-500 text-sm mt-1">Rekap kehadiran karyawan MBG per hari.</p>
      </div>
      <div class="flex flex-col sm:flex-row sm:items-center gap-3">
        <input
          v-model="selectedDate"
          type="date"
          class="bg-white border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-bold text-primary outline-none focus:ring-2 focus:ring-accent/20 focus:border-accent transition-all shadow-sm w-full sm:w-auto"
        >
        <button
          @click="saveAttendance"
          :disabled="isSaving"
          class="btn-primary shadow-xl shadow-primary/20 disabled:opacity-50 justify-center"
        >
          <i class="pi pi-check-circle"></i>
          {{ isSaving ? 'Menyimpan...' : 'Simpan Absensi' }}
        </button>
      </div>
    </div>

    <!-- Attendance Table -->
    <div class="premium-card bg-white p-0 overflow-hidden shadow-2xl shadow-primary/5">

      <!-- Loading skeleton -->
      <div v-if="isLoading" class="divide-y divide-slate-50">
        <div v-for="i in 5" :key="i" class="px-4 py-4 animate-pulse h-16 bg-slate-50/10"></div>
      </div>

      <!-- Empty state -->
      <div v-else-if="employees.length === 0" class="px-6 py-20 text-center">
        <i class="pi pi-users text-4xl text-slate-200 mb-4 block"></i>
        <p class="text-sm text-slate-400 font-medium">Tidak ada karyawan aktif untuk dilakukan absensi.</p>
      </div>

      <template v-else>
        <!-- Mobile cards -->
        <div class="md:hidden divide-y divide-slate-100">
          <div v-for="emp in employees" :key="emp.id" class="px-4 py-4 space-y-3">
            <div class="flex items-center justify-between gap-3">
              <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-full bg-primary/5 border border-primary/10 flex items-center justify-center font-bold text-primary text-sm flex-shrink-0">
                  {{ emp.first_name[0] }}
                </div>
                <div>
                  <p class="text-sm font-bold text-slate-900">{{ emp.first_name }} {{ emp.last_name }}</p>
                  <p class="text-[10px] text-slate-400 font-medium">{{ emp.employee_id }}</p>
                </div>
              </div>
              <select
                v-model="emp.status"
                :class="getStatusColor(emp.status)"
                class="text-[10px] font-bold px-3 py-1.5 rounded-full border outline-none transition-all cursor-pointer appearance-none text-center"
              >
                <option value="PRESENT">HADIR</option>
                <option value="ABSENT">ALPA</option>
                <option value="SICK">SAKIT</option>
                <option value="LEAVE">IZIN</option>
              </select>
            </div>
            <div class="grid grid-cols-2 gap-2">
              <div>
                <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest block mb-1">Masuk</label>
                <input
                  v-model="emp.check_in"
                  type="time"
                  :disabled="emp.status !== 'PRESENT'"
                  class="w-full bg-slate-50 border border-slate-100 rounded-lg px-2 py-1.5 text-xs font-bold text-slate-700 outline-none focus:ring-1 focus:ring-accent/30 disabled:opacity-30 transition-all"
                >
              </div>
              <div>
                <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest block mb-1">Pulang</label>
                <input
                  v-model="emp.check_out"
                  type="time"
                  :disabled="emp.status !== 'PRESENT'"
                  class="w-full bg-slate-50 border border-slate-100 rounded-lg px-2 py-1.5 text-xs font-bold text-slate-700 outline-none focus:ring-1 focus:ring-accent/30 disabled:opacity-30 transition-all"
                >
              </div>
            </div>
            <input
              v-model="emp.notes"
              type="text"
              placeholder="Keterangan..."
              class="w-full bg-slate-50 border border-slate-100 rounded-lg px-3 py-1.5 text-xs font-medium text-slate-600 outline-none focus:ring-1 focus:ring-accent/30 transition-all"
            >
          </div>
        </div>

        <!-- Desktop table -->
        <div class="hidden md:block overflow-x-auto">
          <table class="w-full text-left border-collapse">
            <thead>
              <tr class="bg-slate-50/50 border-b border-slate-100">
                <th class="px-6 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Karyawan</th>
                <th class="px-6 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest text-center">Status</th>
                <th class="px-6 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest text-center">Jam Masuk</th>
                <th class="px-6 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest text-center">Jam Pulang</th>
                <th class="px-6 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Catatan</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
              <tr v-for="emp in employees" :key="emp.id" class="hover:bg-slate-50/30 transition-colors group">
                <td class="px-6 py-5">
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-primary/5 border border-primary/10 flex items-center justify-center font-bold text-primary">
                      {{ emp.first_name[0] }}
                    </div>
                    <div>
                      <p class="text-sm font-bold text-slate-900">{{ emp.first_name }} {{ emp.last_name }}</p>
                      <p class="text-[10px] text-slate-400 font-medium">{{ emp.employee_id }}</p>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-5 text-center">
                  <select
                    v-model="emp.status"
                    :class="getStatusColor(emp.status)"
                    class="text-[10px] font-bold px-3 py-1.5 rounded-full border outline-none transition-all cursor-pointer appearance-none text-center min-w-[100px]"
                  >
                    <option value="PRESENT">HADIR</option>
                    <option value="ABSENT">ALPA</option>
                    <option value="SICK">SAKIT</option>
                    <option value="LEAVE">IZIN</option>
                  </select>
                </td>
                <td class="px-6 py-5 text-center">
                  <input
                    v-model="emp.check_in"
                    type="time"
                    :disabled="emp.status !== 'PRESENT'"
                    class="bg-slate-50 border border-slate-100 rounded-lg px-2 py-1.5 text-xs font-bold text-slate-700 outline-none focus:ring-1 focus:ring-accent/30 disabled:opacity-30 transition-all"
                  >
                </td>
                <td class="px-6 py-5 text-center">
                  <input
                    v-model="emp.check_out"
                    type="time"
                    :disabled="emp.status !== 'PRESENT'"
                    class="bg-slate-50 border border-slate-100 rounded-lg px-2 py-1.5 text-xs font-bold text-slate-700 outline-none focus:ring-1 focus:ring-accent/30 disabled:opacity-30 transition-all"
                  >
                </td>
                <td class="px-6 py-5">
                  <input
                    v-model="emp.notes"
                    type="text"
                    placeholder="Keterangan..."
                    class="w-full bg-slate-50 border border-slate-100 rounded-lg px-3 py-1.5 text-xs font-medium text-slate-600 outline-none focus:ring-1 focus:ring-accent/30 transition-all"
                  >
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
    </div>
  </div>
</template>
