<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->bigInteger('total_budget')->default(0);
            $table->timestamps();
        });

        Schema::create('budget_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('budget_categories')->onDelete('cascade');
            $table->string('name');
            $table->bigInteger('unit_cost')->default(0);
            $table->enum('rate', ['harian', 'mingguan', 'dua_mingguan', 'bulanan', 'custom']);
            $table->integer('multiplier')->default(1);
            $table->bigInteger('total_monthly_budget')->storedAs('unit_cost * multiplier');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('expense_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_item_id')->constrained('budget_items')->onDelete('cascade');
            $table->bigInteger('amount');
            $table->date('transaction_date');
            $table->string('note')->nullable();
            $table->string('receipt_path')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('budget_periods', function (Blueprint $table) {
            $table->id();
            $table->string('month', 7); // YYYY-MM
            $table->foreignId('category_id')->constrained('budget_categories')->onDelete('cascade');
            $table->bigInteger('planned_amount')->default(0);
            $table->bigInteger('actual_amount')->default(0);
            $table->bigInteger('variance')->storedAs('planned_amount - actual_amount');
            $table->enum('status', ['on_track', 'warning', 'over_budget'])->default('on_track');
            $table->timestamps();

            $table->unique(['month', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_periods');
        Schema::dropIfExists('expense_transactions');
        Schema::dropIfExists('budget_items');
        Schema::dropIfExists('budget_categories');
    }
};
