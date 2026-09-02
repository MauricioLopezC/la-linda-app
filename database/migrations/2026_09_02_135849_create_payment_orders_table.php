<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Payment order header (HU-027). The shared PR only creates the schema; order emission,
     * order_number generation (global vs per-supplier, open point 4) and its UNIQUE index
     * belong to HU-027.
     */
    public function up(): void
    {
        Schema::create('payment_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();

            // Correlative number. HU-027 owns how it is generated and whether it is globally unique.
            $table->string('order_number');

            $table->date('date');
            $table->rawColumn('total_amount', 'decimal(12, 2) check (total_amount > 0)');

            /*
             * Only two real states: emitted, or reversed by a counter-entry. The immutability of a
             * confirmed order rests on there being no PUT/PATCH/DELETE route, like stock_movements.
             */
            $table->rawColumn('status', "varchar(20) check (status in ('emitida', 'anulada'))");

            $table->text('notes')->nullable();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();

            // No updated_at, on purpose: a confirmed order is immutable (see status comment).
            $table->timestamp('created_at')->useCurrent();

            $table->index(['supplier_id', 'date']);
            $table->index('payment_method_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_orders');
    }
};
