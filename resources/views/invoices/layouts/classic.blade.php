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
    body { font-family: 'Georgia', 'DejaVu Serif', serif; color: #1f2937; font-size: 12px; }
    .page { padding: 40px; }
    table { width: 100%; border-collapse: collapse; }
    .top { text-align: center; border-bottom: 3px double {{ $company->brand_primary }}; padding-bottom: 16px; margin-bottom: 8px; }
    .brand { font-size: 22px; font-weight: bold; letter-spacing: 1px; color: {{ $company->brand_primary }}; }
    .muted { color: #6b7280; font-size: 11px; line-height: 1.5; }
    .title { text-align: center; font-size: 18px; letter-spacing: 6px; margin: 18px 0 4px; color: {{ $company->brand_primary }}; }
    .subnum { text-align: center; color: #6b7280; margin-bottom: 20px; }
    .parties td { vertical-align: top; width: 50%; padding-bottom: 16px; }
    .label { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #9ca3af; }
    .items th { border-top: 2px solid {{ $company->brand_primary }}; border-bottom: 1px solid {{ $company->brand_primary }};
        padding: 8px 10px; text-align: left; font-size: 11px; }
    .items td { padding: 8px 10px; border-bottom: 1px solid #e5e7eb; }
    .r { text-align: right; }
    .totbox { width: 270px; margin-left: auto; margin-top: 14px; }
    .totbox td { padding: 5px 0; color: #4b5563; }
    .grand td { border-top: 2px solid {{ $company->brand_primary }}; padding-top: 8px; font-size: 15px; font-weight: bold; color: {{ $company->brand_primary }}; }
    .foot { margin-top: 34px; } .foot td { width: 50%; vertical-align: top; }
    .signname { margin-top: 46px; border-top: 1px solid #9ca3af; padding-top: 4px; display: inline-block; font-weight: bold; }
</style></head><body><div class="page">
    <div class="top">
        @if($logo)<img src="{{ $logo }}" style="max-height:50px;max-width:180px;margin-bottom:6px">@endif
        <div class="brand">{{ $company->legal_name ?? $company->name }}</div>
        <div class="muted">{{ $company->address }}</div>
        <div class="muted">@if($company->phone){{ $company->phone }}@endif @if($company->email)&middot; {{ $company->email }}@endif @if($company->npwp)&middot; NPWP {{ $company->npwp }}@endif</div>
    </div>
    <div class="title">INVOICE</div>
    <div class="subnum">{{ $invoice->invoice_number }} &middot; {{ strtoupper($invoice->status) }}</div>

    <table class="parties">
        <tr>
            <td><div class="label">Kepada</div><strong>{{ $invoice->customer->name ?? '-' }}</strong>
                <div class="muted">{{ $invoice->customer->company_address ?? '' }}</div>
                @if($invoice->customer && $invoice->customer->npwp)<div class="muted">NPWP: {{ $invoice->customer->npwp }}</div>@endif</td>
            <td class="r"><div class="label">Tanggal</div><strong>{{ $invoice->issue_date?->format('d M Y') }}</strong>
                @if($invoice->due_date)<div class="label" style="margin-top:6px">Jatuh Tempo</div><strong>{{ $invoice->due_date->format('d M Y') }}</strong>@endif</td>
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
        <tr class="grand"><td>TOTAL</td><td class="r">{{ $rp($invoice->total) }}</td></tr>
    </table>

    @if($invoice->notes)<div class="muted" style="margin-top:18px">{{ $invoice->notes }}</div>@endif

    <table class="foot"><tr>
        <td>@if($company->bank_name)<div class="label">Pembayaran</div><div class="muted">{{ $company->bank_name }} &middot; {{ $company->bank_account }}<br>a.n. {{ $company->bank_holder }}</div>@endif</td>
        <td class="r"><div class="muted">Hormat kami,</div>@if($sign)<img src="{{ $sign }}" style="max-height:56px;max-width:170px;margin-top:6px"><br>@endif<div class="signname">{{ $company->name }}</div></td>
    </tr></table>
</div></body></html>
