<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The invoice issued when a sale is confirmed (HU-042), in the same transaction. It is an
     * internal voucher without CAE until SPIKE-01. Customer data and amounts are frozen at
     * issue time; the PDF (HU-043) is rendered on demand from these rows, never stored.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->unique()->constrained('sales')->restrictOnDelete();
            $table->foreignId('cash_session_id')->constrained('cash_sessions')->restrictOnDelete();
            $table->foreignId('point_of_sale_id')->constrained('points_of_sale')->restrictOnDelete();
            $table->rawColumn('type', "varchar(1) check (type in ('A', 'B'))");

            /* Correlative per point of sale and type, taken with a lock on the point of sale. */
            $table->rawColumn('number', 'integer check (number > 0)');
            $table->timestamp('issued_at')->index();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->string('customer_name', 255);
            $table->string('customer_tax_condition', 50);
            $table->string('customer_id_type', 20)->nullable();
            $table->string('customer_id_number', 20)->nullable();
            $table->string('customer_address', 255)->nullable();
            $table->rawColumn('net_amount', 'decimal(12, 2) check (net_amount >= 0)');
            $table->rawColumn('vat_amount', 'decimal(12, 2) check (vat_amount >= 0)');

            /* Tolerance instead of equality: SQLite stores decimals as floating point. */
            $table->rawColumn('total_amount', 'decimal(12, 2) check (total_amount > 0 and abs(net_amount + vat_amount - total_amount) < 0.005)');
            $table->foreignId('user_id')->constrained()->restrictOnDelete();

            /* Immutable: its cancellation is a credit note (HU-044), never an edit. */
            $table->timestamp('created_at')->useCurrent();

            /* Safety net for the numbering; the lock is what prevents gaps and duplicates. */
            $table->unique(['point_of_sale_id', 'type', 'number']);
            $table->index('cash_session_id');
            $table->index('customer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
