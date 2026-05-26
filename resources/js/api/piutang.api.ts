import axios from 'axios';

const api = axios.create({ baseURL: '/', headers: { 'Accept': 'application/json' } });

export interface ManualPiutang {
    id: number;
    name: string;
    amount: number;
    notes: string | null;
}

export const piutangApi = {
    getAll: () => api.get<{ success: boolean; data: ManualPiutang[] }>('/manual-piutang'),
    create: (data: { name: string; amount: number; notes?: string }) =>
        api.post<{ success: boolean; data: ManualPiutang }>('/manual-piutang', data),
    remove: (id: number) => api.delete(`/manual-piutang/${id}`),
};
