<?php

use App\Actions\Purchasing\EvaluatePurchaseOrderFulfillment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Orders used to close as soon as any voucher covered them. They now close only when every line
 * is both received (remitos) and invoiced (invoices), so orders fulfilled by an invoice alone
 * must go back to issued. No-op on an empty database.
 */
return new class extends Migration
{
    public function up(): void
    {
        $orderIds = DB::table('purchase_orders')
            ->whereIn('status', ['emitida', 'cumplida'])
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        DB::transaction(fn () => app(EvaluatePurchaseOrderFulfillment::class)->handleMany($orderIds));
    }

    public function down(): void
    {
        // Status is derived from the imputations; there is nothing to restore.
    }
};
