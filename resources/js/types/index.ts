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
    is_new_item?: boolean;
}

export interface Sale {
    id: number;
    invoice_number: string;
    recipient_name: string;
    recipient_address: string;
    invoice_date: string;
    notes: string;
    sender_name: string | null;
    sender_address: string | null;
    attachment_path: string | null;
    grand_total: number;
    paid_amount: number;
    status: 'belum_dikirim' | 'sudah_dikirim';
    shipped_at?: string | null;
    items: SaleItem[];
    created_at?: string;
}

export interface Income {
    id: number;
    income_date: string;
    source: string;
    description: string;
    amount: number;
    notes: string | null;
    receipt_path: string | null;
    recorded_by_id: number | null;
    created_at?: string;
}

export interface Expense {
    id: number;
    expense_date: string;
    category: string;
    description: string;
    amount: number;
    paid_by: string | null;
    notes: string | null;
    receipt_path: string | null;
    recorded_by_id: number | null;
    created_at?: string;
}

export interface ApiResponse<T> {
    success: boolean;
    message: string;
    data: T;
}
