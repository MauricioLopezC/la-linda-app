<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('supplier_vouchers', function (Blueprint $table) {
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
                'supplier_vouchers_fiscal_identity_unique'
            );
            $table->index('issue_date');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_vouchers');
    }
};
