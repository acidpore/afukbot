import axios from 'axios';
import type { ApiResponse } from '../types';

const api = axios.create({
    baseURL: '/',
    headers: {
        'Accept': 'application/json',
    }
});

export const inventoryApi = {
    getItems: () => api.get<ApiResponse<any[]>>('/inventory/items'),
    getValuasi: () => api.get<ApiResponse<any>>('/inventory/valuasi'),
    getCategories: () => api.get<ApiResponse<any[]>>('/inventory/categories'),
    createCategory: (data: { name: string }) => api.post<ApiResponse<any>>('/inventory/categories', data),
    bulkCreateItems: (items: any[]) => api.post<ApiResponse<any>>('/inventory/items/bulk', { items }),
    createItem: (data: any) => api.post<ApiResponse<any>>('/inventory/items', data),
    updateItem: (id: number, data: any) => api.put<ApiResponse<any>>(`/inventory/items/${id}`, data),
    deleteItem: (id: number) => api.delete<ApiResponse<any>>(`/inventory/items/${id}`),
    
    adjustStock: (data: { item_id: number, type: 'IN' | 'OUT', quantity: number, notes?: string }) => 
        api.post<ApiResponse<any>>('/inventory/adjust', data),
    getTransactions: () => api.get<ApiResponse<any[]>>('/inventory/transactions'),
};
