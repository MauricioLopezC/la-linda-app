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
        Schema::table('web_orders', function (Blueprint $table) {
            $table->timestamp('stock_reserved_until')->nullable();
            $table->uuid('payment_reservation_token')->nullable()->unique();
            $table->text('mp_init_point')->nullable();
            $table->text('mp_sandbox_init_point')->nullable();
            $table->index(['status', 'stock_reserved_until']);
        });

        Schema::table('web_order_items', function (Blueprint $table) {
            $table->index(['article_id', 'web_order_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('web_order_items', function (Blueprint $table) {
            $table->dropIndex(['article_id', 'web_order_id']);
        });

        Schema::table('web_orders', function (Blueprint $table) {
            $table->dropIndex(['status', 'stock_reserved_until']);
            $table->dropUnique(['payment_reservation_token']);
            $table->dropColumn(['stock_reserved_until', 'payment_reservation_token', 'mp_init_point', 'mp_sandbox_init_point']);
        });
    }
};
