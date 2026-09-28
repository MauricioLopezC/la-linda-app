<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * An online order (HU-062), created with the delivery (HU-049) and Mercado Pago (HU-050)
     * columns from the start so no later story has to rebuild it. The defaults (pickup, no
     * shipping cost) let HU-062 work on its own. It is not a sale: EPIC-15 converts a paid order.
     */
    public function up(): void
    {
        Schema::create('web_orders', function (Blueprint $table) {
            $table->id();
            $table->rawColumn('number', 'integer check (number > 0)')->unique();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->rawColumn('status', "varchar(20) check (status in ('pendiente', 'pagado'))")->default('pendiente');
            $table->index('status');
            $table->rawColumn('delivery_method', "varchar(20) check (delivery_method in ('retiro', 'envio')) check (delivery_method <> 'retiro' or pickup_branch_id is not null) check (delivery_method <> 'envio' or shipping_address is not null)")->default('retiro');
            $table->foreignId('pickup_branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->string('shipping_address', 255)->nullable();
            $table->string('shipping_notes', 255)->nullable();
            $table->rawColumn('items_amount', 'decimal(12, 2) check (items_amount > 0)');

            /* Frozen at placing time: a later change of the configured cost does not touch it. */
            $table->rawColumn('shipping_cost', 'decimal(12, 2) check (shipping_cost >= 0)')->default(0);

            /* Tolerance instead of equality: SQLite stores decimals as floating point. */
            $table->rawColumn('total_amount', 'decimal(12, 2) check (total_amount > 0 and abs(items_amount + shipping_cost - total_amount) < 0.005)');
            $table->string('mp_preference_id', 100)->nullable();

            /* Makes the payment webhook idempotent. */
            $table->string('mp_payment_id', 100)->nullable()->unique();
            $table->rawColumn('paid_at', "timestamp check ((status = 'pagado' and paid_at is not null) or (status <> 'pagado' and paid_at is null))")->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('placed_at');
            $table->timestamps();

            /* "Mis pedidos" lists a customer's orders, newest first. */
            $table->index(['customer_id', 'placed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('web_orders');
    }
};
