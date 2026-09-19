<?php

namespace App\Http\Requests\Customers;

use App\Enums\Customers\CustomerIdType;
use App\Enums\Customers\CustomerTaxCondition;
use App\Enums\Customers\PersonType;
use App\Rules\Customers\ValidCuit;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $idType = $this->input('id_type');

        if ($idType === CustomerIdType::SinIdentificar->value) {
            $this->merge(['id_number' => null]);
        } elseif ($this->has('id_number') && $this->input('id_number') !== null) {
            $sanitized = ValidCuit::sanitize((string) $this->input('id_number'));
            $this->merge(['id_number' => $sanitized !== '' ? $sanitized : null]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $taxCondition = $this->input('tax_condition');
        $personType = $this->input('person_type');
        $idType = $this->input('id_type');

        $isResponsableInscripto = $taxCondition === CustomerTaxCondition::ResponsibleInscripto->value;
        $isJuridica = $personType === PersonType::Juridica->value;
        $isMonotributo = $taxCondition === CustomerTaxCondition::Monotributo->value;
        $isExento = $taxCondition === CustomerTaxCondition::Exento->value;
        $isConsumidorFinal = $taxCondition === CustomerTaxCondition::ConsumidorFinal->value;

        $mustBeCuit = $isResponsableInscripto || $isJuridica || $isMonotributo || $isExento;

        $idNumberRules = ['nullable'];

        if ($mustBeCuit) {
            $idNumberRules = [
                'required',
                'string',
                new ValidCuit,
                'unique:customers,id_number',
            ];
        } elseif ($isConsumidorFinal) {
            if ($idType === CustomerIdType::Cuit->value) {
                $idNumberRules = [
                    'required',
                    'string',
                    new ValidCuit,
                    'unique:customers,id_number',
                ];
            } elseif ($idType === CustomerIdType::Dni->value) {
                $idNumberRules = [
                    'required',
                    'numeric',
                    'digits_between:7,9',
                    'unique:customers,id_number',
                ];
            }
        }

        return [
            'person_type' => ['required', Rule::enum(PersonType::class)],
            'name' => ['required', 'string', 'max:255'],
            'tax_condition' => ['required', Rule::enum(CustomerTaxCondition::class)],
            'id_type' => [
                'required',
                Rule::enum(CustomerIdType::class),
                function (string $attribute, mixed $value, Closure $fail) use ($mustBeCuit, $isResponsableInscripto, $isJuridica) {
                    if ($mustBeCuit && $value !== CustomerIdType::Cuit->value) {
                        if ($isJuridica) {
                            $fail('Las personas jurídicas deben identificarse obligatoriamente con CUIT.');
                        } elseif ($isResponsableInscripto) {
                            $fail('Para Responsables Inscriptos el tipo de documento debe ser CUIT.');
                        } else {
                            $fail('Para esta condición fiscal el tipo de documento debe ser CUIT.');
                        }
                    }
                },
            ],
            'id_number' => $idNumberRules,
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'string', 'email:rfc', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'La razón social o nombre y apellido es obligatorio.',
            'person_type.required' => 'El tipo de persona es obligatorio.',
            'tax_condition.required' => 'La condición fiscal es obligatoria.',
            'id_type.required' => 'El tipo de documento es obligatorio.',
            'id_number.required' => 'El número de identificación es obligatorio para esta condición fiscal.',
            'id_number.unique' => 'Ya existe un cliente registrado con este documento.',
            'id_number.digits_between' => 'El DNI debe tener entre 7 y 9 dígitos.',
            'id_number.numeric' => 'El número de documento debe contener solo dígitos numéricos.',
            'email.email' => 'El correo electrónico debe tener un formato válido.',
        ];
    }
}
