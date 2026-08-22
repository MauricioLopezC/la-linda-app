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
        Schema::create('warehouse_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->decimal('quantity', 12, 2)->default(0);
            $table->decimal('min_stock', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['article_id', 'warehouse_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse_stocks');
    }
};
