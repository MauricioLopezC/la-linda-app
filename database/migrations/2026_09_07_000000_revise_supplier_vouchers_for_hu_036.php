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

        Schema::create('supplier_vouchers_revised', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->rawColumn('type', "varchar(20) check (type in ('factura', 'nota_credito', 'nota_debito'))");
            $table->rawColumn('letter', "varchar(1) check (letter in ('A', 'B', 'C', 'M'))")->default('A');
            $table->rawColumn('point_of_sale', 'varchar(4) check (length(point_of_sale) = 4)');
            $table->rawColumn('number', 'varchar(8) check (length(number) = 8)');
            $table->date('issue_date');
            $table->rawColumn('due_date', 'date check (due_date is null or due_date >= issue_date)')->nullable();
            $table->rawColumn('total_amount', 'decimal(12, 2) check (total_amount > 0)');
            $table->rawColumn(
                'status',
                "varchar(30) check (status in ('pendiente', 'pagada_parcial', 'pagada', 'pendiente_imputar', 'imputada_parcial', 'imputada', 'anulada'))"
            );
            $table->text('notes')->nullable();
            $table->timestamp('annulled_at')->nullable();
            $table->foreignId('annulled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('annulment_reason')->nullable();
            $table->timestamps();

            $table->unique(
                ['supplier_id', 'type', 'letter', 'point_of_sale', 'number'],
                'supplier_vouchers_revised_fiscal_identity_unique'
            );
            $table->index('issue_date');
            $table->index('status');
            $table->index('due_date');
        });

        DB::table('supplier_vouchers_revised')->insertUsing(
            [
                'id', 'supplier_id', 'type', 'letter', 'point_of_sale', 'number',
                'issue_date', 'due_date', 'total_amount', 'status', 'notes',
                'annulled_at', 'annulled_by', 'annulment_reason', 'created_at', 'updated_at',
            ],
            DB::table('supplier_vouchers')->select([
                'id', 'supplier_id', 'type', 'letter', 'point_of_sale', 'number',
                'issue_date', 'due_date', 'total_amount',
                DB::raw("case when type = 'nota_debito' and status in ('pendiente_imputar', 'imputada_parcial', 'imputada') then 'pendiente' else status end"),
                'notes',
                DB::raw('NULL'), DB::raw('NULL'), DB::raw('NULL'), 'created_at', 'updated_at',
            ])
        );

        Schema::drop('supplier_vouchers');
        Schema::rename('supplier_vouchers_revised', 'supplier_vouchers');
        $this->resetPostgreSqlSequence();
        Schema::enableForeignKeyConstraints();
        $this->restoreReferencingForeignKeys();
    }

    public function down(): void
    {
        $this->dropReferencingForeignKeys();
        Schema::disableForeignKeyConstraints();

        Schema::create('supplier_vouchers_legacy', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->rawColumn('type', "varchar(20) check (type in ('factura', 'nota_credito', 'nota_debito'))");
            $table->rawColumn('letter', "varchar(1) check (letter in ('A', 'B', 'C', 'M'))")->default('A');
            $table->rawColumn('point_of_sale', 'varchar(4) check (length(point_of_sale) = 4)');
            $table->rawColumn('number', 'varchar(8) check (length(number) = 8)');
            $table->date('issue_date');
            $table->rawColumn('due_date', 'date check (due_date is null or due_date >= issue_date)')->nullable();
            $table->rawColumn('net_amount', 'decimal(12, 2) check (net_amount >= 0)');
            $table->rawColumn('vat_amount', 'decimal(12, 2) check (vat_amount >= 0)');
            $table->rawColumn('other_taxes_amount', 'decimal(12, 2) check (other_taxes_amount >= 0)')->default(0);
            $table->rawColumn(
                'total_amount',
                'decimal(12, 2) check (total_amount > 0 and round(total_amount, 2) = round(net_amount + vat_amount + other_taxes_amount, 2))'
            );
            $table->rawColumn(
                'status',
                "varchar(30) check (status in ('pendiente', 'pagada_parcial', 'pagada', 'pendiente_imputar', 'imputada_parcial', 'imputada', 'anulada'))"
            );
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(
                ['supplier_id', 'type', 'letter', 'point_of_sale', 'number'],
                'supplier_vouchers_legacy_fiscal_identity_unique'
            );
            $table->index('issue_date');
            $table->index('status');
        });

        DB::table('supplier_vouchers_legacy')->insertUsing(
            [
                'id', 'supplier_id', 'type', 'letter', 'point_of_sale', 'number',
                'issue_date', 'due_date', 'net_amount', 'vat_amount', 'other_taxes_amount',
                'total_amount', 'status', 'notes', 'created_at', 'updated_at',
            ],
            DB::table('supplier_vouchers')->select([
                'id', 'supplier_id', 'type', 'letter', 'point_of_sale', 'number',
                'issue_date', 'due_date', 'total_amount', DB::raw('0'), DB::raw('0'),
                'total_amount', 'status', 'notes', 'created_at', 'updated_at',
            ])
        );

        Schema::drop('supplier_vouchers');
        Schema::rename('supplier_vouchers_legacy', 'supplier_vouchers');
        $this->resetPostgreSqlSequence();
        Schema::enableForeignKeyConstraints();
        $this->restoreReferencingForeignKeys();
    }

    private function dropReferencingForeignKeys(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::table('voucher_applications', function (Blueprint $table) {
            $table->dropForeign(['source_voucher_id']);
            $table->dropForeign(['target_voucher_id']);
        });

        Schema::table('payment_order_items', function (Blueprint $table) {
            $table->dropForeign(['supplier_voucher_id']);
        });
    }

    private function restoreReferencingForeignKeys(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::table('voucher_applications', function (Blueprint $table) {
            $table->foreign('source_voucher_id')->references('id')->on('supplier_vouchers')->restrictOnDelete();
            $table->foreign('target_voucher_id')->references('id')->on('supplier_vouchers')->restrictOnDelete();
        });

        Schema::table('payment_order_items', function (Blueprint $table) {
            $table->foreign('supplier_voucher_id')->references('id')->on('supplier_vouchers')->restrictOnDelete();
        });
    }

    private function resetPostgreSqlSequence(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            select setval(
                pg_get_serial_sequence('supplier_vouchers', 'id'),
                coalesce((select max(id) from supplier_vouchers), 1),
                exists(select 1 from supplier_vouchers)
            )
        SQL);
    }
};
