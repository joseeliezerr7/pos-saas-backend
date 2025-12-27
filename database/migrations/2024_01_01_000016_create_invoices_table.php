<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained('sales');
            $table->foreignId('cai_id')->constrained('cais');
            $table->foreignId('correlative_id')->constrained('correlatives');
            $table->string('invoice_number', 20);
            $table->string('cai_number', 50);

            // Customer information (required by SAR)
            $table->string('customer_rtn', 14);
            $table->string('customer_name');
            $table->text('customer_address')->nullable();

            // Amounts
            $table->decimal('subtotal_exempt', 12, 2)->default(0);
            $table->decimal('subtotal_taxed', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->decimal('tax', 12, 2);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->string('total_in_words');

            // SAR control
            $table->timestamp('issued_at');
            $table->date('cai_expiration_date');
            $table->string('range_authorized')->nullable();

            // Voiding
            $table->boolean('is_voided')->default(false);
            $table->enum('void_reason', ['ERROR_DIGITACION', 'DEVOLUCION', 'DESCUENTO_POSTERIOR', 'DUPLICADO', 'OTRO'])->nullable();
            $table->text('void_notes')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users');

            // DTE (future)
            $table->string('invoice_uuid')->nullable()->unique();
            $table->text('xml_signed')->nullable();
            $table->string('qr_code')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'invoice_number']);
            $table->index(['tenant_id', 'issued_at']);
            $table->index(['customer_rtn', 'issued_at']);
            $table->index(['is_voided', 'issued_at']);
        });

        Schema::create('invoice_voids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices');
            $table->foreignId('user_id')->constrained('users');
            $table->enum('reason', ['ERROR_DIGITACION', 'DEVOLUCION', 'DESCUENTO_POSTERIOR', 'DUPLICADO', 'OTRO']);
            $table->text('notes')->nullable();
            $table->timestamp('voided_at');
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_voids');
        Schema::dropIfExists('invoices');
    }
};
