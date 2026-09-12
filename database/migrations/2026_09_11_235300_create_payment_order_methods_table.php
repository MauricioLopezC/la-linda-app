<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payment_order_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            $table->rawColumn('amount', 'decimal(12, 2) check (amount > 0)');
            $table->string('reference')->nullable();
            $table->string('source_account')->nullable();
            $table->string('transaction_number')->nullable();
            $table->string('check_number')->nullable();
            $table->date('check_due_date')->nullable();
        });

        // Migrate existing payment_method_id from payment_orders
        DB::statement('
            INSERT INTO payment_order_methods (payment_order_id, payment_method_id, amount)
            SELECT id, payment_method_id, total_amount FROM payment_orders
        ');

        Schema::table('payment_orders', function (Blueprint $table) {
            $table->dropForeign(['payment_method_id']);
            $table->dropIndex(['payment_method_id']);
            $table->dropColumn('payment_method_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // First add the column back
        Schema::table('payment_orders', function (Blueprint $table) {
            $table->foreignId('payment_method_id')->nullable()->constrained()->restrictOnDelete();
        });

        // Try to restore the first method for each order
        DB::statement('
            UPDATE payment_orders po
            INNER JOIN (
                SELECT payment_order_id, MIN(payment_method_id) as method_id 
                FROM payment_order_methods 
                GROUP BY payment_order_id
            ) m ON po.id = m.payment_order_id
            SET po.payment_method_id = m.method_id
        ');

        Schema::table('payment_orders', function (Blueprint $table) {
            $table->foreignId('payment_method_id')->nullable(false)->change();
        });

        Schema::dropIfExists('payment_order_methods');
    }
};
