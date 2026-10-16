<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('create unique index warehouses_single_active_online_channel on warehouses (is_online_channel) where is_online_channel and is_active');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('drop index if exists warehouses_single_active_online_channel');
    }
};
