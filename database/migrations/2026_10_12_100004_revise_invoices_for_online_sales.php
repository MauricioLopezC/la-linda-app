<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Allows `invoices` to be issued for online sales without a cash session or an internal
 * operator (`cash_session_id` and `user_id` nullable) and allows `invoice_items` to hold
 * the `"Envío a domicilio"` concept line (`article_id` nullable) (EPIC-15).
 *
 * Rebuilt to preserve inline CHECK constraints across SQLite and PostgreSQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->dropInvoiceItemsForeignKeyToInvoices();
        Schema::disableForeignKeyConstraints();

        Schema::create('invoices_epic15', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->unique()->constrained('sales')->restrictOnDelete();
            $table->foreignId('cash_session_id')->nullable()->constrained('cash_sessions')->restrictOnDelete();
            $table->foreignId('point_of_sale_id')->constrained('points_of_sale')->restrictOnDelete();
            $table->unsignedInteger('point_of_sale_number')->nullable();
            $table->rawColumn('type', "varchar(1) check (type in ('A', 'B'))");
            $table->rawColumn('number', 'integer check (number > 0)');
            $table->timestamp('issued_at')->index();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->string('customer_name', 255);
            $table->string('customer_tax_condition', 50);
            $table->string('customer_id_type', 20)->nullable();
            $table->string('customer_id_number', 20)->nullable();
            $table->string('customer_address', 255)->nullable();
            $table->rawColumn('net_amount', 'decimal(12, 2) check (net_amount >= 0)');
            $table->rawColumn('vat_amount', 'decimal(12, 2) check (vat_amount >= 0)');
            $table->rawColumn('total_amount', 'decimal(12, 2) check (total_amount > 0 and abs(net_amount + vat_amount - total_amount) < 0.005)');
            $table->rawColumn('user_id', 'bigint check ((cash_session_id is null and user_id is null) or (cash_session_id is not null and user_id is not null))')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['point_of_sale_id', 'type', 'number']);
            $table->index('cash_session_id');
            $table->index('customer_id');
        });

        DB::table('invoices_epic15')->insertUsing(
            [
                'id', 'sale_id', 'cash_session_id', 'point_of_sale_id', 'point_of_sale_number',
                'type', 'number', 'issued_at', 'customer_id', 'customer_name',
                'customer_tax_condition', 'customer_id_type', 'customer_id_number',
                'customer_address', 'net_amount', 'vat_amount', 'total_amount',
                'user_id', 'created_at',
            ],
            DB::table('invoices')->select([
                'id', 'sale_id', 'cash_session_id', 'point_of_sale_id', 'point_of_sale_number',
                'type', 'number', 'issued_at', 'customer_id', 'customer_name',
                'customer_tax_condition', 'customer_id_type', 'customer_id_number',
                'customer_address', 'net_amount', 'vat_amount', 'total_amount',
                'user_id', 'created_at',
            ])
        );

        Schema::drop('invoices');
        Schema::rename('invoices_epic15', 'invoices');
        $this->resetPostgreSqlSequence('invoices');

        Schema::create('invoice_items_epic15', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('article_id')->nullable()->constrained('articles')->restrictOnDelete();
            $table->string('description', 255);
            $table->rawColumn('quantity', 'decimal(12, 3) check (quantity > 0)');
            $table->rawColumn('unit_price', 'decimal(12, 2) check (unit_price > 0)');
            $table->rawColumn('vat_rate', 'decimal(5, 2) check (vat_rate >= 0)');
            $table->rawColumn('net_amount', 'decimal(12, 2) check (net_amount >= 0)');
            $table->rawColumn('vat_amount', 'decimal(12, 2) check (vat_amount >= 0)');
            $table->rawColumn('line_total', 'decimal(12, 2) check (line_total > 0 and abs(net_amount + vat_amount - line_total) < 0.005)');

            $table->unique(['invoice_id', 'article_id']);
        });

        DB::table('invoice_items_epic15')->insertUsing(
            [
                'id', 'invoice_id', 'article_id', 'description', 'quantity',
                'unit_price', 'vat_rate', 'net_amount', 'vat_amount', 'line_total',
            ],
            DB::table('invoice_items')->select([
                'id', 'invoice_id', 'article_id', 'description', 'quantity',
                'unit_price', 'vat_rate', 'net_amount', 'vat_amount', 'line_total',
            ])
        );

        Schema::drop('invoice_items');
        Schema::rename('invoice_items_epic15', 'invoice_items');
        $this->resetPostgreSqlSequence('invoice_items');

        /* At most one concept line without article per invoice: the "Envío a domicilio" line. */
        DB::statement('create unique index invoice_items_one_concept_line on invoice_items (invoice_id) where article_id is null');

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        if (
            DB::table('invoices')->whereNull('cash_session_id')->orWhereNull('user_id')->exists()
            || DB::table('invoice_items')->whereNull('article_id')->exists()
        ) {
            throw new RuntimeException(
                'No se puede revertir la migración de facturas online: existen facturas sin turno/usuario o renglones sin artículo.'
            );
        }

        $this->dropInvoiceItemsForeignKeyToInvoices();
        Schema::disableForeignKeyConstraints();

        Schema::create('invoices_sprint4', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->unique()->constrained('sales')->restrictOnDelete();
            $table->foreignId('cash_session_id')->constrained('cash_sessions')->restrictOnDelete();
            $table->foreignId('point_of_sale_id')->constrained('points_of_sale')->restrictOnDelete();
            $table->unsignedInteger('point_of_sale_number')->nullable();
            $table->rawColumn('type', "varchar(1) check (type in ('A', 'B'))");
            $table->rawColumn('number', 'integer check (number > 0)');
            $table->timestamp('issued_at')->index();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->string('customer_name', 255);
            $table->string('customer_tax_condition', 50);
            $table->string('customer_id_type', 20)->nullable();
            $table->string('customer_id_number', 20)->nullable();
            $table->string('customer_address', 255)->nullable();
            $table->rawColumn('net_amount', 'decimal(12, 2) check (net_amount >= 0)');
            $table->rawColumn('vat_amount', 'decimal(12, 2) check (vat_amount >= 0)');
            $table->rawColumn('total_amount', 'decimal(12, 2) check (total_amount > 0 and abs(net_amount + vat_amount - total_amount) < 0.005)');
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['point_of_sale_id', 'type', 'number']);
            $table->index('cash_session_id');
            $table->index('customer_id');
        });

        DB::table('invoices_sprint4')->insertUsing(
            [
                'id', 'sale_id', 'cash_session_id', 'point_of_sale_id', 'point_of_sale_number',
                'type', 'number', 'issued_at', 'customer_id', 'customer_name',
                'customer_tax_condition', 'customer_id_type', 'customer_id_number',
                'customer_address', 'net_amount', 'vat_amount', 'total_amount',
                'user_id', 'created_at',
            ],
            DB::table('invoices')->select([
                'id', 'sale_id', 'cash_session_id', 'point_of_sale_id', 'point_of_sale_number',
                'type', 'number', 'issued_at', 'customer_id', 'customer_name',
                'customer_tax_condition', 'customer_id_type', 'customer_id_number',
                'customer_address', 'net_amount', 'vat_amount', 'total_amount',
                'user_id', 'created_at',
            ])
        );

        Schema::drop('invoices');
        Schema::rename('invoices_sprint4', 'invoices');
        $this->resetPostgreSqlSequence('invoices');

        Schema::create('invoice_items_sprint4', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('article_id')->constrained('articles')->restrictOnDelete();
            $table->string('description', 255);
            $table->rawColumn('quantity', 'decimal(12, 3) check (quantity > 0)');
            $table->rawColumn('unit_price', 'decimal(12, 2) check (unit_price > 0)');
            $table->rawColumn('vat_rate', 'decimal(5, 2) check (vat_rate >= 0)');
            $table->rawColumn('net_amount', 'decimal(12, 2) check (net_amount >= 0)');
            $table->rawColumn('vat_amount', 'decimal(12, 2) check (vat_amount >= 0)');
            $table->rawColumn('line_total', 'decimal(12, 2) check (line_total > 0 and abs(net_amount + vat_amount - line_total) < 0.005)');

            $table->unique(['invoice_id', 'article_id']);
        });

        DB::table('invoice_items_sprint4')->insertUsing(
            [
                'id', 'invoice_id', 'article_id', 'description', 'quantity',
                'unit_price', 'vat_rate', 'net_amount', 'vat_amount', 'line_total',
            ],
            DB::table('invoice_items')->select([
                'id', 'invoice_id', 'article_id', 'description', 'quantity',
                'unit_price', 'vat_rate', 'net_amount', 'vat_amount', 'line_total',
            ])
        );

        Schema::drop('invoice_items');
        Schema::rename('invoice_items_sprint4', 'invoice_items');
        $this->resetPostgreSqlSequence('invoice_items');

        Schema::enableForeignKeyConstraints();
    }

    private function dropInvoiceItemsForeignKeyToInvoices(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $foreignKeys = collect(Schema::getForeignKeys('invoice_items'))
            ->filter(fn (array $foreignKey): bool => $foreignKey['foreign_table'] === 'invoices')
            ->pluck('name');

        if ($foreignKeys->isEmpty()) {
            return;
        }

        Schema::table('invoice_items', function (Blueprint $table) use ($foreignKeys) {
            $foreignKeys->each(fn (string $name) => $table->dropForeign($name));
        });
    }

    private function resetPostgreSqlSequence(string $table): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<SQL
            select setval(
                pg_get_serial_sequence('{$table}', 'id'),
                coalesce((select max(id) from {$table}), 1),
                exists(select 1 from {$table})
            )
        SQL);
    }
};
