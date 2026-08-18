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
        Schema::create('stock_movement_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('name_normalized', 100)->unique();
            $table->string('code', 100)->unique();
            $table->smallInteger('sign')->comment('1 for addition (suma), -1 for deduction (resta)');
            $table->string('description')->nullable();
            $table->boolean('is_system')->default(false)->comment('Protected system types that cannot be deleted');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movement_types');
    }
};
