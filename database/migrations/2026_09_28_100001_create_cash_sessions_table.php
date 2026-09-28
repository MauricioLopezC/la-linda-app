<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('point_of_sale_id')->constrained('points_of_sale')->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->rawColumn('status', "varchar(20) check (status in ('abierta', 'cerrada'))")->default('abierta');
            $table->timestamp('opened_at');

            /* Sum of the opening count: it is derived from cash_counts, never typed. */
            $table->rawColumn('opening_amount', 'decimal(12, 2) check (opening_amount >= 0)');
            $table->rawColumn('closed_at', "timestamp check ((status = 'abierta' and closed_at is null) or (status = 'cerrada' and closed_at is not null))")->nullable();
            $table->text('closing_notes')->nullable();
            $table->timestamps();

            /* HU-061 lists the sessions of a point of sale by date. */
            $table->index(['point_of_sale_id', 'opened_at']);
            $table->index('user_id');
        });

        /*
         * A point of sale and a user each have at most one open session. These are partial indexes:
         * CREATE INDEX ... WHERE is valid in SQLite and Postgres alike, unlike ALTER TABLE ADD
         * CONSTRAINT, so the rule is exercised by the test suite too.
         */
        DB::statement("create unique index cash_sessions_open_point_of_sale_unique on cash_sessions (point_of_sale_id) where status = 'abierta'");
        DB::statement("create unique index cash_sessions_open_user_unique on cash_sessions (user_id) where status = 'abierta'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_sessions');
    }
};
