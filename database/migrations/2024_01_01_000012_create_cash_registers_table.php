<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_registers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('code', 10)->unique();
            $table->string('name');
            $table->json('printer_config')->nullable();
            $table->enum('status', ['active', 'inactive', 'maintenance'])->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'branch_id']);
        });

        Schema::create('cash_openings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('cash_register_id')->constrained('cash_registers')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->decimal('opening_amount', 12, 2);
            $table->text('opening_notes')->nullable();
            $table->timestamp('opened_at');
            $table->boolean('is_open')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'cash_register_id']);
            $table->index(['user_id', 'is_open']);
        });

        Schema::create('cash_closings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_opening_id')->constrained('cash_openings')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->decimal('expected_amount', 12, 2);
            $table->decimal('actual_amount', 12, 2);
            $table->decimal('difference', 12, 2);
            $table->json('denomination_breakdown')->nullable();
            $table->text('closing_notes')->nullable();
            $table->timestamp('closed_at');
            $table->timestamps();

            $table->index('cash_opening_id');
        });

        Schema::create('cash_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('cash_opening_id')->constrained('cash_openings')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->enum('type', ['sale', 'withdrawal', 'deposit', 'expense', 'correction']);
            $table->decimal('amount', 12, 2);
            $table->enum('payment_method', ['cash', 'card', 'transfer', 'qr', 'check', 'other'])->default('cash');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'cash_opening_id']);
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_transactions');
        Schema::dropIfExists('cash_closings');
        Schema::dropIfExists('cash_openings');
        Schema::dropIfExists('cash_registers');
    }
};
