<?php

namespace App\Modules\Attendance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    protected $attendanceService;

    public function __construct(AttendanceService $attendanceService)
    {
        $this->attendanceService = $attendanceService;
    }

    public function index(Request $request)
    {
        $date = $request->get('date', now()->format('Y-m-d'));
        $attendances = $this->attendanceService->getEmployeesForAttendance($date);
        return $this->sendResponse($attendances, 'Attendance data retrieved');
    }

    public function store(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'attendances' => 'required|array',
            'attendances.*.employee_id' => 'required|exists:employees,id',
            'attendances.*.status' => 'required|in:PRESENT,ABSENT,LEAVE,SICK',
        ]);

        $this->attendanceService->bulkStore($request->all());
        return $this->sendResponse(null, 'Attendance recorded successfully');
    }

    public function update(Request $request, $id)
    {
        $attendance = $this->attendanceService->updateAttendance($id, $request->all());
        return $this->sendResponse($attendance, 'Attendance updated');
    }
}
