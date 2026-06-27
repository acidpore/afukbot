<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Samakan kategori expense mirror dengan nama kategori RAB aslinya.
        // Baris lama yang sempat ke-isi literal "RAB" (fallback kategori null) ikut diperbaiki.
        DB::statement("
            UPDATE expenses e
            JOIN expense_transactions t ON t.id = e.expense_transaction_id
            JOIN budget_items i ON i.id = t.budget_item_id
            JOIN budget_categories c ON c.id = i.category_id
            SET e.category = c.name
            WHERE e.expense_transaction_id IS NOT NULL
        ");
    }

    public function down(): void
    {
        // Tidak bisa di-rollback (kategori asli sebelumnya tidak disimpan).
    }
};
