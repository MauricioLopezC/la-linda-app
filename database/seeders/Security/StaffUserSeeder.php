<?php

namespace Database\Seeders\Security;

use App\Enums\Security\UserRole;
use App\Models\Organization\Branch;
use App\Models\User;
use Illuminate\Database\Seeder;

class StaffUserSeeder extends Seeder
{
    /**
     * One demo user per operational role (HU-004), so the role landing screens and module
     * restrictions can be shown without creating staff by hand. The Administrador is seeded by
     * DatabaseSeeder from the demo admin config.
     */
    public function run(): void
    {
        $branchId = Branch::query()->where('name', 'Sucursal Centro')->value('id');

        foreach ([UserRole::Gerente, UserRole::Compras, UserRole::Deposito, UserRole::Cajero] as $role) {
            User::firstOrCreate(
                ['email' => $role->value.'@'.config('demo.staff_email_domain')],
                [
                    'name' => $role->label(),
                    'password' => config('demo.staff_password'),
                    'email_verified_at' => now(),
                    'role' => $role,
                    'branch_id' => $branchId,
                    'is_active' => true,
                ]
            );
        }
    }
}
