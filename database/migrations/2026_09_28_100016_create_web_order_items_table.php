<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Lines of an online order, with the price frozen when it was placed (HU-062).
     */
    public function up(): void
    {
        Schema::create('web_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('web_order_id')->constrained('web_orders')->cascadeOnDelete();
            $table->foreignId('article_id')->constrained('articles')->restrictOnDelete();
            $table->rawColumn('quantity', 'decimal(12, 3) check (quantity > 0)');
            $table->rawColumn('unit_price', 'decimal(12, 2) check (unit_price > 0)');
            $table->foreignId('price_list_id')->constrained('price_lists')->restrictOnDelete();
            $table->rawColumn('line_total', 'decimal(12, 2) check (line_total > 0)');

            $table->unique(['web_order_id', 'article_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('web_order_items');
    }
};
