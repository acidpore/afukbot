<?php

namespace App\Modules\Invoicing;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceCustomer;
use App\Models\InvoiceItem;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function __construct(private InvoiceNumberService $numbers) {}

    public function list(?int $companyId = null, ?string $status = null)
    {
        return Invoice::with(['company:id,name,brand_primary', 'customer:id,name'])
            ->when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->when($status, fn($q) => $q->where('status', $status))
            ->orderByDesc('issue_date')->orderByDesc('id')
            ->get();
    }

    public function find(int $id): Invoice
    {
        return Invoice::with(['company', 'customer', 'items'])->findOrFail($id);
    }

    public function create(array $data): Invoice
    {
        return DB::transaction(function () use ($data) {
            $totals = $this->computeTotals($data['items'], $data['discount'] ?? 0, $data['tax_percent'] ?? 11, $data['price_mode'] ?? 'exclusive');

            $invoice = Invoice::create([
                'company_id'     => $data['company_id'],
                'customer_id'    => $this->resolveCustomerId($data),
                'invoice_number' => $this->numbers->next((int) $data['company_id']),
                'issue_date'     => $data['issue_date'],
                'due_date'       => $data['due_date'] ?? null,
                'status'         => $data['status'] ?? 'draft',
                'price_mode'     => $data['price_mode'] ?? 'exclusive',
                'discount'       => $data['discount'] ?? 0,
                'tax_percent'    => $data['tax_percent'] ?? 11,
                'notes'          => $data['notes'] ?? null,
                ...$totals,
            ]);

            $this->syncItems($invoice, $data['items']);
            return $this->find($invoice->id);
        });
    }

    public function update(int $id, array $data): Invoice
    {
        return DB::transaction(function () use ($id, $data) {
            $invoice = Invoice::findOrFail($id);

            $items      = $data['items'] ?? $invoice->items->toArray();
            $discount   = $data['discount']    ?? $invoice->discount;
            $taxPercent = $data['tax_percent'] ?? $invoice->tax_percent;
            $priceMode  = $data['price_mode']  ?? $invoice->price_mode;
            $totals     = $this->computeTotals($items, $discount, $taxPercent, $priceMode);

            $invoice->update([
                'company_id'     => $data['company_id']     ?? $invoice->company_id,
                'invoice_number' => $data['invoice_number'] ?? $invoice->invoice_number,
                'customer_id' => !empty($data['customer']['name']) || array_key_exists('customer_id', $data)
                    ? $this->resolveCustomerId($data)
                    : $invoice->customer_id,
                'issue_date'  => $data['issue_date']  ?? $invoice->issue_date,
                'due_date'    => array_key_exists('due_date', $data) ? $data['due_date'] : $invoice->due_date,
                'status'      => $data['status']      ?? $invoice->status,
                'price_mode'  => $priceMode,
                'discount'    => $discount,
                'tax_percent' => $taxPercent,
                'notes'       => array_key_exists('notes', $data) ? $data['notes'] : $invoice->notes,
                ...$totals,
            ]);

            if (isset($data['items'])) {
                $invoice->items()->delete();
                $this->syncItems($invoice, $data['items']);
            }

            return $this->find($invoice->id);
        });
    }

    public function delete(int $id): void
    {
        Invoice::findOrFail($id)->delete();
    }

    /** Render HTML invoice dari data form yang belum disimpan (untuk live preview). */
    public function renderDraft(array $data): string
    {
        $company = Company::findOrFail($data['company_id']);
        $totals  = $this->computeTotals($data['items'], $data['discount'] ?? 0, $data['tax_percent'] ?? 11, $data['price_mode'] ?? 'exclusive');

        $invoice = new Invoice(array_merge($totals, [
            'invoice_number' => sprintf('%s/%d/DRAFT', $company->invoice_prefix, now()->year),
            'issue_date'     => $data['issue_date'],
            'due_date'       => $data['due_date'] ?? null,
            'status'         => $data['status'] ?? 'draft',
            'price_mode'     => $data['price_mode'] ?? 'exclusive',
            'discount'       => $data['discount'] ?? 0,
            'tax_percent'    => $data['tax_percent'] ?? 11,
            'notes'          => $data['notes'] ?? null,
        ]));

        $invoice->setRelation('company', $company);
        $invoice->setRelation('customer', !empty($data['customer']['name']) ? new InvoiceCustomer($data['customer']) : null);
        $invoice->setRelation('items', collect($data['items'])->map(function ($it) {
            $qty   = (float) ($it['qty'] ?? 1);
            $price = (int) round($it['unit_price'] ?? 0);
            return new InvoiceItem([
                'description' => $it['description'],
                'qty'         => $qty,
                'unit_price'  => $price,
                'line_total'  => (int) round($qty * $price),
            ]);
        }));

        $variant = view()->exists("invoices.layouts.{$company->template_variant}") ? $company->template_variant : 'modern';
        return view("invoices.layouts.$variant", ['invoice' => $invoice, 'company' => $company])->render();
    }

    /** Pakai customer_id kalau ada, atau buat InvoiceCustomer baru dari objek customer inline. */
    private function resolveCustomerId(array $data): ?int
    {
        if (!empty($data['customer']['name'])) {
            return InvoiceCustomer::create($data['customer'])->id;
        }
        return $data['customer_id'] ?? null;
    }

    private function syncItems(Invoice $invoice, array $items): void
    {
        foreach ($items as $item) {
            $qty   = (float) ($item['qty'] ?? 1);
            $price = (int) round($item['unit_price'] ?? 0);
            $invoice->items()->create([
                'description' => $item['description'],
                'qty'         => $qty,
                'unit_price'  => $price,
                'line_total'  => (int) round($qty * $price),
            ]);
        }
    }

    /**
     * Hitung total di backend (sumber kebenaran).
     * - exclusive: harga item belum termasuk PPN, PPN ditambahkan di atas subtotal.
     * - inclusive: harga item sudah termasuk PPN — DPP & PPN dihitung mundur dari total.
     *   (mis. user mau total pas 350jt → DPP = 350jt / 1,11, PPN = sisanya)
     */
    public function computeTotals(array $items, int $discount, float $taxPercent, string $priceMode): array
    {
        $sumLines = 0;
        foreach ($items as $item) {
            $qty   = (float) ($item['qty'] ?? 1);
            $price = (int) round($item['unit_price'] ?? 0);
            $sumLines += (int) round($qty * $price);
        }

        $rate = $taxPercent / 100;

        if ($priceMode === 'inclusive') {
            $total     = max(0, $sumLines - $discount);
            $subtotal  = (int) round($total / (1 + $rate));   // DPP
            $taxAmount = $total - $subtotal;
        } else {
            $subtotal     = $sumLines;
            $afterDiscount = max(0, $subtotal - $discount);
            $taxAmount    = (int) round($afterDiscount * $rate);
            $total        = $afterDiscount + $taxAmount;
        }

        return [
            'subtotal'   => $subtotal,
            'tax_amount' => $taxAmount,
            'total'      => $total,
        ];
    }
}
