<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The kind tells the cash closing how to count each method (EPIC-04 / HU-060): only `efectivo`
     * gives change and is counted bill by bill, `tarjeta` is declared with the POSNET batch number.
     * Existing methods are classified by name so production does not depend on re-seeding.
     */
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->rawColumn('kind', "varchar(20) check (kind in ('efectivo', 'tarjeta', 'billetera_virtual', 'transferencia', 'otro'))")->default('otro');
        });

        DB::table('payment_methods')->where('name_normalized', 'efectivo')->update(['kind' => 'efectivo']);
        DB::table('payment_methods')->where('name_normalized', 'like', 'tarjeta%')->update(['kind' => 'tarjeta']);
        DB::table('payment_methods')->where('name_normalized', 'like', 'transferencia%')->update(['kind' => 'transferencia']);
        DB::table('payment_methods')->where('name_normalized', 'mercado pago')->update(['kind' => 'billetera_virtual']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
