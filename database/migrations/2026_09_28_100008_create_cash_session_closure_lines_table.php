<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Expected vs. declared per payment method when a cash session closes (HU-060). Written
     * once with the closing, so there are no timestamps: the session's closed_at dates it.
     */
    public function up(): void
    {
        Schema::create('cash_session_closure_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_session_id')->constrained('cash_sessions')->restrictOnDelete();
            $table->foreignId('payment_method_id')->constrained('payment_methods')->restrictOnDelete();
            $table->decimal('expected_amount', 12, 2);

            /* For cash, the sum of the closing count. */
            $table->rawColumn('declared_amount', 'decimal(12, 2) check (declared_amount >= 0)');

            /* declared − expected: positive is a surplus, negative a shortage. */
            $table->rawColumn('difference', 'decimal(12, 2) check (abs(difference - (declared_amount - expected_amount)) < 0.005)');

            /* POSNET batch number; the closing action requires it for `tarjeta` methods. */
            $table->string('batch_reference', 50)->nullable();

            $table->unique(['cash_session_id', 'payment_method_id']);
            $table->index('payment_method_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_session_closure_lines');
    }
};
