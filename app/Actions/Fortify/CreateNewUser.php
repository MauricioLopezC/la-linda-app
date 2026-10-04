<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\Customers\CustomerIdType;
use App\Enums\Customers\CustomerTaxCondition;
use App\Enums\Customers\PersonType;
use App\Enums\Security\UserRole;
use App\Models\Customers\Customer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user as an online store client.
     *
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): User
    {
        if (isset($input['id_number'])) {
            $rawId = trim((string) $input['id_number']);
            $input['id_number'] = $rawId !== '' ? str_replace(['.', '-', ' '], '', $rawId) : null;
        }

        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'phone' => [
                'nullable',
                'string',
                'max:50',
                'regex:/^(?:(?:(?:\+|00)?54\s?9?)?\s?(?:0?[1-9]\d{1,3})?\s?(?:15)?[-\s.]?\d{3,4}[-\s.]?\d{4})$/',
            ],
            'address' => ['nullable', 'string', 'max:255'],
            'id_number' => [
                'nullable',
                'numeric',
                'digits_between:7,9',
                'unique:customers,id_number',
            ],
        ], [
            'id_number.numeric' => 'El DNI debe contener solo caracteres numéricos.',
            'id_number.digits_between' => 'El DNI debe tener entre 7 y 9 dígitos.',
            'id_number.unique' => 'El DNI ingresado ya se encuentra registrado.',
            'phone.regex' => 'El formato del teléfono no es válido. Ej: +54 9 387 1234567',
        ])->validate();

        $idNumber = $input['id_number'] ?? null;

        return DB::transaction(function () use ($input, $idNumber): User {
            $name = trim((string) $input['name']);
            $email = Str::of((string) $input['email'])->trim()->lower()->toString();
            $phone = isset($input['phone']) && trim((string) $input['phone']) !== ''
                ? trim((string) $input['phone'])
                : null;
            $address = isset($input['address']) && trim((string) $input['address']) !== ''
                ? trim((string) $input['address'])
                : null;

            $customer = Customer::create([
                'person_type' => PersonType::Fisica,
                'name' => $name,
                'id_type' => $idNumber !== null ? CustomerIdType::Dni : CustomerIdType::SinIdentificar,
                'id_number' => $idNumber,
                'tax_condition' => CustomerTaxCondition::ConsumidorFinal,
                'address' => $address,
                'phone' => $phone,
                'email' => $email,
                'is_active' => true,
                'is_default' => false,
            ]);

            return User::create([
                'name' => $name,
                'email' => $email,
                'password' => (string) $input['password'],
                'role' => UserRole::Cliente,
                'customer_id' => $customer->id,
            ]);
        });
    }
}
