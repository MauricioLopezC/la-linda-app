<?php

declare(strict_types=1);

namespace App\Http\Requests\Security;

use App\Enums\Security\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'role' => [
                'required',
                'string',
                Rule::in(array_keys(UserRole::internalRoles())),
            ],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'password' => ['nullable', 'string', 'min:8'],
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
            'password' => 'contraseña',
        ];
    }

    /**
     * @return array{name: string, email: string, role: string, branch_id: int|null, password: string|null}
     */
    public function userData(): array
    {
        $validated = $this->validated();

        $branchId = $validated['branch_id'] ?? null;
        $password = $validated['password'] ?? null;

        return [
            'name' => (string) $validated['name'],
            'email' => (string) $validated['email'],
            'role' => (string) $validated['role'],
            'branch_id' => $branchId !== null ? (int) $branchId : null,
            'password' => $password !== null ? (string) $password : null,
        ];
    }
}
