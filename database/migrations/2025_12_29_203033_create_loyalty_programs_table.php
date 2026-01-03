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
        Schema::create('loyalty_programs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);

            // Configuración de puntos
            $table->decimal('points_per_currency', 10, 2)->default(1.00); // Puntos por cada L.1
            $table->integer('min_purchase_amount')->default(0); // Mínimo de compra para ganar puntos
            $table->decimal('point_value', 10, 2)->default(1.00); // Valor de 1 punto en moneda

            // Configuración de expiración
            $table->boolean('points_expire')->default(false);
            $table->integer('expiration_days')->nullable(); // Días para expiración

            // Multiplicadores especiales
            $table->json('special_dates')->nullable(); // Días con puntos multiplicados
            $table->decimal('birthday_multiplier', 5, 2)->default(1.00); // Multiplicador cumpleaños

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('companies')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loyalty_programs');
    }
};
