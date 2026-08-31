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
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('business_name', 255);
            $table->string('business_name_normalized', 255)->index();
            $table->string('tax_id', 11)->unique();
            $table->string('tax_condition', 50)->index();
            $table->string('address', 255)->nullable();
            $table->string('rubro', 100)->nullable()->index();
            $table->string('bank_account', 255)->nullable();
            $table->text('commercial_terms')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
