import axios from 'axios';
import type { ApiResponse, Employee } from '../types';

const api = axios.create({
    baseURL: '/',
    headers: {
        'Accept': 'application/json',
    }
});

export const employeeApi = {
    getEmployees: () => api.get<ApiResponse<Employee[]>>('/employees'),
    getEmployee: (id: number) => api.get<ApiResponse<Employee>>(`/employees/${id}`),
    getDepartments: () => api.get<ApiResponse<any[]>>('/employees/meta/departments'),
    getPositions: () => api.get<ApiResponse<any[]>>('/employees/meta/positions'),
    
    createEmployee: (data: any) => {
        const formData = new FormData();
        Object.keys(data).forEach(key => {
            if (key === 'documents' && Array.isArray(data[key])) {
                data[key].forEach((file: File, index: number) => {
                    formData.append(`documents[${index}]`, file);
                });
            } else if (data[key] !== null) {
                formData.append(key, data[key]);
            }
        });
        return api.post<ApiResponse<Employee>>('/employees', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });
    },
    
    updateEmployee: (id: number, data: any) => {
        // Laravel doesn't handle Multipart in PUT easily, use _method hack
        const formData = new FormData();
        formData.append('_method', 'PUT');
        Object.keys(data).forEach(key => {
            if (key === 'documents' && Array.isArray(data[key])) {
                data[key].forEach((file: File, index: number) => {
                    formData.append(`documents[${index}]`, file);
                });
            } else if (data[key] !== null) {
                formData.append(key, data[key]);
            }
        });
        return api.post<ApiResponse<Employee>>(`/employees/${id}`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });
    },
    
    deleteEmployee: (id: number) => api.delete<ApiResponse<any>>(`/employees/${id}`),
};
