<?php

namespace App\Modules\Attendance;

use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    public function getAttendanceByDate($date)
    {
        return Attendance::with('employee')
            ->whereDate('date', $date)
            ->get();
    }

    public function getEmployeesForAttendance($date)
    {
        // Get all active employees and join with attendance for that date if exists
        return Employee::where('status', 'ACTIVE')
            ->with(['attendances' => function($query) use ($date) {
                $query->whereDate('date', $date);
            }])
            ->get();
    }

    public function bulkStore(array $data)
    {
        return DB::transaction(function () use ($data) {
            foreach ($data['attendances'] as $item) {
                Attendance::updateOrCreate(
                    [
                        'employee_id' => $item['employee_id'],
                        'date' => $data['date']
                    ],
                    [
                        'check_in' => $item['check_in'] ?? null,
                        'check_out' => $item['check_out'] ?? null,
                        'status' => $item['status'],
                        'notes' => $item['notes'] ?? null
                    ]
                );
            }
            return true;
        });
    }

    public function updateAttendance($id, array $data)
    {
        $attendance = Attendance::findOrFail($id);
        $attendance->update($data);
        return $attendance;
    }
}
