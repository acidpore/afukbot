@php
    $rp = fn($n) => 'Rp ' . number_format((int) $n, 0, ',', '.');
    $qty = fn($q) => rtrim(rtrim(number_format($q, 2, ',', '.'), '0'), ',');
    $pct = fn($p) => rtrim(rtrim(number_format($p, 2, ',', '.'), '0'), ',');
    $media = function ($path) {
        if ($path && \Storage::disk('public')->exists($path)) {
            return 'data:' . \Storage::disk('public')->mimeType($path) . ';base64,' . base64_encode(\Storage::disk('public')->get($path));
        }
        return null;
    };
    $logo = $media($company->logo_path);
    $sign = $media($company->signature_path);
    $terbilang = function ($n) {
        $n = (int) abs($n);
        $a = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];
        $f = function ($x) use (&$f, $a) {
            if ($x < 12) return $a[$x];
            if ($x < 20) return $f($x - 10) . ' belas';
            if ($x < 100) return $f(intdiv($x, 10)) . ' puluh' . ($x % 10 ? ' ' . $f($x % 10) : '');
            if ($x < 200) return 'seratus' . ($x - 100 ? ' ' . $f($x - 100) : '');
            if ($x < 1000) return $f(intdiv($x, 100)) . ' ratus' . ($x % 100 ? ' ' . $f($x % 100) : '');
            if ($x < 2000) return 'seribu' . ($x - 1000 ? ' ' . $f($x - 1000) : '');
            if ($x < 1000000) return $f(intdiv($x, 1000)) . ' ribu' . ($x % 1000 ? ' ' . $f($x % 1000) : '');
            if ($x < 1000000000) return $f(intdiv($x, 1000000)) . ' juta' . ($x % 1000000 ? ' ' . $f($x % 1000000) : '');
            if ($x < 1000000000000) return $f(intdiv($x, 1000000000)) . ' miliar' . ($x % 1000000000 ? ' ' . $f($x % 1000000000) : '');
            return $f(intdiv($x, 1000000000000)) . ' triliun' . ($x % 1000000000000 ? ' ' . $f($x % 1000000000000) : '');
        };
        $w = trim(preg_replace('/\s+/', ' ', $f($n)));
        return ucwords($w === '' ? 'nol' : $w);
    };
    $primary = $company->brand_primary;
    $secondary = $company->brand_secondary;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->invoice_number }}</title>
    <style>
        @page { margin: 0; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: '{{ $company->font_family }}', DejaVu Sans, sans-serif; color: #334155; font-size: 12px; line-height: 1.45; }
        .sheet { padding: 0 0 36px; }
        .topbar { height: 8px; background: {{ $primary }}; }
        .pad { padding: 0 44px; }
        table { width: 100%; border-collapse: collapse; }
        .head { margin-top: 30px; }
        .head td { vertical-align: top; }
        .brand { font-size: 20px; font-weight: bold; color: {{ $primary }}; }
        .muted { color: #94a3b8; font-size: 10.5px; line-height: 1.55; }
        .invtitle { font-size: 34px; font-weight: bold; letter-spacing: 3px; color: {{ $primary }}; }
        .invnum { color: #64748b; font-size: 12px; margin-top: 2px; }
        .pill { display: inline-block; margin-top: 8px; padding: 4px 14px; border-radius: 999px;
            font-size: 10px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; color: #fff; background: {{ $secondary }}; }
        .rule { height: 2px; background: #e2e8f0; margin: 22px 0; }

        .cards { margin-bottom: 24px; }
        .cards td { vertical-align: top; width: 50%; }
        .card { background: #f8fafc; border: 1px solid #eef2f7; border-radius: 10px; padding: 14px 16px; }
        .card.r { margin-left: 14px; }
        .label { font-size: 9px; text-transform: uppercase; letter-spacing: 1.5px; color: #94a3b8; margin-bottom: 5px; }
        .strong { font-size: 14px; font-weight: bold; color: #1e293b; }

        .items thead th { background: {{ $primary }}; color: #fff; padding: 11px 12px; font-size: 10px;
            text-transform: uppercase; letter-spacing: .5px; text-align: left; }
        .items thead th.r, .items td.r { text-align: right; }
        .items thead th.c, .items td.c { text-align: center; }
        .items td { padding: 11px 12px; border-bottom: 1px solid #eef2f7; }
        .items tr.alt td { background: #f8fafc; }
        .items .desc { font-weight: 600; color: #1e293b; }

        .sumwrap { margin-top: 18px; }
        .sumwrap td { vertical-align: top; }
        .terbilang { background: #f8fafc; border: 1px solid #eef2f7; border-radius: 10px; padding: 12px 16px; width: 96%; }
        .terbilang .label { margin-bottom: 3px; }
        .terbilang .val { font-style: italic; color: #334155; font-weight: 600; }
        .totbox { width: 280px; margin-left: auto; }
        .totbox td { padding: 7px 4px; color: #64748b; }
        .totbox td.r { text-align: right; color: #334155; font-weight: 600; }
        .totbox .sub td { border-bottom: 1px solid #eef2f7; }
        .grand { background: {{ $primary }}; }
        .grand td { color: #fff; font-size: 16px; font-weight: bold; padding: 12px; }
        .grand td.r { color: #fff; }

        .notes { margin-top: 22px; font-size: 11px; color: #64748b; border-left: 3px solid {{ $secondary }}; padding-left: 12px; }
        .foot { margin-top: 30px; }
        .foot td { vertical-align: bottom; width: 50%; }
        .paybox { border: 1px dashed #cbd5e1; border-radius: 10px; padding: 14px 16px; width: 92%; }
        .paybox .bankname { font-size: 14px; font-weight: bold; color: #1e293b; }
        .paybox .acc { font-size: 16px; font-weight: bold; letter-spacing: 1px; color: {{ $primary }}; }
        .signname { margin-top: 6px; font-weight: bold; color: #1e293b; border-top: 1px solid #cbd5e1; padding-top: 5px; display: inline-block; min-width: 150px; text-align: center; }
        .thanks { text-align: center; margin-top: 34px; color: #94a3b8; font-size: 10.5px; border-top: 1px solid #eef2f7; padding-top: 12px; }
    </style>
</head>
<body>
<div class="sheet">
    <div class="topbar"></div>
    <div class="pad">
        <table class="head">
            <tr>
                <td style="width:55%">
                    @if($logo)<img src="{{ $logo }}" style="max-height:56px;max-width:210px;margin-bottom:10px">@endif
                    <div class="brand">{{ $company->name }}</div>
                    <div class="muted">{{ $company->legal_name }}@if($company->npwp)<br>NPWP {{ $company->npwp }}@endif</div>
                    <div class="muted">{{ $company->address }}</div>
                    <div class="muted">@if($company->phone){{ $company->phone }}@endif @if($company->email)&middot; {{ $company->email }}@endif</div>
                </td>
                <td class="r" style="text-align:right">
                    <div class="invtitle">INVOICE</div>
                    <div class="invnum">{{ $invoice->invoice_number }}</div>
                    <div><span class="pill">{{ strtoupper($invoice->status) }}</span></div>
                </td>
            </tr>
        </table>

        <div class="rule"></div>

        <table class="cards">
            <tr>
                <td>
                    <div class="card" style="margin-right:14px">
                        <div class="label">Ditagihkan Kepada</div>
                        <div class="strong">{{ $invoice->customer->name ?? '-' }}</div>
                        @if($invoice->customer && $invoice->customer->company_address)<div class="muted">{{ $invoice->customer->company_address }}</div>@endif
                        @if($invoice->customer && $invoice->customer->npwp)<div class="muted">NPWP: {{ $invoice->customer->npwp }}</div>@endif
                    </div>
                </td>
                <td>
                    <div class="card r">
                        <table>
                            <tr>
                                <td><div class="label">Tanggal Invoice</div><div class="strong">{{ $invoice->issue_date?->format('d M Y') }}</div></td>
                                @if($invoice->due_date)<td style="text-align:right"><div class="label">Jatuh Tempo</div><div class="strong">{{ $invoice->due_date->format('d M Y') }}</div></td>@endif
                            </tr>
                        </table>
                    </div>
                </td>
            </tr>
        </table>

        <table class="items">
            <thead>
                <tr>
                    <th class="c" style="width:6%">No</th>
                    <th style="width:46%">Deskripsi</th>
                    <th class="c" style="width:10%">Qty</th>
                    <th class="r" style="width:19%">Harga Satuan</th>
                    <th class="r" style="width:19%">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->items as $item)
                    <tr @if($loop->even) class="alt" @endif>
                        <td class="c">{{ $loop->iteration }}</td>
                        <td class="desc">{{ $item->description }}</td>
                        <td class="c">{{ $qty($item->qty) }}</td>
                        <td class="r">{{ $rp($item->unit_price) }}</td>
                        <td class="r">{{ $rp($item->line_total) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="sumwrap">
            <tr>
                <td style="width:52%">
                    <div class="terbilang">
                        <div class="label">Terbilang</div>
                        <div class="val">{{ $terbilang($invoice->total) }} Rupiah</div>
                    </div>
                </td>
                <td style="width:48%">
                    <table class="totbox">
                        <tr class="sub"><td>DPP (Dasar Pengenaan Pajak)</td><td class="r">{{ $rp($invoice->subtotal) }}</td></tr>
                        @if($invoice->discount > 0)<tr class="sub"><td>Diskon</td><td class="r">-{{ $rp($invoice->discount) }}</td></tr>@endif
                        <tr class="sub"><td>PPN {{ $pct($invoice->tax_percent) }}%</td><td class="r">{{ $rp($invoice->tax_amount) }}</td></tr>
                        <tr class="grand"><td>TOTAL</td><td class="r">{{ $rp($invoice->total) }}</td></tr>
                    </table>
                </td>
            </tr>
        </table>

        @if($invoice->notes)<div class="notes">{{ $invoice->notes }}</div>@endif

        @if($company->bank_name)
            <div class="paybox" style="width:100%;margin-top:22px">
                <div class="label">Pembayaran Transfer</div>
                <span class="bankname">{{ $company->bank_name }}</span> &middot;
                <span class="acc">{{ $company->bank_account }}</span>
                <span class="muted">&middot; a.n. {{ $company->bank_holder }}</span>
            </div>
        @endif

        <table class="foot">
            <tr>
                <td style="text-align:center">
                    <div class="label" style="margin-bottom:2px">Penerbit</div>
                    <div class="muted">Hormat kami,</div>
                    @if($sign)<img src="{{ $sign }}" style="max-height:62px;max-width:170px;margin:4px 0">@else<div style="height:58px"></div>@endif
                    <div class="signname">{{ $company->name }}</div>
                </td>
                <td style="text-align:center">
                    <div class="label" style="margin-bottom:2px">Penerima</div>
                    <div class="muted">Diterima oleh,</div>
                    <div style="height:62px"></div>
                    <div class="signname">{{ $invoice->customer->name ?? '(.....................)' }}</div>
                </td>
            </tr>
        </table>

        <div class="thanks">Terima kasih atas kerja samanya. Dokumen ini diterbitkan secara elektronik dan sah tanpa tanda tangan basah.</div>
    </div>
</div>
</body>
</html>
