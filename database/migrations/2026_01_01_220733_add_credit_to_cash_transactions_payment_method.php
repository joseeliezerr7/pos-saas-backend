<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Modify the payment_method ENUM to include 'credit'
        DB::statement("ALTER TABLE cash_transactions MODIFY COLUMN payment_method ENUM('cash', 'card', 'transfer', 'qr', 'check', 'credit', 'other') NOT NULL DEFAULT 'cash'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove 'credit' from the ENUM (only if no records use it)
        DB::statement("ALTER TABLE cash_transactions MODIFY COLUMN payment_method ENUM('cash', 'card', 'transfer', 'qr', 'check', 'other') NOT NULL DEFAULT 'cash'");
    }
};
