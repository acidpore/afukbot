<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Revert dipesan status dulu
        DB::statement("ALTER TABLE sales MODIFY COLUMN status ENUM('rencana','sudah_dikirim') NOT NULL DEFAULT 'rencana'");

        Schema::table('sale_items', function (Blueprint $table) {
            $table->boolean('is_online_order')->default(false)->after('total_price');
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn('is_online_order');
        });
    }
};
