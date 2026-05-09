export interface Category {
    id: number;
    name: string;
}

export interface Item {
    id: number;
    name: string;
    description: string;
    category_id: number;
    quantity: number;
    unit: string;
    category?: Category;
}

export interface Employee {
    id: number;
    employee_id: string;
    first_name: string;
    last_name: string;
    email: string;
    phone: string;
    position_id: number;
    department_id: number;
    hire_date: string;
    status: 'ACTIVE' | 'INACTIVE';
    base_salary: number;
    department?: { name: string };
    position?: { name: string };
}

export interface ApiResponse<T> {
    success: boolean;
    message: string;
    data: T;
}
