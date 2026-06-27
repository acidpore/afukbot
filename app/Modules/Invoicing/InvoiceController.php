<?php

namespace App\Modules\Invoicing;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class InvoiceController extends Controller
{
    public function __construct(private InvoiceService $service) {}

    public function index(Request $request)
    {
        return response()->json($this->service->list(
            $request->query('company_id') ? (int) $request->query('company_id') : null,
            $request->query('status')
        ));
    }

    public function show(int $id)
    {
        return response()->json($this->service->find($id));
    }

    public function store(Request $request)
    {
        return response()->json($this->service->create($this->validateData($request)), 201);
    }

    public function update(Request $request, int $id)
    {
        $data = $this->validateData($request, true);
        if ($request->filled('invoice_number')) {
            $request->validate(['invoice_number' => "string|max:100|unique:invoices,invoice_number,$id"]);
            $data['invoice_number'] = $request->input('invoice_number');
        }
        return response()->json($this->service->update($id, $data));
    }

    /** Preview HTML dari data yang belum disimpan. */
    public function previewDraft(Request $request)
    {
        return response($this->service->renderDraft($this->validateData($request)));
    }

    public function destroy(int $id)
    {
        $this->service->delete($id);
        return response()->json(null, 204);
    }

    /** Live preview: render HTML layout sesuai template_variant company. */
    public function preview(int $id)
    {
        $invoice = $this->service->find($id);
        $variant = $invoice->company->template_variant ?? 'modern';
        if (!view()->exists("invoices.layouts.$variant")) {
            $variant = 'modern'; // ponytail: layout lain nyusul, fallback ke modern
        }
        return view("invoices.layouts.$variant", ['invoice' => $invoice, 'company' => $invoice->company]);
    }

    /** Download PDF via dompdf (sudah terinstall, tanpa Chromium). */
    public function pdf(int $id)
    {
        $invoice = $this->service->find($id);
        $variant = $invoice->company->template_variant ?? 'modern';
        if (!view()->exists("invoices.layouts.$variant")) {
            $variant = 'modern';
        }
        return Pdf::loadView("invoices.layouts.$variant", ['invoice' => $invoice, 'company' => $invoice->company])
            ->download(str_replace('/', '-', $invoice->invoice_number) . '.pdf');
    }

    private function validateData(Request $request, bool $partial = false): array
    {
        $req      = $partial ? 'sometimes' : 'required';
        $itemsReq = $partial ? 'sometimes|array|min:1' : 'required|array|min:1';

        return $request->validate([
            'company_id'         => "$req|exists:companies,id",
            'customer_id'        => 'nullable|exists:invoice_customers,id',
            'customer'           => 'nullable|array',
            'customer.name'      => 'nullable|string|max:150',
            'customer.company_address' => 'nullable|string|max:500',
            'customer.phone'     => 'nullable|string|max:30',
            'customer.email'     => 'nullable|email|max:150',
            'customer.npwp'      => 'nullable|string|max:30',
            'issue_date'         => "$req|date",
            'due_date'           => 'nullable|date',
            'status'             => 'nullable|in:draft,sent,paid,overdue',
            'price_mode'         => 'nullable|in:exclusive,inclusive',
            'discount'           => 'nullable|integer|min:0',
            'tax_percent'        => 'nullable|numeric|min:0|max:100',
            'notes'              => 'nullable|string|max:1000',
            'items'              => $itemsReq,
            'items.*.description' => 'required|string|max:255',
            'items.*.qty'        => 'nullable|numeric|min:0',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);
    }
}
