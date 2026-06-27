import axios from 'axios';

const api = axios.create({ baseURL: '/', headers: { 'Accept': 'application/json' } });

export const budgetApi = {
    // Periods (RAB per periode)
    getPeriods: () => api.get('/budget/periods'),
    createPeriod: (data: { name: string; start_date: string; end_date: string }) =>
        api.post('/budget/periods', data),
    deletePeriod: (id: number) => api.delete(`/budget/periods/${id}`),

    // Proposals (Pengajuan)
    getProposals: (periodId?: number) => api.get('/budget/proposals', { params: { period_id: periodId } }),
    createProposal: (data: object) => api.post('/budget/proposals', data),
    updateProposal: (id: number, data: object) => api.put(`/budget/proposals/${id}`, data),
    deleteProposal: (id: number) => api.delete(`/budget/proposals/${id}`),

    // Categories
    getCategories: (periodId?: number) => api.get('/budget/categories', { params: { period_id: periodId } }),
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
    getTransactions: (params?: { month?: string; budget_item_id?: number; date?: string; period_id?: number }) =>
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
    getSummary: (periodId?: number) => api.get('/budget/summary', { params: { period_id: periodId } }),
    getTrend: (months = 6) => api.get('/budget/trend', { params: { months } }),

    // Period setting (periode aktif)
    getPeriodSetting: () => api.get('/budget/period-setting'),
    setPeriodSetting: (data: { start_date: string; end_date: string }) =>
        api.put('/budget/period-setting', data),
}
