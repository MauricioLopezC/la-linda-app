<?php

declare(strict_types=1);

namespace App\Actions\Security;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ResetUserPassword
{
    public function execute(User $user, ?string $password = null): string
    {
        $plainPassword = ! empty($password)
            ? $password
            : Str::password(length: 12, symbols: true);

        $user->forceFill([
            'password' => Hash::make($plainPassword),
        ])->save();

        return $plainPassword;
    }
}
