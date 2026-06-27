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
    body { font-family: '{{ $company->font_family }}', DejaVu Sans, sans-serif; color: #111827; font-size: 12px; }
    .page { padding: 56px; }
    table { width: 100%; border-collapse: collapse; }
    .brand { font-size: 15px; font-weight: bold; letter-spacing: 1px; }
    .muted { color: #9ca3af; font-size: 10px; line-height: 1.6; }
    .title { font-size: 40px; font-weight: 300; letter-spacing: -1px; color: #111827; }
    .accent { color: {{ $company->brand_primary }}; }
    .label { font-size: 9px; text-transform: uppercase; letter-spacing: 2px; color: #9ca3af; margin-bottom: 3px; }
    .head td { vertical-align: top; padding-bottom: 40px; }
    .parties td { vertical-align: top; width: 50%; padding-bottom: 32px; }
    .items th { padding: 8px 0; text-align: left; font-size: 9px; text-transform: uppercase; letter-spacing: 1px;
        color: #9ca3af; border-bottom: 1px solid #e5e7eb; }
    .items td { padding: 11px 0; border-bottom: 1px solid #f3f4f6; }
    .r { text-align: right; }
    .totbox { width: 240px; margin-left: auto; margin-top: 16px; }
    .totbox td { padding: 5px 0; color: #6b7280; }
    .grand td { padding-top: 10px; font-size: 18px; font-weight: bold; color: #111827; }
    .grand .accent { color: {{ $company->brand_primary }}; }
    .foot { margin-top: 44px; } .foot td { width: 50%; vertical-align: top; }
</style></head><body><div class="page">
    <table class="head">
        <tr>
            <td>@if($logo)<img src="{{ $logo }}" style="max-height:44px;max-width:160px;margin-bottom:6px"><br>@endif<div class="brand">{{ $company->name }}</div><div class="muted">{{ $company->address }}</div>
                <div class="muted">@if($company->npwp)NPWP {{ $company->npwp }}@endif</div></td>
            <td class="r"><div class="title">Invoice</div>
                <div class="muted accent" style="font-size:11px">{{ $invoice->invoice_number }}</div></td>
        </tr>
    </table>

    <table class="parties">
        <tr>
            <td><div class="label">Kepada</div><strong>{{ $invoice->customer->name ?? '-' }}</strong>
                <div class="muted">{{ $invoice->customer->company_address ?? '' }}</div></td>
            <td class="r"><div class="label">Tanggal</div><strong>{{ $invoice->issue_date?->format('d M Y') }}</strong>
                @if($invoice->due_date)<div class="label" style="margin-top:8px">Jatuh Tempo</div><strong>{{ $invoice->due_date->format('d M Y') }}</strong>@endif</td>
        </tr>
    </table>

    <table class="items">
        <thead><tr><th style="width:50%">Deskripsi</th><th class="r">Qty</th><th class="r">Harga</th><th class="r">Jumlah</th></tr></thead>
        <tbody>@foreach($invoice->items as $item)
            <tr><td>{{ $item->description }}</td><td class="r">{{ $qty($item->qty) }}</td><td class="r">{{ $rp($item->unit_price) }}</td><td class="r">{{ $rp($item->line_total) }}</td></tr>
        @endforeach</tbody>
    </table>

    <table class="totbox">
        <tr><td>DPP</td><td class="r">{{ $rp($invoice->subtotal) }}</td></tr>
        @if($invoice->discount > 0)<tr><td>Diskon</td><td class="r">-{{ $rp($invoice->discount) }}</td></tr>@endif
        <tr><td>PPN {{ $pct($invoice->tax_percent) }}%</td><td class="r">{{ $rp($invoice->tax_amount) }}</td></tr>
        <tr class="grand"><td>Total</td><td class="r accent">{{ $rp($invoice->total) }}</td></tr>
    </table>

    @if($invoice->notes)<div class="muted" style="margin-top:20px">{{ $invoice->notes }}</div>@endif

    <table class="foot"><tr>
        <td>@if($company->bank_name)<div class="label">Pembayaran</div><div class="muted">{{ $company->bank_name }} &middot; {{ $company->bank_account }} &middot; a.n. {{ $company->bank_holder }}</div>@endif</td>
        <td class="r">@if($sign)<img src="{{ $sign }}" style="max-height:52px;max-width:160px"><br>@endif<span class="muted">{{ $company->name }}</span></td>
    </tr></table>
</div></body></html>
