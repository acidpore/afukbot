<script setup lang="ts">
import { ref, computed, onMounted, watch, nextTick } from 'vue'
import axios from 'axios'
import jsPDF from 'jspdf'
import autoTable from 'jspdf-autotable'
import ExcelJS from 'exceljs'

// ── State ──────────────────────────────────────────
const bankAccounts    = ref<any[]>([])
const selectedAccount = ref<number | ''>('')
const month           = ref(new Date().getMonth() + 1)
const year            = ref(new Date().getFullYear())
const data            = ref<any>(null)
const loading         = ref(false)

// ── Toast ───────────────────────────────────────────
const toast = ref({ show: false, message: '', type: 'success' })
function showToast(message: string, type = 'success') {
  toast.value = { show: true, message, type }
  setTimeout(() => (toast.value.show = false), 3500)
}

// ── Form transaksi ──────────────────────────────────
const showForm   = ref(false)
const editingId  = ref<number | null>(null)
const submitting = ref(false)
const form = ref({ date: today(), type: 'in', amount: '', description: '', category: '', costs: [] as { label: string; amount: string }[] })

function today() { return new Date().toISOString().slice(0, 10) }

function openForm(mutation?: any) {
  if (mutation) {
    editingId.value = mutation.id
    form.value = {
      date: mutation.date,
      type: mutation.type,
      amount: String(mutation.amount),
      description: mutation.description ?? '',
      category: mutation.category ?? '',
      costs: (mutation.costs ?? []).map((c: any) => ({ label: c.label, amount: c.amount.toLocaleString('id-ID') })),
    }
  } else {
    editingId.value = null
    form.value = { date: today(), type: 'in', amount: '', description: '', category: '', costs: [] }
  }
  showForm.value = true
}

function addCost() { form.value.costs.push({ label: '', amount: '' }) }
function removeCost(i: number) { form.value.costs.splice(i, 1) }
function formatCostInput(e: Event, i: number) {
  const raw = (e.target as HTMLInputElement).value.replace(/\D/g, '')
  form.value.costs[i].amount = raw ? parseInt(raw).toLocaleString('id-ID') : ''
}

const totalCosts = computed(() =>
  form.value.costs.reduce((s, c) => s + (parseInt(c.amount.replace(/\D/g, '')) || 0), 0)
)
const netAmount = computed(() => {
  const gross = parseInt(form.value.amount.replace(/\D/g, '')) || 0
  return gross - totalCosts.value
})

function closeForm() { showForm.value = false }

// ── Saldo awal ──────────────────────────────────────
const showOpeningForm = ref(false)
const openingAmount   = ref('')
const openingDate     = ref(today())
const savingOpening   = ref(false)

// ── Months ─────────────────────────────────────────
const MONTHS = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember']
const monthOptions = MONTHS.map((m, i) => ({ label: m, value: i + 1 }))
const yearOptions  = computed(() => {
  const y = new Date().getFullYear()
  return [y - 1, y, y + 1]
})

const currentMonthLabel = computed(() => MONTHS[(month.value - 1)])

// ── Load ────────────────────────────────────────────
const LS_KEY = 'mutasi_selected_account'

async function loadBankAccounts() {
  const res = await axios.get('/bank-accounts')
  bankAccounts.value = res.data
  if (!res.data.length) return

  const saved = localStorage.getItem(LS_KEY)
  const savedId = saved ? parseInt(saved) : null
  const match = savedId && res.data.find((b: any) => b.id === savedId)
  const fallback = res.data.find((b: any) => b.is_default) ?? res.data[0]
  selectedAccount.value = match ? match.id : fallback.id
}

watch(selectedAccount, (id) => {
  if (id) localStorage.setItem(LS_KEY, String(id))
})

async function loadMutations() {
  if (!selectedAccount.value) return
  loading.value = true
  try {
    const res = await axios.get('/account-mutations', {
      params: { bank_account_id: selectedAccount.value, month: month.value, year: year.value }
    })
    data.value = res.data
    if (data.value.opening_balance != null) {
      openingAmount.value = String(data.value.opening_balance)
    }
  } finally {
    loading.value = false
  }
}

watch([selectedAccount, month, year], loadMutations)

// ── Submit transaksi ────────────────────────────────
async function submitForm() {
  if (!form.value.amount || !selectedAccount.value) return
  submitting.value = true
  try {
    const payload = {
      ...form.value,
      amount: parseInt(form.value.amount.replace(/\D/g, '')),
      bank_account_id: selectedAccount.value,
      costs: form.value.type === 'in'
        ? form.value.costs.filter(c => c.label && c.amount).map(c => ({ label: c.label, amount: parseInt(c.amount.replace(/\D/g, '')) }))
        : [],
    }
    if (editingId.value) {
      await axios.put(`/account-mutations/${editingId.value}`, payload)
      showToast('Transaksi diperbarui')
    } else {
      await axios.post('/account-mutations', payload)
      showToast('Transaksi dicatat')
    }
    closeForm()
    await Promise.all([loadMutations(), loadCategories()])
  } catch {
    showToast('Gagal menyimpan', 'error')
  } finally {
    submitting.value = false
  }
}

async function deleteMutation(id: number) {
  if (!confirm('Hapus transaksi ini?')) return
  try {
    await axios.delete(`/account-mutations/${id}`)
    showToast('Transaksi dihapus')
    await loadMutations()
  } catch {
    showToast('Gagal menghapus', 'error')
  }
}

async function saveOpening() {
  if (!openingAmount.value || !selectedAccount.value) return
  savingOpening.value = true
  try {
    await axios.put('/account-mutations/opening', {
      bank_account_id: selectedAccount.value,
      amount: parseInt(openingAmount.value.replace(/\D/g, '')),
      date: openingDate.value,
    })
    showToast('Saldo awal disimpan')
    showOpeningForm.value = false
    await loadMutations()
  } catch {
    showToast('Gagal menyimpan saldo awal', 'error')
  } finally {
    savingOpening.value = false
  }
}

// ── Format ──────────────────────────────────────────
function fmt(n: number) {
  return 'Rp ' + Number(n).toLocaleString('id-ID')
}
function fmtDate(d: string) {
  return new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
}
function formatInput(e: Event) {
  const raw = (e.target as HTMLInputElement).value.replace(/\D/g, '')
  form.value.amount = raw ? parseInt(raw).toLocaleString('id-ID') : ''
}
function formatOpeningInput(e: Event) {
  const raw = (e.target as HTMLInputElement).value.replace(/\D/g, '')
  openingAmount.value = raw ? parseInt(raw).toLocaleString('id-ID') : ''
}

const selectedAccountInfo = computed(() => bankAccounts.value.find((b: any) => b.id === selectedAccount.value))

// ── Kategori autocomplete ───────────────────────────
const categories = ref<string[]>([])

async function loadCategories() {
  if (!selectedAccount.value) return
  const res = await axios.get('/account-mutations/categories', {
    params: { bank_account_id: selectedAccount.value }
  })
  categories.value = res.data
}

watch(selectedAccount, loadCategories)

// ── Sort ────────────────────────────────────────────
// ── Filter kategori ─────────────────────────────────
const filterCategory = ref('')

const availableCategories = computed(() => {
  const fromMutations = (data.value?.mutations ?? [])
    .map((m: any) => m.category)
    .filter(Boolean)
  return [...new Set([...categories.value, ...fromMutations])] as string[]
})

// ── Sort ────────────────────────────────────────────
type SortKey = 'date' | 'description' | 'category' | 'in' | 'out' | 'balance'
const sortKey = ref<SortKey>('date')
const sortDir = ref<'asc' | 'desc'>('desc')

function setSort(key: SortKey) {
  if (sortKey.value === key) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortKey.value = key
    sortDir.value = 'desc'
  }
}

function sortIcon(key: SortKey) {
  if (sortKey.value !== key) return 'pi-sort-alt text-slate-300'
  return sortDir.value === 'desc' ? 'pi-sort-amount-down text-primary' : 'pi-sort-amount-up-alt text-primary'
}

const sortedMutations = computed(() => {
  if (!data.value?.mutations) return []
  const base = filterCategory.value
    ? data.value.mutations.filter((m: any) => m.category === filterCategory.value)
    : data.value.mutations
  return [...base].sort((a: any, b: any) => {
    let va: any, vb: any
    switch (sortKey.value) {
      case 'date':
        va = a.date + String(a.id).padStart(10, '0')
        vb = b.date + String(b.id).padStart(10, '0')
        break
      case 'description':
        va = (a.description ?? '').toLowerCase()
        vb = (b.description ?? '').toLowerCase()
        break
      case 'category':
        va = (a.category ?? '').toLowerCase()
        vb = (b.category ?? '').toLowerCase()
        break
      case 'in':
        va = a.type === 'in' ? a.amount : 0
        vb = b.type === 'in' ? b.amount : 0
        break
      case 'out':
        va = a.type === 'out' ? a.amount : 0
        vb = b.type === 'out' ? b.amount : 0
        break
      case 'balance':
        va = a.balance_after
        vb = b.balance_after
        break
    }
    if (va < vb) return sortDir.value === 'asc' ? -1 : 1
    if (va > vb) return sortDir.value === 'asc' ? 1 : -1
    return 0
  })
})

function fmtTime(d: string) {
  return new Date(d).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
}

// ── Tab ─────────────────────────────────────────────
const activeTab = ref<'mutasi' | 'pajak'>('mutasi')

// ── Import ──────────────────────────────────────────
const importFileInput  = ref<HTMLInputElement | null>(null)
const importPreviewRows = ref<any[]>([])
const showImportModal  = ref(false)
const importing        = ref(false)
const importResult     = ref<{ inserted: number; skipped: number } | null>(null)

function triggerImport() { importFileInput.value?.click() }

async function onFileSelected(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file || !selectedAccount.value) return
  importing.value = true
  importPreviewRows.value = []
  importResult.value = null
  try {
    const fd = new FormData()
    fd.append('file', file)
    fd.append('bank_account_id', String(selectedAccount.value))
    const res = await axios.post('/account-mutations/import-preview', fd)
    importPreviewRows.value = res.data.rows
    showImportModal.value = true
  } catch (err: any) {
    showToast(err?.response?.data?.message ?? 'Gagal membaca file', 'error')
  } finally {
    importing.value = false
    ;(e.target as HTMLInputElement).value = ''
  }
}

async function commitImport() {
  if (!selectedAccount.value) return
  importing.value = true
  try {
    const res = await axios.post('/account-mutations/import-commit', {
      bank_account_id: selectedAccount.value,
      rows: importPreviewRows.value,
    })
    importResult.value = res.data
    showToast(`${res.data.inserted} transaksi berhasil diimport`)
    await loadMutations()
  } catch {
    showToast('Gagal import', 'error')
  } finally {
    importing.value = false
  }
}

function closeImportModal() {
  showImportModal.value = false
  importPreviewRows.value = []
  importResult.value = null
}

// ── Export ──────────────────────────────────────────
function exportRows() {
  if (!data.value?.mutations?.length) return []
  return data.value.mutations.map((m: any) => ({
    date: m.date,
    description: m.description ?? '',
    category: m.category ?? '',
    in: m.type === 'in' ? m.amount : null,
    out: m.type === 'out' ? m.amount : null,
    balance: m.balance_after,
  }))
}

async function exportExcel() {
  const rows = exportRows()
  if (!rows.length) { showToast('Tidak ada data untuk diekspor', 'error'); return }

  const acc      = selectedAccountInfo.value
  const filename = `mutasi-${acc?.bank_name}-${year.value}-${String(month.value).padStart(2, '0')}`
  const PURPLE   = 'FF6366F1'
  const PURPLE_D = 'FF4F46E5'
  const numFmt   = '#,##0'

  const wb = new ExcelJS.Workbook()
  wb.creator = 'MBG Admin System'
  const ws = wb.addWorksheet('Mutasi')

  ws.columns = [
    { key: 'date',    width: 14 },
    { key: 'desc',    width: 44 },
    { key: 'cat',     width: 22 },
    { key: 'in',      width: 20 },
    { key: 'out',     width: 20 },
    { key: 'balance', width: 20 },
  ]

  // ── Title ──
  const r1 = ws.addRow([`Laporan Mutasi Rekening — ${acc?.account_name} (${acc?.bank_name})`])
  ws.mergeCells('A1:F1')
  r1.height = 26
  Object.assign(r1.getCell(1), {
    font:      { bold: true, size: 13, color: { argb: 'FFFFFFFF' } },
    fill:      { type: 'pattern', pattern: 'solid', fgColor: { argb: PURPLE } },
    alignment: { vertical: 'middle' },
  })

  // ── Subtitle ──
  const r2 = ws.addRow([`No. Rekening: ${acc?.account_number}   |   Periode: ${MONTHS[month.value - 1]} ${year.value}`])
  ws.mergeCells('A2:F2')
  r2.height = 16
  Object.assign(r2.getCell(1), {
    font:      { size: 9, color: { argb: 'FFE0E7FF' } },
    fill:      { type: 'pattern', pattern: 'solid', fgColor: { argb: PURPLE } },
    alignment: { vertical: 'middle' },
  })

  ws.addRow([]).height = 6

  // ── Header ──
  const rHead = ws.addRow(['Tanggal', 'Keterangan', 'Kategori', 'Masuk', 'Keluar', 'Saldo'])
  rHead.height = 18
  rHead.eachCell((cell, col) => {
    cell.font      = { bold: true, size: 9, color: { argb: 'FFFFFFFF' } }
    cell.fill      = { type: 'pattern', pattern: 'solid', fgColor: { argb: PURPLE_D } }
    cell.alignment = { vertical: 'middle', horizontal: col >= 4 ? 'right' : 'left' }
    cell.border    = { bottom: { style: 'medium', color: { argb: PURPLE } } }
  })

  // ── Saldo awal ──
  const rOpen = ws.addRow(['Saldo Awal Periode', '', '', null, null, data.value.balance_before])
  ws.mergeCells(`A${rOpen.number}:C${rOpen.number}`)
  rOpen.getCell(1).font      = { italic: true, size: 9, color: { argb: 'FF64748B' } }
  rOpen.getCell(1).fill      = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFF1F5F9' } }
  rOpen.getCell(6).font      = { bold: true, size: 9 }
  rOpen.getCell(6).numFmt    = numFmt
  rOpen.getCell(6).alignment = { horizontal: 'right' }
  rOpen.getCell(6).fill      = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFF1F5F9' } }

  // ── Data rows ──
  rows.forEach((r, idx) => {
    const row   = ws.addRow([r.date, r.description, r.category, r.in, r.out, r.balance])
    const bg    = idx % 2 === 1 ? 'FFF8FAFC' : 'FFFFFFFF'
    row.eachCell({ includeEmpty: true }, cell => {
      cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: bg } }
      cell.font = { size: 9 }
    })

    const inCell  = row.getCell(4)
    inCell.numFmt    = numFmt
    inCell.alignment = { horizontal: 'right' }
    if (r.in)  inCell.font  = { size: 9, bold: true, color: { argb: 'FF059669' } }

    const outCell = row.getCell(5)
    outCell.numFmt    = numFmt
    outCell.alignment = { horizontal: 'right' }
    if (r.out) outCell.font = { size: 9, bold: true, color: { argb: 'FFEF4444' } }

    const balCell = row.getCell(6)
    balCell.numFmt    = numFmt
    balCell.alignment = { horizontal: 'right' }
    balCell.font      = { size: 9, bold: true }
  })

  // ── Total row ──
  const rTotal = ws.addRow(['', '', 'Total', data.value.total_in, data.value.total_out, data.value.final_balance])
  rTotal.height = 18
  rTotal.eachCell({ includeEmpty: true }, (cell, col) => {
    cell.fill   = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFE2E8F0' } }
    cell.font   = { bold: true, size: 9 }
    cell.border = { top: { style: 'thin', color: { argb: 'FFCBD5E1' } } }
    if (col >= 3) cell.alignment = { horizontal: 'right' }
    if (col >= 4) cell.numFmt = numFmt
  })
  rTotal.getCell(4).font = { bold: true, size: 9, color: { argb: 'FF059669' } }
  rTotal.getCell(5).font = { bold: true, size: 9, color: { argb: 'FFEF4444' } }

  // ── Download ──
  const buffer = await wb.xlsx.writeBuffer()
  const blob   = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' })
  const url    = URL.createObjectURL(blob)
  const a      = document.createElement('a')
  a.href = url
  a.download = `${filename}.xlsx`
  a.click()
  URL.revokeObjectURL(url)
}

function exportPdf() {
  const rows = exportRows()
  if (!rows.length) { showToast('Tidak ada data untuk diekspor', 'error'); return }

  const acc    = selectedAccountInfo.value
  const doc    = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
  const pageW  = doc.internal.pageSize.getWidth()
  const pageH  = doc.internal.pageSize.getHeight()
  const PRIMARY: [number, number, number] = [99, 102, 241]

  // ── Header band ──
  doc.setFillColor(...PRIMARY)
  doc.rect(0, 0, pageW, 42, 'F')

  doc.setTextColor(255, 255, 255)
  doc.setFont('helvetica', 'bold')
  doc.setFontSize(15)
  doc.text('Laporan Mutasi Rekening', 14, 14)

  doc.setFont('helvetica', 'normal')
  doc.setFontSize(8.5)
  doc.text(`${acc?.account_name}  —  ${acc?.bank_name}`, 14, 22)
  doc.text(`No. Rekening: ${acc?.account_number}`, 14, 28)
  doc.text(`Periode: ${MONTHS[month.value - 1]} ${year.value}`, 14, 34)

  // ── Summary (kanan header) ──
  const sumX = pageW - 14
  doc.setFontSize(7.5)
  doc.setTextColor(200, 200, 255)
  doc.text('Total Masuk', sumX - 32, 18, { align: 'right' })
  doc.text('Total Keluar', sumX - 32, 24, { align: 'right' })
  doc.text('Saldo Akhir', sumX - 32, 30, { align: 'right' })

  doc.setFont('helvetica', 'bold')
  doc.setTextColor(255, 255, 255)
  doc.text(fmt(data.value.total_in),     sumX, 18, { align: 'right' })
  doc.text(fmt(data.value.total_out),    sumX, 24, { align: 'right' })
  doc.text(fmt(data.value.final_balance),sumX, 30, { align: 'right' })

  // ── Tabel mutasi ──
  autoTable(doc, {
    startY: 48,
    head: [['Tanggal', 'Keterangan', 'Kategori', 'Masuk', 'Keluar', 'Saldo']],
    body: [
      [{
        content: `Saldo awal periode: ${fmt(data.value.balance_before)}`,
        colSpan: 6,
        styles: { fontStyle: 'italic', textColor: [100, 116, 139], halign: 'left', fillColor: [248, 250, 252] },
      }],
      ...rows.map(r => [
        fmtDate(r.date),
        r.description || '—',
        r.category || '—',
        r.in   ? fmt(r.in)  : '',
        r.out  ? fmt(r.out) : '',
        fmt(r.balance),
      ]),
    ],
    foot: [[
      { content: 'Total', colSpan: 3, styles: { fontStyle: 'bold', halign: 'left' } },
      { content: fmt(data.value.total_in),      styles: { halign: 'right', fontStyle: 'bold', textColor: [5, 150, 105] } },
      { content: fmt(data.value.total_out),     styles: { halign: 'right', fontStyle: 'bold', textColor: [239, 68, 68] } },
      { content: fmt(data.value.final_balance), styles: { halign: 'right', fontStyle: 'bold' } },
    ]],
    showFoot: 'lastPage',
    styles: { fontSize: 8, cellPadding: { top: 3, bottom: 3, left: 3, right: 3 }, overflow: 'ellipsize' },
    headStyles: { fillColor: PRIMARY, textColor: 255, fontStyle: 'bold', fontSize: 8 },
    footStyles: { fillColor: [241, 245, 249], textColor: [30, 41, 59] },
    alternateRowStyles: { fillColor: [248, 250, 252] },
    columnStyles: {
      0: { cellWidth: 24 },
      1: { cellWidth: 56 },
      2: { cellWidth: 30 },
      3: { halign: 'right', textColor: [5, 150, 105],  cellWidth: 28 },
      4: { halign: 'right', textColor: [239, 68, 68],  cellWidth: 28 },
      5: { halign: 'right', fontStyle: 'bold',         cellWidth: 28 },
    },
  })

  // ── Page footer ──
  const pageCount = doc.getNumberOfPages()
  for (let i = 1; i <= pageCount; i++) {
    doc.setPage(i)
    doc.setFontSize(7)
    doc.setFont('helvetica', 'normal')
    doc.setTextColor(148, 163, 184)
    const printed = `Dicetak: ${new Date().toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' })}`
    doc.text(printed, 14, pageH - 8)
    doc.text(`Halaman ${i} / ${pageCount}`, pageW - 14, pageH - 8, { align: 'right' })
    // separator line
    doc.setDrawColor(226, 232, 240)
    doc.line(14, pageH - 12, pageW - 14, pageH - 12)
  }

  doc.save(`mutasi-${acc?.bank_name}-${year.value}-${String(month.value).padStart(2, '0')}.pdf`)
}

// ── Konsultan Pajak ─────────────────────────────────
const taxData    = ref<any>(null)
const taxLoading = ref(false)
const reclassify = ref<{ category: string; newCategory: string } | null>(null)
const reclassifying = ref(false)

// ── Tax Chatbot ──────────────────────────────────────
type ChatMessage = { role: 'user' | 'assistant'; text: string }
const chatOpen      = ref(false)
const chatMessages  = ref<ChatMessage[]>([])
const chatInput     = ref('')
const chatLoading   = ref(false)
const chatContainer = ref<HTMLElement | null>(null)
const rateLimitSecs = ref(0)
let rateLimitTimer: ReturnType<typeof setInterval> | null = null

function startRateLimitCountdown(seconds: number) {
  rateLimitSecs.value = seconds
  if (rateLimitTimer) clearInterval(rateLimitTimer)
  rateLimitTimer = setInterval(() => {
    rateLimitSecs.value--
    if (rateLimitSecs.value <= 0) {
      clearInterval(rateLimitTimer!)
      rateLimitTimer = null
    }
  }, 1000)
}

const SUGGESTED_QUESTIONS = [
  'Berapa pajak yang harus saya bayar bulan ini?',
  'Pengeluaran mana yang bisa mengurangi pajak saya?',
  'Kapan saya harus setor pajak dan ke mana?',
  'Apa risiko jika saya telat bayar pajak?',
]

async function sendChat() {
  const msg = chatInput.value.trim()
  if (!msg || chatLoading.value || !selectedAccount.value) return

  chatMessages.value.push({ role: 'user', text: msg })
  chatInput.value = ''
  chatLoading.value = true

  await nextTick()
  chatContainer.value?.scrollTo({ top: chatContainer.value.scrollHeight, behavior: 'smooth' })

  try {
    const res = await axios.post('/tax-consultant/chat', {
      message: msg,
      history: chatMessages.value.slice(0, -1).slice(-10), // kirim 10 pesan terakhir sebagai konteks
      bank_account_id: selectedAccount.value,
      year: year.value,
    })
    chatMessages.value.push({ role: 'assistant', text: res.data.reply })
  } catch (err: any) {
    const status = err?.response?.status
    if (status === 429) {
      const secs = err.response.data?.retry_after ?? 60
      const wait = err.response.data?.wait ?? `${secs} detik`
      startRateLimitCountdown(secs)
      chatMessages.value.push({ role: 'assistant', text: `Quota AI sedang penuh. Coba lagi dalam ${wait}.` })
    } else {
      chatMessages.value.push({ role: 'assistant', text: 'Maaf, terjadi kesalahan. Coba lagi.' })
    }
  } finally {
    chatLoading.value = false
    await nextTick()
    chatContainer.value?.scrollTo({ top: chatContainer.value.scrollHeight, behavior: 'smooth' })
  }
}

async function loadTaxSummary() {
  if (!selectedAccount.value) return
  taxLoading.value = true
  try {
    const res = await axios.get('/account-mutations/tax-summary', {
      params: { bank_account_id: selectedAccount.value, year: year.value }
    })
    taxData.value = res.data
  } finally {
    taxLoading.value = false
  }
}

watch(activeTab, (tab) => { if (tab === 'pajak') loadTaxSummary() })
watch([selectedAccount, year], () => { if (activeTab.value === 'pajak') loadTaxSummary() })

async function submitReclassify() {
  if (!reclassify.value || !reclassify.value.newCategory.trim()) return
  reclassifying.value = true
  try {
    await axios.post('/account-mutations/reclassify', {
      bank_account_id: selectedAccount.value,
      year: year.value,
      old_category: reclassify.value.category,
      new_category: reclassify.value.newCategory.trim(),
    })
    reclassify.value = null
    await loadTaxSummary()
  } finally {
    reclassifying.value = false
  }
}

function exportTaxPdf() {
  if (!taxData.value) return
  const d    = taxData.value
  const acc  = bankAccounts.value.find(a => a.id === selectedAccount.value)
  const doc  = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
  const pageW = doc.internal.pageSize.getWidth()
  const PRIMARY: [number, number, number] = [99, 102, 241]
  const GREEN:   [number, number, number] = [5, 150, 105]
  const RED:     [number, number, number] = [239, 68, 68]

  // Header
  doc.setFillColor(...PRIMARY)
  doc.rect(0, 0, pageW, 38, 'F')
  doc.setTextColor(255, 255, 255)
  doc.setFont('helvetica', 'bold')
  doc.setFontSize(14)
  doc.text('Laporan Analisis Pajak', 14, 13)
  doc.setFont('helvetica', 'normal')
  doc.setFontSize(8.5)
  doc.text(`${acc?.account_name ?? ''} — ${acc?.bank_name ?? ''}`, 14, 21)
  doc.text(`Tahun: ${d.year}  |  Dibuat: ${new Date().toLocaleDateString('id-ID')}`, 14, 28)
  doc.setFont('helvetica', 'bold')
  doc.setFontSize(9)
  doc.setTextColor(200, 220, 255)
  doc.text(d.over_limit ? 'PPh Badan 22%' : 'PPh Final 0,5%', pageW - 14, 21, { align: 'right' })

  // Ringkasan keuangan
  let y = 46
  doc.setTextColor(30, 41, 59)
  doc.setFont('helvetica', 'bold')
  doc.setFontSize(9)
  doc.text('Ringkasan Keuangan', 14, y)
  y += 4

  autoTable(doc, {
    startY: y,
    body: [
      ['Total Masuk (Omzet)',  fmt(d.total_in)],
      ['Total Keluar',         fmt(d.total_out)],
      ['Biaya Variabel',       fmt(d.variable_costs)],
      ['Laba Bersih',          fmt(d.net_profit)],
      [d.over_limit ? 'Estimasi PPh Badan 22%' : 'Estimasi PPh Final 0,5%',
       fmt(d.over_limit ? d.pph_badan : d.pph_final)],
      ...(d.over_limit && d.angsuran_pph25 ? [['Angsuran PPh 25/bulan', fmt(d.angsuran_pph25)]] : []),
    ],
    styles: { fontSize: 8.5, cellPadding: { top: 2.5, bottom: 2.5, left: 4, right: 4 }, halign: 'center' },
    columnStyles: { 0: { fontStyle: 'bold', halign: 'left' }, 1: { halign: 'center' } },
    alternateRowStyles: { fillColor: [248, 250, 252] },
    margin: { left: 14, right: 14 },
  })

  // Klasifikasi pengeluaran
  y = (doc as any).lastAutoTable.finalY + 8
  doc.setFont('helvetica', 'bold')
  doc.setFontSize(9)
  doc.setTextColor(30, 41, 59)
  doc.text('Klasifikasi Pengeluaran', 14, y)
  y += 3

  const statusLabel: Record<string, string> = { deductible: 'Deductible', non: 'Non-deductible', review: 'Perlu Review' }
  autoTable(doc, {
    startY: y,
    head: [['Kategori', 'Total', 'Transaksi', 'Status']],
    body: (d.expense_categories ?? []).map((c: any) => [
      c.category, fmt(c.total), c.count, statusLabel[c.status] ?? c.status,
    ]),
    foot: [[
      {
        content: `Deductible: ${fmt(d.deductible_total)}   |   Non-deductible: ${fmt(d.non_deductible_total)}   |   Review: ${fmt(d.review_total)}`,
        colSpan: 4,
        styles: { fontStyle: 'italic', halign: 'center', textColor: [100, 116, 139], fillColor: [241, 245, 249] },
      },
    ]],
    showFoot: 'lastPage',
    styles: { fontSize: 8, cellPadding: { top: 2.5, bottom: 2.5, left: 4, right: 4 }, halign: 'center' },
    headStyles: { fillColor: PRIMARY, textColor: 255, fontStyle: 'bold', fontSize: 8, halign: 'center' },
    columnStyles: { 0: { halign: 'left' }, 1: { halign: 'center' }, 2: { halign: 'center', cellWidth: 22 }, 3: { cellWidth: 36, halign: 'center' } },
    alternateRowStyles: { fillColor: [248, 250, 252] },
    margin: { left: 14, right: 14 },
    didParseCell(data) {
      if (data.column.index === 3 && data.section === 'body') {
        const v = data.cell.raw as string
        if (v === 'Deductible')     data.cell.styles.textColor = GREEN
        if (v === 'Non-deductible') data.cell.styles.textColor = RED
        if (v === 'Perlu Review')   data.cell.styles.textColor = [180, 120, 0]
      }
    },
  })

  doc.save(`laporan-pajak-${d.year}.pdf`)
}

onMounted(async () => {
  await loadBankAccounts()
  await loadCategories()
})
</script>

<template>
  <div class="space-y-6 animate-in fade-in slide-in-from-bottom-4 duration-700">

    <!-- Header -->
    <div class="flex items-center justify-between gap-3">
      <div class="min-w-0">
        <h2 class="text-2xl sm:text-3xl font-display font-bold text-primary leading-tight">Mutasi Rekening</h2>
        <p class="text-slate-500 text-sm mt-1 hidden sm:block">Pantau arus kas masuk dan keluar rekening PT.</p>
      </div>
      <div class="flex items-center gap-2 flex-shrink-0">
        <!-- Mobile: icon only -->
        <button @click="showOpeningForm = !showOpeningForm" title="Saldo Awal"
          class="w-10 h-10 flex items-center justify-center border border-slate-200 text-slate-600 rounded-xl hover:bg-slate-50 transition-colors sm:hidden">
          <i class="pi pi-wallet text-sm"></i>
        </button>
        <!-- Desktop: icon + label -->
        <button @click="showOpeningForm = !showOpeningForm"
          class="hidden sm:flex items-center gap-2 px-4 py-2.5 border border-slate-200 text-slate-600 text-sm font-semibold rounded-xl hover:bg-slate-50 transition-colors">
          <i class="pi pi-wallet text-xs"></i>
          <span>Saldo Awal</span>
        </button>
        <!-- Import -->
        <button @click="triggerImport" :disabled="importing"
          class="hidden sm:flex items-center gap-2 px-4 py-2.5 border border-slate-200 text-slate-600 text-sm font-semibold rounded-xl hover:bg-slate-50 transition-colors disabled:opacity-50">
          <i class="pi pi-upload text-xs"></i>
          <span>{{ importing ? 'Membaca...' : 'Import' }}</span>
        </button>
        <input ref="importFileInput" type="file" accept=".csv,.txt,.xlsx,.xls" class="hidden" @change="onFileSelected" />

        <!-- Export Excel -->
        <button @click="exportExcel" :disabled="!data?.mutations?.length"
          class="hidden sm:flex items-center gap-2 px-4 py-2.5 border border-slate-200 text-slate-600 text-sm font-semibold rounded-xl hover:bg-slate-50 transition-colors disabled:opacity-40">
          <i class="pi pi-file-excel text-xs"></i>
          <span>Excel</span>
        </button>

        <!-- Export PDF -->
        <button @click="exportPdf" :disabled="!data?.mutations?.length"
          class="hidden sm:flex items-center gap-2 px-4 py-2.5 border border-slate-200 text-slate-600 text-sm font-semibold rounded-xl hover:bg-slate-50 transition-colors disabled:opacity-40">
          <i class="pi pi-file-pdf text-xs"></i>
          <span>PDF</span>
        </button>

        <!-- Desktop only, mobile pakai FAB -->
        <button @click="openForm()" class="hidden sm:flex btn-primary shadow-lg shadow-primary/20">
          <i class="pi pi-plus"></i>
          Catat Transaksi
        </button>
      </div>
    </div>

    <!-- Tab switcher -->
    <div class="flex bg-slate-100 rounded-2xl p-1 gap-1">
      <button @click="activeTab = 'mutasi'"
        :class="activeTab === 'mutasi' ? 'bg-white text-primary shadow-sm' : 'text-slate-500 hover:text-slate-700'"
        class="flex-1 py-2.5 text-sm font-bold rounded-xl transition-all flex items-center justify-center gap-2">
        <i class="pi pi-list text-xs"></i> Mutasi
      </button>
      <button @click="activeTab = 'pajak'"
        :class="activeTab === 'pajak' ? 'bg-white text-primary shadow-sm' : 'text-slate-500 hover:text-slate-700'"
        class="flex-1 py-2.5 text-sm font-bold rounded-xl transition-all flex items-center justify-center gap-2">
        <i class="pi pi-calculator text-xs"></i> Konsultan Pajak
      </button>
    </div>

    <!-- Filter bar -->
    <div class="premium-card bg-white p-4 flex flex-wrap items-end gap-3">
      <div class="flex-1 min-w-[160px]">
        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">Rekening</label>
        <select v-model="selectedAccount"
          class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-semibold text-primary outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary bg-white">
          <option v-for="b in bankAccounts" :key="b.id" :value="b.id">
            {{ b.account_name }} — {{ b.bank_name }}
          </option>
        </select>
      </div>
      <div class="w-full sm:w-auto">
        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">Bulan</label>
        <select v-model="month"
          class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-semibold text-primary outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary bg-white">
          <option v-for="m in monthOptions" :key="m.value" :value="m.value">{{ m.label }}</option>
        </select>
      </div>
      <div class="w-full sm:w-auto">
        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">Tahun</label>
        <select v-model="year"
          class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-semibold text-primary outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary bg-white">
          <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
        </select>
      </div>
      <div v-if="availableCategories.length" class="w-full sm:w-auto">
        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">Kategori</label>
        <select v-model="filterCategory"
          class="w-full border rounded-xl px-3 py-2.5 text-sm font-semibold outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary bg-white transition-colors"
          :class="filterCategory ? 'border-primary text-primary' : 'border-slate-200 text-primary'">
          <option value="">Semua</option>
          <option v-for="cat in availableCategories" :key="cat" :value="cat">{{ cat }}</option>
        </select>
      </div>
      <div v-if="selectedAccountInfo" class="flex items-center gap-2 px-3 py-2.5 bg-slate-50 rounded-xl border border-slate-100">
        <i class="pi pi-credit-card text-primary text-xs"></i>
        <span class="text-xs text-slate-600 font-mono font-semibold">{{ selectedAccountInfo.account_number }}</span>
      </div>
    </div>

    <template v-if="activeTab === 'mutasi'">

    <!-- Set Saldo Awal Panel -->
    <Transition name="slide-down">
      <div v-if="showOpeningForm" class="premium-card bg-amber-50 border border-amber-200 p-5">
        <div class="flex items-center gap-3 mb-4">
          <div class="w-9 h-9 bg-amber-100 rounded-xl flex items-center justify-center flex-shrink-0">
            <i class="pi pi-wallet text-amber-600"></i>
          </div>
          <div>
            <h4 class="text-sm font-bold text-amber-800">Saldo Awal Rekening</h4>
            <p class="text-xs text-amber-600">Nominal saldo sebelum periode pencatatan dimulai</p>
          </div>
        </div>
        <div class="grid grid-cols-2 gap-3 sm:flex sm:flex-wrap sm:items-end">
          <div class="col-span-2 sm:flex-1 sm:min-w-[180px]">
            <label class="block text-xs font-semibold text-amber-700 mb-1.5">Saldo Awal (Rp)</label>
            <input :value="openingAmount" @input="formatOpeningInput" type="text" inputmode="numeric" placeholder="0"
              class="w-full border border-amber-300 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-amber-300 bg-white" />
          </div>
          <div class="col-span-2 sm:col-span-1">
            <label class="block text-xs font-semibold text-amber-700 mb-1.5">Per Tanggal</label>
            <input v-model="openingDate" type="date"
              class="w-full border border-amber-300 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-amber-300 bg-white" />
          </div>
          <button @click="saveOpening" :disabled="savingOpening"
            class="h-[42px] px-5 bg-amber-500 text-white text-sm font-bold rounded-xl hover:bg-amber-600 transition-colors disabled:opacity-60">
            {{ savingOpening ? 'Menyimpan...' : 'Simpan' }}
          </button>
          <button @click="showOpeningForm = false"
            class="h-[42px] px-4 text-sm font-semibold text-slate-500 hover:text-slate-700 border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors">
            Batal
          </button>
        </div>
      </div>
    </Transition>

    <!-- Summary Cards -->
    <div v-if="data" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <div class="premium-card bg-primary text-white overflow-hidden relative group">
        <div class="absolute -right-8 -bottom-8 w-32 h-32 bg-white/10 rounded-full blur-3xl group-hover:scale-150 transition-transform duration-700"></div>
        <p class="text-[10px] font-bold text-white/60 uppercase tracking-widest mb-3">Saldo Rekening</p>
        <h3 class="text-2xl font-display font-bold" :class="data.final_balance < 0 ? 'text-red-300' : ''">
          {{ fmt(data.final_balance) }}
        </h3>
        <p class="text-[10px] text-white/40 mt-2 font-bold uppercase tracking-widest">Per hari ini</p>
      </div>

      <div class="premium-card bg-white border border-slate-100 group">
        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3">Masuk {{ currentMonthLabel }}</p>
        <div class="flex items-center justify-between">
          <h3 class="text-2xl font-display font-bold text-emerald-600">+{{ fmt(data.total_in) }}</h3>
          <div class="w-10 h-10 rounded-2xl bg-emerald-50 flex items-center justify-center text-emerald-500 text-lg flex-shrink-0">
            <i class="pi pi-arrow-down-left"></i>
          </div>
        </div>
      </div>

      <div class="premium-card bg-white border border-slate-100 group">
        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3">Keluar {{ currentMonthLabel }}</p>
        <div class="flex items-center justify-between">
          <h3 class="text-2xl font-display font-bold text-red-500">-{{ fmt(data.total_out) }}</h3>
          <div class="w-10 h-10 rounded-2xl bg-red-50 flex items-center justify-center text-red-400 text-lg flex-shrink-0">
            <i class="pi pi-arrow-up-right"></i>
          </div>
        </div>
      </div>
    </div>

    <!-- Cashflow Alert -->
    <div v-if="data" class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">

      <!-- Header strip berwarna -->
      <div class="px-5 py-3 flex items-center gap-3"
        :class="data.yearly_total_in >= 4_800_000_000 ? 'bg-red-500' : data.yearly_total_in >= 3_840_000_000 ? 'bg-amber-400' : 'bg-emerald-500'">
        <i class="text-white text-base"
          :class="data.yearly_total_in >= 4_800_000_000 ? 'pi pi-exclamation-circle' : data.yearly_total_in >= 3_840_000_000 ? 'pi pi-exclamation-triangle' : 'pi pi-shield'"></i>
        <span class="text-sm font-bold text-white">
          <template v-if="data.yearly_total_in >= 4_800_000_000">Batas PPh Final UMKM Terlampaui</template>
          <template v-else-if="data.yearly_total_in >= 3_840_000_000">Mendekati Batas PPh Final UMKM</template>
          <template v-else>Cashflow {{ year }} Aman</template>
        </span>
      </div>

      <div class="p-5 space-y-5">

        <!-- Progress omzet -->
        <div>
          <div class="flex justify-between items-end mb-2">
            <div>
              <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest">Total Masuk {{ year }}</p>
              <p class="text-xl font-bold text-slate-900 mt-0.5">{{ fmt(data.yearly_total_in) }}</p>
            </div>
            <div class="text-right">
              <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest">Batas</p>
              <p class="text-sm font-bold text-slate-600 mt-0.5">Rp 4,8 M</p>
            </div>
          </div>
          <div class="h-3 bg-slate-100 rounded-full overflow-hidden">
            <div class="h-full rounded-full transition-all duration-700"
              :class="data.yearly_total_in >= 4_800_000_000 ? 'bg-red-500' : data.yearly_total_in >= 3_840_000_000 ? 'bg-amber-400' : 'bg-emerald-500'"
              :style="{ width: Math.min(data.yearly_total_in / 4_800_000_000 * 100, 100) + '%' }">
            </div>
          </div>
          <p class="text-xs text-slate-500 mt-2">
            <template v-if="data.yearly_total_in >= 4_800_000_000">
              Melewati batas — tarif PPh Final 0,5% tidak lagi berlaku.
            </template>
            <template v-else>
              Sisa ruang <span class="font-semibold text-slate-700">{{ fmt(4_800_000_000 - data.yearly_total_in) }}</span>
              ({{ Math.min(Math.round(data.yearly_total_in / 4_800_000_000 * 100), 100) }}% terpakai)
            </template>
          </p>
        </div>

        <!-- Divider -->
        <div class="h-px bg-slate-100"></div>

        <!-- Estimasi pajak: grid 2 kolom di mobile -->
        <div>
          <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-3">Estimasi Pajak {{ year }}</p>
          <div class="grid grid-cols-2 gap-3">

            <div class="bg-slate-50 rounded-xl p-3">
              <p class="text-[11px] text-slate-400 font-medium mb-1">Total Masuk</p>
              <p class="text-sm font-bold text-slate-800">{{ fmt(data.yearly_total_in) }}</p>
            </div>

            <div class="bg-slate-50 rounded-xl p-3">
              <p class="text-[11px] text-slate-400 font-medium mb-1">Total Keluar</p>
              <p class="text-sm font-bold text-slate-800">−{{ fmt(data.yearly_total_out) }}</p>
            </div>

            <div v-if="data.yearly_variable_costs > 0" class="bg-slate-50 rounded-xl p-3">
              <p class="text-[11px] text-slate-400 font-medium mb-1">Biaya Variabel</p>
              <p class="text-sm font-bold text-slate-800">−{{ fmt(data.yearly_variable_costs) }}</p>
            </div>

            <div class="bg-slate-50 rounded-xl p-3" :class="data.yearly_variable_costs > 0 ? '' : 'col-span-1'">
              <p class="text-[11px] text-slate-400 font-medium mb-1">Laba Bersih</p>
              <p class="text-sm font-bold"
                :class="(data.yearly_total_in - data.yearly_total_out - data.yearly_variable_costs) >= 0 ? 'text-emerald-600' : 'text-red-500'">
                {{ fmt(data.yearly_total_in - data.yearly_total_out - data.yearly_variable_costs) }}
              </p>
            </div>

            <!-- PPh — full width -->
            <div class="col-span-2 rounded-xl p-3 border-2"
              :class="data.yearly_total_in >= 4_800_000_000 ? 'bg-red-50 border-red-200' : 'bg-primary/5 border-primary/20'">
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-[11px] font-bold uppercase tracking-widest mb-0.5"
                    :class="data.yearly_total_in >= 4_800_000_000 ? 'text-red-400' : 'text-primary/60'">
                    {{ data.yearly_total_in >= 4_800_000_000 ? 'PPh Badan 22%' : 'PPh Final 0,5%' }}
                  </p>
                  <p class="text-[10px]" :class="data.yearly_total_in >= 4_800_000_000 ? 'text-red-400' : 'text-slate-400'">
                    {{ data.yearly_total_in >= 4_800_000_000 ? 'Dari laba bersih — konsultasikan ke akuntan' : 'Dari omzet masuk, dibayar per bulan' }}
                  </p>
                </div>
                <p class="text-xl font-bold flex-shrink-0"
                  :class="data.yearly_total_in >= 4_800_000_000 ? 'text-red-600' : 'text-primary'">
                  <template v-if="data.yearly_total_in >= 4_800_000_000">
                    {{ (data.yearly_total_in - data.yearly_total_out - data.yearly_variable_costs) > 0
                      ? fmt(Math.round((data.yearly_total_in - data.yearly_total_out - data.yearly_variable_costs) * 0.22))
                      : 'Rp 0' }}
                  </template>
                  <template v-else>
                    {{ fmt(Math.round(data.yearly_total_in * 0.005)) }}
                  </template>
                </p>
              </div>
            </div>

          </div>
        </div>

      </div>
    </div>

    <!-- Tabel / Cards transaksi -->
    <div class="premium-card bg-white p-0 overflow-hidden shadow-2xl shadow-primary/5">

      <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
        <div>
          <h4 class="text-sm font-bold text-slate-800">Riwayat Transaksi</h4>
          <p class="text-xs text-slate-400 mt-0.5">{{ currentMonthLabel }} {{ year }}</p>
        </div>
        <div class="flex items-center gap-2 flex-wrap justify-end">
          <span v-if="data?.mutations?.length" class="text-xs font-bold text-slate-400 bg-slate-50 px-2.5 py-1 rounded-full">
            {{ sortedMutations.length }}{{ filterCategory ? `/${data.mutations.length}` : '' }} transaksi
          </span>
          <button v-if="data?.mutations?.length" @click="setSort('date')"
            class="flex items-center gap-1.5 px-3 py-1.5 text-[11px] font-semibold text-slate-500 border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors">
            <i class="pi text-[10px]" :class="sortKey === 'date' && sortDir === 'desc' ? 'pi-sort-amount-down text-primary' : 'pi-sort-amount-up-alt'"></i>
            {{ sortKey === 'date' && sortDir === 'desc' ? 'Terbaru' : 'Terlama' }}
          </button>
        </div>
      </div>

      <!-- Loading skeleton -->
      <div v-if="loading" class="divide-y divide-slate-50">
        <div v-for="i in 4" :key="i" class="px-5 py-4 animate-pulse">
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 bg-slate-100 rounded-xl flex-shrink-0"></div>
            <div class="flex-1 space-y-2">
              <div class="h-3 bg-slate-100 rounded w-1/3"></div>
              <div class="h-2.5 bg-slate-50 rounded w-1/4"></div>
            </div>
            <div class="h-4 bg-slate-100 rounded w-20"></div>
          </div>
        </div>
      </div>

      <template v-else-if="data">

        <!-- Empty state -->
        <div v-if="!data.mutations?.length" class="px-6 py-16 text-center">
          <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="pi pi-inbox text-3xl text-slate-200"></i>
          </div>
          <h4 class="text-base font-bold text-slate-400">Belum ada transaksi</h4>
          <p class="text-sm text-slate-400 mt-1">Catat transaksi pertama di {{ currentMonthLabel }} {{ year }}.</p>
          <button @click="openForm()" class="mt-4 btn-primary text-sm hidden sm:flex">
            <i class="pi pi-plus"></i> Catat Transaksi
          </button>
        </div>

        <template v-else>
          <!-- Saldo awal periode -->
          <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between" style="background-color: var(--bg-subtle);">
            <span class="text-xs text-slate-400 font-medium">Saldo awal periode</span>
            <span class="text-xs font-bold text-slate-600">{{ fmt(data.balance_before) }}</span>
          </div>

          <!-- ── MOBILE CARDS (< md) ── -->
          <div class="md:hidden divide-y divide-slate-100">
            <div v-for="m in sortedMutations" :key="m.id" class="p-4 space-y-3">

              <!-- Baris utama: icon + info + nominal -->
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl flex items-center justify-center flex-shrink-0 text-white text-sm"
                  :class="m.type === 'in' ? 'bg-emerald-500' : 'bg-red-400'">
                  <i :class="m.type === 'in' ? 'pi pi-arrow-down-left' : 'pi pi-arrow-up-right'"></i>
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-semibold text-slate-900 truncate">
                    {{ m.description || (m.type === 'in' ? 'Pemasukan' : 'Pengeluaran') }}
                  </p>
                  <div class="flex items-center gap-1.5 mt-0.5 flex-wrap">
                    <span class="text-xs text-slate-400">{{ fmtDate(m.date) }}</span>
                    <span class="text-[10px] text-slate-300">{{ fmtTime(m.created_at) }}</span>
                    <span v-if="m.category" class="text-[10px] bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded-full font-medium">{{ m.category }}</span>
                  </div>
                </div>
                <div class="text-right flex-shrink-0">
                  <p class="text-base font-bold" :class="m.type === 'in' ? 'text-emerald-600' : 'text-red-500'">
                    {{ m.type === 'in' ? '+' : '−' }}{{ fmt(m.amount) }}
                  </p>
                </div>
              </div>

              <!-- Biaya variabel (jika ada) -->
              <div v-if="m.costs?.length" class="ml-[52px] bg-slate-50 rounded-xl px-3 py-2.5 space-y-1.5">
                <div v-for="c in m.costs" :key="c.label" class="flex justify-between items-center">
                  <span class="text-xs text-slate-500">− {{ c.label }}</span>
                  <span class="text-xs font-semibold text-red-500">{{ fmt(c.amount) }}</span>
                </div>
                <div class="flex justify-between items-center pt-1.5 border-t border-slate-200">
                  <span class="text-xs font-bold text-slate-600">Bersih</span>
                  <span class="text-xs font-bold text-slate-800">{{ fmt(m.amount - m.costs.reduce((s: number, c: any) => s + c.amount, 0)) }}</span>
                </div>
              </div>

              <!-- Footer: saldo + tombol aksi -->
              <div class="ml-[52px] flex items-center justify-between">
                <div class="flex items-center gap-1.5">
                  <span class="text-[10px] text-slate-400 uppercase tracking-widest font-medium">Saldo</span>
                  <span class="text-xs font-bold text-slate-700">{{ fmt(m.balance_after) }}</span>
                </div>
                <div class="flex gap-1.5">
                  <button @click="openForm(m)"
                    class="h-8 px-3 text-[11px] font-bold text-primary border border-primary/20 bg-primary/5 rounded-xl active:bg-primary/15 transition-colors">
                    Edit
                  </button>
                  <button @click="deleteMutation(m.id)"
                    class="h-8 px-3 text-[11px] font-bold text-red-500 border border-red-100 bg-red-50 rounded-xl active:bg-red-100 transition-colors">
                    Hapus
                  </button>
                </div>
              </div>

            </div>
          </div>

          <!-- ── DESKTOP TABLE (>= md) ── -->
          <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-sm border-collapse">
              <thead>
                <tr class="border-b border-slate-100" style="background-color: var(--bg-subtle);">
                  <th @click="setSort('date')"
                    class="px-5 py-3.5 text-[10px] font-bold uppercase tracking-widest text-left cursor-pointer select-none transition-colors hover:text-primary"
                    :class="sortKey === 'date' ? 'text-primary' : 'text-slate-400'">
                    <span class="flex items-center gap-1">Tanggal <i class="pi text-[9px]" :class="sortIcon('date')"></i></span>
                  </th>
                  <th @click="setSort('description')"
                    class="px-5 py-3.5 text-[10px] font-bold uppercase tracking-widest text-left cursor-pointer select-none transition-colors hover:text-primary"
                    :class="sortKey === 'description' ? 'text-primary' : 'text-slate-400'">
                    <span class="flex items-center gap-1">Keterangan <i class="pi text-[9px]" :class="sortIcon('description')"></i></span>
                  </th>
                  <th @click="setSort('category')"
                    class="px-5 py-3.5 text-[10px] font-bold uppercase tracking-widest text-center cursor-pointer select-none transition-colors hover:text-primary"
                    :class="sortKey === 'category' ? 'text-primary' : 'text-slate-400'">
                    <span class="flex items-center justify-center gap-1">Kategori <i class="pi text-[9px]" :class="sortIcon('category')"></i></span>
                  </th>
                  <th @click="setSort('in')"
                    class="px-5 py-3.5 text-[10px] font-bold uppercase tracking-widest text-center cursor-pointer select-none transition-colors hover:text-primary"
                    :class="sortKey === 'in' ? 'text-primary' : 'text-slate-400'">
                    <span class="flex items-center justify-center gap-1">Masuk <i class="pi text-[9px]" :class="sortIcon('in')"></i></span>
                  </th>
                  <th @click="setSort('out')"
                    class="px-5 py-3.5 text-[10px] font-bold uppercase tracking-widest text-center cursor-pointer select-none transition-colors hover:text-primary"
                    :class="sortKey === 'out' ? 'text-primary' : 'text-slate-400'">
                    <span class="flex items-center justify-center gap-1">Keluar <i class="pi text-[9px]" :class="sortIcon('out')"></i></span>
                  </th>
                  <th @click="setSort('balance')"
                    class="px-5 py-3.5 text-[10px] font-bold uppercase tracking-widest text-center cursor-pointer select-none transition-colors hover:text-primary"
                    :class="sortKey === 'balance' ? 'text-primary' : 'text-slate-400'">
                    <span class="flex items-center justify-center gap-1">Saldo <i class="pi text-[9px]" :class="sortIcon('balance')"></i></span>
                  </th>
                  <th class="px-5 py-3.5 text-[10px] font-bold text-slate-400 uppercase tracking-widest text-right">Aksi</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-50">
                <tr v-for="m in sortedMutations" :key="m.id" class="hover:bg-slate-50/40 transition-colors group">
                  <td class="px-5 py-4 text-xs text-slate-500 whitespace-nowrap">
                    <div class="flex items-center gap-2">
                      <div :class="m.type === 'in' ? 'bg-emerald-50 text-emerald-500' : 'bg-red-50 text-red-400'"
                        class="w-7 h-7 rounded-lg flex items-center justify-center flex-shrink-0">
                        <i :class="m.type === 'in' ? 'pi pi-arrow-down-left' : 'pi pi-arrow-up-right'" class="text-[11px]"></i>
                      </div>
                      <div>
                        <div>{{ fmtDate(m.date) }}</div>
                        <div class="text-[10px] text-slate-300 mt-0.5">{{ fmtTime(m.created_at) }}</div>
                      </div>
                    </div>
                  </td>
                  <td class="px-5 py-4 text-sm text-slate-700 max-w-[200px]">
                    <p class="truncate">{{ m.description || '—' }}</p>
                    <div v-if="m.costs?.length" class="mt-1 space-y-0.5">
                      <p v-for="c in m.costs" :key="c.label" class="text-[10px] text-slate-400 truncate">
                        − {{ c.label }}: <span class="font-semibold text-red-400">{{ fmt(c.amount) }}</span>
                      </p>
                      <p class="text-[10px] font-bold text-slate-500">Bersih: {{ fmt(m.amount - m.costs.reduce((s: number, c: any) => s + c.amount, 0)) }}</p>
                    </div>
                  </td>
                  <td class="px-5 py-4 text-center">
                    <span v-if="m.category" class="text-[10px] bg-slate-100 text-slate-500 px-2.5 py-1 rounded-full font-medium">{{ m.category }}</span>
                    <span v-else class="text-slate-300 text-xs">—</span>
                  </td>
                  <td class="px-5 py-4 text-center font-bold text-emerald-600 text-sm">
                    {{ m.type === 'in' ? fmt(m.amount) : '' }}
                  </td>
                  <td class="px-5 py-4 text-center font-bold text-red-500 text-sm">
                    {{ m.type === 'out' ? fmt(m.amount) : '' }}
                  </td>
                  <td class="px-5 py-4 text-center font-bold text-slate-800 text-sm">{{ fmt(m.balance_after) }}</td>
                  <td class="px-5 py-4 text-right">
                    <div class="flex items-center justify-end gap-1.5">
                      <button @click="openForm(m)"
                        class="h-7 px-2.5 text-[10px] font-bold text-primary border border-primary/20 bg-primary/5 rounded-lg hover:bg-primary/10 transition-colors uppercase tracking-widest">
                        Edit
                      </button>
                      <button @click="deleteMutation(m.id)"
                        class="h-7 px-2.5 text-[10px] font-bold text-red-500 border border-red-100 bg-red-50 rounded-lg hover:bg-red-100 transition-colors uppercase tracking-widest">
                        Hapus
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </template>
      </template>

      <!-- No account / initial state -->
      <div v-else-if="!loading" class="px-6 py-16 text-center">
        <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4">
          <i class="pi pi-credit-card text-3xl text-slate-200"></i>
        </div>
        <p class="text-sm text-slate-400">Pilih rekening untuk melihat mutasi</p>
      </div>
    </div>

    <!-- Modal form transaksi -->
    <Transition name="modal-fade">
      <div v-if="showForm"
        class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 bg-black/50 backdrop-blur-sm"
        @click.self="closeForm">
        <div class="bg-white w-full sm:max-w-md rounded-t-3xl sm:rounded-2xl shadow-2xl overflow-hidden max-h-[90dvh] flex flex-col">

          <!-- Drag handle (mobile only) -->
          <div class="sm:hidden flex justify-center pt-3 pb-1">
            <div class="w-10 h-1 bg-slate-200 rounded-full"></div>
          </div>

          <div class="px-6 pt-4 pb-6 space-y-4 overflow-y-auto">
            <div class="flex items-center justify-between">
              <h2 class="text-base font-bold text-slate-800">{{ editingId ? 'Edit Transaksi' : 'Catat Transaksi' }}</h2>
              <button @click="closeForm"
                class="w-8 h-8 flex items-center justify-center rounded-xl text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors">
                <i class="pi pi-times text-sm"></i>
              </button>
            </div>

            <!-- Toggle Masuk / Keluar -->
            <div class="grid grid-cols-2 gap-2">
              <button @click="form.type = 'in'"
                :class="['py-3.5 rounded-xl text-sm font-bold border-2 transition-all flex items-center justify-center gap-2',
                  form.type === 'in'
                    ? 'bg-emerald-500 border-emerald-500 text-white shadow-lg shadow-emerald-500/25'
                    : 'border-slate-200 text-slate-500 hover:border-emerald-300 hover:text-emerald-600']">
                <i class="pi pi-arrow-down-left"></i> Masuk
              </button>
              <button @click="form.type = 'out'"
                :class="['py-3.5 rounded-xl text-sm font-bold border-2 transition-all flex items-center justify-center gap-2',
                  form.type === 'out'
                    ? 'bg-red-500 border-red-500 text-white shadow-lg shadow-red-500/25'
                    : 'border-slate-200 text-slate-500 hover:border-red-300 hover:text-red-500']">
                <i class="pi pi-arrow-up-right"></i> Keluar
              </button>
            </div>

            <!-- Nominal -->
            <div>
              <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-1.5">Nominal</label>
              <div class="relative">
                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-sm font-semibold text-slate-400">Rp</span>
                <input :value="form.amount" @input="formatInput" type="text" inputmode="numeric" placeholder="0"
                  class="w-full border border-slate-200 rounded-xl pl-10 pr-4 py-3 text-sm font-semibold outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" />
              </div>
            </div>

            <!-- Tanggal -->
            <div>
              <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-1.5">Tanggal</label>
              <input v-model="form.date" type="date"
                class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" />
            </div>

            <!-- Keterangan -->
            <div>
              <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-1.5">Keterangan</label>
              <input v-model="form.description" type="text" placeholder="Contoh: Terima DP Budi"
                class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" />
            </div>

            <!-- Kategori -->
            <div>
              <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-1.5">
                Kategori <span class="font-normal normal-case text-slate-400">(opsional)</span>
              </label>
              <input v-model="form.category" type="text" list="kategori-list" placeholder="Contoh: Invoice, Tarik Tunai"
                class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" />
              <datalist id="kategori-list">
                <option v-for="cat in categories" :key="cat" :value="cat" />
              </datalist>
            </div>

            <!-- Biaya Variabel (hanya untuk transaksi masuk) -->
            <div v-if="form.type === 'in'" class="space-y-2">
              <div class="flex items-center justify-between">
                <label class="text-xs font-bold text-slate-500 uppercase tracking-widest">Biaya Variabel</label>
                <button @click="addCost" type="button"
                  class="text-[10px] font-bold text-primary flex items-center gap-1 hover:underline">
                  <i class="pi pi-plus text-[10px]"></i> Tambah
                </button>
              </div>
              <div v-for="(cost, i) in form.costs" :key="i" class="flex gap-2 items-center">
                <input v-model="cost.label" type="text" placeholder="Label (mis. Biaya kirim)"
                  class="flex-1 border border-slate-200 rounded-xl px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" />
                <div class="relative w-36">
                  <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400">Rp</span>
                  <input :value="cost.amount" @input="formatCostInput($event, i)" type="text" inputmode="numeric" placeholder="0"
                    class="w-full border border-slate-200 rounded-xl pl-8 pr-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" />
                </div>
                <button @click="removeCost(i)" type="button" class="w-8 h-8 flex items-center justify-center rounded-lg text-red-400 hover:bg-red-50 transition-colors flex-shrink-0">
                  <i class="pi pi-times text-xs"></i>
                </button>
              </div>
              <!-- Net summary -->
              <div v-if="form.costs.length" class="flex justify-between items-center bg-slate-50 rounded-xl px-4 py-2.5 mt-1">
                <span class="text-xs text-slate-500">Pendapatan bersih</span>
                <span class="text-sm font-bold" :class="netAmount >= 0 ? 'text-emerald-600' : 'text-red-500'">
                  {{ fmt(netAmount) }}
                </span>
              </div>
            </div>

            <div class="flex gap-2 pt-1">
              <button @click="submitForm" :disabled="submitting || !form.amount"
                class="flex-1 btn-primary justify-center disabled:opacity-60">
                <i v-if="submitting" class="pi pi-spin pi-spinner"></i>
                {{ submitting ? 'Menyimpan...' : 'Simpan' }}
              </button>
              <button @click="closeForm"
                class="px-5 py-2.5 border border-slate-200 rounded-xl text-sm font-semibold text-slate-500 hover:bg-slate-50 transition-colors">
                Batal
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>

    </template>

    <!-- ── TAB KONSULTAN PAJAK ── -->
    <template v-if="activeTab === 'pajak'">

      <!-- Toolbar -->
      <div v-if="taxData && !taxLoading" class="flex justify-end">
        <button @click="exportTaxPdf"
          class="flex items-center gap-2 px-4 py-2 bg-white border border-slate-200 rounded-xl text-sm font-semibold text-slate-600 hover:bg-slate-50 shadow-sm transition-colors">
          <i class="pi pi-file-pdf text-red-500"></i> Export PDF
        </button>
      </div>

      <!-- Loading -->
      <div v-if="taxLoading" class="premium-card bg-white p-10 text-center text-slate-400 text-sm animate-pulse">
        Menganalisis data pajak...
      </div>

      <template v-else-if="taxData">

        <!-- Posisi Pajak -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
          <div class="px-5 py-3 flex items-center gap-3"
            :class="taxData.over_limit ? 'bg-red-500' : 'bg-emerald-500'">
            <i class="text-white text-base" :class="taxData.over_limit ? 'pi pi-exclamation-circle' : 'pi pi-shield'"></i>
            <span class="text-sm font-bold text-white">
              {{ taxData.over_limit ? 'Wajib PPh Badan 22%' : 'Masih dalam tarif PPh Final 0,5%' }}
            </span>
          </div>
          <div class="p-5 grid grid-cols-2 gap-3">
            <div class="bg-slate-50 rounded-xl p-3">
              <p class="text-[11px] text-slate-400 font-medium mb-1">Omzet {{ taxData.year }}</p>
              <p class="text-sm font-bold text-slate-800">{{ fmt(taxData.total_in) }}</p>
            </div>
            <div class="bg-slate-50 rounded-xl p-3">
              <p class="text-[11px] text-slate-400 font-medium mb-1">Biaya Variabel</p>
              <p class="text-sm font-bold text-red-500">−{{ fmt(taxData.variable_costs) }}</p>
            </div>
            <div class="bg-slate-50 rounded-xl p-3">
              <p class="text-[11px] text-slate-400 font-medium mb-1">Total Pengeluaran</p>
              <p class="text-sm font-bold text-red-500">−{{ fmt(taxData.total_out) }}</p>
            </div>
            <div class="bg-slate-50 rounded-xl p-3">
              <p class="text-[11px] text-slate-400 font-medium mb-1">Laba Bersih</p>
              <p class="text-sm font-bold" :class="taxData.net_profit >= 0 ? 'text-emerald-600' : 'text-red-500'">
                {{ fmt(taxData.net_profit) }}
              </p>
            </div>
            <div class="col-span-2 rounded-xl p-4 border-2"
              :class="taxData.over_limit ? 'bg-red-50 border-red-200' : 'bg-primary/5 border-primary/20'">
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-[11px] font-bold uppercase tracking-widest mb-0.5"
                    :class="taxData.over_limit ? 'text-red-400' : 'text-primary/60'">
                    {{ taxData.over_limit ? 'Estimasi PPh Badan 22%' : 'Estimasi PPh Final 0,5%' }}
                  </p>
                  <p class="text-[10px]" :class="taxData.over_limit ? 'text-red-400' : 'text-slate-400'">
                    {{ taxData.over_limit ? 'Dari laba bersih' : 'Dari omzet masuk' }}
                  </p>
                </div>
                <p class="text-2xl font-bold" :class="taxData.over_limit ? 'text-red-600' : 'text-primary'">
                  {{ fmt(taxData.over_limit ? taxData.pph_badan : taxData.pph_final) }}
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- Proyeksi Akhir Tahun -->
        <div v-if="taxData.months_passed < 12 && taxData.avg_monthly_in > 0" class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
          <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-3">Proyeksi Akhir Tahun {{ taxData.year }}</p>
          <div class="grid grid-cols-2 gap-3">
            <div class="bg-slate-50 rounded-xl p-3">
              <p class="text-[11px] text-slate-400 font-medium mb-1">Rata-rata Masuk/Bulan</p>
              <p class="text-sm font-bold text-slate-800">{{ fmt(taxData.avg_monthly_in) }}</p>
            </div>
            <div class="bg-slate-50 rounded-xl p-3">
              <p class="text-[11px] text-slate-400 font-medium mb-1">Estimasi Omzet Akhir Tahun</p>
              <p class="text-sm font-bold" :class="taxData.projected_yearly_in >= taxData.limit ? 'text-red-600' : 'text-emerald-600'">
                {{ fmt(taxData.projected_yearly_in) }}
              </p>
            </div>
          </div>
          <div v-if="taxData.months_to_limit !== null" class="mt-3 flex gap-3 p-3 rounded-xl border"
            :class="taxData.months_to_limit <= 3 ? 'bg-red-50 border-red-200' : 'bg-amber-50 border-amber-200'">
            <i class="pi pi-clock mt-0.5 flex-shrink-0" :class="taxData.months_to_limit <= 3 ? 'text-red-500' : 'text-amber-500'"></i>
            <p class="text-xs" :class="taxData.months_to_limit <= 3 ? 'text-red-700' : 'text-amber-700'">
              Dengan tren saat ini, batas Rp 4,8 M akan terlampaui dalam
              <strong>{{ taxData.months_to_limit }} bulan lagi</strong> — beralih ke rezim PPh Badan 22%.
            </p>
          </div>
          <div v-else-if="taxData.projected_yearly_in < taxData.limit" class="mt-3 flex gap-3 p-3 bg-emerald-50 border border-emerald-200 rounded-xl">
            <i class="pi pi-check-circle text-emerald-500 mt-0.5 flex-shrink-0"></i>
            <p class="text-xs text-emerald-700">Dengan tren saat ini, omzet tahun ini diperkirakan <strong>tidak melampaui</strong> batas Rp 4,8 M. Tetap di tarif PPh Final 0,5%.</p>
          </div>
        </div>

        <!-- Angsuran PPh 25 -->
        <div v-if="taxData.over_limit && taxData.angsuran_pph25 > 0" class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
          <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-3">Angsuran PPh 25 (Bulanan)</p>
          <div class="flex items-center justify-between bg-orange-50 border border-orange-200 rounded-xl p-4">
            <div>
              <p class="text-xs text-orange-600 font-medium">Setor tiap bulan ke kas negara</p>
              <p class="text-[10px] text-slate-400 mt-0.5">PPh Badan estimasi / 12 bulan</p>
            </div>
            <p class="text-2xl font-bold text-orange-600">{{ fmt(taxData.angsuran_pph25) }}</p>
          </div>
          <p class="text-[10px] text-slate-400 mt-3">Angsuran PPh 25 wajib dibayar paling lambat tanggal 15 bulan berikutnya via SSP/Billing DJP Online.</p>
        </div>

        <!-- Alur Pembayaran Pajak -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
          <div class="px-5 py-4 border-b border-slate-100">
            <h4 class="text-sm font-bold text-slate-800">Alur Pembayaran Pajak {{ taxData.year }}</h4>
            <p class="text-xs text-slate-400 mt-0.5">
              {{ taxData.over_limit ? 'Rezim PPh Badan 22% — 3 jenis kewajiban pajak' : 'Rezim PPh Final 0,5% — bayar bulanan dari omzet' }}
            </p>
          </div>

          <!-- PPh Final (tidak over limit) -->
          <template v-if="!taxData.over_limit">
            <div class="p-5 space-y-3">
              <div class="flex gap-4 items-start">
                <div class="w-7 h-7 rounded-full bg-primary flex items-center justify-center flex-shrink-0 mt-0.5">
                  <span class="text-[10px] font-bold text-white">1</span>
                </div>
                <div class="flex-1">
                  <p class="text-xs font-bold text-slate-800">Hitung omzet bulan ini</p>
                  <p class="text-[11px] text-slate-500 mt-0.5">Total semua pemasukan masuk ke rekening bulan tersebut.</p>
                </div>
              </div>
              <div class="flex gap-4 items-start">
                <div class="w-7 h-7 rounded-full bg-primary flex items-center justify-center flex-shrink-0 mt-0.5">
                  <span class="text-[10px] font-bold text-white">2</span>
                </div>
                <div class="flex-1">
                  <p class="text-xs font-bold text-slate-800">Kalikan 0,5%</p>
                  <p class="text-[11px] text-slate-500 mt-0.5">Tidak perlu hitung laba atau biaya. Langsung dari omzet.</p>
                  <div class="mt-2 bg-primary/5 rounded-lg px-3 py-2 text-[11px] text-primary font-medium">
                    Contoh: omzet Rp 200.000 → bayar Rp 1.000
                  </div>
                </div>
              </div>
              <div class="flex gap-4 items-start">
                <div class="w-7 h-7 rounded-full bg-primary flex items-center justify-center flex-shrink-0 mt-0.5">
                  <span class="text-[10px] font-bold text-white">3</span>
                </div>
                <div class="flex-1">
                  <p class="text-xs font-bold text-slate-800">Setor paling lambat tanggal 15 bulan berikutnya</p>
                  <p class="text-[11px] text-slate-500 mt-0.5">Via DJP Online → e-Billing → pilih kode pajak PPh Final UMKM (411128-420).</p>
                </div>
              </div>
              <div class="flex gap-4 items-start">
                <div class="w-7 h-7 rounded-full bg-slate-300 flex items-center justify-center flex-shrink-0 mt-0.5">
                  <span class="text-[10px] font-bold text-white">4</span>
                </div>
                <div class="flex-1">
                  <p class="text-xs font-bold text-slate-500">Lapor SPT Tahunan — April {{ taxData.year + 1 }}</p>
                  <p class="text-[11px] text-slate-400 mt-0.5">Rekap semua setoran bulanan. Jika sudah semua dibayar, SPT nihil — tidak ada tambahan bayar.</p>
                </div>
              </div>
            </div>
            <div class="px-5 py-3.5 bg-emerald-50 border-t border-emerald-100 flex items-center gap-3">
              <i class="pi pi-check-circle text-emerald-500 flex-shrink-0"></i>
              <p class="text-[11px] text-emerald-700">
                Estimasi PPh Final tahun ini: <strong>{{ fmt(taxData.pph_final) }}</strong> —
                dibayar cicil tiap bulan, tidak ada tagihan besar di akhir tahun.
              </p>
            </div>
          </template>

          <!-- PPh Badan (over limit) -->
          <template v-else>
            <div class="p-5 space-y-3">
              <!-- PPh 25 -->
              <div class="flex gap-4 items-start">
                <div class="w-7 h-7 rounded-full bg-orange-500 flex items-center justify-center flex-shrink-0 mt-0.5">
                  <span class="text-[10px] font-bold text-white">1</span>
                </div>
                <div class="flex-1">
                  <div class="flex items-center justify-between">
                    <p class="text-xs font-bold text-slate-800">PPh 25 — Angsuran Bulanan Wajib</p>
                    <p class="text-sm font-bold text-orange-600">{{ fmt(taxData.angsuran_pph25) }}<span class="text-[10px] font-normal text-slate-400">/bln</span></p>
                  </div>
                  <p class="text-[11px] text-slate-500 mt-1">Dihitung dari PPh Badan tahun lalu dibagi 12. Tahun pertama kena PPh Badan biasanya nihil. Setor paling lambat <strong>tanggal 15</strong> tiap bulan. Telat kena sanksi bunga <strong>2%/bulan</strong>.</p>
                </div>
              </div>

              <div class="ml-11 h-px bg-slate-100"></div>

              <!-- SPT Tahunan -->
              <div class="flex gap-4 items-start">
                <div class="w-7 h-7 rounded-full bg-slate-500 flex items-center justify-center flex-shrink-0 mt-0.5">
                  <span class="text-[10px] font-bold text-white">2</span>
                </div>
                <div class="flex-1">
                  <div class="flex items-center justify-between">
                    <p class="text-xs font-bold text-slate-800">SPT Tahunan — April {{ taxData.year + 1 }}</p>
                    <p class="text-sm font-bold text-slate-700">22% × laba bersih</p>
                  </div>
                  <p class="text-[11px] text-slate-500 mt-1">Hitung PPh Badan sesungguhnya dari laba bersih tahun ini. Hasilnya dibandingkan dengan total PPh 25 yang sudah disetor.</p>
                  <div class="mt-2 bg-slate-50 rounded-lg px-3 py-2 space-y-1">
                    <div class="flex justify-between text-[11px]">
                      <span class="text-slate-500">PPh Badan terutang (estimasi)</span>
                      <span class="font-bold text-slate-700">{{ fmt(taxData.pph_badan) }}</span>
                    </div>
                    <div class="flex justify-between text-[11px]">
                      <span class="text-slate-500">Total PPh 25 setahun (estimasi)</span>
                      <span class="font-bold text-orange-600">{{ fmt(taxData.angsuran_pph25 * 12) }}</span>
                    </div>
                  </div>
                </div>
              </div>

              <div class="ml-11 h-px bg-slate-100"></div>

              <!-- PPh 28/29 -->
              <div class="flex gap-4 items-start">
                <div class="w-7 h-7 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5"
                  :class="taxData.pph_badan > taxData.angsuran_pph25 * 12 ? 'bg-red-500' : 'bg-emerald-500'">
                  <span class="text-[10px] font-bold text-white">3</span>
                </div>
                <div class="flex-1">
                  <template v-if="taxData.pph_badan > taxData.angsuran_pph25 * 12">
                    <div class="flex items-center justify-between">
                      <p class="text-xs font-bold text-red-700">PPh 29 — Kurang Bayar di SPT</p>
                      <p class="text-sm font-bold text-red-600">{{ fmt(taxData.pph_badan - taxData.angsuran_pph25 * 12) }}</p>
                    </div>
                    <p class="text-[11px] text-slate-500 mt-1">Selisih ini harus dilunasi saat lapor SPT Tahunan, paling lambat <strong>30 April {{ taxData.year + 1 }}</strong>.</p>
                  </template>
                  <template v-else-if="taxData.pph_badan < taxData.angsuran_pph25 * 12">
                    <div class="flex items-center justify-between">
                      <p class="text-xs font-bold text-emerald-700">PPh 28 — Lebih Bayar</p>
                      <p class="text-sm font-bold text-emerald-600">{{ fmt(taxData.angsuran_pph25 * 12 - taxData.pph_badan) }}</p>
                    </div>
                    <p class="text-[11px] text-slate-500 mt-1">Kelebihan ini bisa dikompensasi ke tahun berikutnya atau diajukan restitusi (pengembalian) ke DJP.</p>
                  </template>
                  <template v-else>
                    <p class="text-xs font-bold text-slate-700">Pajak Pas — Tidak Ada Kurang/Lebih Bayar</p>
                    <p class="text-[11px] text-slate-500 mt-1">Total angsuran tepat sama dengan PPh Badan terutang.</p>
                  </template>
                </div>
              </div>
            </div>
          </template>
        </div>

        <!-- Biaya Variabel Breakdown -->
        <div v-if="taxData.variable_cost_breakdown?.length" class="premium-card bg-white p-0 overflow-hidden">
          <div class="px-5 py-4 border-b border-slate-100">
            <h4 class="text-sm font-bold text-slate-800">Rincian Biaya Variabel</h4>
            <p class="text-xs text-slate-400 mt-0.5">Biaya yang melekat pada pemasukan — mengurangi laba bersih</p>
          </div>
          <div class="divide-y divide-slate-50">
            <div v-for="c in taxData.variable_cost_breakdown" :key="c.label" class="px-5 py-3.5 flex items-center justify-between">
              <div class="flex items-center gap-2">
                <div class="w-2 h-2 rounded-full bg-orange-400 flex-shrink-0"></div>
                <span class="text-sm text-slate-700">{{ c.label }}</span>
              </div>
              <span class="text-sm font-bold text-red-500">−{{ fmt(c.total) }}</span>
            </div>
          </div>
        </div>

        <!-- Klasifikasi Pengeluaran -->
        <div class="premium-card bg-white p-0 overflow-hidden">
          <div class="px-5 py-4 border-b border-slate-100">
            <h4 class="text-sm font-bold text-slate-800">Klasifikasi Pengeluaran</h4>
            <p class="text-xs text-slate-400 mt-0.5">Pengeluaran yang bisa dikurangkan dari penghasilan kena pajak (PPh Badan)</p>
          </div>

          <!-- Legend -->
          <div class="px-5 py-3 flex flex-wrap gap-3 bg-slate-50 border-b border-slate-100">
            <div class="flex items-center gap-1.5">
              <div class="w-2.5 h-2.5 rounded-full bg-emerald-500"></div>
              <span class="text-[11px] text-slate-600 font-medium">Deductible (bisa kurangi pajak)</span>
            </div>
            <div class="flex items-center gap-1.5">
              <div class="w-2.5 h-2.5 rounded-full bg-red-400"></div>
              <span class="text-[11px] text-slate-600 font-medium">Non-deductible</span>
            </div>
            <div class="flex items-center gap-1.5">
              <div class="w-2.5 h-2.5 rounded-full bg-amber-400"></div>
              <span class="text-[11px] text-slate-600 font-medium">Perlu review</span>
            </div>
          </div>

          <div v-if="!taxData.expense_categories?.length" class="px-5 py-10 text-center text-slate-400 text-sm">
            Belum ada data pengeluaran {{ taxData.year }}
          </div>
          <div v-else class="divide-y divide-slate-50">
            <div v-for="cat in taxData.expense_categories" :key="cat.category"
              class="px-5 py-3.5 flex items-center justify-between gap-3">
              <div class="flex items-center gap-2.5 min-w-0">
                <div class="w-2.5 h-2.5 rounded-full flex-shrink-0"
                  :class="cat.status === 'deductible' ? 'bg-emerald-500' : cat.status === 'non' ? 'bg-red-400' : 'bg-amber-400'"></div>
                <div class="min-w-0">
                  <p class="text-sm text-slate-800 truncate">{{ cat.category }}</p>
                  <p class="text-[10px] text-slate-400">{{ cat.count }} transaksi</p>
                </div>
              </div>
              <div class="flex items-center gap-3 flex-shrink-0">
                <!-- Inline reklasifikasi untuk kategori review -->
                <template v-if="cat.status === 'review'">
                  <template v-if="reclassify?.category === cat.category">
                    <input v-model="reclassify.newCategory" @keyup.enter="submitReclassify" @keyup.esc="reclassify = null"
                      class="text-xs border border-slate-200 rounded-lg px-2 py-1 w-32 focus:outline-none focus:ring-1 focus:ring-primary"
                      placeholder="Kategori baru" autofocus />
                    <button @click="submitReclassify" :disabled="reclassifying"
                      class="text-[11px] font-bold text-white bg-primary px-2.5 py-1 rounded-lg disabled:opacity-50">
                      {{ reclassifying ? '...' : 'Simpan' }}
                    </button>
                    <button @click="reclassify = null" class="text-[11px] text-slate-400 hover:text-slate-600">Batal</button>
                  </template>
                  <button v-else @click="reclassify = { category: cat.category, newCategory: cat.category }"
                    class="text-[11px] text-amber-600 border border-amber-200 bg-amber-50 px-2.5 py-1 rounded-lg hover:bg-amber-100 font-medium flex-shrink-0">
                    Klasifikasi
                  </button>
                </template>
                <div class="text-right">
                  <p class="text-sm font-bold text-slate-700">{{ fmt(cat.total) }}</p>
                  <p class="text-[10px] font-medium"
                    :class="cat.status === 'deductible' ? 'text-emerald-600' : cat.status === 'non' ? 'text-red-400' : 'text-amber-500'">
                    {{ cat.status === 'deductible' ? 'Deductible' : cat.status === 'non' ? 'Non-deductible' : 'Perlu review' }}
                  </p>
                </div>
              </div>
            </div>
          </div>

          <!-- Ringkasan deductible -->
          <div v-if="taxData.expense_categories?.length" class="px-5 py-4 bg-slate-50 border-t border-slate-100 grid grid-cols-3 gap-3">
            <div class="text-center">
              <p class="text-[10px] text-slate-400 font-medium mb-1">Deductible</p>
              <p class="text-sm font-bold text-emerald-600">{{ fmt(taxData.deductible_total) }}</p>
            </div>
            <div class="text-center">
              <p class="text-[10px] text-slate-400 font-medium mb-1">Non-deductible</p>
              <p class="text-sm font-bold text-red-500">{{ fmt(taxData.non_deductible_total) }}</p>
            </div>
            <div class="text-center">
              <p class="text-[10px] text-slate-400 font-medium mb-1">Perlu Review</p>
              <p class="text-sm font-bold text-amber-500">{{ fmt(taxData.review_total) }}</p>
            </div>
          </div>
        </div>

        <!-- Rekomendasi -->
        <div class="premium-card bg-white space-y-3">
          <h4 class="text-sm font-bold text-slate-800">Rekomendasi</h4>

          <div v-if="!taxData.over_limit && taxData.total_in > 0" class="flex gap-3 p-3 bg-blue-50 rounded-xl border border-blue-100">
            <i class="pi pi-info-circle text-blue-500 mt-0.5 flex-shrink-0"></i>
            <div>
              <p class="text-xs font-bold text-blue-800">Manfaatkan PPh Final 0,5%</p>
              <p class="text-xs text-blue-600 mt-0.5">Kamu masih dalam tarif PPh Final 0,5% dari omzet. Lebih hemat dibanding PPh Badan 22% dari laba.</p>
            </div>
          </div>

          <div v-if="taxData.non_deductible_total > 0" class="flex gap-3 p-3 bg-red-50 rounded-xl border border-red-100">
            <i class="pi pi-exclamation-triangle text-red-400 mt-0.5 flex-shrink-0"></i>
            <div>
              <p class="text-xs font-bold text-red-700">Pengeluaran Non-deductible {{ fmt(taxData.non_deductible_total) }}</p>
              <p class="text-xs text-red-500 mt-0.5">Pengeluaran ini (tarik tunai, keperluan pribadi) tidak bisa dikurangkan dari penghasilan kena pajak. Pastikan ada bukti pengeluaran yang sah.</p>
            </div>
          </div>

          <div v-if="taxData.review_total > 0" class="flex gap-3 p-3 bg-amber-50 rounded-xl border border-amber-100">
            <i class="pi pi-exclamation-triangle text-amber-500 mt-0.5 flex-shrink-0"></i>
            <div>
              <p class="text-xs font-bold text-amber-800">{{ fmt(taxData.review_total) }} perlu dikategorikan</p>
              <p class="text-xs text-amber-600 mt-0.5">Beberapa pengeluaran belum jelas kategorinya. Tambahkan kategori yang lebih spesifik (mis. "Sewa", "Gaji") agar bisa dihitung sebagai deductible.</p>
            </div>
          </div>

          <div v-if="taxData.variable_costs > 0" class="flex gap-3 p-3 bg-emerald-50 rounded-xl border border-emerald-100">
            <i class="pi pi-check-circle text-emerald-500 mt-0.5 flex-shrink-0"></i>
            <div>
              <p class="text-xs font-bold text-emerald-800">Biaya variabel {{ fmt(taxData.variable_costs) }} sudah tercatat</p>
              <p class="text-xs text-emerald-600 mt-0.5">Biaya kirim, kuli, dan biaya produksi lainnya sudah mengurangi laba bersih. Pastikan semua biaya terkait pemasukan dicatat.</p>
            </div>
          </div>

          <div v-if="taxData.over_limit && taxData.potential_saving > 0" class="flex gap-3 p-3 bg-primary/5 rounded-xl border border-primary/20">
            <i class="pi pi-tag text-primary mt-0.5 flex-shrink-0"></i>
            <div>
              <p class="text-xs font-bold text-primary">Potensi hemat pajak {{ fmt(taxData.potential_saving) }}</p>
              <p class="text-xs text-slate-500 mt-0.5">Dengan memaksimalkan biaya deductible dan variabel cost, estimasi PPh Badan bisa berkurang sebesar ini. Konsultasikan ke akuntan untuk optimasi lebih lanjut.</p>
            </div>
          </div>
        </div>

        <!-- Breakdown Per Bulan -->
        <div v-if="taxData.monthly_breakdown?.length" class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
          <div class="px-5 py-4 border-b border-slate-100">
            <h4 class="text-sm font-bold text-slate-800">Timeline Omzet {{ taxData.year }}</h4>
            <p class="text-xs text-slate-400 mt-0.5">Masuk & keluar per bulan — akumulatif vs batas Rp 4,8 M</p>
          </div>
          <!-- Desktop -->
          <div class="hidden md:block">
            <table class="w-full text-xs">
              <thead>
                <tr class="bg-slate-50 text-slate-500 font-bold">
                  <th class="text-center px-3 py-2.5">Bulan</th>
                  <th class="text-center px-3 py-2.5">Masuk</th>
                  <th class="text-center px-3 py-2.5">Keluar</th>
                  <th class="text-center px-3 py-2.5">Akumulatif</th>
                  <th class="text-center px-3 py-2.5">Status</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-50">
                <tr v-for="row in taxData.monthly_breakdown" :key="row.month"
                  :class="row.cumulative >= taxData.limit ? 'bg-red-50' : ''">
                  <td class="text-center px-3 py-2.5 font-medium text-slate-700">
                    {{ ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'][row.month - 1] }}
                  </td>
                  <td class="text-center px-3 py-2.5 text-emerald-600 font-medium">{{ fmt(row.total_in) }}</td>
                  <td class="text-center px-3 py-2.5 text-red-500">{{ fmt(row.total_out) }}</td>
                  <td class="text-center px-3 py-2.5 font-bold"
                    :class="row.cumulative >= taxData.limit ? 'text-red-600' : 'text-slate-800'">
                    {{ fmt(row.cumulative) }}
                  </td>
                  <td class="text-center px-3 py-2.5">
                    <span v-if="row.cumulative >= taxData.limit"
                      class="text-[10px] font-bold text-red-600 bg-red-100 px-2 py-0.5 rounded-full">Melampaui</span>
                    <span v-else-if="row.cumulative >= taxData.limit * 0.8"
                      class="text-[10px] font-bold text-amber-600 bg-amber-100 px-2 py-0.5 rounded-full">Mendekati</span>
                    <span v-else class="text-[10px] font-bold text-emerald-600 bg-emerald-100 px-2 py-0.5 rounded-full">Aman</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Mobile: card per bulan -->
          <div class="md:hidden divide-y divide-slate-50">
            <div v-for="row in taxData.monthly_breakdown" :key="row.month"
              class="px-4 py-3"
              :class="row.cumulative >= taxData.limit ? 'bg-red-50' : ''">
              <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-bold text-slate-700">
                  {{ ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'][row.month - 1] }}
                </span>
                <span v-if="row.cumulative >= taxData.limit"
                  class="text-[10px] font-bold text-red-600 bg-red-100 px-2 py-0.5 rounded-full">Melampaui</span>
                <span v-else-if="row.cumulative >= taxData.limit * 0.8"
                  class="text-[10px] font-bold text-amber-600 bg-amber-100 px-2 py-0.5 rounded-full">Mendekati</span>
                <span v-else class="text-[10px] font-bold text-emerald-600 bg-emerald-100 px-2 py-0.5 rounded-full">Aman</span>
              </div>
              <div class="grid grid-cols-3 gap-2 text-xs">
                <div class="bg-white rounded-lg p-2 text-center border border-slate-100 min-w-0">
                  <p class="text-[10px] text-slate-400 mb-0.5">Masuk</p>
                  <p class="font-bold text-emerald-600 text-[11px] truncate">{{ fmt(row.total_in) }}</p>
                </div>
                <div class="bg-white rounded-lg p-2 text-center border border-slate-100 min-w-0">
                  <p class="text-[10px] text-slate-400 mb-0.5">Keluar</p>
                  <p class="font-bold text-red-500 text-[11px] truncate">{{ fmt(row.total_out) }}</p>
                </div>
                <div class="bg-white rounded-lg p-2 text-center border border-slate-100 min-w-0">
                  <p class="text-[10px] text-slate-400 mb-0.5">Akumulatif</p>
                  <p class="font-bold text-[11px] truncate" :class="row.cumulative >= taxData.limit ? 'text-red-600' : 'text-slate-800'">
                    {{ fmt(row.cumulative) }}
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>

      </template>

      <div v-else class="premium-card bg-white py-16 text-center text-slate-400 text-sm">
        Pilih rekening dan tahun untuk melihat analisis pajak
      </div>

    </template>

    <!-- ── Floating AI Chat Bubble (tab pajak) ── -->
    <Teleport to="body">
      <div v-if="activeTab === 'pajak' && selectedAccount" class="fixed bottom-6 right-6 z-50 flex flex-col items-end gap-3">

        <!-- Chat popup -->
        <Transition name="chat-popup">
          <div v-if="chatOpen"
            class="w-[340px] sm:w-[380px] bg-white rounded-2xl shadow-2xl border border-slate-100 flex flex-col overflow-hidden"
            style="height: 520px; max-height: calc(100dvh - 100px)">

            <!-- Header -->
            <div class="px-4 py-3 bg-primary flex items-center gap-3 flex-shrink-0">
              <div class="w-7 h-7 rounded-lg bg-white/20 flex items-center justify-center flex-shrink-0">
                <i class="pi pi-sparkles text-white text-sm"></i>
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-bold text-white leading-tight">Konsultan Pajak AI</p>
                <p class="text-[10px] text-white/70">Data keuangan {{ year }} sudah dibaca</p>
              </div>
              <div class="flex items-center gap-2">
                <button v-if="chatMessages.length" @click="chatMessages = []" title="Reset chat"
                  class="w-6 h-6 rounded-lg bg-white/15 hover:bg-white/25 flex items-center justify-center transition-colors">
                  <i class="pi pi-refresh text-white text-[10px]"></i>
                </button>
                <button @click="chatOpen = false"
                  class="w-6 h-6 rounded-lg bg-white/15 hover:bg-white/25 flex items-center justify-center transition-colors">
                  <i class="pi pi-times text-white text-[10px]"></i>
                </button>
              </div>
            </div>

            <!-- Messages -->
            <div ref="chatContainer" class="flex-1 overflow-y-auto p-4 space-y-3 bg-slate-50/60">

              <!-- Empty state -->
              <template v-if="!chatMessages.length">
                <div class="text-center pt-4 pb-2">
                  <p class="text-xs font-medium text-slate-600">Halo! Saya siap bantu soal pajak Anda.</p>
                  <p class="text-[11px] text-slate-400 mt-1">Pilih pertanyaan atau ketik sendiri.</p>
                </div>
                <div class="space-y-1.5">
                  <button v-for="q in SUGGESTED_QUESTIONS" :key="q"
                    @click="chatInput = q; sendChat()"
                    class="w-full text-left text-[11px] text-primary bg-white border border-primary/20 rounded-xl px-3 py-2.5 hover:bg-primary/5 transition-colors shadow-sm">
                    {{ q }}
                  </button>
                </div>
              </template>

              <!-- Messages -->
              <template v-else>
                <div v-for="(msg, i) in chatMessages" :key="i"
                  class="flex gap-2" :class="msg.role === 'user' ? 'justify-end' : 'justify-start'">
                  <div v-if="msg.role === 'assistant'"
                    class="w-6 h-6 rounded-lg bg-primary flex items-center justify-center flex-shrink-0 mt-0.5">
                    <i class="pi pi-sparkles text-white text-[9px]"></i>
                  </div>
                  <div class="max-w-[82%] rounded-2xl px-3 py-2 text-xs leading-relaxed whitespace-pre-wrap"
                    :class="msg.role === 'user'
                      ? 'bg-primary text-white rounded-tr-sm'
                      : 'bg-white border border-slate-100 text-slate-700 shadow-sm rounded-tl-sm'">
                    {{ msg.text }}
                  </div>
                </div>
                <!-- Loading dots -->
                <div v-if="chatLoading" class="flex gap-2 justify-start">
                  <div class="w-6 h-6 rounded-lg bg-primary flex items-center justify-center flex-shrink-0 mt-0.5">
                    <i class="pi pi-sparkles text-white text-[9px]"></i>
                  </div>
                  <div class="bg-white border border-slate-100 rounded-2xl rounded-tl-sm px-4 py-3 shadow-sm flex gap-1.5 items-center">
                    <span class="w-1.5 h-1.5 bg-slate-300 rounded-full animate-bounce" style="animation-delay:0ms"></span>
                    <span class="w-1.5 h-1.5 bg-slate-300 rounded-full animate-bounce" style="animation-delay:150ms"></span>
                    <span class="w-1.5 h-1.5 bg-slate-300 rounded-full animate-bounce" style="animation-delay:300ms"></span>
                  </div>
                </div>
              </template>
            </div>

            <!-- Rate limit banner -->
            <div v-if="rateLimitSecs > 0" class="px-4 py-2 bg-amber-50 border-t border-amber-200 flex items-center gap-2 flex-shrink-0">
              <i class="pi pi-clock text-amber-500 text-xs flex-shrink-0"></i>
              <p class="text-[11px] text-amber-700 flex-1">Quota penuh — coba lagi dalam <strong>{{ rateLimitSecs }}s</strong></p>
            </div>

            <!-- Input -->
            <div class="p-3 border-t border-slate-100 flex gap-2 flex-shrink-0 bg-white">
              <input v-model="chatInput" @keyup.enter="sendChat" :disabled="chatLoading || rateLimitSecs > 0"
                placeholder="Ketik pertanyaan..."
                class="flex-1 text-xs bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary disabled:opacity-50" />
              <button @click="sendChat" :disabled="chatLoading || !chatInput.trim() || rateLimitSecs > 0"
                class="w-9 h-9 bg-primary rounded-xl flex items-center justify-center flex-shrink-0 disabled:opacity-40 hover:bg-primary/90 transition-colors">
                <i class="pi pi-send text-white text-xs"></i>
              </button>
            </div>
          </div>
        </Transition>

        <!-- Bubble button -->
        <button @click="chatOpen = !chatOpen"
          class="w-14 h-14 bg-primary rounded-full shadow-lg hover:shadow-xl hover:scale-105 active:scale-95 transition-all flex items-center justify-center relative">
          <i class="pi text-white text-xl" :class="chatOpen ? 'pi-times' : 'pi-sparkles'"></i>
          <!-- Unread dot -->
          <span v-if="!chatOpen && chatMessages.length" class="absolute top-1 right-1 w-3 h-3 bg-red-500 rounded-full border-2 border-white"></span>
        </button>
      </div>
    </Teleport>

    <!-- Import Preview Modal -->
    <Transition name="modal-fade">
      <div v-if="showImportModal"
        class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 bg-black/50 backdrop-blur-sm"
        @click.self="closeImportModal">
        <div class="bg-white w-full sm:max-w-2xl rounded-t-3xl sm:rounded-2xl shadow-2xl overflow-hidden max-h-[90dvh] flex flex-col">

          <div class="sm:hidden flex justify-center pt-3 pb-1 flex-shrink-0">
            <div class="w-10 h-1 bg-slate-200 rounded-full"></div>
          </div>

          <div class="px-5 pt-4 pb-3 border-b border-slate-100 flex items-center justify-between flex-shrink-0">
            <div>
              <h3 class="text-base font-bold text-slate-800">Preview Import Mandiri</h3>
              <p class="text-xs text-slate-400 mt-0.5">{{ importPreviewRows.length }} transaksi terdeteksi</p>
            </div>
            <button @click="closeImportModal" class="w-8 h-8 flex items-center justify-center rounded-xl text-slate-400 hover:bg-slate-100">
              <i class="pi pi-times text-sm"></i>
            </button>
          </div>

          <!-- Hasil setelah import -->
          <div v-if="importResult" class="px-5 py-8 text-center flex-shrink-0">
            <div class="w-16 h-16 bg-emerald-50 rounded-full flex items-center justify-center mx-auto mb-4">
              <i class="pi pi-check-circle text-3xl text-emerald-500"></i>
            </div>
            <h4 class="text-lg font-bold text-slate-800">Import Selesai</h4>
            <p class="text-sm text-slate-500 mt-1">
              <span class="font-semibold text-emerald-600">{{ importResult.inserted }} transaksi</span> diimport,
              <span class="font-semibold text-slate-500">{{ importResult.skipped }} duplikat</span> dilewati
            </p>
            <button @click="closeImportModal" class="mt-5 btn-primary justify-center">Selesai</button>
          </div>

          <!-- Preview tabel -->
          <template v-else>
            <div class="overflow-y-auto flex-1">
              <table class="w-full text-sm border-collapse">
                <thead class="sticky top-0 bg-slate-50">
                  <tr class="border-b border-slate-100">
                    <th class="px-4 py-3 text-[10px] font-bold text-slate-400 uppercase tracking-widest text-left">Tanggal</th>
                    <th class="px-4 py-3 text-[10px] font-bold text-slate-400 uppercase tracking-widest text-left">Keterangan</th>
                    <th class="px-4 py-3 text-[10px] font-bold text-slate-400 uppercase tracking-widest text-center">Tipe</th>
                    <th class="px-4 py-3 text-[10px] font-bold text-slate-400 uppercase tracking-widest text-right">Nominal</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                  <tr v-for="(row, i) in importPreviewRows" :key="i" class="hover:bg-slate-50/50">
                    <td class="px-4 py-2.5 text-xs text-slate-500 whitespace-nowrap">{{ fmtDate(row.date) }}</td>
                    <td class="px-4 py-2.5 text-xs text-slate-700 max-w-[200px] truncate">{{ row.description || '—' }}</td>
                    <td class="px-4 py-2.5 text-center">
                      <span class="text-[10px] font-bold px-2 py-0.5 rounded-full"
                        :class="row.type === 'in' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-600'">
                        {{ row.type === 'in' ? 'Masuk' : 'Keluar' }}
                      </span>
                    </td>
                    <td class="px-4 py-2.5 text-right font-semibold text-xs"
                      :class="row.type === 'in' ? 'text-emerald-600' : 'text-red-500'">
                      {{ row.type === 'in' ? '+' : '−' }}{{ fmt(row.amount) }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="px-5 py-4 border-t border-slate-100 flex gap-2 flex-shrink-0">
              <button @click="commitImport" :disabled="importing"
                class="flex-1 btn-primary justify-center disabled:opacity-60">
                <i v-if="importing" class="pi pi-spin pi-spinner"></i>
                {{ importing ? 'Mengimport...' : `Import ${importPreviewRows.length} Transaksi` }}
              </button>
              <button @click="closeImportModal" class="px-4 border border-slate-200 rounded-xl text-sm font-semibold text-slate-500 hover:bg-slate-50">
                Batal
              </button>
            </div>
          </template>

        </div>
      </div>
    </Transition>

    <!-- FAB mobile -->
    <button @click="openForm()"
      class="md:hidden fixed bottom-6 right-5 z-40 w-14 h-14 bg-primary text-white rounded-full shadow-xl shadow-primary/30 flex items-center justify-center text-xl active:scale-95 transition-transform">
      <i class="pi pi-plus"></i>
    </button>

    <!-- Toast -->
    <Transition name="toast">
      <div v-if="toast.show"
        class="fixed bottom-6 left-1/2 -translate-x-1/2 z-[60] px-5 py-3 rounded-xl shadow-lg text-sm font-semibold text-white whitespace-nowrap"
        :class="toast.type === 'error' ? 'bg-red-500' : 'bg-emerald-500'">
        {{ toast.message }}
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.modal-fade-enter-active, .modal-fade-leave-active { transition: opacity 0.2s; }
.modal-fade-enter-from, .modal-fade-leave-to { opacity: 0; }

.chat-popup-enter-active { transition: opacity 0.2s, transform 0.2s; }
.chat-popup-leave-active { transition: opacity 0.15s, transform 0.15s; }
.chat-popup-enter-from, .chat-popup-leave-to { opacity: 0; transform: translateY(12px) scale(0.97); }

.slide-down-enter-active, .slide-down-leave-active { transition: all 0.25s ease; }
.slide-down-enter-from, .slide-down-leave-to { opacity: 0; transform: translateY(-8px); }

.toast-enter-active, .toast-leave-active { transition: all 0.3s ease; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translate(-50%, 1rem); }
</style>
