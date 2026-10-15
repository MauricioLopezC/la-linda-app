<?php

declare(strict_types=1);

namespace App\Actions\Security;

use App\Enums\Security\UserRole;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UpdateUser
{
    /**
     * @param array{
     *     name: string,
     *     email: string,
     *     role: UserRole|string,
     *     branch_id?: int|null,
     * } $data
     *
     * @throws ValidationException
     */
    public function execute(User $user, array $data): User
    {
        $role = $data['role'] instanceof UserRole
            ? $data['role']
            : UserRole::from((string) $data['role']);

        if ($user->role === UserRole::Administrador && $role !== UserRole::Administrador) {
            $activeAdminsCount = User::query()
                ->where('role', UserRole::Administrador)
                ->where('is_active', true)
                ->count();

            if ($user->is_active && $activeAdminsCount <= 1) {
                throw ValidationException::withMessages([
                    'role' => 'No es posible cambiar el rol del único administrador activo del sistema.',
                ]);
            }
        }

        $user->update([
            'name' => trim((string) $data['name']),
            'email' => Str::lower(trim((string) $data['email'])),
            'role' => $role,
            'branch_id' => ! empty($data['branch_id']) ? (int) $data['branch_id'] : null,
        ]);

        return $user;
    }
}
