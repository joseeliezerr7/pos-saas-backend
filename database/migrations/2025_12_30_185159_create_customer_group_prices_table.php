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
        Schema::create('customer_group_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_group_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->decimal('price', 10, 2)->comment('Precio especial para este grupo');
            $table->date('valid_from')->nullable()->comment('Fecha de inicio de validez');
            $table->date('valid_until')->nullable()->comment('Fecha de fin de validez');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Unique constraint: un producto solo puede tener un precio por grupo (si ambos están activos)
            $table->unique(['customer_group_id', 'product_id', 'is_active'], 'cgp_group_product_active_unique');

            // Indexes
            $table->index(['product_id', 'is_active']);
            $table->index(['customer_group_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_group_prices');
    }
};
