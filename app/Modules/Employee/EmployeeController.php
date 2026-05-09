<?php

namespace App\Modules\Employee;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    protected $employeeService;

    public function __construct(EmployeeService $employeeService)
    {
        $this->employeeService = $employeeService;
    }

    public function index()
    {
        $employees = $this->employeeService->getAllEmployees();
        return $this->sendResponse($employees, 'Employees retrieved successfully');
    }

    public function store(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|string|unique:employees',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'email' => 'required|email|unique:employees',
            'position_id' => 'required|exists:positions,id',
            'department_id' => 'required|exists:departments,id',
            'hire_date' => 'required|date',
            'base_salary' => 'required|numeric',
            'documents.*' => 'nullable|file|max:2048',
        ]);

        $employee = $this->employeeService->createEmployee($request->all());
        return $this->sendResponse($employee, 'Employee created successfully', 201);
    }

    public function show($id)
    {
        $employee = $this->employeeService->getEmployeeById($id);
        return $this->sendResponse($employee, 'Employee retrieved successfully');
    }

    public function update(Request $request, $id)
    {
        $employee = $this->employeeService->updateEmployee($id, $request->all());
        return $this->sendResponse($employee, 'Employee updated successfully');
    }

    public function destroy($id)
    {
        $this->employeeService->deleteEmployee($id);
        return $this->sendResponse(null, 'Employee deleted successfully');
    }

    public function departments()
    {
        return $this->sendResponse($this->employeeService->getAllDepartments(), 'Departments retrieved');
    }

    public function positions()
    {
        return $this->sendResponse($this->employeeService->getAllPositions(), 'Positions retrieved');
    }

    public function uploadDocument(Request $request, $id)
    {
        $request->validate([
            'document_type' => 'required|string',
            'file_url' => 'required|string', // Assuming file is already uploaded to storage
        ]);

        $doc = $this->employeeService->uploadDocument($id, $request->all());
        return $this->sendResponse($doc, 'Document uploaded successfully');
    }
}
