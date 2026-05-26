<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Department;
use App\Models\Position;
use App\Models\Employee;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(BudgetSeeder::class);
        // 1. Admin Account
        User::create([
            'name' => 'Super Admin MBG',
            'email' => 'admin@mbg.com',
            'password' => Hash::make('password'),
        ]);

        // 2. Employee Meta Data
        $dept1 = Department::create(['name' => 'Kitchen']);
        $dept2 = Department::create(['name' => 'Service']);
        $dept3 = Department::create(['name' => 'Management']);

        $pos1 = Position::create(['name' => 'Executive Chef']);
        $pos2 = Position::create(['name' => 'Sous Chef']);
        $pos3 = Position::create(['name' => 'Senior Waiter']);
        $pos4 = Position::create(['name' => 'Store Manager']);

        // 4. Dummy Employees
        Employee::create([
            'employee_id' => 'MBG-001',
            'first_name' => 'Ahmad',
            'last_name' => 'Subardjo',
            'email' => 'ahmad@mbg.com',
            'phone' => '08123456789',
            'position_id' => $pos1->id,
            'department_id' => $dept1->id,
            'hire_date' => '2023-01-10',
            'status' => 'ACTIVE',
            'base_salary' => 8500000
        ]);

        Employee::create([
            'employee_id' => 'MBG-002',
            'first_name' => 'Siti',
            'last_name' => 'Aminah',
            'email' => 'siti@mbg.com',
            'phone' => '08129876543',
            'position_id' => $pos3->id,
            'department_id' => $dept2->id,
            'hire_date' => '2023-03-15',
            'status' => 'ACTIVE',
            'base_salary' => 4500000
        ]);

        Employee::create([
            'employee_id' => 'MBG-003',
            'first_name' => 'Wilson',
            'last_name' => 'Putra',
            'email' => 'wilson@mbg.com',
            'phone' => '08121112223',
            'position_id' => $pos4->id,
            'department_id' => $dept3->id,
            'hire_date' => '2022-11-20',
            'status' => 'ACTIVE',
            'base_salary' => 12000000
        ]);
    }
}
