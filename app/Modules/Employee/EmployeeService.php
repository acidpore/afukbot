<?php

namespace App\Modules\Employee;

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\Position;

class EmployeeService
{
    public function getAllEmployees()
    {
        return Employee::with(['department', 'position'])->get();
    }

    public function getEmployeeById($id)
    {
        return Employee::with(['department', 'position', 'documents'])->findOrFail($id);
    }

    public function createEmployee(array $data)
    {
        $documents = $data['documents'] ?? [];
        unset($data['documents']);
        
        $employee = Employee::create($data);

        foreach ($documents as $file) {
            $path = $file->store('employee_documents', 'public');
            EmployeeDocument::create([
                'employee_id' => $employee->id,
                'document_type' => $file->getClientOriginalName(),
                'file_url' => $path
            ]);
        }

        return $employee;
    }

    public function updateEmployee($id, array $data)
    {
        $employee = Employee::findOrFail($id);
        
        $documents = $data['documents'] ?? [];
        unset($data['documents']);
        
        $employee->update($data);

        foreach ($documents as $file) {
            $path = $file->store('employee_documents', 'public');
            EmployeeDocument::create([
                'employee_id' => $employee->id,
                'document_type' => $file->getClientOriginalName(),
                'file_url' => $path
            ]);
        }

        return $employee;
    }

    public function deleteEmployee($id)
    {
        $employee = Employee::findOrFail($id);
        return $employee->delete();
    }

    public function getAllDepartments()
    {
        return Department::all();
    }

    public function getAllPositions()
    {
        return Position::all();
    }

    public function uploadDocument($id, array $data)
    {
        $data['employee_id'] = $id;
        return EmployeeDocument::create($data);
    }
}
