<?php

use App\Models\Pricing\PriceList;
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
        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('price_list_id')
                ->nullable()
                ->after('tax_condition')
                ->constrained('price_lists')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeignIdFor(PriceList::class);
            $table->dropColumn('price_list_id');
        });
    }
};
