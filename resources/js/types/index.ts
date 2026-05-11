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

export interface SaleItem {
    id?: number;
    sale_id?: number;
    item_name: string;
    description: string;
    qty: number;
    unit_price: number;
    total_price?: number;
    inventory_item_ids?: number[];
}

export interface Sale {
    id: number;
    invoice_number: string;
    recipient_name: string;
    recipient_address: string;
    invoice_date: string;
    notes: string;
    grand_total: number;
    paid_amount: number;
    status: 'belum_dikirim' | 'sudah_dikirim';
    items: SaleItem[];
    created_at?: string;
}

export interface ApiResponse<T> {
    success: boolean;
    message: string;
    data: T;
}
