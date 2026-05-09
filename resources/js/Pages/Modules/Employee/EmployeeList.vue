<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { employeeApi } from '@/api/employee.api';
import type { Employee } from '@/types';

const employees = ref<Employee[]>([]);
const departments = ref<any[]>([]);
const positions = ref<any[]>([]);
const isLoading = ref(true);
const isModalOpen = ref(false);
const isDetailModalOpen = ref(false);
const modalMode = ref<'create' | 'edit'>('create');
const selectedEmployee = ref<Employee | null>(null);

const form = ref({
  id: null as number | null,
  employee_id: '',
  first_name: '',
  last_name: '',
  email: '',
  phone: '',
  position_id: '',
  department_id: '',
  hire_date: new Date().toISOString().split('T')[0],
  base_salary: 0,
  status: 'ACTIVE',
  documents: [] as File[]
});

const fetchEmployees = async () => {
  isLoading.value = true;
  try {
    const res = await employeeApi.getEmployees();
    employees.value = res.data.data;
  } catch (error) {
    console.error('Gagal mengambil data karyawan:', error);
  } finally {
    isLoading.value = false;
  }
};

const fetchMetadata = async () => {
  try {
    const [deptRes, posRes] = await Promise.all([
      employeeApi.getDepartments(),
      employeeApi.getPositions()
    ]);
    departments.value = deptRes.data.data;
    positions.value = posRes.data.data;
  } catch (error) {
    console.error('Gagal mengambil metadata:', error);
  }
};

onMounted(() => {
  fetchEmployees();
  fetchMetadata();
});

const openCreateModal = () => {
  modalMode.value = 'create';
  form.value = {
    id: null,
    employee_id: `MBG-${Math.floor(1000 + Math.random() * 9000)}`,
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    position_id: '',
    department_id: '',
    hire_date: new Date().toISOString().split('T')[0],
    base_salary: 0,
    status: 'ACTIVE',
    documents: []
  };
  isModalOpen.value = true;
};

const openEditModal = (emp: Employee) => {
  modalMode.value = 'edit';
  form.value = {
    id: emp.id,
    employee_id: emp.employee_id,
    first_name: emp.first_name,
    last_name: emp.last_name,
    email: emp.email,
    phone: emp.phone,
    position_id: emp.position_id.toString(),
    department_id: emp.department_id.toString(),
    hire_date: emp.hire_date,
    base_salary: emp.base_salary,
    status: emp.status,
    documents: []
  };
  isModalOpen.value = true;
};

const openDetailModal = async (emp: Employee) => {
  try {
    const res = await employeeApi.getEmployee(emp.id);
    selectedEmployee.value = res.data.data;
    isDetailModalOpen.value = true;
  } catch (error) {
    alert('Gagal mengambil detail karyawan');
  }
};

const handleFileChange = (e: any) => {
  const files = e.target.files;
  if (files) {
    form.value.documents = Array.from(files);
  }
};

const handleSave = async () => {
  try {
    if (modalMode.value === 'create') {
      await employeeApi.createEmployee(form.value);
    } else {
      if (form.value.id) {
        await employeeApi.updateEmployee(form.value.id, form.value);
      }
    }
    isModalOpen.value = false;
    fetchEmployees();
  } catch (error) {
    alert('Gagal menyimpan data karyawan');
  }
};

const handleDelete = async (id: number) => {
  if (!confirm('Apakah Anda yakin ingin menghapus karyawan ini?')) return;
  try {
    await employeeApi.deleteEmployee(id);
    fetchEmployees();
  } catch (error) {
    alert('Gagal menghapus data');
  }
};

const getStatusClass = (status: string) => {
  return status === 'ACTIVE' 
    ? 'bg-emerald-50 text-emerald-600 border-emerald-100' 
    : 'bg-slate-50 text-slate-500 border-slate-100';
};
</script>

<template>
  <div class="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-700">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
      <div>
        <h2 class="text-3xl font-display font-bold text-primary">Data Karyawan</h2>
        <p class="text-slate-500 text-sm mt-1">Manajemen database staff, posisi, dan departemen.</p>
      </div>
      <button @click="openCreateModal" class="btn-primary">
        <i class="pi pi-plus"></i>
        Tambah Karyawan
      </button>
    </div>

    <!-- Table Card -->
    <div class="premium-card bg-white p-0 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-slate-50/50 border-b border-slate-100">
              <th class="px-6 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Karyawan</th>
              <th class="px-6 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Posisi / Dept</th>
              <th class="px-6 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Gaji Pokok</th>
              <th class="px-6 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Status</th>
              <th class="px-6 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50">
            <tr v-if="isLoading" v-for="i in 3" :key="i" class="animate-pulse">
              <td colspan="5" class="px-6 py-8 h-20 bg-slate-50/20"></td>
            </tr>
            
            <tr v-else v-for="emp in employees" :key="emp.id" class="hover:bg-slate-50/50 transition-colors group">
              <td class="px-6 py-5">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 rounded-full bg-accent/10 border border-accent/20 flex items-center justify-center font-bold text-accent">
                    {{ emp.first_name[0] }}
                  </div>
                  <div>
                    <p class="text-sm font-bold text-slate-900">{{ emp.first_name }} {{ emp.last_name }}</p>
                    <p class="text-[10px] text-slate-400 font-medium">{{ emp.employee_id }}</p>
                  </div>
                </div>
              </td>
              <td class="px-6 py-5">
                <p class="text-xs font-bold text-slate-700">{{ emp.position?.name || 'Staff' }}</p>
                <p class="text-[10px] text-slate-400 uppercase tracking-widest">{{ emp.department?.name || 'Umum' }}</p>
              </td>
              <td class="px-6 py-5">
                <p class="text-sm font-bold text-primary">
                  Rp {{ new Intl.NumberFormat('id-ID').format(emp.base_salary) }}
                </p>
              </td>
              <td class="px-6 py-5">
                <span :class="getStatusClass(emp.status)" class="text-[9px] font-bold px-2.5 py-1 rounded-full border tracking-widest uppercase">
                  {{ emp.status }}
                </span>
              </td>
              <td class="px-6 py-5">
                <div class="flex items-center gap-2">
                  <button @click="openDetailModal(emp)" class="w-8 h-8 rounded-lg border border-slate-100 flex items-center justify-center text-slate-400 hover:text-emerald-500 hover:border-emerald-100 transition-all">
                    <i class="pi pi-eye text-xs"></i>
                  </button>
                  <button @click="openEditModal(emp)" class="w-8 h-8 rounded-lg border border-slate-100 flex items-center justify-center text-slate-400 hover:text-primary hover:border-primary/30 transition-all">
                    <i class="pi pi-pencil text-xs"></i>
                  </button>
                  <button @click="handleDelete(emp.id)" class="w-8 h-8 rounded-lg border border-slate-100 flex items-center justify-center text-slate-400 hover:text-red-500 hover:border-red-100 transition-all">
                    <i class="pi pi-trash text-xs"></i>
                  </button>
                </div>
              </td>
            </tr>

            <tr v-if="!isLoading && employees.length === 0">
              <td colspan="5" class="px-6 py-20 text-center">
                <div class="text-4xl mb-4 text-slate-200">
                  <i class="pi pi-inbox"></i>
                </div>
                <p class="text-sm text-slate-400 font-medium">Belum ada data karyawan.</p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal Form -->
    <div v-if="isModalOpen" class="fixed inset-0 z-[100] flex items-center justify-center p-6">
      <div @click="isModalOpen = false" class="absolute inset-0 bg-primary/40 backdrop-blur-sm"></div>
      <div class="relative w-full max-w-2xl bg-white rounded-3xl shadow-2xl overflow-hidden animate-in zoom-in-95 duration-300 max-h-[90vh] flex flex-col">
        <div class="p-8 border-b border-slate-100 flex justify-between items-center bg-slate-50/50 flex-shrink-0">
          <div>
            <h3 class="text-xl font-display font-bold text-primary">{{ modalMode === 'create' ? 'Tambah' : 'Edit' }} Karyawan</h3>
            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-1">Lengkapi data informasi staff</p>
          </div>
          <button @click="isModalOpen = false" class="w-10 h-10 rounded-full hover:bg-white flex items-center justify-center text-slate-400 transition-colors">
            <i class="pi pi-times"></i>
          </button>
        </div>

        <form @submit.prevent="handleSave" class="p-8 space-y-6 overflow-y-auto flex-1 custom-scrollbar">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">ID Karyawan</label>
              <input v-model="form.employee_id" type="text" disabled class="w-full bg-slate-100 border-none rounded-xl px-4 py-3 text-sm text-slate-500 cursor-not-allowed">
            </div>
            <div>
              <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Status Pekerja</label>
              <select v-model="form.status" class="w-full bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-accent/20 focus:border-accent outline-none transition-all">
                <option value="ACTIVE">ACTIVE</option>
                <option value="INACTIVE">INACTIVE</option>
              </select>
            </div>
            <div>
              <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Nama Depan</label>
              <input v-model="form.first_name" type="text" required class="w-full bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-accent/20 focus:border-accent outline-none transition-all">
            </div>
            <div>
              <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Nama Belakang</label>
              <input v-model="form.last_name" type="text" required class="w-full bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-accent/20 focus:border-accent outline-none transition-all">
            </div>
            <div>
              <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Email</label>
              <input v-model="form.email" type="email" required class="w-full bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-accent/20 focus:border-accent outline-none transition-all">
            </div>
            <div>
              <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Telepon</label>
              <input v-model="form.phone" type="text" class="w-full bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-accent/20 focus:border-accent outline-none transition-all">
            </div>
            <div>
              <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Departemen</label>
              <select v-model="form.department_id" required class="w-full bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-accent/20 focus:border-accent outline-none transition-all">
                <option value="" disabled>Pilih Departemen</option>
                <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Posisi</label>
              <select v-model="form.position_id" required class="w-full bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-accent/20 focus:border-accent outline-none transition-all">
                <option value="" disabled>Pilih Posisi</option>
                <option v-for="p in positions" :key="p.id" :value="p.id">{{ p.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Gaji Pokok</label>
              <input v-model.number="form.base_salary" type="number" required class="w-full bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-accent/20 focus:border-accent outline-none transition-all">
            </div>
            <div>
              <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Tanggal Bergabung</label>
              <input v-model="form.hire_date" type="date" required class="w-full bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-accent/20 focus:border-accent outline-none transition-all">
            </div>
            <div class="md:col-span-2">
              <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Dokumen Pendukung (KTP/SIM/Lainnya)</label>
              <div class="relative group">
                <input @change="handleFileChange" type="file" multiple class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                <div class="w-full bg-slate-50 border-2 border-dashed border-slate-200 rounded-2xl p-6 flex flex-col items-center justify-center group-hover:border-accent/50 transition-all">
                  <i class="pi pi-upload text-2xl text-slate-300 mb-2 group-hover:text-accent"></i>
                  <p class="text-xs text-slate-400 font-medium">Klik atau seret file ke sini</p>
                  <p class="text-[10px] text-slate-300 mt-1 uppercase">PDF, JPG, PNG (Maks 2MB)</p>
                </div>
              </div>
              <div v-if="form.documents.length > 0" class="mt-3 flex flex-wrap gap-2">
                <div v-for="(file, idx) in form.documents" :key="idx" class="bg-accent/5 border border-accent/10 px-3 py-1.5 rounded-lg flex items-center gap-2">
                  <i class="pi pi-file text-[10px] text-accent"></i>
                  <span class="text-[10px] font-bold text-accent truncate max-w-[150px]">{{ file.name }}</span>
                </div>
              </div>
            </div>
          </div>

          <div class="pt-6 flex justify-end gap-3 flex-shrink-0 bg-white border-t border-slate-50 sticky bottom-0">
            <button @click="isModalOpen = false" type="button" class="px-6 py-3 rounded-xl text-sm font-bold text-slate-400 hover:text-slate-600 hover:bg-slate-50 transition-all">Batal</button>
            <button type="submit" class="btn-primary px-10 py-3 shadow-xl shadow-primary/20">Simpan Data</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Detail Modal -->
    <div v-if="isDetailModalOpen && selectedEmployee" class="fixed inset-0 z-[100] flex items-center justify-center p-6">
      <div @click="isDetailModalOpen = false" class="absolute inset-0 bg-primary/40 backdrop-blur-sm"></div>
      <div class="relative w-full max-w-4xl bg-[#faf9f6] rounded-[2rem] shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-500">
        <div class="flex h-[600px]">
          <!-- Left Profile Section -->
          <div class="w-1/3 bg-white border-r border-slate-100 p-8 flex flex-col items-center text-center">
            <div class="w-32 h-32 rounded-3xl bg-primary/5 border border-primary/10 flex items-center justify-center text-5xl font-display font-bold text-primary mb-6 shadow-xl shadow-primary/5">
              {{ selectedEmployee.first_name[0] }}
            </div>
            <h3 class="text-2xl font-display font-bold text-primary">{{ selectedEmployee.first_name }} {{ selectedEmployee.last_name }}</h3>
            <p class="text-xs font-bold text-accent uppercase tracking-widest mt-2">{{ selectedEmployee.position?.name }}</p>
            <div class="mt-8 w-full space-y-4">
              <div class="p-4 bg-slate-50 rounded-2xl text-left">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">ID Karyawan</p>
                <p class="text-sm font-bold text-slate-700 mt-1">{{ selectedEmployee.employee_id }}</p>
              </div>
              <div class="p-4 bg-slate-50 rounded-2xl text-left">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Departemen</p>
                <p class="text-sm font-bold text-slate-700 mt-1">{{ selectedEmployee.department?.name }}</p>
              </div>
              <div class="p-4 bg-slate-50 rounded-2xl text-left">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Gaji Pokok</p>
                <p class="text-sm font-bold text-emerald-700 mt-1">Rp {{ new Intl.NumberFormat('id-ID').format(selectedEmployee.base_salary) }}</p>
              </div>
            </div>
          </div>

          <!-- Right Content Section -->
          <div class="flex-1 p-10 overflow-y-auto">
            <div class="flex justify-between items-center mb-8">
              <h4 class="font-display text-xl font-bold text-primary">Informasi Lengkap</h4>
              <button @click="isDetailModalOpen = false" class="w-10 h-10 rounded-full hover:bg-white flex items-center justify-center text-slate-400 transition-colors">
                <i class="pi pi-times"></i>
              </button>
            </div>

            <div class="grid grid-cols-2 gap-8 mb-10">
              <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Email Address</p>
                <p class="text-sm font-medium text-slate-700">{{ selectedEmployee.email }}</p>
              </div>
              <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Phone Number</p>
                <p class="text-sm font-medium text-slate-700">{{ selectedEmployee.phone || '-' }}</p>
              </div>
              <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Hire Date</p>
                <p class="text-sm font-medium text-slate-700">{{ selectedEmployee.hire_date }}</p>
              </div>
              <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Status</p>
                <span :class="getStatusClass(selectedEmployee.status)" class="text-[9px] font-bold px-3 py-1 rounded-full border tracking-widest uppercase">
                  {{ selectedEmployee.status }}
                </span>
              </div>
            </div>

            <!-- Documents Section -->
            <div>
              <div class="flex items-center gap-4 mb-4">
                <h4 class="font-display text-lg font-bold text-primary">Dokumen Pendukung</h4>
                <div class="h-[1px] flex-1 bg-slate-100"></div>
              </div>
              
              <div v-if="selectedEmployee.documents?.length" class="grid grid-cols-2 gap-4">
                <div v-for="doc in selectedEmployee.documents" :key="doc.id" class="premium-card p-4 flex items-center gap-4 bg-white border-slate-100 hover:border-accent/30 transition-all cursor-pointer">
                  <div class="w-10 h-10 bg-accent/10 rounded-xl flex items-center justify-center text-accent">
                    <i class="pi pi-file-pdf text-xl"></i>
                  </div>
                  <div class="flex-1 min-w-0">
                    <p class="text-[10px] font-bold text-slate-900 truncate uppercase">{{ doc.document_type }}</p>
                    <p class="text-[9px] text-slate-400 font-medium">Lihat Dokumen</p>
                  </div>
                  <i class="pi pi-external-link text-slate-300 text-xs"></i>
                </div>
              </div>
              <div v-else class="text-center py-12 bg-slate-50 rounded-3xl border border-dashed border-slate-200">
                <i class="pi pi-folder-open text-3xl text-slate-200 mb-2"></i>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Tidak ada dokumen dilampirkan</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
