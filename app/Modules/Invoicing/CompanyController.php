<?php

namespace App\Modules\Invoicing;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CompanyController extends Controller
{
    public function index()
    {
        return response()->json(Company::orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $this->handleUploads($request, $data);
        return response()->json(Company::create($data), 201);
    }

    public function update(Request $request, int $id)
    {
        $company = Company::findOrFail($id);
        $data = $this->validateData($request, true);
        $this->handleUploads($request, $data);
        $company->update($data);
        return response()->json($company->fresh());
    }

    private function handleUploads(Request $request, array &$data): void
    {
        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('company-logos', 'public');
        }
        if ($request->hasFile('signature')) {
            $data['signature_path'] = $request->file('signature')->store('company-signatures', 'public');
        }
        unset($data['logo'], $data['signature']); // bukan kolom DB
    }

    public function destroy(int $id)
    {
        Company::findOrFail($id)->delete();
        return response()->json(null, 204);
    }

    private function validateData(Request $request, bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'required';
        return $request->validate([
            'name'             => "$req|string|max:150",
            'legal_name'       => 'nullable|string|max:200',
            'npwp'             => 'nullable|string|max:30',
            'address'          => 'nullable|string|max:500',
            'phone'            => 'nullable|string|max:30',
            'email'            => 'nullable|email|max:150',
            'bank_name'        => 'nullable|string|max:100',
            'bank_account'     => 'nullable|string|max:50',
            'bank_holder'      => 'nullable|string|max:150',
            'brand_primary'    => 'nullable|string|max:9',
            'brand_secondary'  => 'nullable|string|max:9',
            'font_family'      => 'nullable|string|max:100',
            'template_variant' => 'nullable|in:modern,classic,minimal,bold',
            'invoice_prefix'   => "$req|string|max:50",
            'logo'             => 'nullable|image|max:2048',
            'signature'        => 'nullable|image|max:2048',
        ]);
    }
}
