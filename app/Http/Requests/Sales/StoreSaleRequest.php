<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class StoreSaleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The channel is not an input: counter sales are always `mostrador` (see OpenSale).
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'point_of_sale_id' => ['required', 'integer', 'exists:points_of_sale,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'point_of_sale_id' => 'punto de venta',
            'customer_id' => 'cliente',
        ];
    }
}
