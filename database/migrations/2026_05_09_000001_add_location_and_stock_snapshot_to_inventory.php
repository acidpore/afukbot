<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->string('location')->default('Ruko')->after('unit');
        });

        Schema::table('stock_transactions', function (Blueprint $table) {
            $table->integer('stock_before')->nullable()->after('quantity');
            $table->integer('stock_after')->nullable()->after('stock_before');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('location');
        });

        Schema::table('stock_transactions', function (Blueprint $table) {
            $table->dropColumn(['stock_before', 'stock_after']);
        });
    }
};
