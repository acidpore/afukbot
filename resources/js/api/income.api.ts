import axios from 'axios';
import type { ApiResponse, Income } from '../types';

const api = axios.create({
    baseURL: '/',
    headers: { 'Accept': 'application/json' },
});

export const incomeApi = {
    getAll: ()                          => api.get<ApiResponse<Income[]>>('/incomes'),
    create: (data: object)              => api.post<ApiResponse<Income>>('/incomes', data),
    update: (id: number, data: object)  => api.put<ApiResponse<Income>>(`/incomes/${id}`, data),
    remove: (id: number)                => api.delete<ApiResponse<null>>(`/incomes/${id}`),
    import: (file: File) => {
        const form = new FormData();
        form.append('file', file);
        return api.post<ApiResponse<{ imported: number; skipped: string[] }>>('/incomes/import', form, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
    },
    uploadReceipt: (id: number, file: File) => {
        const form = new FormData();
        form.append('receipt', file);
        return api.post<ApiResponse<Income>>(`/incomes/${id}/receipt`, form, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
    },
    deleteReceipt: (id: number) => api.delete<ApiResponse<Income>>(`/incomes/${id}/receipt`),
};
