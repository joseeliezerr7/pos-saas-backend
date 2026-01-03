<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('slug', 100)->unique()->after('name')->nullable();
        });

        // Generar slugs para empresas existentes
        DB::table('companies')->orderBy('id')->chunk(100, function ($companies) {
            foreach ($companies as $company) {
                $baseSlug = Str::slug($company->name);
                $slug = $baseSlug;
                $counter = 1;

                // Verificar que el slug sea único
                while (DB::table('companies')->where('slug', $slug)->where('id', '!=', $company->id)->exists()) {
                    $slug = $baseSlug . '-' . $counter;
                    $counter++;
                }

                DB::table('companies')
                    ->where('id', $company->id)
                    ->update(['slug' => $slug]);
            }
        });

        // Hacer el campo NOT NULL después de llenar los datos
        Schema::table('companies', function (Blueprint $table) {
            $table->string('slug', 100)->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
