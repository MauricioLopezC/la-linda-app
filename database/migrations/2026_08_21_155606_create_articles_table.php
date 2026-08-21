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
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('description', 150);
            $table->string('internal_code', 50);
            $table->string('internal_code_normalized', 50)->unique();
            $table->string('barcode', 50)->nullable();
            $table->string('barcode_normalized', 50)->nullable()->unique();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->foreignId('unit_of_measure_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->foreignId('vat_rate_id')->constrained('vat_rates')->restrictOnDelete();
            $table->string('status', 20)->default('active')->index();
            $table->boolean('is_online_publishable')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
