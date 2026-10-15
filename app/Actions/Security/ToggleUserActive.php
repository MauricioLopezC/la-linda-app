<?php

declare(strict_types=1);

namespace App\Actions\Security;

use App\Enums\Security\UserRole;
use App\Models\Sales\CashSession;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ToggleUserActive
{
    public function execute(User $user): User
    {
        if ($user->is_active) {
            $this->validateDeactivationRules($user);
        }

        $user->is_active = ! $user->is_active;
        $user->save();

        return $user;
    }

    private function validateDeactivationRules(User $user): void
    {
        // 1. Impedir autodesactivación
        if (Auth::id() === $user->id) {
            throw ValidationException::withMessages([
                'user' => 'No podés desactivar tu propia cuenta de usuario.',
            ]);
        }

        // 2. Impedir desactivar al último administrador activo
        if ($user->role === UserRole::Administrador) {
            $activeAdminsCount = User::query()
                ->where('role', UserRole::Administrador)
                ->where('is_active', true)
                ->count();

            if ($activeAdminsCount <= 1) {
                throw ValidationException::withMessages([
                    'user' => 'No es posible desactivar al único administrador activo del sistema.',
                ]);
            }
        }

        // 3. Impedir desactivar si tiene un turno de caja abierto
        if (CashSession::query()->openForUser($user->id)->exists()) {
            throw ValidationException::withMessages([
                'user' => 'No se puede desactivar a un usuario con un turno de caja abierto actualmente.',
            ]);
        }
    }
}
