<?php

namespace App\Actions\Ecommerce;

use App\Enums\Customers\CustomerIdType;
use App\Enums\Customers\CustomerTaxCondition;
use App\Enums\Customers\PersonType;
use App\Models\Customers\Customer;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateCustomerContactInfo
{
    /**
     * Update customer contact info and user display name.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(User $user, array $data): Customer
    {
        return DB::transaction(function () use ($user, $data): Customer {
            $name = trim((string) $data['name']);
            $phone = isset($data['phone']) && trim((string) $data['phone']) !== ''
                ? trim((string) $data['phone'])
                : null;
            $address = isset($data['address']) && trim((string) $data['address']) !== ''
                ? trim((string) $data['address'])
                : null;

            $user->update(['name' => $name]);

            /** @var Customer $customer */
            $customer = $user->customer ?? Customer::create([
                'person_type' => PersonType::Fisica,
                'name' => $name,
                'id_type' => CustomerIdType::SinIdentificar,
                'id_number' => null,
                'tax_condition' => CustomerTaxCondition::ConsumidorFinal,
                'email' => $user->email,
                'is_active' => true,
                'is_default' => false,
            ]);

            if ($user->customer_id === null) {
                $user->update(['customer_id' => $customer->id]);
            }

            $customer->update([
                'name' => $name,
                'phone' => $phone,
                'address' => $address,
            ]);

            return $customer;
        });
    }
}
