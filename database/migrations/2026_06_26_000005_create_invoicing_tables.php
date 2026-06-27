<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('signature_path')->nullable();
            $table->string('npwp')->nullable();
            $table->text('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account')->nullable();
            $table->string('bank_holder')->nullable();
            $table->string('brand_primary', 9)->default('#0f172a');
            $table->string('brand_secondary', 9)->default('#f59e0b');
            $table->string('font_family')->default('Inter');
            $table->enum('template_variant', ['modern', 'classic', 'minimal', 'bold'])->default('modern');
            $table->string('invoice_prefix')->default('INV');
            $table->unsignedInteger('invoice_counter')->default(0);
            $table->timestamps();
        });

        Schema::create('invoice_customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('company_address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('npwp')->nullable();
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('invoice_customers')->nullOnDelete();
            $table->string('invoice_number')->unique();
            $table->date('issue_date');
            $table->date('due_date')->nullable();
            $table->enum('status', ['draft', 'sent', 'paid', 'overdue'])->default('draft');
            $table->enum('price_mode', ['exclusive', 'inclusive'])->default('exclusive'); // inclusive = total sudah termasuk PPN
            $table->bigInteger('subtotal')->default(0);   // DPP (dasar pengenaan pajak)
            $table->bigInteger('discount')->default(0);
            $table->decimal('tax_percent', 5, 2)->default(11);
            $table->bigInteger('tax_amount')->default(0);
            $table->bigInteger('total')->default(0);
            $table->string('currency', 5)->default('IDR');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->string('description');
            $table->decimal('qty', 12, 2)->default(1);
            $table->bigInteger('unit_price')->default(0);
            $table->bigInteger('line_total')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('invoice_customers');
        Schema::dropIfExists('companies');
    }
};
