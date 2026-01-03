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
        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('customer_group_id')->nullable()->after('id')->constrained()->nullOnDelete();

            // Campos para análisis RFM (Recency, Frequency, Monetary)
            $table->date('last_purchase_date')->nullable()->comment('Fecha de última compra (Recency)');
            $table->integer('total_purchases')->default(0)->comment('Total de compras (Frequency)');
            $table->decimal('lifetime_value', 12, 2)->default(0)->comment('Valor total gastado (Monetary)');

            // Score RFM (1-5, donde 5 es el mejor)
            $table->integer('rfm_recency_score')->nullable()->comment('Score 1-5');
            $table->integer('rfm_frequency_score')->nullable()->comment('Score 1-5');
            $table->integer('rfm_monetary_score')->nullable()->comment('Score 1-5');
            $table->string('rfm_segment', 50)->nullable()->comment('Champions, Loyal, At Risk, etc.');

            // Index para búsquedas
            $table->index('customer_group_id');
            $table->index('rfm_segment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['customer_group_id']);
            $table->dropColumn([
                'customer_group_id',
                'last_purchase_date',
                'total_purchases',
                'lifetime_value',
                'rfm_recency_score',
                'rfm_frequency_score',
                'rfm_monetary_score',
                'rfm_segment'
            ]);
        });
    }
};
