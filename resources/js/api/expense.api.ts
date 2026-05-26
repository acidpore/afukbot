import axios from 'axios';
import type { ApiResponse, Expense } from '../types';

const api = axios.create({
    baseURL: '/',
    headers: { 'Accept': 'application/json' },
});

export const expenseApi = {
    getAll:  ()                           => api.get<ApiResponse<Expense[]>>('/expenses'),
    create:  (data: object)               => api.post<ApiResponse<Expense>>('/expenses', data),
    update:  (id: number, data: object)   => api.put<ApiResponse<Expense>>(`/expenses/${id}`, data),
    remove:  (id: number)                 => api.delete<ApiResponse<null>>(`/expenses/${id}`),
    summary: (month: string)              => api.get<ApiResponse<{ category: string; total: number }[]>>(`/expenses/summary?month=${month}`),
    import:  (file: File)                 => {
        const form = new FormData();
        form.append('file', file);
        return api.post<ApiResponse<{ imported: number; skipped: string[] }>>('/expenses/import', form, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
    },
    uploadReceipt: (id: number, file: File) => {
        const form = new FormData();
        form.append('receipt', file);
        return api.post<ApiResponse<Expense>>(`/expenses/${id}/receipt`, form, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
    },
    deleteReceipt: (id: number) => api.delete<ApiResponse<Expense>>(`/expenses/${id}/receipt`),
};
