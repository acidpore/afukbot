import axios from 'axios';

const api = axios.create({ baseURL: '/', headers: { 'Accept': 'application/json' } });

export const budgetApi = {
    // Categories
    getCategories: () => api.get('/budget/categories'),
    createCategory: (data: { name: string }) => api.post('/budget/categories', data),
    updateCategory: (id: number, data: { name: string }) => api.put(`/budget/categories/${id}`, data),
    deleteCategory: (id: number) => api.delete(`/budget/categories/${id}`),

    // Items
    createItem: (data: object) => api.post('/budget/items', data),
    updateItem: (id: number, data: object) => api.put(`/budget/items/${id}`, data),
    deleteItem: (id: number) => api.delete(`/budget/items/${id}`),

    bulkStoreItems: (data: { category_id: number; items: object[] }) =>
        api.post('/budget/items/bulk', data),

    // Transactions
    getTransactions: (params?: { month?: string; budget_item_id?: number }) =>
        api.get('/budget/transactions', { params }),
    createTransaction: (data: object) => api.post('/budget/transactions', data),
    updateTransaction: (id: number, data: object) => api.put(`/budget/transactions/${id}`, data),
    deleteTransaction: (id: number) => api.delete(`/budget/transactions/${id}`),
    uploadReceipt: (id: number, file: File) => {
        const fd = new FormData()
        fd.append('receipt', file)
        return api.post(`/budget/transactions/${id}/receipt`, fd)
    },

    // Dashboard
    getSummary: (month: string) => api.get('/budget/summary', { params: { month } }),
    getTrend: (months = 6) => api.get('/budget/trend', { params: { months } }),
}
