<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Frozen copy of the sale lines (HU-042). The VAT breakdown of the PDF is a GROUP BY vat_rate
     * over these rows, so it is not stored separately.
     */
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('article_id')->constrained('articles')->restrictOnDelete();
            $table->string('description', 255);
            $table->rawColumn('quantity', 'decimal(12, 3) check (quantity > 0)');

            /* VAT included, as in the price list. */
            $table->rawColumn('unit_price', 'decimal(12, 2) check (unit_price > 0)');
            $table->rawColumn('vat_rate', 'decimal(5, 2) check (vat_rate >= 0)');
            $table->rawColumn('net_amount', 'decimal(12, 2) check (net_amount >= 0)');
            $table->rawColumn('vat_amount', 'decimal(12, 2) check (vat_amount >= 0)');

            /* Tolerance instead of equality: SQLite stores decimals as floating point. */
            $table->rawColumn('line_total', 'decimal(12, 2) check (line_total > 0 and abs(net_amount + vat_amount - line_total) < 0.005)');

            $table->unique(['invoice_id', 'article_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
