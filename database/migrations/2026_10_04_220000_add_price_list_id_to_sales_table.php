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
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('ALTER TABLE sales ADD COLUMN price_list_id INTEGER REFERENCES price_lists(id) ON DELETE RESTRICT');
        } else {
            Schema::table('sales', function (Blueprint $table) {
                $table->foreignId('price_list_id')->nullable()->constrained('price_lists')->restrictOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('price_list_id');
        });
    }
};
