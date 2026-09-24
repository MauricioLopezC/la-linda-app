<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An imputation now counts as "received" (remito) or "invoiced" (factura) depending on the
 * voucher type, so the column holds the quantity applied to the order line, not a reception.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_voucher_imputations', function (Blueprint $table) {
            $table->renameColumn('quantity_received', 'quantity_applied');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_voucher_imputations', function (Blueprint $table) {
            $table->renameColumn('quantity_applied', 'quantity_received');
        });
    }
};
