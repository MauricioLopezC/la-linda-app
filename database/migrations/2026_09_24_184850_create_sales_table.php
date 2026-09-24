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
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('point_of_sale_id')->constrained('points_of_sale')->restrictOnDelete();
            $table->rawColumn('channel', "varchar(20) check (channel in ('mostrador', 'online'))");
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->timestamp('opened_at')->index();
            $table->rawColumn('status', "varchar(20) check (status in ('abierta', 'descartada'))")->default('abierta');
            $table->index('status');
            $table->rawColumn('total_amount', 'decimal(12, 2) check (total_amount >= 0)')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
