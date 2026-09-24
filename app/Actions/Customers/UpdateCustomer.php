<?php

namespace App\Actions\Customers;

use App\Enums\Customers\CustomerIdType;
use App\Enums\Pricing\PriceListScope;
use App\Enums\Pricing\PriceListValidityStatus;
use App\Models\Customers\Customer;
use App\Models\Pricing\PriceList;
use App\Rules\Customers\ValidCuit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class UpdateCustomer
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Customer $customer, array $data): Customer
    {
        if ($customer->isProtected()) {
            throw ValidationException::withMessages([
                'customer' => 'El cliente por defecto Consumidor Final es inmutable y no puede ser modificado.',
            ]);
        }

        $priceListId = isset($data['price_list_id']) && $data['price_list_id'] !== ''
            ? (int) $data['price_list_id']
            : null;

        if ($priceListId !== null) {
            $this->validatePriceList($priceListId);
        }

        return DB::transaction(function () use ($customer, $data, $priceListId): Customer {
            $idType = $data['id_type'];
            $idNumber = null;

            if (isset($data['id_number']) && $data['id_number'] !== '' && $idType !== CustomerIdType::SinIdentificar->value) {
                $idNumber = ValidCuit::sanitize((string) $data['id_number']);
            }

            if ($customer->hasAssociatedRecords()) {
                if ($customer->id_number !== $idNumber) {
                    throw ValidationException::withMessages([
                        'id_number' => 'No se puede modificar el número de documento de un cliente que ya posee operaciones registradas.',
                    ]);
                }

                $taxConditionValue = is_string($data['tax_condition']) ? $data['tax_condition'] : $data['tax_condition']->value;
                if ($customer->tax_condition->value !== $taxConditionValue) {
                    throw ValidationException::withMessages([
                        'tax_condition' => 'No se puede modificar la condición fiscal de un cliente que ya posee operaciones registradas.',
                    ]);
                }
            }

            $customer->update([
                'person_type' => $data['person_type'],
                'name' => (string) $data['name'],
                'id_type' => $idType,
                'id_number' => $idNumber,
                'tax_condition' => $data['tax_condition'],
                'price_list_id' => $priceListId,
                'address' => isset($data['address']) && $data['address'] !== '' ? (string) $data['address'] : null,
                'phone' => isset($data['phone']) && $data['phone'] !== '' ? (string) $data['phone'] : null,
                'email' => isset($data['email']) && $data['email'] !== '' ? (string) $data['email'] : null,
                'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : $customer->is_active,
            ]);

            Log::info(sprintf(
                'Customer updated [ID: %d, Name: %s, TaxCondition: %s, ID Number: %s] by User ID: %s',
                $customer->id,
                $customer->name,
                $customer->tax_condition->value,
                $customer->id_number ?? 'N/A',
                auth()->id() ?? 'system'
            ));

            return $customer;
        });
    }

    /**
     * Ensure the given price list can be assigned to a customer:
     * it must be of scope `particular`, active and currently valid.
     */
    private function validatePriceList(int $priceListId): void
    {
        $priceList = PriceList::find($priceListId);

        if ($priceList === null) {
            throw ValidationException::withMessages([
                'price_list_id' => 'La lista de precios seleccionada no existe.',
            ]);
        }

        if ($priceList->scope !== PriceListScope::Particular) {
            throw ValidationException::withMessages([
                'price_list_id' => 'Solo se pueden asignar listas de tipo particular a un cliente. Las listas de canal son el precio base del canal de venta.',
            ]);
        }

        if (! $priceList->is_active) {
            throw ValidationException::withMessages([
                'price_list_id' => 'La lista de precios seleccionada no está activa.',
            ]);
        }

        if ($priceList->validityStatus() === PriceListValidityStatus::Vencida) {
            throw ValidationException::withMessages([
                'price_list_id' => 'La lista de precios seleccionada está vencida.',
            ]);
        }

        if ($priceList->validityStatus() === PriceListValidityStatus::Futura) {
            throw ValidationException::withMessages([
                'price_list_id' => 'La lista de precios seleccionada aún no está vigente.',
            ]);
        }
    }
}
