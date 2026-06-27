<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rab_periods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        // Seed periode awal dari setting lama (atau bulan ini) sebagai periode aktif
        $old = DB::table('budget_period_setting')->first();
        $start = $old->start_date ?? now()->startOfMonth()->toDateString();
        $end   = $old->end_date   ?? now()->endOfMonth()->toDateString();

        $periodId = DB::table('rab_periods')->insertGetId([
            'name'       => 'Periode Awal',
            'start_date' => $start,
            'end_date'   => $end,
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('budget_categories', function (Blueprint $table) {
            $table->foreignId('period_id')->nullable()->after('id')
                ->constrained('rab_periods')->nullOnDelete();
        });

        // Semua kategori existing masuk ke periode awal
        DB::table('budget_categories')->update(['period_id' => $periodId]);
    }

    public function down(): void
    {
        Schema::table('budget_categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('period_id');
        });
        Schema::dropIfExists('rab_periods');
    }
};
