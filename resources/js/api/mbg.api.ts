import axios from 'axios';

const api = axios.create({
    baseURL: '/',
    headers: { 'Accept': 'application/json' },
});

export const mbgApi = {
    getDashboardOverview:  (params?: object) => api.get('/mbg/dashboard/overview', { params }),
    getUsers:              (params?: object) => api.get('/mbg/users', { params }),
    getOrganizations:      (params?: object) => api.get('/mbg/organizations', { params }),
    getSubscriptions:      (params?: object) => api.get('/mbg/subscriptions', { params }),
    getPlans:              (params?: object) => api.get('/mbg/plans', { params }),
    getRoles:              (params?: object) => api.get('/mbg/roles', { params }),
    getVendors:            (params?: object) => api.get('/mbg/vendors', { params }),
    getSales:              (params?: object) => api.get('/mbg/sales', { params }),
    getFoundations:        (params?: object) => api.get('/mbg/foundations', { params }),
    getAuditLogs:          (params?: object) => api.get('/mbg/audit-logs', { params }),
    getSystemSettings:     (params?: object) => api.get('/mbg/system-settings', { params }),
    getNotifications:      (params?: object) => api.get('/mbg/notifications', { params }),
    getKitchenEquipment:   (params?: object) => api.get('/mbg/kitchen-equipment', { params }),
    getMarketplaceSettings:(params?: object) => api.get('/mbg/marketplace-settings', { params }),
    getSalesPayrolls:      (params?: object) => api.get('/mbg/sales-payrolls', { params }),
};
