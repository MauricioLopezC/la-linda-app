<?php

namespace App\Actions\Customers;

use App\Enums\Customers\CustomerIdType;
use App\Models\Customers\Customer;
use App\Rules\Customers\ValidCuit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateCustomer
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): Customer
    {
        return DB::transaction(function () use ($data): Customer {
            $idType = $data['id_type'];
            $idNumber = null;

            if (isset($data['id_number']) && $data['id_number'] !== '' && $idType !== CustomerIdType::SinIdentificar->value) {
                $idNumber = ValidCuit::sanitize((string) $data['id_number']);
            }

            $customer = Customer::create([
                'person_type' => $data['person_type'],
                'name' => (string) $data['name'],
                'id_type' => $idType,
                'id_number' => $idNumber,
                'tax_condition' => $data['tax_condition'],
                'address' => isset($data['address']) && $data['address'] !== '' ? (string) $data['address'] : null,
                'phone' => isset($data['phone']) && $data['phone'] !== '' ? (string) $data['phone'] : null,
                'email' => isset($data['email']) && $data['email'] !== '' ? (string) $data['email'] : null,
                'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : true,
                'is_default' => false,
            ]);

            Log::info(sprintf(
                'Customer created [ID: %d, Name: %s, TaxCondition: %s, ID Number: %s] by User ID: %s',
                $customer->id,
                $customer->name,
                $customer->tax_condition->value,
                $customer->id_number ?? 'N/A',
                auth()->id() ?? 'system'
            ));

            return $customer;
        });
    }
}
