<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('correlatives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cai_id')->constrained('cais')->cascadeOnDelete();
            $table->unsignedBigInteger('number');
            $table->string('formatted_number', 20);
            $table->enum('status', ['available', 'used', 'voided'])->default('available');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();

            $table->unique(['cai_id', 'number']);
            $table->index(['cai_id', 'status']);
            $table->index('formatted_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('correlatives');
    }
};
