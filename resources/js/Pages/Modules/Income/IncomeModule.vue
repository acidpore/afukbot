<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { incomeApi } from '../../../api/income.api';
import type { Income } from '../../../types';
import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';

// ── State ──────────────────────────────────────────────────
const incomes    = ref<Income[]>([]);
const loading    = ref(false);
const submitting = ref(false);
const errorMsg   = ref('');
const successMsg = ref('');

const filterMonth  = ref(new Date().toISOString().slice(0, 7));
const filterSource = ref('');

// ── Form ───────────────────────────────────────────────────
const showForm = ref(false);
const form = ref({
    income_date: new Date().toISOString().slice(0, 10),
    source:      '',
    description: '',
    amount:      null as number | null,
    notes:       '',
});
const amountDisplay = ref('');

// ── Modal Edit ─────────────────────────────────────────────
const editModal = ref<{ open: boolean; income: Income | null }>({ open: false, income: null });
const editForm  = ref({
    income_date: '',
    source:      '',
    description: '',
    amount:      null as number | null,
    notes:       '',
});
const editAmountDisplay = ref('');
const editSubmitting    = ref(false);

// ── Computed ───────────────────────────────────────────────
const filtered = computed(() =>
    incomes.value.filter(i => {
        const matchMonth  = !filterMonth.value  || i.income_date.startsWith(filterMonth.value);
        const matchSource = !filterSource.value || i.source === filterSource.value;
        return matchMonth && matchSource;
    })
);

const totalFiltered = computed(() =>
    filtered.value.reduce((sum, i) => sum + i.amount, 0)
);

const summaryBySource = computed(() => {
    const map: Record<string, number> = {};
    for (const i of filtered.value) {
        map[i.source] = (map[i.source] ?? 0) + i.amount;
    }
    return Object.entries(map)
        .map(([source, total]) => ({ source, total }))
        .sort((a, b) => b.total - a.total);
});

const sourceOptions = computed(() => [...new Set(incomes.value.map(i => i.source))]);

const formValid = computed(() =>
    form.value.income_date &&
    form.value.source.trim() &&
    form.value.description.trim() &&
    Number(form.value.amount) > 0
);

// ── Helpers ────────────────────────────────────────────────
function fmt(n: number): string {
    return 'Rp ' + n.toLocaleString('id-ID');
}

function formatDate(dateStr: string): string {
    const d = new Date(dateStr + 'T00:00:00');
    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' });
}

function parseAmount(e: Event): number {
    const raw = (e.target as HTMLInputElement).value.replace(/\./g, '').replace(/\D/g, '');
    return parseInt(raw) || 0;
}

function onAmountInput(e: Event) {
    const num = parseAmount(e);
    form.value.amount = num;
    amountDisplay.value = num > 0 ? num.toLocaleString('id-ID') : '';
    (e.target as HTMLInputElement).value = amountDisplay.value;
}
function onAmountFocus(e: Event) {
    (e.target as HTMLInputElement).value = form.value.amount ? String(form.value.amount) : '';
}
function onAmountBlur(e: Event) {
    amountDisplay.value = Number(form.value.amount) > 0 ? Number(form.value.amount).toLocaleString('id-ID') : '';
    (e.target as HTMLInputElement).value = amountDisplay.value;
}

function onEditAmountInput(e: Event) {
    const num = parseAmount(e);
    editForm.value.amount = num;
    editAmountDisplay.value = num > 0 ? num.toLocaleString('id-ID') : '';
    (e.target as HTMLInputElement).value = editAmountDisplay.value;
}
function onEditAmountFocus(e: Event) {
    (e.target as HTMLInputElement).value = editForm.value.amount ? String(editForm.value.amount) : '';
}
function onEditAmountBlur(e: Event) {
    editAmountDisplay.value = Number(editForm.value.amount) > 0 ? Number(editForm.value.amount).toLocaleString('id-ID') : '';
    (e.target as HTMLInputElement).value = editAmountDisplay.value;
}

function resetForm() {
    form.value = {
        income_date: new Date().toISOString().slice(0, 10),
        source:      '',
        description: '',
        amount:      null,
        notes:       '',
    };
    amountDisplay.value = '';
    showForm.value = false;
}

function openEditModal(income: Income) {
    editForm.value = {
        income_date: income.income_date,
        source:      income.source,
        description: income.description,
        amount:      income.amount,
        notes:       income.notes ?? '',
    };
    editAmountDisplay.value = income.amount > 0 ? income.amount.toLocaleString('id-ID') : '';
    editModal.value = { open: true, income };
}

// ── API ────────────────────────────────────────────────────
async function loadIncomes() {
    loading.value = true;
    try {
        const res = await incomeApi.getAll();
        incomes.value = res.data.data;
    } finally {
        loading.value = false;
    }
}

async function submitIncome() {
    if (!formValid.value) return;
    submitting.value = true;
    errorMsg.value   = '';
    successMsg.value = '';
    try {
        const res = await incomeApi.create({ ...form.value });
        incomes.value.unshift(res.data.data);
        successMsg.value = 'Pemasukan berhasil dicatat.';
        resetForm();
    } catch (e: any) {
        errorMsg.value = e?.response?.data?.message || 'Terjadi kesalahan.';
    } finally {
        submitting.value = false;
    }
}

async function submitEdit() {
    if (!editForm.value.income_date || !editForm.value.source || !editForm.value.description || !Number(editForm.value.amount)) return;
    editSubmitting.value = true;
    try {
        const res = await incomeApi.update(editModal.value.income!.id, { ...editForm.value });
        const idx = incomes.value.findIndex(i => i.id === editModal.value.income!.id);
        if (idx !== -1) incomes.value[idx] = res.data.data;
        editModal.value.open = false;
    } catch (e: any) {
        alert(e?.response?.data?.message || 'Gagal menyimpan perubahan.');
    } finally {
        editSubmitting.value = false;
    }
}

async function deleteIncome(id: number) {
    if (!confirm('Hapus catatan pemasukan ini?')) return;
    try {
        await incomeApi.remove(id);
        incomes.value = incomes.value.filter(i => i.id !== id);
    } catch (e: any) {
        alert(e?.response?.data?.message || 'Gagal menghapus.');
    }
}

// ── Export PDF ─────────────────────────────────────────────
function exportPDF() {
    const data = filtered.value;
    if (data.length === 0) return;

    const doc  = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });
    const NAVY      = [29, 53, 87]    as [number, number, number];
    const WHITE     = [255, 255, 255] as [number, number, number];
    const LIGHT_BG  = [245, 247, 250] as [number, number, number];
    const GRAY_LINE = [200, 206, 214] as [number, number, number];
    const GREEN     = [21, 128, 61]   as [number, number, number];

    const LM = 20, RM = 20, pageW = 210;
    const CW = pageW - LM - RM;
    let y = 15;

    // Header
    doc.setFontSize(18); doc.setFont('times', 'bold'); doc.setTextColor(...NAVY);
    doc.text('PT Indo Pangan', pageW / 2, y, { align: 'center' });
    y += 5;
    doc.setFontSize(8); doc.setFont('times', 'normal'); doc.setTextColor(80, 80, 80);
    for (const line of ['Ruko Puncak CBD — Jalan Dukuh Kramat I No.36 Blok 7E PSR', 'Jajar Tunggal, Kec. Wiyung, Kota Surabaya, Jawa Timur']) {
        doc.text(line, pageW / 2, y, { align: 'center' }); y += 4;
    }
    y += 1;
    doc.setDrawColor(...NAVY); doc.setLineWidth(0.7); doc.line(LM, y, pageW - RM, y); y += 1;
    doc.setLineWidth(0.2); doc.line(LM, y, pageW - RM, y); y += 6;

    // Judul
    doc.setFontSize(13); doc.setFont('times', 'bold'); doc.setTextColor(...NAVY);
    doc.text('LAPORAN PEMASUKAN RUKO', pageW / 2, y, { align: 'center' });
    y += 5;

    // Info
    const periodLabel = filterMonth.value
        ? new Date(filterMonth.value + '-01').toLocaleDateString('id-ID', { month: 'long', year: 'numeric' })
        : 'Semua Periode';
    const printedAt = new Date().toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' });

    doc.setFontSize(8); doc.setFont('times', 'normal'); doc.setTextColor(80, 80, 80);
    doc.text(`Periode  : ${periodLabel}`, LM, y);
    doc.text(`Dicetak  : ${printedAt}`, pageW - RM, y, { align: 'right' });
    y += 4.5;
    doc.text(`Sumber   : ${filterSource.value || 'Semua'}`, LM, y);
    doc.text(`Total Transaksi : ${data.length}`, pageW - RM, y, { align: 'right' });
    y += 7;

    // Ringkasan per sumber
    doc.setFontSize(9); doc.setFont('times', 'bold'); doc.setTextColor(...NAVY);
    doc.text('Ringkasan per Sumber', LM, y); y += 2;

    autoTable(doc, {
        startY: y, margin: { left: LM, right: RM },
        head: [['Sumber', 'Jml. Transaksi', 'Total']],
        body: summaryBySource.value.map(r => [
            r.source,
            data.filter(i => i.source === r.source).length.toString(),
            'Rp ' + r.total.toLocaleString('id-ID'),
        ]),
        styles: { font: 'times', fontSize: 8.5, cellPadding: { top: 3, bottom: 3, left: 4, right: 4 }, lineColor: GRAY_LINE, lineWidth: 0.2 },
        headStyles: { fillColor: NAVY, textColor: WHITE, fontStyle: 'bold', halign: 'center' },
        columnStyles: {
            0: { halign: 'left',   cellWidth: CW * 0.45 },
            1: { halign: 'center', cellWidth: CW * 0.20 },
            2: { halign: 'right',  cellWidth: CW * 0.35 },
        },
        alternateRowStyles: { fillColor: LIGHT_BG },
        foot: [[
            { content: 'TOTAL', styles: { fontStyle: 'bold', halign: 'left' } },
            { content: data.length.toString(), styles: { fontStyle: 'bold', halign: 'center' } },
            { content: 'Rp ' + totalFiltered.value.toLocaleString('id-ID'), styles: { fontStyle: 'bold', halign: 'right', textColor: GREEN } },
        ]],
        footStyles: { fillColor: [235, 238, 243], fontStyle: 'bold', fontSize: 9 },
    });

    y = (doc as any).lastAutoTable.finalY + 8;

    // Detail
    doc.setFontSize(9); doc.setFont('times', 'bold'); doc.setTextColor(...NAVY);
    doc.text('Detail Transaksi', LM, y); y += 2;

    autoTable(doc, {
        startY: y, margin: { left: LM, right: RM },
        head: [['No', 'Tanggal', 'Sumber', 'Deskripsi', 'Jumlah']],
        body: data.map((i, idx) => [
            (idx + 1).toString(),
            formatDate(i.income_date),
            i.source,
            i.description + (i.notes ? `\n${i.notes}` : ''),
            'Rp ' + i.amount.toLocaleString('id-ID'),
        ]),
        styles: { font: 'times', fontSize: 8, cellPadding: { top: 3, bottom: 3, left: 3, right: 3 }, lineColor: GRAY_LINE, lineWidth: 0.2, overflow: 'linebreak' },
        headStyles: { fillColor: NAVY, textColor: WHITE, fontStyle: 'bold', halign: 'center', fontSize: 8 },
        columnStyles: {
            0: { halign: 'center', cellWidth: CW * 0.07 },
            1: { halign: 'center', cellWidth: CW * 0.18 },
            2: { halign: 'center', cellWidth: CW * 0.18 },
            3: { halign: 'left',   cellWidth: CW * 0.38 },
            4: { halign: 'right',  cellWidth: CW * 0.19 },
        },
        alternateRowStyles: { fillColor: LIGHT_BG },
        foot: [[
            { content: '', colSpan: 3, styles: { fontStyle: 'bold' } },
            { content: 'TOTAL', styles: { fontStyle: 'bold', halign: 'right' } },
            { content: 'Rp ' + totalFiltered.value.toLocaleString('id-ID'), styles: { fontStyle: 'bold', halign: 'right', textColor: GREEN } },
        ]],
        footStyles: { fillColor: [235, 238, 243], fontStyle: 'bold', fontSize: 9 },
        didParseCell(data) {
            if (data.section === 'body' && data.column.index === 4) {
                data.cell.styles.textColor = GREEN;
                data.cell.styles.fontStyle = 'bold';
            }
        },
    });

    // Tanda tangan
    const finalY = (doc as any).lastAutoTable.finalY + 10;
    const sigX   = LM + CW * 0.60;
    const sigW   = CW * 0.40;
    doc.setFontSize(8.5); doc.setFont('times', 'normal'); doc.setTextColor(60, 60, 60);
    doc.text('Surabaya, ' + printedAt, sigX + sigW / 2, finalY, { align: 'center' });
    doc.text('Dibuat oleh,', sigX + sigW / 2, finalY + 5, { align: 'center' });
    doc.setDrawColor(...GRAY_LINE); doc.setLineWidth(0.3);
    doc.line(sigX + 5, finalY + 22, sigX + sigW - 5, finalY + 22);
    doc.setFontSize(8);
    doc.text('( ....................................... )', sigX + sigW / 2, finalY + 27, { align: 'center' });

    // Nomor halaman
    const pageCount = (doc as any).internal.getNumberOfPages();
    for (let p = 1; p <= pageCount; p++) {
        doc.setPage(p);
        doc.setFontSize(7.5); doc.setFont('times', 'normal'); doc.setTextColor(150, 150, 150);
        doc.text(`Halaman ${p} dari ${pageCount}`, pageW / 2, 290, { align: 'center' });
    }

    doc.save(`Laporan-Pemasukan_${filterMonth.value || 'semua'}.pdf`);
}

// ── Import CSV ─────────────────────────────────────────────
const importModal   = ref(false);
const importFile    = ref<File | null>(null);
const importPreview = ref<string[][]>([]);
const importing     = ref(false);
const importResult  = ref<{ imported: number; skipped: string[] } | null>(null);

function downloadIncomeTemplate() {
    const content = 'tanggal,sumber,deskripsi,jumlah,catatan\n03/05/2026,Ronald,Setoran kas Ronald,2000000,\n';
    const blob = new Blob([content], { type: 'text/csv' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'template_pemasukan.csv';
    a.click();
}

function onImportFileChange(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (!file) return;
    importFile.value  = file;
    importResult.value = null;
    const reader = new FileReader();
    reader.onload = (ev) => {
        const lines = (ev.target?.result as string).trim().split('\n').slice(0, 6);
        importPreview.value = lines.map(l => l.split(',').map(c => c.trim().replace(/^"|"$/g, '')));
    };
    reader.readAsText(file);
}

async function submitImport() {
    if (!importFile.value) return;
    importing.value = true;
    importResult.value = null;
    try {
        const res = await incomeApi.import(importFile.value);
        importResult.value = res.data.data;
        if (res.data.data.imported > 0) {
            const incRes = await incomeApi.getAll();
            incomes.value = incRes.data.data;
        }
    } catch (e: any) {
        importResult.value = { imported: 0, skipped: [e?.response?.data?.message || 'Terjadi kesalahan.'] };
    } finally {
        importing.value = false;
    }
}

function closeImportModal() {
    importModal.value   = false;
    importFile.value    = null;
    importPreview.value = [];
    importResult.value  = null;
}

onMounted(loadIncomes);
</script>

<template>
    <div class="min-h-screen bg-slate-50 p-6">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Pemasukan</h1>
                <p class="text-sm text-slate-500 mt-1">Catat dan kelola pemasukan ruko</p>
            </div>
            <div class="flex items-center gap-2">
                <button
                    @click="importModal = true"
                    class="flex items-center gap-2 border border-slate-300 text-slate-600 text-sm font-bold px-4 py-2.5 rounded-xl hover:bg-slate-100 transition-colors"
                >
                    <i class="pi pi-upload text-xs"></i>
                    Import CSV
                </button>
                <button
                    @click="exportPDF"
                    :disabled="filtered.length === 0"
                    class="flex items-center gap-2 border border-emerald-600 text-emerald-700 text-sm font-bold px-4 py-2.5 rounded-xl hover:bg-emerald-50 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
                >
                    <i class="pi pi-file-pdf text-xs"></i>
                    Export PDF
                </button>
                <button
                    @click="showForm = !showForm"
                    class="flex items-center gap-2 bg-emerald-600 text-white text-sm font-bold px-5 py-2.5 rounded-xl hover:bg-emerald-700 transition-colors"
                >
                    <i :class="showForm ? 'pi pi-times' : 'pi pi-plus'" class="text-xs"></i>
                    {{ showForm ? 'Batal' : 'Catat Pemasukan' }}
                </button>
            </div>
        </div>

        <!-- Alert -->
        <div v-if="successMsg" class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm px-4 py-3 rounded-xl">{{ successMsg }}</div>
        <div v-if="errorMsg"   class="mb-4 bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-xl">{{ errorMsg }}</div>

        <!-- Form Tambah -->
        <div v-if="showForm" class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm mb-6">
            <h2 class="text-base font-bold text-slate-700 mb-4">Catat Pemasukan Baru</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1.5">Tanggal <span class="text-red-500">*</span></label>
                    <input v-model="form.income_date" type="date"
                        class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500"
                    />
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1.5">Sumber <span class="text-red-500">*</span></label>
                    <input v-model="form.source" type="text" placeholder="Contoh: Ronald"
                        class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500"
                    />
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1.5">Jumlah <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm pointer-events-none">Rp</span>
                        <input
                            :value="amountDisplay"
                            @input="onAmountInput" @focus="onAmountFocus" @blur="onAmountBlur"
                            type="text" inputmode="numeric" placeholder="0"
                            class="w-full border border-slate-300 rounded-xl pl-8 pr-4 py-2.5 text-sm text-right focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500"
                        />
                    </div>
                </div>
                <div class="lg:col-span-2">
                    <label class="block text-xs font-semibold text-slate-500 mb-1.5">Deskripsi <span class="text-red-500">*</span></label>
                    <input v-model="form.description" type="text" placeholder="Contoh: Setoran kas Ronald"
                        class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500"
                    />
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1.5">Catatan</label>
                    <input v-model="form.notes" type="text" placeholder="Opsional"
                        class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500"
                    />
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-5">
                <button @click="resetForm" class="px-5 py-2.5 rounded-xl text-sm font-bold border border-slate-300 text-slate-600 hover:bg-slate-100 transition-colors">Batal</button>
                <button
                    @click="submitIncome" :disabled="!formValid || submitting"
                    class="px-6 py-2.5 rounded-xl text-sm font-bold bg-emerald-600 text-white hover:bg-emerald-700 disabled:opacity-40 disabled:cursor-not-allowed transition-colors flex items-center gap-2"
                >
                    <i v-if="submitting" class="pi pi-spin pi-spinner text-xs"></i>
                    {{ submitting ? 'Menyimpan...' : 'Simpan' }}
                </button>
            </div>
        </div>

        <!-- Filter & Summary -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="md:col-span-2 bg-white border border-slate-200 rounded-2xl p-4 shadow-sm flex flex-wrap items-center gap-3">
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Bulan</label>
                    <input v-model="filterMonth" type="month"
                        class="border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500/30 focus:border-emerald-500"
                    />
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Sumber</label>
                    <select v-model="filterSource"
                        class="border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500/30 focus:border-emerald-500 bg-white"
                    >
                        <option value="">Semua</option>
                        <option v-for="src in sourceOptions" :key="src" :value="src">{{ src }}</option>
                    </select>
                </div>
                <div class="ml-auto text-right">
                    <p class="text-[10px] font-bold text-slate-400 uppercase">Total Masuk</p>
                    <p class="text-lg font-bold text-emerald-600">{{ fmt(totalFiltered) }}</p>
                    <p class="text-xs text-slate-400">{{ filtered.length }} transaksi</p>
                </div>
            </div>

            <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm overflow-y-auto max-h-48">
                <p class="text-[10px] font-bold text-slate-400 uppercase mb-2">Per Sumber</p>
                <div v-if="summaryBySource.length === 0" class="text-xs text-slate-400 text-center py-4">Tidak ada data</div>
                <div v-for="row in summaryBySource" :key="row.source" class="flex items-center justify-between py-1 border-b border-slate-50 last:border-0">
                    <span class="text-xs font-semibold text-slate-600">{{ row.source }}</span>
                    <span class="text-xs font-bold text-emerald-600">{{ fmt(row.total) }}</span>
                </div>
            </div>
        </div>

        <!-- Tabel -->
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
            <div v-if="loading" class="flex items-center justify-center py-16 text-slate-400 text-sm gap-2">
                <i class="pi pi-spin pi-spinner"></i> Memuat data...
            </div>
            <template v-else>
                <div v-if="filtered.length === 0" class="flex flex-col items-center justify-center py-16 text-slate-400">
                    <i class="pi pi-arrow-circle-down text-3xl mb-2"></i>
                    <p class="text-sm">Tidak ada catatan pemasukan</p>
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 border-b border-slate-200">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wide">Tanggal</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wide">Sumber</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wide">Deskripsi</th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase tracking-wide">Jumlah</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-slate-500 uppercase tracking-wide">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="income in filtered" :key="income.id" class="hover:bg-emerald-50/30 transition-colors">
                                <td class="px-4 py-3 text-slate-600 whitespace-nowrap">{{ formatDate(income.income_date) }}</td>
                                <td class="px-4 py-3">
                                    <span class="text-[11px] font-bold px-3.5 py-1.5 rounded-lg bg-emerald-50 text-emerald-700 whitespace-nowrap">
                                        {{ income.source }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-semibold text-slate-700">{{ income.description }}</div>
                                    <div v-if="income.notes" class="text-xs text-slate-400 mt-0.5">{{ income.notes }}</div>
                                </td>
                                <td class="px-4 py-3 text-right font-bold text-emerald-600 whitespace-nowrap">{{ fmt(income.amount) }}</td>
                                <td class="px-4 py-3 text-center">
                                    <div class="flex items-center justify-center gap-3">
                                        <button @click="openEditModal(income)" class="text-slate-500 hover:text-[#1D3557] transition-colors" title="Edit">
                                            <i class="pi pi-pencil"></i>
                                        </button>
                                        <button @click="deleteIncome(income.id)" class="text-red-400 hover:text-red-600 transition-colors" title="Hapus">
                                            <i class="pi pi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </div>

        <!-- Modal Edit -->
        <div v-if="editModal.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg">
                <div class="flex items-center justify-between p-5 border-b border-slate-100">
                    <h3 class="text-base font-bold text-slate-800">Edit Pemasukan</h3>
                    <button @click="editModal.open = false" class="text-slate-400 hover:text-slate-600 transition-colors">
                        <i class="pi pi-times text-lg"></i>
                    </button>
                </div>
                <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1.5">Tanggal <span class="text-red-500">*</span></label>
                        <input v-model="editForm.income_date" type="date"
                            class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1.5">Sumber <span class="text-red-500">*</span></label>
                        <input v-model="editForm.source" type="text"
                            class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1.5">Jumlah <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm pointer-events-none">Rp</span>
                            <input
                                :value="editAmountDisplay"
                                @input="onEditAmountInput" @focus="onEditAmountFocus" @blur="onEditAmountBlur"
                                type="text" inputmode="numeric" placeholder="0"
                                class="w-full border border-slate-300 rounded-xl pl-8 pr-4 py-2.5 text-sm text-right focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500"
                            />
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1.5">Catatan</label>
                        <input v-model="editForm.notes" type="text"
                            class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500"
                        />
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-slate-500 mb-1.5">Deskripsi <span class="text-red-500">*</span></label>
                        <input v-model="editForm.description" type="text"
                            class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500"
                        />
                    </div>
                </div>
                <div class="flex gap-3 p-5 border-t border-slate-100">
                    <button @click="editModal.open = false" class="flex-1 border border-slate-300 text-slate-600 text-sm font-bold py-2.5 rounded-xl hover:bg-slate-50 transition-colors">Batal</button>
                    <button
                        @click="submitEdit"
                        :disabled="editSubmitting || !editForm.income_date || !editForm.source || !editForm.description || !Number(editForm.amount)"
                        class="flex-1 bg-emerald-600 text-white text-sm font-bold py-2.5 rounded-xl hover:bg-emerald-700 disabled:opacity-40 transition-colors flex items-center justify-center gap-2"
                    >
                        <i v-if="editSubmitting" class="pi pi-spin pi-spinner text-xs"></i>
                        {{ editSubmitting ? 'Menyimpan...' : 'Simpan Perubahan' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal Import CSV -->
        <div v-if="importModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xl">
                <div class="flex items-center justify-between p-5 border-b border-slate-100">
                    <div>
                        <h3 class="text-base font-bold text-slate-800">Import Pemasukan dari CSV</h3>
                        <p class="text-xs text-slate-400 mt-0.5">Format: tanggal, sumber, deskripsi, jumlah, catatan (opsional)</p>
                    </div>
                    <button @click="closeImportModal" class="text-slate-400 hover:text-slate-600 transition-colors">
                        <i class="pi pi-times text-lg"></i>
                    </button>
                </div>

                <div class="p-5 space-y-4">
                    <div class="flex items-center justify-between bg-slate-50 rounded-xl px-4 py-3">
                        <div>
                            <p class="text-sm font-semibold text-slate-700">Belum punya template?</p>
                            <p class="text-xs text-slate-400">Download file CSV contoh, isi di Excel, lalu upload.</p>
                        </div>
                        <button @click="downloadIncomeTemplate"
                            class="flex items-center gap-1.5 text-xs font-bold text-emerald-700 border border-emerald-600 px-3 py-1.5 rounded-lg hover:bg-emerald-50 transition-colors"
                        >
                            <i class="pi pi-download text-xs"></i> Template
                        </button>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1.5">Pilih File CSV</label>
                        <input
                            type="file" accept=".csv,.txt"
                            @change="onImportFileChange"
                            class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-emerald-600 file:text-white hover:file:bg-emerald-700 cursor-pointer"
                        />
                    </div>

                    <div v-if="importPreview.length > 0">
                        <p class="text-xs font-semibold text-slate-500 mb-1.5">Preview (5 baris pertama)</p>
                        <div class="overflow-x-auto border border-slate-200 rounded-xl">
                            <table class="w-full text-xs">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th v-for="(col, i) in importPreview[0]" :key="i"
                                            class="px-3 py-2 text-left font-bold text-slate-500 uppercase tracking-wide whitespace-nowrap">
                                            {{ col }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="(row, ri) in importPreview.slice(1)" :key="ri" class="hover:bg-slate-50">
                                        <td v-for="(cell, ci) in row" :key="ci" class="px-3 py-2 text-slate-600 whitespace-nowrap">{{ cell }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div v-if="importResult">
                        <div v-if="importResult.imported > 0" class="bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm px-4 py-3 rounded-xl">
                            {{ importResult.imported }} data berhasil diimport.
                        </div>
                        <div v-if="importResult.skipped.length > 0" class="mt-2 bg-amber-50 border border-amber-200 rounded-xl px-4 py-3">
                            <p class="text-xs font-bold text-amber-700 mb-1">{{ importResult.skipped.length }} baris dilewati:</p>
                            <ul class="text-xs text-amber-600 space-y-0.5 max-h-24 overflow-y-auto">
                                <li v-for="(msg, i) in importResult.skipped" :key="i">{{ msg }}</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="flex gap-3 p-5 border-t border-slate-100">
                    <button @click="closeImportModal" class="flex-1 border border-slate-300 text-slate-600 text-sm font-bold py-2.5 rounded-xl hover:bg-slate-50 transition-colors">
                        {{ importResult?.imported ? 'Tutup' : 'Batal' }}
                    </button>
                    <button
                        v-if="!importResult?.imported"
                        @click="submitImport"
                        :disabled="!importFile || importing"
                        class="flex-1 bg-emerald-600 text-white text-sm font-bold py-2.5 rounded-xl hover:bg-emerald-700 disabled:opacity-40 transition-colors flex items-center justify-center gap-2"
                    >
                        <i v-if="importing" class="pi pi-spin pi-spinner text-xs"></i>
                        {{ importing ? 'Mengimport...' : 'Import Sekarang' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
