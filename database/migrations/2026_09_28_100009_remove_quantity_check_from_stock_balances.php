<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A sale is never blocked by missing stock (EPIC-06): if the article is at the till, it is sold,
 * and the balance may go negative. The `quantity >= 0` CHECK goes away; the rule that manual
 * movements and transfers cannot leave stock negative now lives only in their actions.
 *
 * Rebuilt because SQLite cannot drop a CHECK in place. No table references stock_balances.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->rebuild('stock_balances_revised', 'decimal(12, 3)');
    }

    /**
     * Fails if some balance is already negative: the old CHECK cannot hold that data.
     */
    public function down(): void
    {
        $this->rebuild('stock_balances_legacy', 'decimal(12, 3) check (quantity >= 0)');
    }

    private function rebuild(string $temporaryTable, string $quantityDefinition): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create($temporaryTable, function (Blueprint $table) use ($quantityDefinition) {
            $table->id();
            $table->foreignId('article_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->rawColumn('quantity', $quantityDefinition)->default(0);
            $table->timestamps();

            $table->unique(['article_id', 'warehouse_id']);
            $table->index('warehouse_id');
        });

        DB::table($temporaryTable)->insertUsing(
            ['id', 'article_id', 'warehouse_id', 'quantity', 'created_at', 'updated_at'],
            DB::table('stock_balances')->select(['id', 'article_id', 'warehouse_id', 'quantity', 'created_at', 'updated_at'])
        );

        Schema::drop('stock_balances');
        Schema::rename($temporaryTable, 'stock_balances');

        $this->resetPostgreSqlSequence();
        Schema::enableForeignKeyConstraints();
    }

    private function resetPostgreSqlSequence(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            select setval(
                pg_get_serial_sequence('stock_balances', 'id'),
                coalesce((select max(id) from stock_balances), 1),
                exists(select 1 from stock_balances)
            )
        SQL);
    }
};
