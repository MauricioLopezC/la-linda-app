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
        Schema::create('stock_movement_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_movement_id')->constrained()->restrictOnDelete();
            $table->foreignId('article_id')->constrained()->restrictOnDelete();

            /*
             * Signed delta, the source of truth for the sign of the movement: negative leaves the
             * warehouse, positive enters it. stock_movement_types.sign is informative metadata and
             * is never read for a calculation. Declared with rawColumn for the same reason as
             * stock_balances.quantity: SQLite has no ALTER TABLE ADD CONSTRAINT.
             */
            $table->rawColumn('quantity', 'decimal(12, 3) check (quantity <> 0)');

            /*
             * Snapshot of stock_balances.quantity before the movement. Only the manual adjustment
             * fills it, as evidence of the physical count; automatic movements leave it null.
             * Informative, never an input to a calculation.
             */
            $table->decimal('system_quantity', 12, 3)->nullable();

            /* An article cannot be repeated within the same movement. */
            $table->unique(['stock_movement_id', 'article_id']);

            /* HU-018 filters the history by article. */
            $table->index('article_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movement_items');
    }
};
