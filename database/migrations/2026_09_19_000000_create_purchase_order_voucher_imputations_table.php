<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_voucher_imputations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_item_id')->constrained('purchase_order_items')->restrictOnDelete();
            $table->foreignId('supplier_voucher_item_id')->constrained('supplier_voucher_items')->cascadeOnDelete();
            $table->rawColumn('quantity_received', 'decimal(12, 3) check (quantity_received > 0)');
            $table->rawColumn('quantity_excess', 'decimal(12, 3) default 0 check (quantity_excess >= 0)');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['purchase_order_item_id', 'supplier_voucher_item_id'], 'po_voucher_imputations_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_voucher_imputations');
    }
};
