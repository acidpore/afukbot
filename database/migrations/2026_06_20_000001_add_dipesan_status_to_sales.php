<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE sales MODIFY COLUMN status ENUM('rencana','dipesan','sudah_dikirim') NOT NULL DEFAULT 'rencana'");
    }

    public function down(): void
    {
        DB::table('sales')->where('status', 'dipesan')->update(['status' => 'rencana']);
        DB::statement("ALTER TABLE sales MODIFY COLUMN status ENUM('rencana','sudah_dikirim') NOT NULL DEFAULT 'rencana'");
    }
};
