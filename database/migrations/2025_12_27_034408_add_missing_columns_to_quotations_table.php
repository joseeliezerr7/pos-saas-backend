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
        Schema::table('quotations', function (Blueprint $table) {
            // Add missing columns
            $table->string('customer_rtn')->nullable()->after('quotation_number');
            $table->string('customer_email')->nullable()->after('customer_name');
            $table->string('customer_phone')->nullable()->after('customer_email');
            $table->timestamp('quoted_at')->nullable()->after('valid_until');
            $table->foreignId('converted_to_sale_id')->nullable()->after('quoted_at')->constrained('sales')->onDelete('set null');
        });

        // Update status enum to include new values
        DB::statement("ALTER TABLE quotations MODIFY COLUMN status ENUM('pending', 'accepted', 'rejected', 'expired', 'converted') NOT NULL DEFAULT 'pending'");

        // Change valid_until from date to timestamp
        DB::statement("ALTER TABLE quotations MODIFY COLUMN valid_until TIMESTAMP NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropForeign(['converted_to_sale_id']);
            $table->dropColumn(['customer_rtn', 'customer_email', 'customer_phone', 'quoted_at', 'converted_to_sale_id']);
        });

        // Revert status enum
        DB::statement("ALTER TABLE quotations MODIFY COLUMN status ENUM('draft','sent','accepted','rejected','expired','converted') NOT NULL DEFAULT 'draft'");

        // Revert valid_until to date
        DB::statement("ALTER TABLE quotations MODIFY COLUMN valid_until DATE NULL");
    }
};
