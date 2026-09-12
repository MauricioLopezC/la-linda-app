<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the UNIQUE constraint to payment_orders.order_number.
     *
     * PR #28 created the column without the constraint so HU-027 could decide the generation
     * strategy (global vs per-supplier). Decision: global correlative, format OP-XXXXXX.
     */
    public function up(): void
    {
        Schema::table('payment_orders', function (Blueprint $table) {
            $table->unique('order_number', 'payment_orders_order_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payment_orders', function (Blueprint $table) {
            $table->dropUnique('payment_orders_order_number_unique');
        });
    }
};
