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

        Schema::create('purchase_orders_revised', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->string('order_number', 32)->unique();
            $table->string('payment_terms', 255)->nullable();
            $table->date('issue_date')->index();
            $table->rawColumn('expected_delivery_date', 'date check (expected_delivery_date is null or expected_delivery_date >= issue_date)')->nullable();
            $table->rawColumn('total_amount', 'decimal(12, 2) check (total_amount >= 0)')->default(0);
            $table->rawColumn('status', "varchar(20) check (status in ('borrador', 'emitida', 'cumplida', 'cancelada'))")->default('borrador');
            $table->index('status');
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users');
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
        });

        DB::table('purchase_orders_revised')->insertUsing(
            [
                'id', 'supplier_id', 'warehouse_id', 'order_number', 'payment_terms',
                'issue_date', 'expected_delivery_date', 'total_amount', 'status', 'notes',
                'user_id', 'cancelled_at', 'cancelled_by', 'cancellation_reason',
                'created_at', 'updated_at',
            ],
            DB::table('purchase_orders')->select([
                'id', 'supplier_id', 'warehouse_id', 'order_number', 'payment_terms',
                'issue_date', 'expected_delivery_date', 'total_amount', 'status', 'notes',
                'user_id', 'cancelled_at', 'cancelled_by', 'cancellation_reason',
                'created_at', 'updated_at',
            ])
        );

        Schema::drop('purchase_orders');
        Schema::rename('purchase_orders_revised', 'purchase_orders');

        $this->resetPostgreSqlSequence();
        Schema::enableForeignKeyConstraints();
        $this->restoreReferencingForeignKeys();
    }

    public function down(): void
    {
        $this->dropReferencingForeignKeys();
        Schema::disableForeignKeyConstraints();

        Schema::create('purchase_orders_legacy', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->string('order_number', 32)->unique();
            $table->string('payment_terms', 255)->nullable();
            $table->date('issue_date')->index();
            $table->rawColumn('expected_delivery_date', 'date check (expected_delivery_date is null or expected_delivery_date >= issue_date)')->nullable();
            $table->rawColumn('total_amount', 'decimal(12, 2) check (total_amount >= 0)')->default(0);
            $table->rawColumn('status', "varchar(20) check (status in ('borrador', 'emitida', 'cancelada'))")->default('borrador');
            $table->index('status');
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users');
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
        });

        DB::table('purchase_orders_legacy')->insertUsing(
            [
                'id', 'supplier_id', 'warehouse_id', 'order_number', 'payment_terms',
                'issue_date', 'expected_delivery_date', 'total_amount', 'status', 'notes',
                'user_id', 'cancelled_at', 'cancelled_by', 'cancellation_reason',
                'created_at', 'updated_at',
            ],
            DB::table('purchase_orders')->select([
                'id', 'supplier_id', 'warehouse_id', 'order_number', 'payment_terms',
                'issue_date', 'expected_delivery_date', 'total_amount', 'status', 'notes',
                'user_id', 'cancelled_at', 'cancelled_by', 'cancellation_reason',
                'created_at', 'updated_at',
            ])
        );

        Schema::drop('purchase_orders');
        Schema::rename('purchase_orders_legacy', 'purchase_orders');

        $this->resetPostgreSqlSequence();
        Schema::enableForeignKeyConstraints();
        $this->restoreReferencingForeignKeys();
    }

    private function dropReferencingForeignKeys(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropForeign(['purchase_order_id']);
        });
    }

    private function restoreReferencingForeignKeys(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->cascadeOnDelete();
        });
    }

    private function resetPostgreSqlSequence(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            select setval(
                pg_get_serial_sequence('purchase_orders', 'id'),
                coalesce((select max(id) from purchase_orders), 1),
                exists(select 1 from purchase_orders)
            )
        SQL);
    }
};
