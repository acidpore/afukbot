import axios from 'axios';
import type { ApiResponse } from '../types';

const api = axios.create({
    baseURL: '/',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    }
});

export const attendanceApi = {
    getAttendance: (date: string) => api.get<ApiResponse<any[]>>(`/attendances?date=${date}`),
    saveAttendance: (data: { date: string, attendances: any[] }) => api.post<ApiResponse<any>>('/attendances', data),
    updateAttendance: (id: number, data: any) => api.put<ApiResponse<any>>(`/attendances/${id}`, data),
};
