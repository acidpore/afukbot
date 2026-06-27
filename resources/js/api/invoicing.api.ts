import axios from 'axios';

const api = axios.create({ baseURL: '/', headers: { 'Accept': 'application/json' } });

export const invoicingApi = {
    getCompanies: () => api.get('/companies'),
    createCompany: (fd: FormData) => api.post('/companies', fd),
    updateCompany: (id: number, fd: FormData) => api.post(`/companies/${id}`, fd), // fd berisi _method=PUT
    deleteCompany: (id: number) => api.delete(`/companies/${id}`),

    getInvoices: (params?: { company_id?: number; status?: string }) =>
        api.get('/invoices', { params }),
    getInvoice: (id: number) => api.get(`/invoices/${id}`),
    createInvoice: (data: object) => api.post('/invoices', data),
    updateInvoice: (id: number, data: object) => api.put(`/invoices/${id}`, data),
    deleteInvoice: (id: number) => api.delete(`/invoices/${id}`),
    previewDraft: (data: object) => api.post('/invoices/preview', data, { responseType: 'text' }),

    previewUrl: (id: number) => `/invoices/${id}/preview`,
    pdfUrl: (id: number) => `/invoices/${id}/pdf`,
};
