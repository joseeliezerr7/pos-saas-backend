<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_loyalty_id');
            $table->enum('type', ['earn', 'redeem', 'expire', 'adjust']); // Tipo de transacción
            $table->integer('points'); // Puntos ganados/canjeados (negativo para canje)
            $table->integer('balance_after'); // Balance después de la transacción

            // Referencia opcional a la venta
            $table->unsignedBigInteger('sale_id')->nullable();

            $table->text('description')->nullable(); // Descripción de la transacción
            $table->timestamp('expires_at')->nullable(); // Fecha de expiración de puntos (si aplica)

            $table->timestamps();

            $table->foreign('customer_loyalty_id')->references('id')->on('customer_loyalty')->onDelete('cascade');
            $table->foreign('sale_id')->references('id')->on('sales')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loyalty_transactions');
    }
};
