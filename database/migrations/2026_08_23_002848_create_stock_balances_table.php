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
        Schema::create('stock_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();

            /*
             * The CHECK is declared inline via rawColumn because SQLite (dev and tests) does not
             * support ALTER TABLE ADD CONSTRAINT, so a DB::statement() after Schema::create()
             * would only ever run on Postgres and leave the rule untested.
             *
             * This is the single guarantee that no flow can leave stock negative: it holds for
             * the manual adjustment of HU-017 and for every automatic movement that comes later.
             */
            $table->rawColumn('quantity', 'decimal(12, 3) check (quantity >= 0)')->default(0);
            $table->timestamps();

            /* Makes the balance upsert atomic: one row per article/warehouse pair. */
            $table->unique(['article_id', 'warehouse_id']);

            /* HU-016 lists the stock of a single warehouse; Postgres does not index FKs on its own. */
            $table->index('warehouse_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_balances');
    }
};
