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
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('companies')->onDelete('cascade');

            // Información básica
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('code')->unique()->nullable(); // Código de cupón

            // Tipo de promoción
            $table->enum('type', [
                'percentage',      // Descuento porcentual
                'fixed_amount',    // Descuento monto fijo
                'bogo',           // Buy One Get One (2x1, 3x2, etc)
                'volume',         // Descuento por volumen
                'bundle',         // Combo de productos
                'free_shipping'   // Envío gratis (futuro)
            ]);

            // Valor del descuento
            $table->decimal('discount_value', 10, 2); // Porcentaje o monto

            // Para promociones BOGO
            $table->integer('buy_quantity')->nullable(); // Compra X
            $table->integer('get_quantity')->nullable(); // Lleva Y

            // Restricciones
            $table->decimal('min_purchase_amount', 10, 2)->nullable();
            $table->decimal('max_discount_amount', 10, 2)->nullable();
            $table->integer('usage_limit')->nullable(); // Límite global
            $table->integer('usage_per_customer')->nullable(); // Límite por cliente
            $table->integer('times_used')->default(0);

            // Aplicabilidad
            $table->enum('applicable_to', ['all', 'products', 'categories', 'brands'])->default('all');
            $table->json('applicable_ids')->nullable(); // IDs de productos, categorías o marcas
            $table->json('branch_ids')->nullable(); // Sucursales donde aplica (null = todas)
            $table->json('customer_group_ids')->nullable(); // Grupos de clientes (null = todos)

            // Programación temporal
            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->json('days_of_week')->nullable(); // [1,2,3,4,5] para lun-vie
            $table->time('start_time')->nullable(); // Para happy hour
            $table->time('end_time')->nullable();

            // Control
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_apply')->default(false); // Aplicar automáticamente o requiere cupón
            $table->integer('priority')->default(0); // Mayor prioridad = mayor número

            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index(['tenant_id', 'is_active']);
            $table->index(['start_date', 'end_date']);
            $table->index('code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
