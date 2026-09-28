<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Every movement of money in a cash session (HU-057 / EPIC-04 / HU-058). The amount is always
     * positive: the sign comes from the type. A sale paid with two methods is two `venta` rows,
     * so the closing per payment method is a plain sum.
     */
    public function up(): void
    {
        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_session_id')->constrained('cash_sessions')->restrictOnDelete();
            $table->rawColumn('type', "varchar(20) check (type in ('apertura', 'venta', 'ingreso', 'egreso')) check ((type = 'venta' and sale_id is not null) or (type <> 'venta' and sale_id is null)) check (type not in ('ingreso', 'egreso') or reason is not null)");
            $table->foreignId('payment_method_id')->constrained('payment_methods')->restrictOnDelete();
            $table->rawColumn('amount', 'decimal(12, 2) check (amount > 0)');
            $table->foreignId('sale_id')->nullable()->constrained('sales')->restrictOnDelete();

            /* What the customer handed over in cash; only kept to print the change on the invoice. */
            $table->rawColumn('tendered_amount', 'decimal(12, 2) check (tendered_amount is null or tendered_amount >= amount)')->nullable();
            $table->string('reason', 255)->nullable();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();

            /* Immutable: a mistake is corrected with the opposite movement, never edited. */
            $table->timestamp('created_at')->useCurrent();

            $table->index(['cash_session_id', 'type']);
            $table->index('sale_id');
            $table->index('payment_method_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
    }
};
