<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE sales MODIFY COLUMN status ENUM('belum_dikirim','rencana','sudah_dikirim') NOT NULL DEFAULT 'belum_dikirim'");
        DB::table('sales')->where('status', 'belum_dikirim')->update(['status' => 'rencana']);
        DB::statement("ALTER TABLE sales MODIFY COLUMN status ENUM('rencana','sudah_dikirim') NOT NULL DEFAULT 'rencana'");
    }

    public function down(): void
    {
        DB::table('sales')->where('status', 'rencana')->update(['status' => 'belum_dikirim']);
        DB::statement("ALTER TABLE sales MODIFY COLUMN status ENUM('belum_dikirim','sudah_dikirim') NOT NULL DEFAULT 'belum_dikirim'");
    }
};
