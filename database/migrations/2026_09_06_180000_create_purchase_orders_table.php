<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->string('order_number', 32)->unique();
            $table->string('payment_terms', 255)->nullable();
            $table->date('issue_date')->index();
            $table->rawColumn('expected_delivery_date', 'date check (expected_delivery_date is null or expected_delivery_date >= issue_date)')->nullable();
            $table->rawColumn('total_amount', 'decimal(12, 2) check (total_amount >= 0)')->default(0);
            $table->rawColumn('status', "varchar(20) check (status in ('borrador', 'emitida', 'cancelada'))")->default('borrador');
            $table->index('status');
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users');
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
