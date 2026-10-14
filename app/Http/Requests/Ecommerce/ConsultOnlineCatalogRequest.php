<?php

namespace App\Http\Requests\Ecommerce;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConsultOnlineCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where('is_active', true),
            ],
            'brand_id' => [
                'nullable',
                'integer',
                Rule::exists('brands', 'id')->where('is_active', true),
            ],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => [
                'nullable',
                'numeric',
                'min:0',
                Rule::when($this->filled('min_price'), ['gte:min_price']),
            ],
            'sort' => [
                'nullable',
                'string',
                Rule::in(['relevance', 'price_asc', 'price_desc', 'name_asc', 'name_desc']),
            ],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category_id.exists' => 'La categoría seleccionada no existe o no está activa.',
            'brand_id.exists' => 'La marca seleccionada no existe o no está activa.',
            'min_price.numeric' => 'El precio mínimo debe ser un número.',
            'min_price.min' => 'El precio mínimo no puede ser menor a cero.',
            'max_price.numeric' => 'El precio máximo debe ser un número.',
            'max_price.min' => 'El precio máximo no puede ser menor a cero.',
            'max_price.gte' => 'El precio máximo debe ser mayor o igual al precio mínimo.',
            'sort.in' => 'El criterio de ordenamiento seleccionado no es válido.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $merges = [];

        if ($this->query('category_id') === 'all' || $this->query('category_id') === '') {
            $merges['category_id'] = null;
        }

        if ($this->query('brand_id') === 'all' || $this->query('brand_id') === '') {
            $merges['brand_id'] = null;
        }

        if ($this->query('min_price') === '') {
            $merges['min_price'] = null;
        }

        if ($this->query('max_price') === '') {
            $merges['max_price'] = null;
        }

        if ($this->query('sort') === '' || $this->query('sort') === null) {
            $merges['sort'] = 'relevance';
        }

        if (! empty($merges)) {
            $this->merge($merges);
        }
    }
}
