import axios from 'axios';
import type { ApiResponse, Sale } from '../types';

const api = axios.create({
    baseURL: '/',
    headers: { 'Accept': 'application/json' },
});

export const salesApi = {
    getAll:         ()       => api.get<ApiResponse<Sale[]>>('/sales'),
    getPendingItems: ()      => api.get<ApiResponse<{ items: { item_name: string; total_qty: number; stok: number | null; invoices: { invoice_number: string; recipient_name: string; qty: number }[] }[]; total_invoices: number }>>('/sales/pending-items'),
    getById: (id: number)   => api.get<ApiResponse<Sale>>(`/sales/${id}`),
    create:  (data: object) => api.post<ApiResponse<Sale>>('/sales', data),
    update:  (id: number, data: object)   => api.put<ApiResponse<Sale>>(`/sales/${id}`, data),
    pay:        (id: number, amount: number) => api.patch<ApiResponse<Sale>>(`/sales/${id}/pay`, { amount }),
    setPayment: (id: number, amount: number) => api.patch<ApiResponse<Sale>>(`/sales/${id}/set-payment`, { amount }),
    ship:        (id: number, data: { shipped_at?: string; notes?: string }) => api.patch<ApiResponse<Sale>>(`/sales/${id}/ship`, data),
    revertStock:      (id: number) => api.patch<ApiResponse<Sale>>(`/sales/${id}/revert-stock`, {}),
    remove:           (id: number) => api.delete<ApiResponse<null>>(`/sales/${id}`),
    uploadAttachment: (id: number, file: File) => {
        const form = new FormData();
        form.append('attachment', file);
        return api.post<ApiResponse<Sale>>(`/sales/${id}/attachment`, form, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
    },
    deleteAttachment: (id: number) => api.delete<ApiResponse<Sale>>(`/sales/${id}/attachment`),
};
