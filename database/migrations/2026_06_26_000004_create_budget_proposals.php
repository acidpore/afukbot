<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained('rab_periods')->cascadeOnDelete();
            $table->string('name');                 // yang mau dibeli, e.g. "TV"
            $table->string('brand')->nullable();    // merk diusulkan, e.g. "Samsung"
            $table->bigInteger('price')->default(0);// harga diusulkan
            $table->text('note')->nullable();       // alasan/keterangan
            $table->json('analysis')->nullable();   // [{brand, price, note}]
            $table->enum('status', ['pending', 'bought'])->default('pending');
            $table->date('bought_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_proposals');
    }
};
