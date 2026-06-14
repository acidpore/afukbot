<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_calibrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calibrated_by')->constrained('users');
            $table->date('calibrated_at');
            $table->text('notes')->nullable();
            $table->integer('total_items');
            $table->integer('total_adjusted');
            $table->timestamps();
        });

        Schema::create('stock_calibration_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calibration_id')->constrained('stock_calibrations')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items');
            $table->string('item_name');
            $table->integer('qty_system');
            $table->integer('qty_physical');
            $table->integer('delta');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_calibration_items');
        Schema::dropIfExists('stock_calibrations');
    }
};
