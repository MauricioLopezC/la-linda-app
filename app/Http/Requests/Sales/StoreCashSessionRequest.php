<?php

namespace App\Http\Requests\Sales;

use App\Enums\Sales\CashDenomination;
use Illuminate\Foundation\Http\FormRequest;

class StoreCashSessionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The opening amount is not an input: OpenCashSession derives it from the count.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $denominations = array_map(fn (CashDenomination $denomination): int => $denomination->value, CashDenomination::cases());

        return [
            'point_of_sale_id' => ['required', 'integer', 'exists:points_of_sale,id'],
            'counts' => ['required', 'array:'.implode(',', $denominations), 'required_array_keys:'.implode(',', $denominations)],
            'counts.*' => ['required', 'integer', 'min:0', 'max:100000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'point_of_sale_id' => 'punto de venta',
            'counts' => 'conteo de billetes',
            'counts.*' => 'cantidad de billetes',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'counts.array' => 'Solo se cuentan billetes de $20.000 a $10.',
        ];
    }
}
