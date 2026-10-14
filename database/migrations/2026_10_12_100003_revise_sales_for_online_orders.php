<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Links every online sale to the paid web order that originated it (`web_order_id`, unique)
 * and stores its shipping amount (`shipping_amount`) so the sale total matches the payment
 * accredited by Mercado Pago (EPIC-15).
 *
 * Rebuilt because SQLite cannot modify the `channel` CHECK constraint in place.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('sales')->where('channel', 'online')->exists()) {
            throw new RuntimeException(
                'No se puede aplicar la migración de ventas online: existen ventas online sin pedido web que las origine.'
            );
        }

        $this->dropReferencingForeignKeys();
        Schema::disableForeignKeyConstraints();

        Schema::create('sales_epic15', function (Blueprint $table) {
            $table->id();
            $table->foreignId('point_of_sale_id')->constrained('points_of_sale')->restrictOnDelete();
            $table->rawColumn(
                'channel',
                "varchar(20) check (channel in ('mostrador', 'online')) check (channel = 'online' or cash_session_id is not null) check ((channel = 'online' and web_order_id is not null) or (channel = 'mostrador' and web_order_id is null))"
            );
            $table->foreignId('cash_session_id')->nullable()->constrained('cash_sessions')->restrictOnDelete();
            $table->foreignId('web_order_id')->nullable()->unique()->constrained('web_orders')->restrictOnDelete();
            $table->foreignId('price_list_id')->nullable()->constrained('price_lists')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->timestamp('opened_at')->index();
            $table->rawColumn('status', "varchar(20) check (status in ('abierta', 'confirmada', 'descartada'))")->default('abierta');
            $table->index('status');
            $table->rawColumn('confirmed_at', "timestamp check ((status = 'confirmada' and confirmed_at is not null) or (status <> 'confirmada' and confirmed_at is null))")->nullable();
            $table->rawColumn('shipping_amount', 'decimal(12, 2) check (shipping_amount >= 0)')->default(0);
            $table->rawColumn('total_amount', 'decimal(12, 2) check (total_amount >= 0)')->default(0);
            $table->timestamps();

            $table->index(['cash_session_id', 'status']);
            $table->index(['status', 'confirmed_at']);
        });

        DB::table('sales_epic15')->insertUsing(
            [
                'id', 'point_of_sale_id', 'channel', 'cash_session_id', 'price_list_id',
                'customer_id', 'user_id', 'opened_at', 'status', 'confirmed_at',
                'total_amount', 'created_at', 'updated_at',
            ],
            DB::table('sales')->select([
                'id', 'point_of_sale_id', 'channel', 'cash_session_id', 'price_list_id',
                'customer_id', 'user_id', 'opened_at', 'status', 'confirmed_at',
                'total_amount', 'created_at', 'updated_at',
            ])
        );

        Schema::drop('sales');
        Schema::rename('sales_epic15', 'sales');

        $this->resetPostgreSqlSequence();
        Schema::enableForeignKeyConstraints();
        $this->restoreReferencingForeignKeys();
    }

    public function down(): void
    {
        if (DB::table('sales')->whereNotNull('web_order_id')->orWhere('shipping_amount', '>', 0)->exists()) {
            throw new RuntimeException(
                'No se puede revertir la migración de ventas online: existen ventas vinculadas a pedidos web o con costo de envío.'
            );
        }

        $this->dropReferencingForeignKeys();
        Schema::disableForeignKeyConstraints();

        Schema::create('sales_sprint4', function (Blueprint $table) {
            $table->id();
            $table->foreignId('point_of_sale_id')->constrained('points_of_sale')->restrictOnDelete();
            $table->rawColumn('channel', "varchar(20) check (channel in ('mostrador', 'online')) check (channel = 'online' or cash_session_id is not null)");
            $table->foreignId('cash_session_id')->nullable()->constrained('cash_sessions')->restrictOnDelete();
            $table->foreignId('price_list_id')->nullable()->constrained('price_lists')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->timestamp('opened_at')->index();
            $table->rawColumn('status', "varchar(20) check (status in ('abierta', 'confirmada', 'descartada'))")->default('abierta');
            $table->index('status');
            $table->rawColumn('confirmed_at', "timestamp check ((status = 'confirmada' and confirmed_at is not null) or (status <> 'confirmada' and confirmed_at is null))")->nullable();
            $table->rawColumn('total_amount', 'decimal(12, 2) check (total_amount >= 0)')->default(0);
            $table->timestamps();

            $table->index(['cash_session_id', 'status']);
        });

        DB::table('sales_sprint4')->insertUsing(
            [
                'id', 'point_of_sale_id', 'channel', 'cash_session_id', 'price_list_id',
                'customer_id', 'user_id', 'opened_at', 'status', 'confirmed_at',
                'total_amount', 'created_at', 'updated_at',
            ],
            DB::table('sales')->select([
                'id', 'point_of_sale_id', 'channel', 'cash_session_id', 'price_list_id',
                'customer_id', 'user_id', 'opened_at', 'status', 'confirmed_at',
                'total_amount', 'created_at', 'updated_at',
            ])
        );

        Schema::drop('sales');
        Schema::rename('sales_sprint4', 'sales');

        $this->resetPostgreSqlSequence();
        Schema::enableForeignKeyConstraints();
        $this->restoreReferencingForeignKeys();
    }

    private function dropReferencingForeignKeys(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (['sale_items', 'cash_movements', 'stock_movements', 'invoices'] as $referencingTable) {
            if (! Schema::hasTable($referencingTable)) {
                continue;
            }

            $foreignKeys = collect(Schema::getForeignKeys($referencingTable))
                ->filter(fn (array $foreignKey): bool => $foreignKey['foreign_table'] === 'sales')
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

        Schema::table('sale_items', function (Blueprint $table) {
            $table->foreign('sale_id')->references('id')->on('sales')->cascadeOnDelete();
        });

        Schema::table('cash_movements', function (Blueprint $table) {
            $table->foreign('sale_id')->references('id')->on('sales')->restrictOnDelete();
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreign('sale_id')->references('id')->on('sales')->restrictOnDelete();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreign('sale_id')->references('id')->on('sales')->restrictOnDelete();
        });
    }

    private function resetPostgreSqlSequence(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            select setval(
                pg_get_serial_sequence('sales', 'id'),
                coalesce((select max(id) from sales), 1),
                exists(select 1 from sales)
            )
        SQL);
    }
};
