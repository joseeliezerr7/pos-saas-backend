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
        Schema::create('customer_loyalty', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('loyalty_program_id');
            $table->unsignedBigInteger('current_tier_id')->nullable();

            // Puntos
            $table->integer('points')->default(0); // Puntos actuales
            $table->integer('lifetime_points')->default(0); // Puntos totales ganados históricamente
            $table->integer('points_redeemed')->default(0); // Puntos canjeados

            // Estadísticas
            $table->decimal('total_spent', 15, 2)->default(0); // Total gastado
            $table->integer('purchases_count')->default(0); // Cantidad de compras
            $table->timestamp('last_purchase_at')->nullable();
            $table->timestamp('enrolled_at')->nullable(); // Cuándo se unió al programa

            $table->timestamps();

            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->foreign('loyalty_program_id')->references('id')->on('loyalty_programs')->onDelete('cascade');
            $table->foreign('current_tier_id')->references('id')->on('loyalty_tiers')->onDelete('set null');

            $table->unique(['customer_id', 'loyalty_program_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_loyalty');
    }
};
