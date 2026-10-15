<?php

declare(strict_types=1);

namespace App\Http\Controllers\Security;

use App\Actions\Security\CreateUser;
use App\Actions\Security\ResetUserPassword;
use App\Actions\Security\ToggleUserActive;
use App\Actions\Security\UpdateUser;
use App\Data\Organization\BranchData;
use App\Data\Security\UserData;
use App\Enums\Security\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Security\StoreUserRequest;
use App\Http\Requests\Security\UpdateUserRequest;
use App\Models\Organization\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $users = User::query()
            ->with('branch')
            ->whereNull('customer_id')
            ->where('role', '!=', UserRole::Cliente)
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = trim((string) $request->input('search'));
                $lowerSearch = mb_strtolower($search);

                $query->where(function (Builder $q) use ($lowerSearch): void {
                    $q->whereRaw('LOWER(name) LIKE ?', ["%{$lowerSearch}%"])
                        ->orWhereRaw('LOWER(email) LIKE ?', ["%{$lowerSearch}%"]);
                });
            })
            ->when($request->filled('role') && $request->input('role') !== 'all', function (Builder $query) use ($request): void {
                $query->where('role', $request->input('role'));
            })
            ->when($request->filled('status') && $request->input('status') !== 'all', function (Builder $query) use ($request): void {
                match ($request->input('status')) {
                    'active' => $query->where('is_active', true),
                    'inactive' => $query->where('is_active', false),
                    default => $query,
                };
            })
            ->orderBy('id', 'desc')
            ->paginate(15)
            ->withQueryString();

        $branches = Branch::query()->active()->orderBy('name')->get();

        $roleOptions = array_map(
            fn (string $value, string $label): array => ['value' => $value, 'label' => $label],
            array_keys(UserRole::internalRoles()),
            array_values(UserRole::internalRoles())
        );

        return Inertia::render('settings/users/index', [
            'users' => UserData::collect($users),
            'branches' => BranchData::collect($branches),
            'roles' => $roleOptions,
            'filters' => [
                'search' => (string) $request->input('search', ''),
                'role' => (string) $request->input('role', 'all'),
                'status' => (string) $request->input('status', 'all'),
            ],
        ]);
    }

    public function store(StoreUserRequest $request, CreateUser $createUser): RedirectResponse
    {
        $result = $createUser->execute($request->userData());

        Inertia::flash('generated_password', $result['plain_password']);

        return redirect()->back()->with('success', 'Usuario creado exitosamente.');
    }

    public function update(UpdateUserRequest $request, User $user, UpdateUser $updateUser): RedirectResponse
    {
        $updateUser->execute($user, $request->userData());

        return redirect()->back()->with('success', 'Usuario actualizado exitosamente.');
    }

    public function toggleActive(User $user, ToggleUserActive $toggleUserActive): RedirectResponse
    {
        $toggleUserActive->execute($user);

        $statusMessage = $user->is_active ? 'Usuario activado exitosamente.' : 'Usuario desactivado exitosamente.';

        return redirect()->back()->with('success', $statusMessage);
    }

    public function resetPassword(Request $request, User $user, ResetUserPassword $resetUserPassword): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['nullable', 'string', 'min:8'],
        ], [
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
        ]);

        $newPassword = $resetUserPassword->execute($user, $validated['password'] ?? null);

        Inertia::flash('generated_password', $newPassword);

        return redirect()->back()->with('success', 'Contraseña restablecida exitosamente.');
    }
}
