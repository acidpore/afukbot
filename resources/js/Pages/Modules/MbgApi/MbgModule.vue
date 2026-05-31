<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import { mbgApi } from '@/api/mbg.api';

const TABS = [
    { id: 'overview',             label: 'Overview',             fetch: () => mbgApi.getDashboardOverview() },
    { id: 'users',                label: 'Users',                fetch: () => mbgApi.getUsers() },
    { id: 'organizations',        label: 'Organisasi',           fetch: () => mbgApi.getOrganizations() },
    { id: 'subscriptions',        label: 'Langganan',            fetch: () => mbgApi.getSubscriptions() },
    { id: 'plans',                label: 'Paket',                fetch: () => mbgApi.getPlans() },
    { id: 'roles',                label: 'Roles',                fetch: () => mbgApi.getRoles() },
    { id: 'vendors',              label: 'Vendor',               fetch: () => mbgApi.getVendors() },
    { id: 'sales',                label: 'Sales',                fetch: () => mbgApi.getSales() },
    { id: 'foundations',          label: 'Yayasan',              fetch: () => mbgApi.getFoundations() },
    { id: 'audit-logs',           label: 'Audit Log',            fetch: () => mbgApi.getAuditLogs() },
    { id: 'system-settings',      label: 'System Settings',      fetch: () => mbgApi.getSystemSettings() },
    { id: 'notifications',        label: 'Notifikasi',           fetch: () => mbgApi.getNotifications() },
    { id: 'kitchen-equipment',    label: 'Peralatan Dapur',      fetch: () => mbgApi.getKitchenEquipment() },
    { id: 'marketplace-settings', label: 'Marketplace Settings', fetch: () => mbgApi.getMarketplaceSettings() },
    { id: 'sales-payrolls',       label: 'Penggajian Sales',     fetch: () => mbgApi.getSalesPayrolls() },
];

const activeTab = ref('overview');
const data      = ref<any>(null);
const loading   = ref(false);
const error     = ref<string | null>(null);

async function load() {
    const tab = TABS.find(t => t.id === activeTab.value);
    if (!tab) return;

    loading.value = true;
    error.value   = null;
    data.value    = null;

    try {
        const res  = await tab.fetch();
        data.value = res.data?.data ?? res.data;
    } catch (e: any) {
        error.value = e?.response?.data?.meta?.message ?? e?.message ?? 'Gagal mengambil data.';
    } finally {
        loading.value = false;
    }
}

watch(activeTab, load);
onMounted(load);

function isArray(val: any): val is any[] {
    return Array.isArray(val);
}

function keys(obj: object): string[] {
    return Object.keys(obj);
}

function strVal(val: any): string {
    if (val === null || val === undefined) return '—';
    if (typeof val === 'object') return JSON.stringify(val);
    return String(val);
}
</script>

<template>
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">MBG Admin</h1>
            <p class="text-sm text-gray-500 mt-0.5">Data platform dari Go MBG via service API</p>
        </div>

        <!-- Tab nav -->
        <div class="border-b border-gray-200 overflow-x-auto">
            <nav class="flex gap-1 min-w-max">
                <button
                    v-for="tab in TABS"
                    :key="tab.id"
                    @click="activeTab = tab.id"
                    :class="[
                        'pb-3 px-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap',
                        activeTab === tab.id
                            ? 'border-primary text-primary'
                            : 'border-transparent text-gray-500 hover:text-gray-700'
                    ]"
                >
                    {{ tab.label }}
                </button>
            </nav>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="flex items-center justify-center py-16">
            <div class="w-8 h-8 border-4 border-primary border-t-transparent rounded-full animate-spin"></div>
        </div>

        <!-- Error -->
        <div v-else-if="error" class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-5 py-4">
            {{ error }}
        </div>

        <!-- Data: array of objects -> table -->
        <div v-else-if="isArray(data) && data.length > 0" class="bg-white rounded-2xl shadow overflow-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                    <tr>
                        <th v-for="col in keys(data[0])" :key="col" class="px-4 py-3 text-left whitespace-nowrap">
                            {{ col }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="(row, i) in data" :key="i" class="hover:bg-gray-50">
                        <td
                            v-for="col in keys(data[0])"
                            :key="col"
                            class="px-4 py-2.5 text-gray-700 text-xs max-w-[200px] truncate"
                            :title="strVal(row[col])"
                        >
                            {{ strVal(row[col]) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Data: empty array -->
        <div v-else-if="isArray(data) && data.length === 0" class="text-center text-sm text-gray-400 py-12">
            Tidak ada data.
        </div>

        <!-- Data: object / overview stats -->
        <div v-else-if="data !== null && !isArray(data)" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div
                v-for="key in keys(data)"
                :key="key"
                class="bg-white rounded-2xl shadow px-5 py-4"
            >
                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">{{ key }}</p>
                <p class="text-lg font-bold text-primary break-all">{{ strVal(data[key]) }}</p>
            </div>
        </div>

        <!-- No data yet -->
        <div v-else-if="!loading" class="text-center text-sm text-gray-400 py-12">
            Tidak ada data.
        </div>
    </div>
</template>
