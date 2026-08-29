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
             * Signed delta, the stored source of truth for the effect of the line: negative leaves
             * the warehouse, positive enters it. The manual movement action derives it once on
             * write as stock_movement_types.sign * (positive quantity typed by the user); after
             * that, balances are recomputed from this column, never from the type's sign (the type
             * is user-editable). Declared with rawColumn for the same reason as
             * stock_balances.quantity: SQLite has no ALTER TABLE ADD CONSTRAINT.
             */
            $table->rawColumn('quantity', 'decimal(12, 3) check (quantity <> 0)');

            /*
             * Snapshot of stock_balances.quantity right before this line was applied. The manual
             * movement action fills it automatically; automatic movements leave it null. Purely
             * informative (audit trail / future running balance), never an input to a calculation
             * and never typed by the user.
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
