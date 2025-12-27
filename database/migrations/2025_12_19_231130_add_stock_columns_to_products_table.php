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
        Schema::table('products', function (Blueprint $table) {
            $table->integer('stock')->default(0)->after('price'); // Stock actual/disponible
            $table->integer('stock_min')->default(0)->after('stock'); // Stock mínimo (alerta)
            $table->integer('stock_max')->default(0)->after('stock_min'); // Stock máximo
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['stock', 'stock_min', 'stock_max']);
        });
    }
};
