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
        Schema::create('article_supplier', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->string('supplier_article_code', 100);
            $table->string('supplier_article_code_normalized', 100);
            $table->rawColumn('last_cost', 'decimal(12, 2) check (last_cost is null or last_cost > 0)')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['article_id', 'supplier_id'], 'article_supplier_unique');
            $table->unique(['supplier_id', 'supplier_article_code_normalized'], 'supplier_article_code_unique');
            $table->index('article_id');
            $table->index('supplier_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('article_supplier');
    }
};
