<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { expenseApi } from '../../../api/expense.api';
import { incomeApi } from '../../../api/income.api';
import type { Expense, Income } from '../../../types';
import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';

const CATEGORIES = ['Go MBG', 'Makan', 'Afuk', 'Belanja', 'Lainnya'];

// ── State ──────────────────────────────────────────────────
const expenses   = ref<Expense[]>([]);
const incomes    = ref<Income[]>([]);
const loading    = ref(false);
const submitting = ref(false);
const errorMsg   = ref('');
const successMsg = ref('');

const filterMonth = ref(new Date().toISOString().slice(0, 7));
const filterCategory = ref('');

// ── Form (tambah) ──────────────────────────────────────────
const showForm = ref(false);
const form = ref({
    expense_date: new Date().toISOString().slice(0, 10),
    category:     '',
    description:  '',
    amount:       null as number | null,
    paid_by:      '',
    notes:        '',
});
const customCategory     = ref('');
const amountDisplay      = ref('');
const receiptFile        = ref<File | null>(null);

// ── Modal Edit ─────────────────────────────────────────────
const editModal = ref<{ open: boolean; expense: Expense | null }>({ open: false, expense: null });
const editForm  = ref({
    expense_date: '',
    category:     '',
    description:  '',
    amount:       null as number | null,
    paid_by:      '',
    notes:        '',
});
const editCustomCategory  = ref('');
const editAmountDisplay   = ref('');
const editSubmitting      = ref(false);
const editReceiptFile     = ref<File | null>(null);
const receiptPreviewModal = ref<string | null>(null);

// ── Computed ───────────────────────────────────────────────
const filtered = computed(() => {
    return expenses.value.filter(e => {
        const matchMonth    = !filterMonth.value    || e.expense_date.startsWith(filterMonth.value);
        const matchCategory = !filterCategory.value || e.category === filterCategory.value;
        return matchMonth && matchCategory;
    });
});

const totalFiltered = computed(() =>
    filtered.value.reduce((sum, e) => sum + e.amount, 0)
);

const summaryByCategory = computed(() => {
    const map: Record<string, number> = {};
    for (const e of filtered.value) {
        map[e.category] = (map[e.category] ?? 0) + e.amount;
    }
    return Object.entries(map)
        .map(([category, total]) => ({ category, total }))
        .sort((a, b) => b.total - a.total);
});

const filteredIncomes = computed(() =>
    incomes.value.filter(i => !filterMonth.value || i.income_date.startsWith(filterMonth.value))
);

const totalIncome = computed(() =>
    filteredIncomes.value.reduce((sum, i) => sum + i.amount, 0)
);

const saldo = computed(() => totalIncome.value - totalFiltered.value);

const summaryByIncomeSource = computed(() => {
    const map: Record<string, number> = {};
    for (const i of filteredIncomes.value) {
        map[i.source] = (map[i.source] ?? 0) + i.amount;
    }
    return Object.entries(map)
        .map(([source, total]) => ({ source, total }))
        .sort((a, b) => b.total - a.total);
});

const formValid = computed(() =>
    form.value.expense_date &&
    form.value.category &&
    (form.value.category !== 'Lainnya' || customCategory.value.trim()) &&
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

function parseAmountInput(e: Event): number {
    const raw = (e.target as HTMLInputElement).value.replace(/\./g, '').replace(/\D/g, '');
    return parseInt(raw) || 0;
}

function onAmountInput(e: Event) {
    const num = parseAmountInput(e);
    form.value.amount = num;
    amountDisplay.value = num > 0 ? num.toLocaleString('id-ID') : '';
    (e.target as HTMLInputElement).value = amountDisplay.value;
}

function onAmountFocus(e: Event) {
    (e.target as HTMLInputElement).value = form.value.amount ? String(form.value.amount) : '';
}

function onAmountBlur(e: Event) {
    const num = Number(form.value.amount) || 0;
    amountDisplay.value = num > 0 ? num.toLocaleString('id-ID') : '';
    (e.target as HTMLInputElement).value = amountDisplay.value;
}

function onEditAmountInput(e: Event) {
    const num = parseAmountInput(e);
    editForm.value.amount = num;
    editAmountDisplay.value = num > 0 ? num.toLocaleString('id-ID') : '';
    (e.target as HTMLInputElement).value = editAmountDisplay.value;
}

function onEditAmountFocus(e: Event) {
    (e.target as HTMLInputElement).value = editForm.value.amount ? String(editForm.value.amount) : '';
}

function onEditAmountBlur(e: Event) {
    const num = Number(editForm.value.amount) || 0;
    editAmountDisplay.value = num > 0 ? num.toLocaleString('id-ID') : '';
    (e.target as HTMLInputElement).value = editAmountDisplay.value;
}

function resetForm() {
    form.value = {
        expense_date: new Date().toISOString().slice(0, 10),
        category:     '',
        description:  '',
        amount:       null,
        paid_by:      '',
        notes:        '',
    };
    amountDisplay.value  = '';
    customCategory.value = '';
    receiptFile.value    = null;
    showForm.value = false;
}

function openEditModal(expense: Expense) {
    const isFixed = CATEGORIES.slice(0, -1).includes(expense.category);
    editForm.value = {
        expense_date: expense.expense_date,
        category:     isFixed ? expense.category : 'Lainnya',
        description:  expense.description,
        amount:       expense.amount,
        paid_by:      expense.paid_by ?? '',
        notes:        expense.notes ?? '',
    };
    editCustomCategory.value = isFixed ? '' : expense.category;
    editAmountDisplay.value  = expense.amount > 0 ? expense.amount.toLocaleString('id-ID') : '';
    editReceiptFile.value    = null;
    editModal.value = { open: true, expense };
}

function receiptUrl(path: string): string {
    return `/storage/${path}`;
}

function isPdf(path: string): boolean {
    return path.toLowerCase().endsWith('.pdf');
}

// ── API ────────────────────────────────────────────────────
async function loadExpenses() {
    loading.value = true;
    try {
        const [expRes, incRes] = await Promise.all([expenseApi.getAll(), incomeApi.getAll()]);
        expenses.value = expRes.data.data;
        incomes.value  = incRes.data.data;
    } finally {
        loading.value = false;
    }
}

async function submitExpense() {
    if (!formValid.value) return;
    submitting.value = true;
    errorMsg.value   = '';
    successMsg.value = '';
    try {
        const payload = { ...form.value };
        if (payload.category === 'Lainnya') payload.category = customCategory.value.trim();
        const res = await expenseApi.create(payload);
        let expense = res.data.data;
        if (receiptFile.value) {
            const upRes = await expenseApi.uploadReceipt(expense.id, receiptFile.value);
            expense = upRes.data.data;
        }
        expenses.value.unshift(expense);
        successMsg.value = 'Pengeluaran berhasil dicatat.';
        resetForm();
    } catch (e: any) {
        errorMsg.value = e?.response?.data?.message || 'Terjadi kesalahan.';
    } finally {
        submitting.value = false;
    }
}

async function submitEdit() {
    if (!editForm.value.expense_date || !editForm.value.category || !editForm.value.description || !Number(editForm.value.amount)) return;
    editSubmitting.value = true;
    try {
        const payload = { ...editForm.value };
        if (payload.category === 'Lainnya') payload.category = editCustomCategory.value.trim();
        const res = await expenseApi.update(editModal.value.expense!.id, payload);
        let updated = res.data.data;
        if (editReceiptFile.value) {
            const upRes = await expenseApi.uploadReceipt(updated.id, editReceiptFile.value);
            updated = upRes.data.data;
        }
        const idx = expenses.value.findIndex(e => e.id === editModal.value.expense!.id);
        if (idx !== -1) expenses.value[idx] = updated;
        editModal.value.open = false;
    } catch (e: any) {
        alert(e?.response?.data?.message || 'Gagal menyimpan perubahan.');
    } finally {
        editSubmitting.value = false;
    }
}

async function removeReceipt(expense: Expense) {
    if (!confirm('Hapus bukti struk ini?')) return;
    try {
        const res = await expenseApi.deleteReceipt(expense.id);
        const idx = expenses.value.findIndex(e => e.id === expense.id);
        if (idx !== -1) expenses.value[idx] = res.data.data;
        if (editModal.value.expense?.id === expense.id) {
            editModal.value.expense = res.data.data;
        }
    } catch (e: any) {
        alert(e?.response?.data?.message || 'Gagal menghapus struk.');
    }
}

async function deleteExpense(id: number) {
    if (!confirm('Hapus catatan pengeluaran ini?')) return;
    try {
        await expenseApi.remove(id);
        expenses.value = expenses.value.filter(e => e.id !== id);
    } catch (e: any) {
        alert(e?.response?.data?.message || 'Gagal menghapus.');
    }
}

// ── Import CSV ─────────────────────────────────────────────
const importModal    = ref(false);
const importFile     = ref<File | null>(null);
const importPreview  = ref<string[][]>([]);
const importing      = ref(false);
const importResult   = ref<{ imported: number; skipped: string[] } | null>(null);

const EXPENSE_TEMPLATE_HEADERS = 'tanggal,kategori,deskripsi,jumlah,dibayar_oleh,catatan';
const EXPENSE_TEMPLATE_EXAMPLE = '03/05/2026,Makan,Makan siang,59000,,';

function downloadExpenseTemplate() {
    const content = `${EXPENSE_TEMPLATE_HEADERS}\n${EXPENSE_TEMPLATE_EXAMPLE}\n`;
    const blob = new Blob([content], { type: 'text/csv' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'template_pengeluaran.csv';
    a.click();
}

function onImportFileChange(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (!file) return;
    importFile.value  = file;
    importResult.value = null;

    const reader = new FileReader();
    reader.onload = (ev) => {
        const text  = ev.target?.result as string;
        const lines = text.trim().split('\n').slice(0, 6);
        importPreview.value = lines.map(l => l.split(',').map(c => c.trim().replace(/^"|"$/g, '')));
    };
    reader.readAsText(file);
}

async function submitImport() {
    if (!importFile.value) return;
    importing.value = true;
    importResult.value = null;
    try {
        const res = await expenseApi.import(importFile.value);
        importResult.value = res.data.data;
        if (res.data.data.imported > 0) {
            const expRes = await expenseApi.getAll();
            expenses.value = expRes.data.data;
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

// ── Export PDF ─────────────────────────────────────────────
function exportPDF() {
    const expData = filtered.value;
    const incData = filteredIncomes.value;
    if (expData.length === 0 && incData.length === 0) return;

    const doc       = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });
    const NAVY      = [29, 53, 87]    as [number, number, number];
    const WHITE     = [255, 255, 255] as [number, number, number];
    const LIGHT_BG  = [245, 247, 250] as [number, number, number];
    const GRAY_LINE = [200, 206, 214] as [number, number, number];
    const RED       = [185, 28, 28]   as [number, number, number];
    const GREEN     = [21, 128, 61]   as [number, number, number];
    const NAVY_DARK = [15, 30, 55]    as [number, number, number];

    const LM = 20, RM = 20, pageW = 210;
    const CW = pageW - LM - RM;
    let y = 15;

    const periodLabel = filterMonth.value
        ? new Date(filterMonth.value + '-01').toLocaleDateString('id-ID', { month: 'long', year: 'numeric' })
        : 'Semua Periode';
    const printedAt = new Date().toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' });

    // ── Header ────────────────────────────────────────────
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

    // ── Judul ─────────────────────────────────────────────
    doc.setFontSize(13); doc.setFont('times', 'bold'); doc.setTextColor(...NAVY);
    doc.text('LAPORAN KEUANGAN RUKO', pageW / 2, y, { align: 'center' });
    y += 5;

    doc.setFontSize(8); doc.setFont('times', 'normal'); doc.setTextColor(80, 80, 80);
    doc.text(`Periode  : ${periodLabel}`, LM, y);
    doc.text(`Dicetak  : ${printedAt}`, pageW - RM, y, { align: 'right' });
    y += 4.5;
    doc.text(`Kategori : ${filterCategory.value || 'Semua Kategori'}`, LM, y);
    y += 8;

    // ── Ringkasan Eksekutif ────────────────────────────────
    doc.setFontSize(9); doc.setFont('times', 'bold'); doc.setTextColor(...NAVY);
    doc.text('Ringkasan Keuangan', LM, y); y += 2;

    const saldoVal = saldo.value;
    autoTable(doc, {
        startY: y, margin: { left: LM, right: RM },
        body: [
            ['Total Pemasukan',  'Rp ' + totalIncome.value.toLocaleString('id-ID')],
            ['Total Pengeluaran','Rp ' + totalFiltered.value.toLocaleString('id-ID')],
            ['Saldo Bersih',     (saldoVal >= 0 ? '' : '-') + 'Rp ' + Math.abs(saldoVal).toLocaleString('id-ID')],
        ],
        styles: { font: 'times', fontSize: 9, cellPadding: { top: 4, bottom: 4, left: 6, right: 6 }, lineColor: GRAY_LINE, lineWidth: 0.2 },
        columnStyles: {
            0: { halign: 'left',  cellWidth: CW * 0.55, fontStyle: 'bold' },
            1: { halign: 'right', cellWidth: CW * 0.45 },
        },
        didParseCell(hookData) {
            if (hookData.section === 'body') {
                if (hookData.row.index === 0) hookData.cell.styles.textColor = GREEN;
                if (hookData.row.index === 1) hookData.cell.styles.textColor = RED;
                if (hookData.row.index === 2) {
                    hookData.cell.styles.fontStyle = 'bold';
                    hookData.cell.styles.fontSize  = 10;
                    hookData.cell.styles.textColor = saldoVal >= 0 ? GREEN : RED;
                    hookData.cell.styles.fillColor = saldoVal >= 0 ? [236, 253, 245] : [254, 242, 242];
                }
            }
        },
    });

    y = (doc as any).lastAutoTable.finalY + 8;

    // ── Ringkasan Pemasukan per Sumber ─────────────────────
    doc.setFontSize(9); doc.setFont('times', 'bold'); doc.setTextColor(...NAVY);
    doc.text('Pemasukan per Sumber', LM, y); y += 2;

    autoTable(doc, {
        startY: y, margin: { left: LM, right: RM },
        head: [['Sumber', 'Jml. Transaksi', 'Total']],
        body: summaryByIncomeSource.value.map(r => [
            r.source,
            incData.filter(i => i.source === r.source).length.toString(),
            'Rp ' + r.total.toLocaleString('id-ID'),
        ]),
        styles: { font: 'times', fontSize: 8.5, cellPadding: { top: 3, bottom: 3, left: 4, right: 4 }, lineColor: GRAY_LINE, lineWidth: 0.2 },
        headStyles: { fillColor: [22, 101, 52], textColor: WHITE, fontStyle: 'bold', halign: 'center' },
        columnStyles: {
            0: { halign: 'left',   cellWidth: CW * 0.45 },
            1: { halign: 'center', cellWidth: CW * 0.20 },
            2: { halign: 'right',  cellWidth: CW * 0.35 },
        },
        alternateRowStyles: { fillColor: LIGHT_BG },
        foot: [[
            { content: 'TOTAL', styles: { fontStyle: 'bold', halign: 'left' } },
            { content: incData.length.toString(), styles: { fontStyle: 'bold', halign: 'center' } },
            { content: 'Rp ' + totalIncome.value.toLocaleString('id-ID'), styles: { fontStyle: 'bold', halign: 'right', textColor: GREEN } },
        ]],
        footStyles: { fillColor: [235, 238, 243], fontStyle: 'bold', fontSize: 9, textColor: [30, 30, 30] },
    });

    y = (doc as any).lastAutoTable.finalY + 8;

    // ── Ringkasan Pengeluaran per Kategori ─────────────────
    doc.setFontSize(9); doc.setFont('times', 'bold'); doc.setTextColor(...NAVY);
    doc.text('Pengeluaran per Kategori', LM, y); y += 2;

    autoTable(doc, {
        startY: y, margin: { left: LM, right: RM },
        head: [['Kategori', 'Jml. Transaksi', 'Total']],
        body: summaryByCategory.value.map(r => [
            r.category,
            expData.filter(e => e.category === r.category).length.toString(),
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
            { content: expData.length.toString(), styles: { fontStyle: 'bold', halign: 'center' } },
            { content: 'Rp ' + totalFiltered.value.toLocaleString('id-ID'), styles: { fontStyle: 'bold', halign: 'right', textColor: RED } },
        ]],
        footStyles: { fillColor: [235, 238, 243], fontStyle: 'bold', fontSize: 9, textColor: [30, 30, 30] },
    });

    y = (doc as any).lastAutoTable.finalY + 8;

    // ── Detail Pemasukan ───────────────────────────────────
    doc.setFontSize(9); doc.setFont('times', 'bold'); doc.setTextColor(...NAVY);
    doc.text('Detail Pemasukan', LM, y); y += 2;

    autoTable(doc, {
        startY: y, margin: { left: LM, right: RM },
        head: [['No', 'Tanggal', 'Sumber', 'Deskripsi', 'Jumlah']],
        body: incData.map((i, idx) => [
            (idx + 1).toString(),
            formatDate(i.income_date),
            i.source,
            i.description + (i.notes ? `\n${i.notes}` : ''),
            'Rp ' + i.amount.toLocaleString('id-ID'),
        ]),
        styles: { font: 'times', fontSize: 8, cellPadding: { top: 3, bottom: 3, left: 3, right: 3 }, lineColor: GRAY_LINE, lineWidth: 0.2, overflow: 'linebreak' },
        headStyles: { fillColor: [22, 101, 52], textColor: WHITE, fontStyle: 'bold', halign: 'center', fontSize: 8 },
        columnStyles: {
            0: { halign: 'center', cellWidth: CW * 0.07 },
            1: { halign: 'center', cellWidth: CW * 0.18 },
            2: { halign: 'center', cellWidth: CW * 0.18 },
            3: { halign: 'left',   cellWidth: CW * 0.38 },
            4: { halign: 'right',  cellWidth: CW * 0.19 },
        },
        alternateRowStyles: { fillColor: LIGHT_BG },
        foot: [[
            { content: '', colSpan: 3, styles: {} },
            { content: 'TOTAL', styles: { fontStyle: 'bold', halign: 'right' } },
            { content: 'Rp ' + totalIncome.value.toLocaleString('id-ID'), styles: { fontStyle: 'bold', halign: 'right', textColor: GREEN } },
        ]],
        footStyles: { fillColor: [235, 238, 243], fontStyle: 'bold', fontSize: 9, textColor: [30, 30, 30] },
        showFoot: 'lastPage',
        didParseCell(hookData) {
            if (hookData.section === 'body' && hookData.column.index === 4) {
                hookData.cell.styles.textColor = GREEN;
                hookData.cell.styles.fontStyle = 'bold';
            }
        },
    });

    y = (doc as any).lastAutoTable.finalY + 8;

    // ── Detail Pengeluaran ─────────────────────────────────
    doc.setFontSize(9); doc.setFont('times', 'bold'); doc.setTextColor(...NAVY);
    doc.text('Detail Pengeluaran', LM, y); y += 2;

    autoTable(doc, {
        startY: y, margin: { left: LM, right: RM },
        head: [['No', 'Tanggal', 'Kategori', 'Deskripsi', 'Dibayar oleh', 'Jumlah']],
        body: expData.map((e, i) => [
            (i + 1).toString(),
            formatDate(e.expense_date),
            e.category,
            e.description + (e.notes ? `\n${e.notes}` : ''),
            e.paid_by || '-',
            'Rp ' + e.amount.toLocaleString('id-ID'),
        ]),
        styles: { font: 'times', fontSize: 8, cellPadding: { top: 3, bottom: 3, left: 3, right: 3 }, lineColor: GRAY_LINE, lineWidth: 0.2, overflow: 'linebreak' },
        headStyles: { fillColor: NAVY, textColor: WHITE, fontStyle: 'bold', halign: 'center', fontSize: 8 },
        columnStyles: {
            0: { halign: 'center', cellWidth: CW * 0.06 },
            1: { halign: 'center', cellWidth: CW * 0.16 },
            2: { halign: 'center', cellWidth: CW * 0.14 },
            3: { halign: 'left',   cellWidth: CW * 0.34 },
            4: { halign: 'center', cellWidth: CW * 0.14 },
            5: { halign: 'right',  cellWidth: CW * 0.16 },
        },
        alternateRowStyles: { fillColor: LIGHT_BG },
        foot: [[
            { content: '', colSpan: 4, styles: {} },
            { content: 'TOTAL', styles: { fontStyle: 'bold', halign: 'right' } },
            { content: 'Rp ' + totalFiltered.value.toLocaleString('id-ID'), styles: { fontStyle: 'bold', halign: 'right', textColor: RED } },
        ]],
        footStyles: { fillColor: [235, 238, 243], fontStyle: 'bold', fontSize: 9, textColor: [30, 30, 30] },
        showFoot: 'lastPage',
        didParseCell(hookData) {
            if (hookData.section === 'body' && hookData.column.index === 5) {
                hookData.cell.styles.textColor = RED;
                hookData.cell.styles.fontStyle = 'bold';
            }
        },
    });

    // ── Saldo penutup ──────────────────────────────────────
    const closeY = (doc as any).lastAutoTable.finalY + 6;
    doc.setFillColor(...(saldoVal >= 0 ? [22, 101, 52] : [153, 27, 27]));
    doc.roundedRect(LM, closeY, CW, 11, 2, 2, 'F');
    doc.setFontSize(10); doc.setFont('times', 'bold'); doc.setTextColor(...WHITE);
    doc.text('Saldo Bersih Periode Ini', LM + 5, closeY + 7);
    doc.text(
        (saldoVal >= 0 ? '' : '-') + 'Rp ' + Math.abs(saldoVal).toLocaleString('id-ID'),
        pageW - RM - 5, closeY + 7, { align: 'right' }
    );

    // ── Tanda tangan ───────────────────────────────────────
    const sigStartY = closeY + 18;
    const sigX = LM + CW * 0.60, sigW = CW * 0.40;
    doc.setFontSize(8.5); doc.setFont('times', 'normal'); doc.setTextColor(60, 60, 60);
    doc.text('Surabaya, ' + printedAt, sigX + sigW / 2, sigStartY, { align: 'center' });
    doc.text('Dibuat oleh,', sigX + sigW / 2, sigStartY + 5, { align: 'center' });
    doc.setDrawColor(...GRAY_LINE); doc.setLineWidth(0.3);
    doc.line(sigX + 5, sigStartY + 22, sigX + sigW - 5, sigStartY + 22);
    doc.setFontSize(8);
    doc.text('( ....................................... )', sigX + sigW / 2, sigStartY + 27, { align: 'center' });

    // ── Nomor halaman ──────────────────────────────────────
    const pageCount = (doc as any).internal.getNumberOfPages();
    for (let i = 1; i <= pageCount; i++) {
        doc.setPage(i);
        doc.setFontSize(7.5); doc.setFont('times', 'normal'); doc.setTextColor(150, 150, 150);
        doc.text(`Halaman ${i} dari ${pageCount}`, pageW / 2, 290, { align: 'center' });
    }

    doc.save(`Laporan-Keuangan-Ruko_${filterMonth.value || 'semua'}.pdf`);
}

onMounted(loadExpenses);
</script>

<template>
    <div class="min-h-screen bg-slate-50 p-4 sm:p-6">
        <div class="mb-5 flex items-start justify-between gap-3">
            <div>
                <h1 class="text-xl sm:text-2xl font-bold text-slate-800">Pengeluaran</h1>
                <p class="text-sm text-slate-500 mt-1">Catat dan kelola pengeluaran ruko</p>
            </div>
            <div class="flex items-center gap-2 flex-wrap justify-end">
                <button
                    @click="exportPDF"
                    :disabled="filtered.length === 0"
                    class="flex items-center gap-1.5 border border-[#1D3557] text-[#1D3557] text-xs sm:text-sm font-bold px-3 py-2 sm:px-4 sm:py-2.5 rounded-xl hover:bg-[#1D3557]/5 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
                >
                    <i class="pi pi-file-pdf text-xs"></i>
                    <span class="hidden sm:inline">Export PDF</span>
                </button>
                <button
                    @click="importModal = true"
                    class="flex items-center gap-1.5 border border-slate-300 text-slate-600 text-xs sm:text-sm font-bold px-3 py-2 sm:px-4 sm:py-2.5 rounded-xl hover:bg-slate-100 transition-colors"
                >
                    <i class="pi pi-upload text-xs"></i>
                    <span class="hidden sm:inline">Import CSV</span>
                </button>
                <button
                    @click="showForm = !showForm"
                    class="flex items-center gap-1.5 bg-[#1D3557] text-white text-xs sm:text-sm font-bold px-3 py-2 sm:px-5 sm:py-2.5 rounded-xl hover:bg-[#162840] transition-colors"
                >
                    <i :class="showForm ? 'pi pi-times' : 'pi pi-plus'" class="text-xs"></i>
                    <span class="hidden sm:inline">{{ showForm ? 'Batal' : 'Catat Pengeluaran' }}</span>
                    <span class="sm:hidden">{{ showForm ? 'Batal' : 'Tambah' }}</span>
                </button>
            </div>
        </div>

        <!-- Alert -->
        <div v-if="successMsg" class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm px-4 py-3 rounded-xl">
            {{ successMsg }}
        </div>
        <div v-if="errorMsg" class="mb-4 bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-xl">
            {{ errorMsg }}
        </div>

        <!-- Form Tambah -->
        <div v-if="showForm" class="bg-white border border-slate-200 rounded-2xl p-4 sm:p-6 shadow-sm mb-5">
            <h2 class="text-base font-bold text-slate-700 mb-4">Catat Pengeluaran Baru</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1.5">Tanggal <span class="text-red-500">*</span></label>
                    <input
                        v-model="form.expense_date"
                        type="date"
                        class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1D3557]/30 focus:border-[#1D3557]"
                    />
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1.5">Kategori <span class="text-red-500">*</span></label>
                    <select
                        v-model="form.category"
                        class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1D3557]/30 focus:border-[#1D3557] bg-white"
                    >
                        <option value="" disabled>Pilih kategori</option>
                        <option v-for="cat in CATEGORIES" :key="cat" :value="cat">{{ cat }}</option>
                    </select>
                    <input
                        v-if="form.category === 'Lainnya'"
                        v-model="customCategory"
                        type="text"
                        placeholder="Tulis kategori..."
                        class="w-full mt-2 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1D3557]/30 focus:border-[#1D3557]"
                    />
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1.5">Jumlah <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm pointer-events-none">Rp</span>
                        <input
                            :value="amountDisplay"
                            @input="onAmountInput"
                            @focus="onAmountFocus"
                            @blur="onAmountBlur"
                            type="text"
                            inputmode="numeric"
                            placeholder="0"
                            class="w-full border border-slate-300 rounded-xl pl-8 pr-4 py-2.5 text-sm text-right focus:outline-none focus:ring-2 focus:ring-[#1D3557]/30 focus:border-[#1D3557]"
                        />
                    </div>
                </div>
                <div class="lg:col-span-2">
                    <label class="block text-xs font-semibold text-slate-500 mb-1.5">Deskripsi <span class="text-red-500">*</span></label>
                    <input
                        v-model="form.description"
                        type="text"
                        placeholder="Contoh: Bayar listrik bulan Mei"
                        class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1D3557]/30 focus:border-[#1D3557]"
                    />
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1.5">Dibayar oleh</label>
                    <input
                        v-model="form.paid_by"
                        type="text"
                        placeholder="Nama / kas ruko"
                        class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1D3557]/30 focus:border-[#1D3557]"
                    />
                </div>
                <div class="lg:col-span-3">
                    <label class="block text-xs font-semibold text-slate-500 mb-1.5">Catatan</label>
                    <input
                        v-model="form.notes"
                        type="text"
                        placeholder="Catatan tambahan (opsional)"
                        class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1D3557]/30 focus:border-[#1D3557]"
                    />
                </div>
                <div class="lg:col-span-3">
                    <label class="block text-xs font-semibold text-slate-500 mb-1.5">Bukti Struk / Nota</label>
                    <input
                        type="file"
                        accept="image/jpeg,image/png,image/webp,application/pdf"
                        @change="(e) => { receiptFile = (e.target as HTMLInputElement).files?.[0] ?? null }"
                        class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200 cursor-pointer"
                    />
                    <p class="text-[11px] text-slate-400 mt-1 pl-1">JPG, PNG, WebP, atau PDF. Maks. 5 MB.</p>
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-5">
                <button @click="resetForm" class="px-5 py-2.5 rounded-xl text-sm font-bold border border-slate-300 text-slate-600 hover:bg-slate-100 transition-colors">
                    Batal
                </button>
                <button
                    @click="submitExpense"
                    :disabled="!formValid || submitting"
                    class="px-6 py-2.5 rounded-xl text-sm font-bold bg-[#1D3557] text-white hover:bg-[#162840] disabled:opacity-40 disabled:cursor-not-allowed transition-colors flex items-center gap-2"
                >
                    <i v-if="submitting" class="pi pi-spin pi-spinner text-xs"></i>
                    {{ submitting ? 'Menyimpan...' : 'Simpan' }}
                </button>
            </div>
        </div>

        <!-- Filter -->
        <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm mb-4 grid grid-cols-2 sm:flex sm:flex-wrap items-end gap-3">
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Bulan</label>
                <input
                    v-model="filterMonth"
                    type="month"
                    class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#1D3557]/30 focus:border-[#1D3557] bg-white shadow-sm cursor-pointer"
                />
            </div>
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Kategori</label>
                <select
                    v-model="filterCategory"
                    class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#1D3557]/30 focus:border-[#1D3557] bg-white shadow-sm cursor-pointer"
                >
                    <option value="">Semua</option>
                    <option v-for="cat in [...new Set(expenses.map(e => e.category))]" :key="cat" :value="cat">{{ cat }}</option>
                </select>
            </div>
        </div>

        <!-- Ringkasan Keuangan -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
            <!-- Total Pemasukan -->
            <div class="bg-white border border-emerald-200 rounded-2xl p-4 shadow-sm">
                <p class="text-[10px] font-bold text-emerald-500 uppercase mb-1">Total Pemasukan</p>
                <p class="text-xl font-bold text-emerald-600">{{ fmt(totalIncome) }}</p>
                <p class="text-xs text-slate-400 mt-0.5">{{ filteredIncomes.length }} transaksi</p>
                <div class="mt-2 pt-2 border-t border-slate-100 space-y-1">
                    <div v-for="row in summaryByIncomeSource" :key="row.source" class="flex justify-between">
                        <span class="text-xs text-slate-500">{{ row.source }}</span>
                        <span class="text-xs font-semibold text-emerald-600">{{ fmt(row.total) }}</span>
                    </div>
                </div>
            </div>

            <!-- Total Pengeluaran -->
            <div class="bg-white border border-red-200 rounded-2xl p-4 shadow-sm">
                <p class="text-[10px] font-bold text-red-400 uppercase mb-1">Total Pengeluaran</p>
                <p class="text-xl font-bold text-red-600">{{ fmt(totalFiltered) }}</p>
                <p class="text-xs text-slate-400 mt-0.5">{{ filtered.length }} transaksi</p>
                <div class="mt-2 pt-2 border-t border-slate-100 space-y-1">
                    <div v-for="row in summaryByCategory" :key="row.category" class="flex justify-between">
                        <span class="text-xs text-slate-500">{{ row.category }}</span>
                        <span class="text-xs font-semibold text-red-600">{{ fmt(row.total) }}</span>
                    </div>
                </div>
            </div>

            <!-- Saldo Bersih -->
            <div
                class="rounded-2xl p-4 shadow-sm border"
                :class="saldo >= 0 ? 'bg-emerald-600 border-emerald-700' : 'bg-red-100/80 border-red-200'"
            >
                <p :class="saldo >= 0 ? 'text-white/70' : 'text-red-400'" class="text-[10px] font-bold uppercase mb-1">Saldo Bersih</p>
                <p :class="saldo >= 0 ? 'text-white' : 'text-red-600'" class="text-xl font-bold">
                    {{ (saldo < 0 ? '-' : '') + fmt(Math.abs(saldo)) }}
                </p>
                <p :class="saldo >= 0 ? 'text-white/60' : 'text-red-400/80'" class="text-xs mt-0.5">Pemasukan - Pengeluaran</p>
                <div :class="saldo >= 0 ? 'border-white/20' : 'border-red-200'" class="mt-3 pt-3 border-t space-y-1">
                    <div class="flex justify-between text-xs" :class="saldo >= 0 ? 'text-white/80' : 'text-red-500'">
                        <span>Masuk</span><span class="font-semibold">{{ fmt(totalIncome) }}</span>
                    </div>
                    <div class="flex justify-between text-xs" :class="saldo >= 0 ? 'text-white/80' : 'text-red-500'">
                        <span>Keluar</span><span class="font-semibold">{{ fmt(totalFiltered) }}</span>
                    </div>
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
                    <i class="pi pi-wallet text-3xl mb-2"></i>
                    <p class="text-sm">Tidak ada catatan pengeluaran</p>
                </div>
                <div v-else>
                    <!-- Mobile cards -->
                    <div class="md:hidden divide-y divide-slate-100">
                        <div v-for="expense in filtered" :key="expense.id" class="p-4 hover:bg-slate-50/60 transition-colors">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2 mb-1 flex-wrap">
                                        <span class="text-[11px] font-bold px-2.5 py-1 rounded-lg bg-slate-100 text-slate-600 whitespace-nowrap">{{ expense.category }}</span>
                                        <span class="text-xs text-slate-400">{{ formatDate(expense.expense_date) }}</span>
                                    </div>
                                    <p class="font-semibold text-slate-700 text-sm">{{ expense.description }}</p>
                                    <p v-if="expense.notes" class="text-xs text-slate-400 mt-0.5">{{ expense.notes }}</p>
                                    <p v-if="expense.paid_by" class="text-xs text-slate-400 mt-0.5">Oleh: {{ expense.paid_by }}</p>
                                    <a
                                        v-if="expense.receipt_path"
                                        :href="receiptUrl(expense.receipt_path)"
                                        target="_blank"
                                        class="inline-flex items-center gap-1 text-xs text-[#1D3557] font-semibold mt-1 hover:underline"
                                    >
                                        <i :class="isPdf(expense.receipt_path) ? 'pi pi-file-pdf text-red-500' : 'pi pi-image text-sky-500'" class="text-xs"></i>
                                        Lihat struk
                                    </a>
                                </div>
                                <div class="flex-shrink-0 flex flex-col items-end gap-2">
                                    <span class="font-bold text-red-600 text-sm whitespace-nowrap">{{ fmt(expense.amount) }}</span>
                                    <div class="flex items-center gap-3">
                                        <button @click="openEditModal(expense)" class="text-slate-400 hover:text-[#1D3557] transition-colors">
                                            <i class="pi pi-pencil text-sm"></i>
                                        </button>
                                        <button @click="deleteExpense(expense.id)" class="text-red-300 hover:text-red-600 transition-colors">
                                            <i class="pi pi-trash text-sm"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Desktop table -->
                    <div class="hidden md:block overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 border-b border-slate-200">
                            <tr>
                                <th class="px-4 py-3 text-center text-xs font-bold text-slate-500 uppercase tracking-wide">Tanggal</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-slate-500 uppercase tracking-wide">Kategori</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-slate-500 uppercase tracking-wide">Deskripsi</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-slate-500 uppercase tracking-wide">Jumlah</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-slate-500 uppercase tracking-wide">Dibayar oleh</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-slate-500 uppercase tracking-wide">Struk</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-slate-500 uppercase tracking-wide">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="expense in filtered" :key="expense.id" class="hover:bg-slate-50/60 transition-colors">
                                <td class="px-4 py-3 text-center text-slate-600 whitespace-nowrap">{{ formatDate(expense.expense_date) }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="text-[11px] font-bold px-3.5 py-1.5 rounded-lg bg-slate-100 text-slate-600 whitespace-nowrap">
                                        {{ expense.category }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <div class="font-semibold text-slate-700">{{ expense.description }}</div>
                                    <div v-if="expense.notes" class="text-xs text-slate-400 mt-0.5">{{ expense.notes }}</div>
                                </td>
                                <td class="px-4 py-3 text-center font-bold text-red-600 whitespace-nowrap">
                                    {{ fmt(expense.amount) }}
                                </td>
                                <td class="px-4 py-3 text-center text-slate-500 text-xs">{{ expense.paid_by || '-' }}</td>
                                <td class="px-4 py-3 text-center">
                                    <a
                                        v-if="expense.receipt_path"
                                        :href="receiptUrl(expense.receipt_path)"
                                        target="_blank"
                                        class="inline-flex items-center gap-1 text-xs text-[#1D3557] font-semibold hover:underline"
                                        :title="isPdf(expense.receipt_path) ? 'Lihat PDF' : 'Lihat gambar'"
                                    >
                                        <i :class="isPdf(expense.receipt_path) ? 'pi pi-file-pdf text-red-500' : 'pi pi-image text-sky-500'"></i>
                                        Lihat
                                    </a>
                                    <span v-else class="text-slate-300 text-xs">-</span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <div class="flex items-center justify-center gap-3">
                                        <button @click="openEditModal(expense)" class="text-slate-500 hover:text-[#1D3557] transition-colors" title="Edit">
                                            <i class="pi pi-pencil"></i>
                                        </button>
                                        <button @click="deleteExpense(expense.id)" class="text-red-400 hover:text-red-600 transition-colors" title="Hapus">
                                            <i class="pi pi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    </div>
                </div>
            </template>
        </div>

        <!-- Modal Edit -->
        <div v-if="editModal.open" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/40 p-0 sm:p-4">
            <div class="bg-white rounded-t-2xl sm:rounded-2xl shadow-2xl w-full sm:max-w-lg max-h-[92vh] flex flex-col">
                <div class="flex items-center justify-between p-5 border-b border-slate-100">
                    <h3 class="text-base font-bold text-slate-800">Edit Pengeluaran</h3>
                    <button @click="editModal.open = false" class="text-slate-400 hover:text-slate-600 transition-colors">
                        <i class="pi pi-times text-lg"></i>
                    </button>
                </div>
                <div class="overflow-y-auto p-4 sm:p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1.5">Tanggal <span class="text-red-500">*</span></label>
                        <input v-model="editForm.expense_date" type="date"
                            class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1D3557]/30 focus:border-[#1D3557]"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1.5">Kategori <span class="text-red-500">*</span></label>
                        <select v-model="editForm.category"
                            class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1D3557]/30 focus:border-[#1D3557] bg-white"
                        >
                            <option value="" disabled>Pilih kategori</option>
                            <option v-for="cat in CATEGORIES" :key="cat" :value="cat">{{ cat }}</option>
                        </select>
                        <input
                            v-if="editForm.category === 'Lainnya'"
                            v-model="editCustomCategory"
                            type="text"
                            placeholder="Tulis kategori..."
                            class="w-full mt-2 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1D3557]/30 focus:border-[#1D3557]"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1.5">Jumlah <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm pointer-events-none">Rp</span>
                            <input
                                :value="editAmountDisplay"
                                @input="onEditAmountInput"
                                @focus="onEditAmountFocus"
                                @blur="onEditAmountBlur"
                                type="text" inputmode="numeric" placeholder="0"
                                class="w-full border border-slate-300 rounded-xl pl-8 pr-4 py-2.5 text-sm text-right focus:outline-none focus:ring-2 focus:ring-[#1D3557]/30 focus:border-[#1D3557]"
                            />
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1.5">Dibayar oleh</label>
                        <input v-model="editForm.paid_by" type="text" placeholder="Nama / kas ruko"
                            class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1D3557]/30 focus:border-[#1D3557]"
                        />
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-slate-500 mb-1.5">Deskripsi <span class="text-red-500">*</span></label>
                        <input v-model="editForm.description" type="text"
                            class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1D3557]/30 focus:border-[#1D3557]"
                        />
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-slate-500 mb-1.5">Catatan</label>
                        <input v-model="editForm.notes" type="text"
                            class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1D3557]/30 focus:border-[#1D3557]"
                        />
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-slate-500 mb-1.5">Bukti Struk / Nota</label>
                        <div v-if="editModal.expense?.receipt_path" class="flex items-center gap-3 mb-2 p-2.5 bg-slate-50 rounded-xl border border-slate-200">
                            <a
                                :href="receiptUrl(editModal.expense.receipt_path)"
                                target="_blank"
                                class="flex items-center gap-1.5 text-xs font-semibold text-[#1D3557] hover:underline flex-1 min-w-0"
                            >
                                <i :class="isPdf(editModal.expense.receipt_path) ? 'pi pi-file-pdf text-red-500' : 'pi pi-image text-sky-500'" class="text-sm flex-shrink-0"></i>
                                <span class="truncate">{{ editModal.expense.receipt_path.split('/').pop() }}</span>
                            </a>
                            <button
                                @click="removeReceipt(editModal.expense!)"
                                class="text-red-400 hover:text-red-600 transition-colors flex-shrink-0"
                                title="Hapus struk"
                            >
                                <i class="pi pi-times text-xs"></i>
                            </button>
                        </div>
                        <input
                            type="file"
                            accept="image/jpeg,image/png,image/webp,application/pdf"
                            @change="(e) => { editReceiptFile = (e.target as HTMLInputElement).files?.[0] ?? null }"
                            class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200 cursor-pointer"
                        />
                        <p class="text-[11px] text-slate-400 mt-1 pl-1">{{ editModal.expense?.receipt_path ? 'Pilih file baru untuk mengganti struk.' : 'JPG, PNG, WebP, atau PDF. Maks. 5 MB.' }}</p>
                    </div>
                </div>
                <div class="flex gap-3 p-5 border-t border-slate-100">
                    <button @click="editModal.open = false" class="flex-1 border border-slate-300 text-slate-600 text-sm font-bold py-2.5 rounded-xl hover:bg-slate-50 transition-colors">
                        Batal
                    </button>
                    <button
                        @click="submitEdit"
                        :disabled="editSubmitting || !editForm.expense_date || !editForm.category || !editForm.description || !Number(editForm.amount)"
                        class="flex-1 bg-[#1D3557] text-white text-sm font-bold py-2.5 rounded-xl hover:bg-[#162840] disabled:opacity-40 transition-colors flex items-center justify-center gap-2"
                    >
                        <i v-if="editSubmitting" class="pi pi-spin pi-spinner text-xs"></i>
                        {{ editSubmitting ? 'Menyimpan...' : 'Simpan Perubahan' }}
                    </button>
                </div>
            </div>
        </div>
        <!-- Modal Import CSV -->
        <div v-if="importModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xl flex flex-col max-h-[90vh]">
                <div class="flex items-center justify-between p-5 border-b border-slate-100">
                    <div>
                        <h3 class="text-base font-bold text-slate-800">Import Pengeluaran dari CSV</h3>
                        <p class="text-xs text-slate-400 mt-0.5">Format: tanggal, kategori, deskripsi, jumlah, dibayar_oleh (opsional), catatan (opsional)</p>
                    </div>
                    <button @click="closeImportModal" class="text-slate-400 hover:text-slate-600 transition-colors">
                        <i class="pi pi-times text-lg"></i>
                    </button>
                </div>

                <div class="p-5 space-y-4 overflow-y-auto">
                    <!-- Download template -->
                    <div class="flex items-center justify-between bg-slate-50 rounded-xl px-4 py-3">
                        <div>
                            <p class="text-sm font-semibold text-slate-700">Belum punya template?</p>
                            <p class="text-xs text-slate-400">Download file CSV contoh, isi di Excel, lalu upload.</p>
                        </div>
                        <button @click="downloadExpenseTemplate"
                            class="flex items-center gap-1.5 text-xs font-bold text-[#1D3557] border border-[#1D3557] px-3 py-1.5 rounded-lg hover:bg-[#1D3557]/5 transition-colors"
                        >
                            <i class="pi pi-download text-xs"></i> Template
                        </button>
                    </div>

                    <!-- Upload file -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1.5">Pilih File CSV</label>
                        <input
                            type="file" accept=".csv,.txt"
                            @change="onImportFileChange"
                            class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-[#1D3557] file:text-white hover:file:bg-[#162840] cursor-pointer"
                        />
                    </div>

                    <!-- Preview -->
                    <div v-if="importPreview.length > 0">
                        <p class="text-xs font-semibold text-slate-500 mb-1.5">Preview (5 baris pertama)</p>
                        <div class="overflow-x-auto border border-slate-200 rounded-xl">
                            <table class="w-full text-xs">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th v-for="(col, i) in importPreview[0]" :key="i"
                                            class="px-3 py-2 text-center font-bold text-slate-500 uppercase tracking-wide whitespace-nowrap">
                                            {{ col }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="(row, ri) in importPreview.slice(1)" :key="ri" class="hover:bg-slate-50">
                                        <td v-for="(cell, ci) in row" :key="ci" class="px-3 py-2 text-center text-slate-600 whitespace-nowrap">{{ cell }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Hasil import -->
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
                        class="flex-1 bg-[#1D3557] text-white text-sm font-bold py-2.5 rounded-xl hover:bg-[#162840] disabled:opacity-40 transition-colors flex items-center justify-center gap-2"
                    >
                        <i v-if="importing" class="pi pi-spin pi-spinner text-xs"></i>
                        {{ importing ? 'Mengimport...' : 'Import Sekarang' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
