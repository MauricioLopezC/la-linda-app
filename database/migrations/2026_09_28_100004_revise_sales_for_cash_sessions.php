<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ties counter sales to the cash session they happen in (HU-039) and adds the confirmed state
 * (EPIC-04), in a single rebuild of `sales`: SQLite cannot change a CHECK in place.
 *
 * Counter sales opened before cash sessions existed have no session to belong to. None of them
 * could be confirmed (the state did not exist), so they never moved stock or money: they are
 * deleted with their lines instead of being attached to a fictitious session.
 */
return new class extends Migration
{
    public function up(): void
    {
        $sessionlessSales = DB::table('sales')->where('channel', 'mostrador')->select('id');
        DB::table('sale_items')->whereIn('sale_id', $sessionlessSales)->delete();
        DB::table('sales')->where('channel', 'mostrador')->delete();

        $this->dropReferencingForeignKeys();
        Schema::disableForeignKeyConstraints();

        Schema::create('sales_revised', function (Blueprint $table) {
            $table->id();
            $table->foreignId('point_of_sale_id')->constrained('points_of_sale')->restrictOnDelete();
            $table->rawColumn('channel', "varchar(20) check (channel in ('mostrador', 'online')) check (channel = 'online' or cash_session_id is not null)");
            $table->foreignId('cash_session_id')->nullable()->constrained('cash_sessions')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->timestamp('opened_at')->index();
            $table->rawColumn('status', "varchar(20) check (status in ('abierta', 'confirmada', 'descartada'))")->default('abierta');
            $table->index('status');
            $table->rawColumn('confirmed_at', "timestamp check ((status = 'confirmada' and confirmed_at is not null) or (status <> 'confirmada' and confirmed_at is null))")->nullable();
            $table->rawColumn('total_amount', 'decimal(12, 2) check (total_amount >= 0)')->default(0);
            $table->timestamps();

            /* HU-060 rejects closing a session that still has open sales. */
            $table->index(['cash_session_id', 'status']);
        });

        DB::table('sales_revised')->insertUsing(
            [
                'id', 'point_of_sale_id', 'channel', 'customer_id', 'user_id', 'opened_at',
                'status', 'total_amount', 'created_at', 'updated_at',
            ],
            DB::table('sales')->select([
                'id', 'point_of_sale_id', 'channel', 'customer_id', 'user_id', 'opened_at',
                'status', 'total_amount', 'created_at', 'updated_at',
            ])
        );

        Schema::drop('sales');
        Schema::rename('sales_revised', 'sales');

        $this->resetPostgreSqlSequence();
        Schema::enableForeignKeyConstraints();
        $this->restoreReferencingForeignKeys();
    }

    /**
     * Confirmed sales cannot go back to the previous schema, which has no such state: rolling
     * back fails on them by design instead of silently turning them into open sales.
     */
    public function down(): void
    {
        $this->dropReferencingForeignKeys();
        Schema::disableForeignKeyConstraints();

        Schema::create('sales_legacy', function (Blueprint $table) {
            $table->id();
            $table->foreignId('point_of_sale_id')->constrained('points_of_sale')->restrictOnDelete();
            $table->rawColumn('channel', "varchar(20) check (channel in ('mostrador', 'online'))");
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->timestamp('opened_at')->index();
            $table->rawColumn('status', "varchar(20) check (status in ('abierta', 'descartada'))")->default('abierta');
            $table->index('status');
            $table->rawColumn('total_amount', 'decimal(12, 2) check (total_amount >= 0)')->default(0);
            $table->timestamps();
        });

        DB::table('sales_legacy')->insertUsing(
            [
                'id', 'point_of_sale_id', 'channel', 'customer_id', 'user_id', 'opened_at',
                'status', 'total_amount', 'created_at', 'updated_at',
            ],
            DB::table('sales')->select([
                'id', 'point_of_sale_id', 'channel', 'customer_id', 'user_id', 'opened_at',
                'status', 'total_amount', 'created_at', 'updated_at',
            ])
        );

        Schema::drop('sales');
        Schema::rename('sales_legacy', 'sales');

        $this->resetPostgreSqlSequence();
        Schema::enableForeignKeyConstraints();
        $this->restoreReferencingForeignKeys();
    }

    private function dropReferencingForeignKeys(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        /*
         * Looked up by target instead of by conventional name: after a rebuild of sale_items
         * the constraint keeps the temporary table's name (e.g. sale_items_revised_sale_id_foreign).
         */
        $foreignKeysToSales = collect(Schema::getForeignKeys('sale_items'))
            ->filter(fn (array $foreignKey): bool => $foreignKey['foreign_table'] === 'sales')
            ->pluck('name');

        Schema::table('sale_items', function (Blueprint $table) use ($foreignKeysToSales) {
            $foreignKeysToSales->each(fn (string $name) => $table->dropForeign($name));
        });
    }

    private function restoreReferencingForeignKeys(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::table('sale_items', function (Blueprint $table) {
            $table->foreign('sale_id')->references('id')->on('sales')->cascadeOnDelete();
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
