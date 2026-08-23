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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_movement_type_id')->constrained()->restrictOnDelete();

            /* One row = one affected warehouse. Holds for every movement type, present and future. */
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();

            /*
             * Nullable in the schema: the reason is only mandatory for inventory adjustments, and
             * that depends on the value of stock_movement_types.code, which a FK cannot express.
             * The HU-017 action enforces it against StockMovementType::CODE_INVENTORY_ADJUSTMENT.
             */
            $table->foreignId('stock_adjustment_reason_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();

            /*
             * No updated_at, on purpose. A confirmed movement is immutable: there is no update or
             * delete route for this resource, and leaving out the column means there is nowhere to
             * store an edit either. Creating the row IS the confirmation, so created_at doubles as
             * the date and time of the movement.
             */
            $table->timestamp('created_at')->useCurrent();

            /* HU-018 filters: warehouse, type, user and date range. */
            $table->index(['warehouse_id', 'created_at']);
            $table->index('stock_movement_type_id');
            $table->index('stock_adjustment_reason_id');
            $table->index('user_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
