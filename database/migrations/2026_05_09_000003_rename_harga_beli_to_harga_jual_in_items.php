<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->renameColumn('harga_beli', 'harga_jual');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->renameColumn('harga_jual', 'harga_beli');
        });
    }
};
