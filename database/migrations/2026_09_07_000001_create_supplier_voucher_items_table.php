<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_voucher_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_voucher_id')->constrained()->restrictOnDelete();
            $table->rawColumn('position', 'integer check (position > 0)');
            $table->foreignId('article_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('description', 500);
            $table->rawColumn('quantity', 'decimal(12, 3) check (quantity > 0)');
            $table->string('unit_of_measure', 50);
            $table->rawColumn('unit_price', 'decimal(12, 2) check (unit_price > 0)');
            $table->rawColumn('line_total', 'decimal(12, 2) check (line_total > 0)');

            $table->unique(['supplier_voucher_id', 'position']);
            $table->index('article_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_voucher_items');
    }
};
