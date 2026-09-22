<?php

namespace App\Http\Requests\Pricing;

use App\Rules\Pricing\ArticleIsActiveForPricing;
use Illuminate\Foundation\Http\FormRequest;

class StorePriceListPricesRequest extends FormRequest
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
            'prices' => [
                'required',
                'array',
                'min:1',
            ],
            'prices.*.article_id' => [
                'required',
                'integer',
                'distinct',
                new ArticleIsActiveForPricing,
            ],
            'prices.*.price' => [
                'required',
                'numeric',
                'gt:0',
                'decimal:0,2',
                'max:9999999999',
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
            'prices' => 'precios',
            'prices.*.article_id' => 'artículo',
            'prices.*.price' => 'precio',
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
            'prices.required' => 'No hay ningún precio para guardar.',
            'prices.min' => 'No hay ningún precio para guardar.',
            'prices.*.article_id.distinct' => 'No se puede cargar dos veces el mismo artículo en la lista.',
            'prices.*.price.gt' => 'El precio de venta debe ser mayor a cero.',
            'prices.*.price.decimal' => 'El precio de venta admite como máximo dos decimales.',
        ];
    }
}
