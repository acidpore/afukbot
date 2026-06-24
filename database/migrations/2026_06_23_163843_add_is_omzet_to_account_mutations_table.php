<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_mutations', function (Blueprint $table) {
            $table->boolean('is_omzet')->default(true)->after('costs');
        });
    }

    public function down(): void
    {
        Schema::table('account_mutations', function (Blueprint $table) {
            $table->dropColumn('is_omzet');
        });
    }
};
