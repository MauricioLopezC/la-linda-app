<?php

declare(strict_types=1);

namespace App\Actions\Security;

use App\Enums\Security\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateUser
{
    /**
     * @param array{
     *     name: string,
     *     email: string,
     *     role: UserRole|string,
     *     branch_id?: int|null,
     *     password?: string|null,
     * } $data
     * @return array{user: User, plain_password: string}
     */
    public function execute(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            $plainPassword = ! empty($data['password'])
                ? (string) $data['password']
                : Str::password(length: 12, symbols: true);

            $role = $data['role'] instanceof UserRole
                ? $data['role']
                : UserRole::from((string) $data['role']);

            $user = User::create([
                'name' => trim((string) $data['name']),
                'email' => Str::lower(trim((string) $data['email'])),
                'password' => Hash::make($plainPassword),
                'role' => $role,
                'branch_id' => ! empty($data['branch_id']) ? (int) $data['branch_id'] : null,
                'is_active' => true,
            ]);

            return [
                'user' => $user,
                'plain_password' => $plainPassword,
            ];
        });
    }
}
