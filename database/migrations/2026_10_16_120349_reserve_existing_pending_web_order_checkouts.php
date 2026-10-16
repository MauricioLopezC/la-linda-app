<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('web_orders')
            ->where('status', 'pendiente')
            ->whereNotNull('mp_preference_id')
            ->whereNull('stock_reserved_until')
            ->update(['stock_reserved_until' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Existing checkout reservations must remain protected until reconciled.
    }
};
