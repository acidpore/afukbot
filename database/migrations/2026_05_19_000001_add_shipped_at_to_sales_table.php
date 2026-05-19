<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->timestamp('shipped_at')->nullable()->after('status');
        });

        DB::table('sales')
            ->where('status', 'sudah_dikirim')
            ->update(['shipped_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('shipped_at');
        });
    }
};
