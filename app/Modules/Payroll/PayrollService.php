<?php

namespace App\Modules\Payroll;

use App\Models\Employee;
use App\Models\Payroll;
use App\Models\Attendance;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PayrollService
{
    public function getPayrolls($month, $year)
    {
        return Payroll::with('employee')
            ->whereYear('period_start', $year)
            ->whereMonth('period_start', $month)
            ->get();
    }

    public function generateMonthlyPayroll($month, $year)
    {
        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth();

        $employees = Employee::where('status', 'ACTIVE')->get();

        return DB::transaction(function () use ($employees, $startDate, $endDate) {
            foreach ($employees as $employee) {
                // Basic Calculation
                $baseSalary = $employee->base_salary;
                
                // Simple deduction logic based on absences
                $absentCount = Attendance::where('employee_id', $employee->id)
                    ->whereBetween('date', [$startDate, $endDate])
                    ->where('status', 'ABSENT')
                    ->count();
                
                // Deduction: 1 day salary for each absence (assuming 25 working days)
                $dailyRate = $baseSalary / 25;
                $deduction = $absentCount * $dailyRate;
                
                $netSalary = $baseSalary - $deduction;

                Payroll::updateOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'period_start' => $startDate->format('Y-m-d'),
                        'period_end' => $endDate->format('Y-m-d'),
                    ],
                    [
                        'base_salary' => $baseSalary,
                        'allowance' => 0, // Manual for now
                        'deduction' => $deduction,
                        'net_salary' => $netSalary,
                        'status' => 'PENDING'
                    ]
                );
            }
            return true;
        });
    }

    public function updateStatus($id, $status)
    {
        $payroll = Payroll::findOrFail($id);
        $payroll->status = $status;
        if ($status === 'PAID') {
            $payroll->payment_date = now();
        }
        $payroll->save();
        return $payroll;
    }
}
