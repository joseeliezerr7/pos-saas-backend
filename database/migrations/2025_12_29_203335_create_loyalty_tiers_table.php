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
        Schema::create('loyalty_tiers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('loyalty_program_id');
            $table->string('name'); // Ej: Bronce, Plata, Oro, Platino
            $table->string('color')->default('#gray'); // Color para UI
            $table->integer('min_points')->default(0); // Puntos mínimos para alcanzar este nivel
            $table->integer('order')->default(0); // Orden del nivel

            // Beneficios del nivel
            $table->decimal('discount_percentage', 5, 2)->default(0); // Descuento automático
            $table->decimal('points_multiplier', 5, 2)->default(1.00); // Multiplicador de puntos
            $table->json('benefits')->nullable(); // Otros beneficios (texto descriptivo)

            $table->timestamps();

            $table->foreign('loyalty_program_id')->references('id')->on('loyalty_programs')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loyalty_tiers');
    }
};
