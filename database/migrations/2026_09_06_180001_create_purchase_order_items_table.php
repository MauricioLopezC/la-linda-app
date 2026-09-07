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
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('article_id')->constrained('articles');
            $table->rawColumn('quantity', 'decimal(12, 3) check (quantity > 0)');
            $table->rawColumn('unit_price', 'decimal(12, 2) check (unit_price > 0)');
            $table->rawColumn('line_total', 'decimal(12, 2) check (line_total > 0)');
            $table->timestamps();

            $table->unique(['purchase_order_id', 'article_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
