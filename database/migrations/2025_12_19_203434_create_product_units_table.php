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
        Schema::create('product_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('unit_id')->constrained()->onDelete('cascade');
            $table->decimal('quantity', 10, 2)->default(1); // Cantidad de unidades base (ej: 1 caja = 24 unidades)
            $table->decimal('price', 10, 2); // Precio de venta para esta unidad
            $table->string('barcode')->nullable(); // Código de barras específico para esta unidad
            $table->boolean('is_base_unit')->default(false); // Si es la unidad base del producto
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_units');
    }
};
