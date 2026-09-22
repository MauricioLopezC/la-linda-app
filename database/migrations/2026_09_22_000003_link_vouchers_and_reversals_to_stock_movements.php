<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->dropReferencingForeignKeys();
        Schema::disableForeignKeyConstraints();

        Schema::create('stock_movements_hu026', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_movement_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_voucher_id')->nullable()->constrained('supplier_vouchers')->restrictOnDelete();
            $table->foreignId('reversal_of_movement_id')->nullable()->constrained('stock_movements_hu026')->restrictOnDelete();
            $table->unique('supplier_voucher_id', 'stock_movements_hu026_supplier_voucher_id_unique');
            $table->unique('reversal_of_movement_id', 'stock_movements_hu026_reversal_of_movement_id_unique');
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['warehouse_id', 'created_at']);
            $table->index('stock_movement_type_id');
            $table->index('user_id');
            $table->index('created_at');
        });

        DB::table('stock_movements_hu026')->insertUsing(
            [
                'id', 'stock_movement_type_id', 'warehouse_id', 'supplier_voucher_id',
                'reversal_of_movement_id', 'notes', 'user_id', 'created_at',
            ],
            DB::table('stock_movements')->select([
                'id', 'stock_movement_type_id', 'warehouse_id', DB::raw('NULL'),
                DB::raw('NULL'), 'notes', 'user_id', 'created_at',
            ])
        );

        Schema::drop('stock_movements');
        Schema::rename('stock_movements_hu026', 'stock_movements');
        $this->resetPostgreSqlSequence();
        Schema::enableForeignKeyConstraints();
        $this->restoreReferencingForeignKeys();
    }

    public function down(): void
    {
        $this->dropReferencingForeignKeys();
        Schema::disableForeignKeyConstraints();

        Schema::create('stock_movements_legacy', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_movement_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['warehouse_id', 'created_at']);
            $table->index('stock_movement_type_id');
            $table->index('user_id');
            $table->index('created_at');
        });

        DB::table('stock_movements_legacy')->insertUsing(
            [
                'id', 'stock_movement_type_id', 'warehouse_id', 'notes', 'user_id', 'created_at',
            ],
            DB::table('stock_movements')->select([
                'id', 'stock_movement_type_id', 'warehouse_id', 'notes', 'user_id', 'created_at',
            ])
        );

        Schema::drop('stock_movements');
        Schema::rename('stock_movements_legacy', 'stock_movements');
        $this->resetPostgreSqlSequence();
        Schema::enableForeignKeyConstraints();
        $this->restoreReferencingForeignKeys();
    }

    private function dropReferencingForeignKeys(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::table('stock_movement_items', function (Blueprint $table) {
            $table->dropForeign(['stock_movement_id']);
        });
    }

    private function restoreReferencingForeignKeys(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::table('stock_movement_items', function (Blueprint $table) {
            $table->foreign('stock_movement_id')->references('id')->on('stock_movements')->restrictOnDelete();
        });
    }

    private function resetPostgreSqlSequence(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            select setval(
                pg_get_serial_sequence('stock_movements', 'id'),
                coalesce((select max(id) from stock_movements), 1),
                exists(select 1 from stock_movements)
            )
        SQL);
    }
};
