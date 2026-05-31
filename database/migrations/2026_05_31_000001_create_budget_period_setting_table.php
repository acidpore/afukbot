<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_period_setting', function (Blueprint $table) {
            $table->id();
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamps();
        });

        // Seed dengan periode bulan ini sebagai default
        DB::table('budget_period_setting')->insert([
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date'   => now()->endOfMonth()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_period_setting');
    }
};
