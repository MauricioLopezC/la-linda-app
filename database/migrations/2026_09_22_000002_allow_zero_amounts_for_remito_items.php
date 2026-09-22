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

        Schema::create('supplier_voucher_items_hu026', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_voucher_id')->constrained('supplier_vouchers')->restrictOnDelete();
            $table->rawColumn('position', 'integer check (position > 0)');
            $table->foreignId('article_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('description', 500);
            $table->rawColumn('quantity', 'decimal(12, 3) check (quantity > 0)');
            $table->string('unit_of_measure', 50);
            $table->rawColumn('unit_price', 'decimal(12, 2) check (unit_price >= 0)');
            $table->rawColumn('line_total', 'decimal(12, 2) check (line_total >= 0)');

            $table->unique(['supplier_voucher_id', 'position'], 'supplier_voucher_items_hu026_pos_unique');
            $table->index('article_id');
        });

        DB::table('supplier_voucher_items_hu026')->insertUsing(
            [
                'id', 'supplier_voucher_id', 'position', 'article_id', 'description',
                'quantity', 'unit_of_measure', 'unit_price', 'line_total',
            ],
            DB::table('supplier_voucher_items')->select([
                'id', 'supplier_voucher_id', 'position', 'article_id', 'description',
                'quantity', 'unit_of_measure', 'unit_price', 'line_total',
            ])
        );

        Schema::drop('supplier_voucher_items');
        Schema::rename('supplier_voucher_items_hu026', 'supplier_voucher_items');
        $this->resetPostgreSqlSequence();
        Schema::enableForeignKeyConstraints();
        $this->restoreReferencingForeignKeys();
    }

    public function down(): void
    {
        if (DB::table('supplier_voucher_items')
            ->where('unit_price', '<=', 0)
            ->orWhere('line_total', '<=', 0)
            ->exists()) {
            throw new RuntimeException(
                'No se puede revertir la migración HU-026: existen renglones con importes incompatibles con el esquema anterior.'
            );
        }

        $this->dropReferencingForeignKeys();
        Schema::disableForeignKeyConstraints();

        Schema::create('supplier_voucher_items_legacy', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_voucher_id')->constrained('supplier_vouchers')->restrictOnDelete();
            $table->rawColumn('position', 'integer check (position > 0)');
            $table->foreignId('article_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('description', 500);
            $table->rawColumn('quantity', 'decimal(12, 3) check (quantity > 0)');
            $table->string('unit_of_measure', 50);
            $table->rawColumn('unit_price', 'decimal(12, 2) check (unit_price > 0)');
            $table->rawColumn('line_total', 'decimal(12, 2) check (line_total > 0)');

            $table->unique(['supplier_voucher_id', 'position'], 'supplier_voucher_items_legacy_pos_unique');
            $table->index('article_id');
        });

        DB::table('supplier_voucher_items_legacy')->insertUsing(
            [
                'id', 'supplier_voucher_id', 'position', 'article_id', 'description',
                'quantity', 'unit_of_measure', 'unit_price', 'line_total',
            ],
            DB::table('supplier_voucher_items')->select([
                'id', 'supplier_voucher_id', 'position', 'article_id', 'description',
                'quantity', 'unit_of_measure', 'unit_price', 'line_total',
            ])
        );

        Schema::drop('supplier_voucher_items');
        Schema::rename('supplier_voucher_items_legacy', 'supplier_voucher_items');
        $this->resetPostgreSqlSequence();
        Schema::enableForeignKeyConstraints();
        $this->restoreReferencingForeignKeys();
    }

    private function dropReferencingForeignKeys(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::table('purchase_order_voucher_imputations', function (Blueprint $table) {
            $table->dropForeign(['supplier_voucher_item_id']);
        });
    }

    private function restoreReferencingForeignKeys(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::table('purchase_order_voucher_imputations', function (Blueprint $table) {
            $table->foreign('supplier_voucher_item_id')->references('id')->on('supplier_voucher_items')->cascadeOnDelete();
        });
    }

    private function resetPostgreSqlSequence(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            select setval(
                pg_get_serial_sequence('supplier_voucher_items', 'id'),
                coalesce((select max(id) from supplier_voucher_items), 1),
                exists(select 1 from supplier_voucher_items)
            )
        SQL);
    }
};
