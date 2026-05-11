<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->enum('status', ['belum_dikirim', 'sudah_dikirim'])
                  ->default('belum_dikirim')
                  ->after('grand_total');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            // JSON array of inventory item IDs to deduct when shipped
            $table->json('inventory_item_ids')->nullable()->after('total_price');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('status');
        });
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn('inventory_item_ids');
        });
    }
};
