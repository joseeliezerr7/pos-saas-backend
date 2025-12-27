<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('cai_number', 50)->unique();
            $table->enum('document_type', ['FACTURA', 'NOTA_CREDITO', 'NOTA_DEBITO', 'RECIBO_HONORARIOS', 'FACTURA_EXPORTACION'])->default('FACTURA');
            $table->string('range_start', 20);
            $table->string('range_end', 20);
            $table->integer('total_documents');
            $table->integer('used_documents')->default(0);
            $table->date('authorization_date');
            $table->date('expiration_date');
            $table->enum('status', ['active', 'expired', 'depleted', 'canceled'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'branch_id']);
            $table->index(['status', 'expiration_date']);
            $table->index('cai_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cais');
    }
};
