<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockAdjustmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => [
                'required',
                'integer',
                Rule::exists('warehouses', 'id')->where('is_active', true),
            ],
            'stock_adjustment_reason_id' => [
                'required',
                'integer',
                Rule::exists('stock_adjustment_reasons', 'id')->where('is_active', true),
            ],
            'notes' => [
                'nullable',
                'string',
                'max:500',
            ],
            'items' => [
                'required',
                'array',
                'min:1',
            ],
            'items.*.article_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('articles', 'id'),
            ],
            'items.*.counted_quantity' => [
                'required',
                'numeric',
                'min:0',
                'max:100000',
            ],
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
            'warehouse_id' => 'depósito',
            'stock_adjustment_reason_id' => 'motivo de ajuste',
            'notes' => 'observaciones',
            'items' => 'artículos a ajustar',
            'items.*.article_id' => 'artículo',
            'items.*.counted_quantity' => 'cantidad contada',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Debe agregar al menos un artículo para ajustar.',
            'items.min' => 'Debe agregar al menos un artículo para ajustar.',
            'items.*.article_id.distinct' => 'No se puede repetir el mismo artículo en el ajuste.',
            'items.*.counted_quantity.min' => 'La cantidad contada no puede ser negativa.',
            'items.*.counted_quantity.max' => 'La cantidad contada no puede superar las 100.000 unidades.',
        ];
    }
}
