<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Internal staff roles introduced in Sprint 5 (HU-004), used by down() to fold them back.
     *
     * @var list<string>
     */
    private const array INTERNAL_ROLES = ['administrador', 'gerente', 'compras', 'deposito', 'cajero'];

    /**
     * Run the migrations.
     *
     * The generic 'personal_interno' role is replaced by the fixed operational roles of HU-004:
     * every existing staff member becomes 'administrador' so nobody loses access, and the column
     * default drops to 'cliente' (least privilege) since staff accounts always get an explicit role.
     * Values are literals on purpose: this migration must not depend on the current UserRole cases.
     */
    public function up(): void
    {
        DB::table('users')->where('role', 'personal_interno')->update(['role' => 'administrador']);

        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 50)->default('cliente')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('users')->whereIn('role', self::INTERNAL_ROLES)->update(['role' => 'personal_interno']);

        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 50)->default('personal_interno')->change();
        });
    }
};
