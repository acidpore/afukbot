from reportlab.lib.pagesizes import A4
from reportlab.lib import colors
from reportlab.lib.units import mm
from reportlab.platypus import SimpleDocTemplate, Table, TableStyle, Paragraph, Spacer, HRFlowable
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.enums import TA_LEFT, TA_CENTER, TA_RIGHT
from reportlab.pdfgen import canvas as canvas_module

NAVY      = colors.HexColor('#1D3557')
WHITE     = colors.white
BLACK     = colors.black
GRAY_LINE = colors.HexColor('#AAAAAA')
BG_PO     = colors.HexColor('#EEF2F7')

W, H = A4  # 595.27 x 841.89 pts
LM = RM = 20 * mm
TM = 15 * mm
BM = 15 * mm
CW = W - LM - RM   # usable width = ~555 pt

styles = getSampleStyleSheet()

def ps(name, **kw):
    kw.setdefault('parent', styles['Normal'])
    kw.setdefault('fontName', 'Helvetica')
    kw.setdefault('fontSize', 8)
    kw.setdefault('leading', kw.get('fontSize', 8) * 1.35)
    kw.setdefault('textColor', BLACK)
    return ParagraphStyle(name, **kw)

S = {
    'co_name'  : ps('co_name',   fontSize=22, fontName='Helvetica-Bold', textColor=NAVY, alignment=TA_CENTER, leading=28),
    'co_addr'  : ps('co_addr',   fontSize=8,  alignment=TA_CENTER, leading=11, textColor=colors.HexColor('#333333')),
    'lbl'      : ps('lbl',       fontSize=8,  textColor=colors.HexColor('#666666')),
    'val'      : ps('val',       fontSize=9,  fontName='Helvetica-Bold'),
    'po_title' : ps('po_title',  fontSize=15, fontName='Helvetica-Bold', textColor=NAVY, leading=20),
    'po_lbl'   : ps('po_lbl',    fontSize=8,  textColor=colors.HexColor('#555555')),
    'po_val'   : ps('po_val',    fontSize=8,  fontName='Helvetica-Bold'),
    'th'       : ps('th',        fontSize=9,  fontName='Helvetica-Bold', textColor=WHITE, alignment=TA_CENTER, leading=12),
    'td_c'     : ps('td_c',      fontSize=9,  alignment=TA_CENTER),
    'td_l'     : ps('td_l',      fontSize=9,  alignment=TA_LEFT),
    'td_r'     : ps('td_r',      fontSize=9,  alignment=TA_RIGHT),
    'sm'       : ps('sm',        fontSize=8),
    'sm_bold'  : ps('sm_bold',   fontSize=8,  fontName='Helvetica-Bold'),
    'sm_r'     : ps('sm_r',      fontSize=8,  alignment=TA_RIGHT),
    'wh'       : ps('wh',        fontSize=9,  fontName='Helvetica-Bold', textColor=WHITE),
    'wh_r'     : ps('wh_r',      fontSize=9,  fontName='Helvetica-Bold', textColor=WHITE, alignment=TA_RIGHT),
    'pg'       : ps('pg',        fontSize=8,  alignment=TA_CENTER, textColor=colors.HexColor('#888888')),
}

# ── Data ──────────────────────────────────────────────────
qty   = 8000
harga = 35000
total = qty * harga

def fmt(n): return f"{n:,}".replace(",", ".")

story = []

# ─────────────────────────────────────────────────────────
# 1. COMPANY HEADER  (title on its own line, then address)
# ─────────────────────────────────────────────────────────
story.append(Paragraph("PT Indo Pangan", S['co_name']))
story.append(Spacer(1, 1*mm))
story.append(Paragraph(
    "Ruko Puncak CBD, Jalan Dukuh Kramat I No.36 Blok 7E OFT, Jajar Tunggal, Kec. Wiyung<br/>"
    "Kota Surabaya Jawa Timur<br/>Indonesia",
    S['co_addr']
))
story.append(Spacer(1, 3*mm))
story.append(HRFlowable(width="100%", thickness=1.2, color=NAVY))
story.append(Spacer(1, 4*mm))

# ─────────────────────────────────────────────────────────
# 2. KEPADA / DIKIRIM KE  |  PESANAN PEMBELIAN BOX
# ─────────────────────────────────────────────────────────
# Left side: two mini-columns (Kepada, Dikirim ke)
left_w  = CW * 0.58   # ~322 pt
right_w = CW * 0.42   # ~233 pt

# PO info inner table
po_inner = Table([
    [Paragraph("Nomor",        S['po_lbl']), Paragraph(":",  S['po_lbl']), Paragraph("IP-080526/00988", S['po_val'])],
    [Paragraph("Tanggal",      S['po_lbl']), Paragraph(":",  S['po_lbl']), Paragraph("08 – 05 -2026",   S['po_val'])],
    [Paragraph("Tanggal Kirim:", S['po_lbl']),Paragraph(":", S['po_lbl']), Paragraph("-",                S['po_val'])],
], colWidths=[28*mm, 4*mm, right_w - 32*mm - 8])
po_inner.setStyle(TableStyle([
    ('TOPPADDING',    (0,0), (-1,-1), 1.5),
    ('BOTTOMPADDING', (0,0), (-1,-1), 1.5),
    ('LEFTPADDING',   (0,0), (-1,-1), 0),
    ('RIGHTPADDING',  (0,0), (-1,-1), 0),
    ('VALIGN',        (0,0), (-1,-1), 'MIDDLE'),
]))

po_box = Table([
    [Paragraph("Pesanan Pembelian", S['po_title'])],
    [po_inner],
], colWidths=[right_w - 8])
po_box.setStyle(TableStyle([
    ('BOX',           (0,0), (-1,-1), 0.8, NAVY),
    ('BACKGROUND',    (0,0), (-1,-1), BG_PO),
    ('TOPPADDING',    (0,0), (-1,-1), 5),
    ('BOTTOMPADDING', (0,0), (-1,-1), 5),
    ('LEFTPADDING',   (0,0), (-1,-1), 6),
    ('RIGHTPADDING',  (0,0), (-1,-1), 6),
    ('VALIGN',        (0,0), (-1,-1), 'TOP'),
]))

# Kepada / Dikirim ke side
left_sub = Table([
    [Paragraph("Kepada", S['lbl']),    Paragraph("Dikirim ke", S['lbl'])],
    [Paragraph("Abdul Amad",   S['val']),    Paragraph("Probolinggo",   S['val'])],
], colWidths=[left_w/2, left_w/2])
left_sub.setStyle(TableStyle([
    ('TOPPADDING',    (0,0), (-1,-1), 2),
    ('BOTTOMPADDING', (0,0), (-1,-1), 2),
    ('LEFTPADDING',   (0,0), (-1,-1), 0),
    ('RIGHTPADDING',  (0,0), (-1,-1), 0),
    ('VALIGN',        (0,0), (-1,-1), 'TOP'),
]))

header_row = Table(
    [[left_sub, po_box]],
    colWidths=[left_w, right_w]
)
header_row.setStyle(TableStyle([
    ('VALIGN',        (0,0), (-1,-1), 'TOP'),
    ('TOPPADDING',    (0,0), (-1,-1), 0),
    ('BOTTOMPADDING', (0,0), (-1,-1), 0),
    ('LEFTPADDING',   (0,0), (-1,-1), 0),
    ('RIGHTPADDING',  (0,0), (0,0),   6),   # gap between left & right
]))
story.append(header_row)
story.append(Spacer(1, 5*mm))

# ─────────────────────────────────────────────────────────
# 3. ITEM TABLE
# ─────────────────────────────────────────────────────────
col_w = [
    CW * 0.10,  # Kode Barang
    CW * 0.28,  # Nama Barang
    CW * 0.10,  # Kts
    CW * 0.18,  # @Harga
    CW * 0.17,  # Keterangan
    CW * 0.17,  # Total
]

item_data = [
    [Paragraph("Kode Barang", S['th']),
     Paragraph("Nama Barang",  S['th']),
     Paragraph("Kts.",         S['th']),
     Paragraph("@Harga",       S['th']),
     Paragraph("Keterangan",   S['th']),
     Paragraph("Total",        S['th'])],
    [Paragraph("1",                      S['td_c']),
     Paragraph("Foodtray SUS 304",       S['td_l']),
     Paragraph(fmt(qty),                 S['td_c']),
     Paragraph(f"Rp {fmt(harga)}",       S['td_r']),
     Paragraph("-",                      S['td_c']),
     Paragraph(f"Rp {fmt(total)}",       S['td_r'])],
]

item_tbl = Table(item_data, colWidths=col_w)
item_tbl.setStyle(TableStyle([
    ('BACKGROUND',    (0,0), (-1,0), NAVY),
    ('TEXTCOLOR',     (0,0), (-1,0), WHITE),
    ('FONTNAME',      (0,0), (-1,0), 'Helvetica-Bold'),
    ('FONTSIZE',      (0,0), (-1,-1), 9),
    ('BACKGROUND',    (0,1), (-1,1), WHITE),
    ('BOX',           (0,0), (-1,-1), 0.5, colors.HexColor('#BBBBBB')),
    ('INNERGRID',     (0,0), (-1,-1), 0.5, colors.HexColor('#BBBBBB')),
    ('TOPPADDING',    (0,0), (-1,-1), 7),
    ('BOTTOMPADDING', (0,0), (-1,-1), 7),
    ('LEFTPADDING',   (0,0), (-1,-1), 5),
    ('RIGHTPADDING',  (0,0), (-1,-1), 5),
    ('VALIGN',        (0,0), (-1,-1), 'MIDDLE'),
    ('ALIGN',         (0,0), (-1,0), 'CENTER'),
]))
story.append(item_tbl)
story.append(Spacer(1, 6*mm))

# ─────────────────────────────────────────────────────────
# 4. BOTTOM: left notes  |  right summary
# ─────────────────────────────────────────────────────────
note_w    = CW * 0.52
summary_w = CW * 0.48

# Summary box (Sub Total / Diskon / PPN)
sum_col = [summary_w * 0.5, summary_w * 0.5]
summary_tbl = Table([
    [Paragraph("Sub Total", S['sm']),  Paragraph(f"IDR {fmt(total)}", S['sm_r'])],
    [Paragraph("Diskon",    S['sm']),  Paragraph("",                  S['sm_r'])],
    [Paragraph("PPN (0%)",  S['sm']),  Paragraph("",                  S['sm_r'])],
], colWidths=sum_col)
summary_tbl.setStyle(TableStyle([
    ('BOX',           (0,0), (-1,-1), 0.5, NAVY),
    ('TOPPADDING',    (0,0), (-1,-1), 4),
    ('BOTTOMPADDING', (0,0), (-1,-1), 4),
    ('LEFTPADDING',   (0,0), (-1,-1), 5),
    ('RIGHTPADDING',  (0,0), (-1,-1), 5),
    ('FONTSIZE',      (0,0), (-1,-1), 8),
]))

total_tbl = Table([
    [Paragraph("Total :", S['wh']), Paragraph(f"IDR {fmt(total)}", S['wh_r'])]
], colWidths=sum_col)
total_tbl.setStyle(TableStyle([
    ('BACKGROUND',    (0,0), (-1,-1), NAVY),
    ('TOPPADDING',    (0,0), (-1,-1), 6),
    ('BOTTOMPADDING', (0,0), (-1,-1), 6),
    ('LEFTPADDING',   (0,0), (-1,-1), 5),
    ('RIGHTPADDING',  (0,0), (-1,-1), 5),
]))

right_block = Table([
    [summary_tbl],
    [Spacer(1, 0)],
    [total_tbl],
], colWidths=[summary_w])
right_block.setStyle(TableStyle([
    ('TOPPADDING',    (0,0), (-1,-1), 0),
    ('BOTTOMPADDING', (0,0), (-1,-1), 0),
    ('LEFTPADDING',   (0,0), (-1,-1), 0),
    ('RIGHTPADDING',  (0,0), (-1,-1), 0),
]))

# Left notes block
def hr(): return HRFlowable(width=note_w - 4, thickness=0.5, color=GRAY_LINE)

left_block = Table([
    [Paragraph("Keterangan :", S['sm'])],
    [Spacer(1, 6*mm)],
    [hr()],
    [Spacer(1, 2*mm)],
    [Paragraph("Top :", S['sm'])],
    [Spacer(1, 6*mm)],
    [hr()],
    [Spacer(1, 3*mm)],
    [Paragraph("RONALDO CHANDRA SUSANTO", S['sm_bold'])],
    [Spacer(1, 1.5*mm)],
    [Paragraph("Bank Mandiri", S['sm'])],
    [Spacer(1, 1.5*mm)],
    [Paragraph("<b>1430033951870</b>", S['sm'])],
    [Spacer(1, 3*mm)],
    [hr()],
], colWidths=[note_w])
left_block.setStyle(TableStyle([
    ('TOPPADDING',    (0,0), (-1,-1), 0),
    ('BOTTOMPADDING', (0,0), (-1,-1), 0),
    ('LEFTPADDING',   (0,0), (-1,-1), 0),
    ('RIGHTPADDING',  (0,0), (-1,-1), 0),
]))

bottom_row = Table([[left_block, right_block]], colWidths=[note_w, summary_w])
bottom_row.setStyle(TableStyle([
    ('VALIGN',        (0,0), (-1,-1), 'TOP'),
    ('TOPPADDING',    (0,0), (-1,-1), 0),
    ('BOTTOMPADDING', (0,0), (-1,-1), 0),
    ('LEFTPADDING',   (0,0), (-1,-1), 0),
    ('RIGHTPADDING',  (0,0), (-1,-1), 0),
]))
story.append(bottom_row)
story.append(Spacer(1, 5*mm))

# ─────────────────────────────────────────────────────────
# 5. DP NOTE + ORDERED BY
# ─────────────────────────────────────────────────────────
story.append(Paragraph("<b>DP 50% PELUNASAN</b>", S['sm']))
story.append(Spacer(1, 1*mm))
story.append(Paragraph("GARANSI  SUS 304 ANTI MAGNET LOLOS UJI LAB DAN VERIFIKASI MBG", S['sm']))
story.append(Spacer(1, 6*mm))
sig_inner_w = 45*mm
left_pad = 10*mm

ordered_style = ps('ordered', fontSize=8, alignment=TA_LEFT)
name_style    = ps('name_sig', fontSize=8, alignment=TA_LEFT)

sig_block = Table([
    [Paragraph("Ordered by :", ordered_style), ''],
    [Spacer(1, 14*mm),                         ''],
    [HRFlowable(width=sig_inner_w, thickness=0.5, color=BLACK), ''],
    [Paragraph("Abdul Amad", name_style),      ''],
], colWidths=[left_pad + sig_inner_w, CW - left_pad - sig_inner_w])
sig_block.setStyle(TableStyle([
    ('VALIGN',        (0,0), (-1,-1), 'TOP'),
    ('TOPPADDING',    (0,0), (-1,-1), 0),
    ('BOTTOMPADDING', (0,0), (-1,-1), 0),
    ('LEFTPADDING',   (0,0), (-1,-1), left_pad),
    ('RIGHTPADDING',  (0,0), (-1,-1), 0),
    ('SPAN',          (0,0), (1,0)),
    ('SPAN',          (0,1), (1,1)),
    ('SPAN',          (0,2), (1,2)),
    ('SPAN',          (0,3), (1,3)),
]))
story.append(sig_block)

# ─────────────────────────────────────────────────────────
# 6. PAGE NUMBER
# ─────────────────────────────────────────────────────────
story.append(Spacer(1, 8*mm))
story.append(Paragraph("1", S['pg']))

# Build
doc = SimpleDocTemplate(
    "/mnt/user-data/outputs/Invoice_Pak_Afif_Updated.pdf",
    pagesize=A4,
    leftMargin=LM, rightMargin=RM,
    topMargin=TM,  bottomMargin=BM,
)
doc.build(story)
print("Done!")
