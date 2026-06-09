import axios from 'axios';
import type { ApiResponse } from '../types';

const api = axios.create({
    baseURL: '/',
    headers: { 'Accept': 'application/json' },
});

export interface SuratJalanItem {
    id?: number;
    surat_jalan_id?: number;
    sale_item_id: number;
    qty_kirim: number;
    sale_item?: {
        item_name: string;
        qty: number;
        unit_price: number;
    };
}

export interface SuratJalan {
    id: number;
    sale_id: number;
    nomor_sj: string;
    tanggal_kirim: string;
    catatan: string | null;
    items: SuratJalanItem[];
    created_at?: string;
}

export interface ItemProgress {
    item_name: string;
    qty_order: number;
    qty_kirim: number;
    qty_sisa: number;
}

export interface SaleProgress {
    items: Record<number, ItemProgress>;
    qty_total_order: number;
    qty_total_kirim: number;
    qty_total_sisa: number;
}

export interface CompletedInvoice {
    id: number;
    invoice_number: string;
    recipient_name: string;
    invoice_date: string;
    shipped_at: string | null;
    grand_total: number;
    surat_jalans: {
        id: number;
        nomor_sj: string;
        tanggal_kirim: string;
        catatan: string | null;
        items: { id: number; qty_kirim: number; sale_item: { item_name: string } | null }[];
    }[];
}

export interface InvoiceWithProgress {
    id: number;
    invoice_number: string;
    recipient_name: string;
    recipient_address: string;
    invoice_date: string;
    grand_total: number;
    paid_amount: number;
    status: string;
    items: { id: number; item_name: string; qty: number; unit_price: number; inventory_item_ids?: number[] }[];
    progress: SaleProgress;
}

export const suratJalanApi = {
    getAll: () => api.get<ApiResponse<SuratJalan[]>>('/surat-jalan'),
    getInvoicesProgress: () => api.get<ApiResponse<InvoiceWithProgress[]>>('/surat-jalan/invoices-progress'),
    getCompletedInvoices: () => api.get<ApiResponse<CompletedInvoice[]>>('/surat-jalan/invoices-completed'),
    getBySale: (saleId: number) => api.get<ApiResponse<{ surat_jalans: SuratJalan[]; progress: SaleProgress }>>(`/surat-jalan/by-sale/${saleId}`),
    create: (data: {
        sale_id: number;
        tanggal_kirim: string;
        catatan?: string;
        items: { sale_item_id: number; qty_kirim: number }[];
    }) => api.post<ApiResponse<SuratJalan>>('/surat-jalan', data),
    remove: (id: number) => api.delete<ApiResponse<null>>(`/surat-jalan/${id}`),
};
