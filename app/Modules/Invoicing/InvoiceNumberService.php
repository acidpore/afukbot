<?php

namespace App\Modules\Invoicing;

use App\Models\Company;
use Illuminate\Support\Facades\DB;

class InvoiceNumberService
{
    /** Nomor invoice dengan sequence per-company yang atomik. Format: {prefix}/{tahun}/{urut 3 digit}. */
    public function next(int $companyId): string
    {
        return DB::transaction(function () use ($companyId) {
            $company = Company::lockForUpdate()->findOrFail($companyId);
            $counter = $company->invoice_counter + 1;
            $company->update(['invoice_counter' => $counter]);

            return sprintf('%s/%d/%03d', $company->invoice_prefix, now()->year, $counter);
        });
    }
}
