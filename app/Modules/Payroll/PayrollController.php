<?php

namespace App\Modules\Payroll;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    protected $payrollService;

    public function __construct(PayrollService $payrollService)
    {
        $this->payrollService = $payrollService;
    }

    public function index(Request $request)
    {
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);
        
        $payrolls = $this->payrollService->getPayrolls($month, $year);
        return $this->sendResponse($payrolls, 'Payroll list retrieved');
    }

    public function generate(Request $request)
    {
        $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer',
        ]);

        $this->payrollService->generateMonthlyPayroll($request->month, $request->year);
        return $this->sendResponse(null, 'Payroll generated successfully');
    }

    public function markAsPaid($id)
    {
        $payroll = $this->payrollService->updateStatus($id, 'PAID');
        return $this->sendResponse($payroll, 'Payroll marked as paid');
    }
}
