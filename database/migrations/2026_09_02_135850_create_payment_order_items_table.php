<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Payment order detail (HU-027): the N:N table between a payment order and the invoices it
     * cancels, carrying the amount imputed from each order to each invoice.
     */
    public function up(): void
    {
        Schema::create('payment_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_order_id')->constrained()->restrictOnDelete();

            // Always an invoice, never a credit/debit note. Enforced by the HU-027 action.
            $table->foreignId('supplier_voucher_id')->constrained()->restrictOnDelete();

            $table->rawColumn('amount_applied', 'decimal(12, 2) check (amount_applied > 0)');

            // An invoice appears at most once per order.
            $table->unique(['payment_order_id', 'supplier_voucher_id'], 'payment_order_items_order_voucher_unique');
            $table->index('supplier_voucher_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_order_items');
    }
};
