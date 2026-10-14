<?php

declare(strict_types=1);

namespace App\Actions\Security;

use App\Enums\Security\UserRole;
use App\Models\User;
use Illuminate\Support\Str;

class UpdateUser
{
    /**
     * @param array{
     *     name: string,
     *     email: string,
     *     role: UserRole|string,
     *     branch_id?: int|null,
     * } $data
     */
    public function execute(User $user, array $data): User
    {
        $role = $data['role'] instanceof UserRole
            ? $data['role']
            : UserRole::from((string) $data['role']);

        $user->update([
            'name' => trim((string) $data['name']),
            'email' => Str::lower(trim((string) $data['email'])),
            'role' => $role,
            'branch_id' => ! empty($data['branch_id']) ? (int) $data['branch_id'] : null,
        ]);

        return $user;
    }
}
