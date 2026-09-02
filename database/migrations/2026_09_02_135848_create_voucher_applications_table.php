<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One imputation of a credit or debit note onto an invoice (HU-054).
     *
     * The invoice balance is never stored: it is derived as
     * total_amount − Σ payment_order_items.amount_applied − Σ credit notes + Σ debit notes,
     * summing the rows of this table whose target_voucher_id is the invoice.
     */
    public function up(): void
    {
        Schema::create('voucher_applications', function (Blueprint $table) {
            $table->id();

            // The credit or debit note being imputed.
            $table->foreignId('source_voucher_id')->constrained('supplier_vouchers')->restrictOnDelete();

            /*
             * The invoice whose balance this application moves. A note can never target itself;
             * the same-supplier / note-is-source / target-is-invoice rules live in the HU-054 action.
             */
            $table->rawColumn('target_voucher_id', 'bigint check (target_voucher_id <> source_voucher_id)');
            $table->foreign('target_voucher_id')->references('id')->on('supplier_vouchers')->restrictOnDelete();

            // Always positive; the sign (credit subtracts, debit adds) comes from the note's type.
            $table->rawColumn('amount', 'decimal(12, 2) check (amount > 0)');

            $table->foreignId('user_id')->constrained()->restrictOnDelete();

            /*
             * No updated_at, on purpose: a confirmed application is immutable. There is no update
             * or delete route, and a correction is a counter-entry, never an edit. created_at
             * doubles as the moment the imputation was made.
             */
            $table->timestamp('created_at')->useCurrent();

            $table->index('source_voucher_id');
            $table->index('target_voucher_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_applications');
    }
};
