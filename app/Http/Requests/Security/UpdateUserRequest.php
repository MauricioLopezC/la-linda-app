<?php

declare(strict_types=1);

namespace App\Http\Requests\Security;

use App\Enums\Security\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare inputs before validation rules run.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('email') && is_string($this->email)) {
            $this->merge([
                'email' => Str::lower(trim($this->email)),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var User|int|string|null $user */
        $user = $this->route('user');
        $userId = $user instanceof User ? $user->id : (int) $user;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'role' => [
                'required',
                'string',
                Rule::in(array_keys(UserRole::internalRoles())),
            ],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'email' => 'correo electrónico',
            'role' => 'rol',
            'branch_id' => 'sucursal',
        ];
    }

    /**
     * @return array{name: string, email: string, role: string, branch_id: int|null}
     */
    public function userData(): array
    {
        $validated = $this->validated();

        $branchId = $validated['branch_id'] ?? null;

        return [
            'name' => (string) $validated['name'],
            'email' => (string) $validated['email'],
            'role' => (string) $validated['role'],
            'branch_id' => $branchId !== null ? (int) $branchId : null,
        ];
    }
}
