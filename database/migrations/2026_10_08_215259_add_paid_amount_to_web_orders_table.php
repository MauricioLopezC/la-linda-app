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
        Schema::table('web_orders', function (Blueprint $table) {
            $table->rawColumn('paid_amount', 'decimal(12, 2) check (paid_amount is null or paid_amount > 0)')->nullable()->after('mp_payment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('web_orders', function (Blueprint $table) {
            $table->dropColumn('paid_amount');
        });
    }
};
