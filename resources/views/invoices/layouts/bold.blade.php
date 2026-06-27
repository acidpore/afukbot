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
@endphp
<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><title>{{ $invoice->invoice_number }}</title>
<style>
    @page { margin: 0; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: '{{ $company->font_family }}', DejaVu Sans, sans-serif; color: #1f2937; font-size: 12px; }
    .banner { background: {{ $company->brand_primary }}; color: #fff; padding: 34px 40px; }
    table { width: 100%; border-collapse: collapse; }
    .banner .brand { font-size: 24px; font-weight: bold; }
    .banner .muted { color: rgba(255,255,255,.8); font-size: 11px; line-height: 1.5; }
    .banner .title { font-size: 30px; font-weight: bold; letter-spacing: 2px; }
    .banner .num { color: rgba(255,255,255,.85); font-size: 12px; }
    .page { padding: 32px 40px; }
    .label { font-size: 9px; text-transform: uppercase; letter-spacing: 1px; color: #9ca3af; }
    .muted { color: #6b7280; font-size: 11px; line-height: 1.5; }
    .parties td { vertical-align: top; width: 50%; padding-bottom: 22px; }
    .items th { background: #111827; color: #fff; padding: 9px 12px; text-align: left; font-size: 10px; text-transform: uppercase; }
    .items td { padding: 9px 12px; border-bottom: 1px solid #f1f5f9; }
    .r { text-align: right; }
    .totbox { width: 280px; margin-left: auto; margin-top: 16px; }
    .totbox td { padding: 6px 0; color: #4b5563; }
    .grand td { background: {{ $company->brand_primary }}; color: #fff; padding: 12px; font-size: 16px; font-weight: bold; }
    .foot { margin-top: 34px; } .foot td { width: 50%; vertical-align: top; }
    .signname { margin-top: 46px; border-top: 2px solid {{ $company->brand_primary }}; padding-top: 4px; display: inline-block; font-weight: bold; }
</style></head><body>
    <table class="banner">
        <tr>
            <td>
                @if($logo)<img src="{{ $logo }}" style="max-height:48px;max-width:180px;margin-bottom:6px">@endif
                <div class="brand">{{ $company->name }}</div>
                <div class="muted">{{ $company->address }} @if($company->npwp)&middot; NPWP {{ $company->npwp }}@endif</div></td>
            <td class="r"><div class="title">INVOICE</div><div class="num">{{ $invoice->invoice_number }} &middot; {{ strtoupper($invoice->status) }}</div></td>
        </tr>
    </table>
<div class="page">
    <table class="parties">
        <tr>
            <td><div class="label">Ditagihkan Kepada</div><strong>{{ $invoice->customer->name ?? '-' }}</strong>
                <div class="muted">{{ $invoice->customer->company_address ?? '' }}</div>
                @if($invoice->customer && $invoice->customer->npwp)<div class="muted">NPWP: {{ $invoice->customer->npwp }}</div>@endif</td>
            <td class="r"><div class="label">Tanggal</div><strong>{{ $invoice->issue_date?->format('d M Y') }}</strong>
                @if($invoice->due_date)<div class="label" style="margin-top:6px">Jatuh Tempo</div><strong>{{ $invoice->due_date->format('d M Y') }}</strong>@endif</td>
        </tr>
    </table>

    <table class="items">
        <thead><tr><th style="width:50%">Deskripsi</th><th class="r">Qty</th><th class="r">Harga Satuan</th><th class="r">Jumlah</th></tr></thead>
        <tbody>@foreach($invoice->items as $item)
            <tr><td>{{ $item->description }}</td><td class="r">{{ $qty($item->qty) }}</td><td class="r">{{ $rp($item->unit_price) }}</td><td class="r">{{ $rp($item->line_total) }}</td></tr>
        @endforeach</tbody>
    </table>

    <table class="totbox">
        <tr><td>DPP (Dasar Pengenaan Pajak)</td><td class="r">{{ $rp($invoice->subtotal) }}</td></tr>
        @if($invoice->discount > 0)<tr><td>Diskon</td><td class="r">-{{ $rp($invoice->discount) }}</td></tr>@endif
        <tr><td>PPN {{ $pct($invoice->tax_percent) }}%</td><td class="r">{{ $rp($invoice->tax_amount) }}</td></tr>
        <tr class="grand"><td>TOTAL</td><td class="r">{{ $rp($invoice->total) }}</td></tr>
    </table>

    @if($invoice->notes)<div class="muted" style="margin-top:18px">{{ $invoice->notes }}</div>@endif

    <table class="foot"><tr>
        <td>@if($company->bank_name)<div class="label">Pembayaran</div><div class="muted">{{ $company->bank_name }}<br>{{ $company->bank_account }}<br>a.n. {{ $company->bank_holder }}</div>@endif</td>
        <td class="r"><div class="muted">Hormat kami,</div>@if($sign)<img src="{{ $sign }}" style="max-height:56px;max-width:170px;margin-top:6px"><br>@endif<div class="signname">{{ $company->name }}</div></td>
    </tr></table>
</div></body></html>
