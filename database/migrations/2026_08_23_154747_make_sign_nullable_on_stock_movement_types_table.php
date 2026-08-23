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
        Schema::table('stock_movement_types', function (Blueprint $table) {
            $table->smallInteger('sign')->nullable()->comment('1 for addition (suma), -1 for deduction (resta), null when it varies per line')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_movement_types', function (Blueprint $table) {
            $table->smallInteger('sign')->comment('1 for addition (suma), -1 for deduction (resta)')->change();
        });
    }
};
