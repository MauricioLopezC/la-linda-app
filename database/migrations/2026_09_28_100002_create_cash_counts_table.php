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
        /*
         * Bill-by-bill count of a cash session, at opening and at closing (HU-057 / HU-060).
         * Written once with the session event, so there are no timestamps.
         */
        Schema::create('cash_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_session_id')->constrained('cash_sessions')->cascadeOnDelete();
            $table->rawColumn('moment', "varchar(20) check (moment in ('apertura', 'cierre'))");

            /* A CashDenomination value. The enum owns the list, so a new bill needs no rebuild. */
            $table->rawColumn('denomination', 'decimal(12, 2) check (denomination > 0)');
            $table->rawColumn('quantity', 'integer check (quantity >= 0)');

            $table->unique(['cash_session_id', 'moment', 'denomination']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_counts');
    }
};
