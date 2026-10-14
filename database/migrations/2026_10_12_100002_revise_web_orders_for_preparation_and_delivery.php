<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Extends `web_orders` with the warehouse preparation and delivery lifecycle (EPIC-17):
 * `pendiente` → `pagado` → `en_preparacion` → `entregado`, with separate audit columns for
 * who started preparation (`prepared_by`, `preparation_started_at`) and who confirmed the
 * exit from the warehouse (`delivered_by`, `delivered_at`).
 *
 * Rebuilt because SQLite cannot modify the inline CHECK constraints on `status` and `paid_at`
 * in place.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->dropReferencingForeignKeys();
        Schema::disableForeignKeyConstraints();

        Schema::create('web_orders_epic17', function (Blueprint $table) {
            $table->id();
            $table->rawColumn('number', 'integer check (number > 0)')->unique();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->rawColumn('status', "varchar(30) check (status in ('pendiente', 'pagado', 'en_preparacion', 'entregado'))")->default('pendiente');
            $table->index('status');
            $table->rawColumn('delivery_method', "varchar(20) check (delivery_method in ('retiro', 'envio')) check (delivery_method <> 'retiro' or pickup_branch_id is not null) check (delivery_method <> 'envio' or shipping_address is not null)")->default('retiro');
            $table->foreignId('pickup_branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->string('shipping_address', 255)->nullable();
            $table->string('shipping_notes', 255)->nullable();
            $table->rawColumn('items_amount', 'decimal(12, 2) check (items_amount > 0)');
            $table->rawColumn('shipping_cost', 'decimal(12, 2) check (shipping_cost >= 0)')->default(0);
            $table->rawColumn('total_amount', 'decimal(12, 2) check (total_amount > 0 and abs(items_amount + shipping_cost - total_amount) < 0.005)');
            $table->string('mp_preference_id', 100)->nullable();
            $table->string('mp_payment_id', 100)->nullable()->unique();
            $table->rawColumn('paid_amount', 'decimal(12, 2) check (paid_amount is null or paid_amount > 0)')->nullable();
            $table->rawColumn('paid_at', "timestamp check ((status = 'pendiente' and paid_at is null) or (status <> 'pendiente' and paid_at is not null))")->nullable();
            $table->rawColumn(
                'preparation_started_at',
                "timestamp check ((status in ('en_preparacion', 'entregado') and preparation_started_at is not null and prepared_by is not null) or (status in ('pendiente', 'pagado') and preparation_started_at is null and prepared_by is null))"
            )->nullable();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->rawColumn(
                'delivered_at',
                "timestamp check ((status = 'entregado' and delivered_at is not null and delivered_by is not null) or (status <> 'entregado' and delivered_at is null and delivered_by is null))"
            )->nullable();
            $table->foreignId('delivered_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('placed_at');
            $table->timestamps();

            $table->index(['customer_id', 'placed_at']);
            $table->index(['status', 'paid_at']);
        });

        DB::table('web_orders_epic17')->insertUsing(
            [
                'id', 'number', 'customer_id', 'status', 'delivery_method', 'pickup_branch_id',
                'shipping_address', 'shipping_notes', 'items_amount', 'shipping_cost',
                'total_amount', 'mp_preference_id', 'mp_payment_id', 'paid_amount', 'paid_at',
                'notes', 'placed_at', 'created_at', 'updated_at',
            ],
            DB::table('web_orders')->select([
                'id', 'number', 'customer_id', 'status', 'delivery_method', 'pickup_branch_id',
                'shipping_address', 'shipping_notes', 'items_amount', 'shipping_cost',
                'total_amount', 'mp_preference_id', 'mp_payment_id', 'paid_amount', 'paid_at',
                'notes', 'placed_at', 'created_at', 'updated_at',
            ])
        );

        Schema::drop('web_orders');
        Schema::rename('web_orders_epic17', 'web_orders');

        $this->resetPostgreSqlSequence();
        Schema::enableForeignKeyConstraints();
        $this->restoreReferencingForeignKeys();
    }

    public function down(): void
    {
        if (DB::table('web_orders')->whereIn('status', ['en_preparacion', 'entregado'])->exists()) {
            throw new RuntimeException(
                'No se puede revertir la migración de pedidos web: existen pedidos en preparación o entregados.'
            );
        }

        $this->dropReferencingForeignKeys();
        Schema::disableForeignKeyConstraints();

        Schema::create('web_orders_sprint4', function (Blueprint $table) {
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
            $table->rawColumn('shipping_cost', 'decimal(12, 2) check (shipping_cost >= 0)')->default(0);
            $table->rawColumn('total_amount', 'decimal(12, 2) check (total_amount > 0 and abs(items_amount + shipping_cost - total_amount) < 0.005)');
            $table->string('mp_preference_id', 100)->nullable();
            $table->string('mp_payment_id', 100)->nullable()->unique();
            $table->rawColumn('paid_amount', 'decimal(12, 2) check (paid_amount is null or paid_amount > 0)')->nullable();
            $table->rawColumn('paid_at', "timestamp check ((status = 'pagado' and paid_at is not null) or (status <> 'pagado' and paid_at is null))")->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('placed_at');
            $table->timestamps();

            $table->index(['customer_id', 'placed_at']);
        });

        DB::table('web_orders_sprint4')->insertUsing(
            [
                'id', 'number', 'customer_id', 'status', 'delivery_method', 'pickup_branch_id',
                'shipping_address', 'shipping_notes', 'items_amount', 'shipping_cost',
                'total_amount', 'mp_preference_id', 'mp_payment_id', 'paid_amount', 'paid_at',
                'notes', 'placed_at', 'created_at', 'updated_at',
            ],
            DB::table('web_orders')->select([
                'id', 'number', 'customer_id', 'status', 'delivery_method', 'pickup_branch_id',
                'shipping_address', 'shipping_notes', 'items_amount', 'shipping_cost',
                'total_amount', 'mp_preference_id', 'mp_payment_id', 'paid_amount', 'paid_at',
                'notes', 'placed_at', 'created_at', 'updated_at',
            ])
        );

        Schema::drop('web_orders');
        Schema::rename('web_orders_sprint4', 'web_orders');

        $this->resetPostgreSqlSequence();
        Schema::enableForeignKeyConstraints();
        $this->restoreReferencingForeignKeys();
    }

    private function dropReferencingForeignKeys(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (['web_order_items', 'sales'] as $referencingTable) {
            if (! Schema::hasTable($referencingTable)) {
                continue;
            }

            $foreignKeys = collect(Schema::getForeignKeys($referencingTable))
                ->filter(fn (array $foreignKey): bool => $foreignKey['foreign_table'] === 'web_orders')
                ->pluck('name');

            if ($foreignKeys->isEmpty()) {
                continue;
            }

            Schema::table($referencingTable, function (Blueprint $table) use ($foreignKeys) {
                $foreignKeys->each(fn (string $name) => $table->dropForeign($name));
            });
        }
    }

    private function restoreReferencingForeignKeys(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::table('web_order_items', function (Blueprint $table) {
            $table->foreign('web_order_id')->references('id')->on('web_orders')->cascadeOnDelete();
        });

        if (Schema::hasColumn('sales', 'web_order_id')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->foreign('web_order_id')->references('id')->on('web_orders')->restrictOnDelete();
            });
        }
    }

    private function resetPostgreSqlSequence(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            select setval(
                pg_get_serial_sequence('web_orders', 'id'),
                coalesce((select max(id) from web_orders), 1),
                exists(select 1 from web_orders)
            )
        SQL);
    }
};
