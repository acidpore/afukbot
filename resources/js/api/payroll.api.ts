import axios from 'axios';
import type { ApiResponse } from '../types';

const api = axios.create({
    baseURL: '/',
    headers: {
        'Accept': 'application/json',
    }
});

export const payrollApi = {
    getPayrolls: (month: number, year: number) => api.get<ApiResponse<any[]>>(`/payrolls?month=${month}&year=${year}`),
    generatePayroll: (month: number, year: number) => api.post<ApiResponse<any>>('/payrolls/generate', { month, year }),
    markAsPaid: (id: number) => api.post<ApiResponse<any>>(`/payrolls/${id}/pay`),
};
