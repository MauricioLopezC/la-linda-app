<?php

namespace App\Http\Requests\Catalog;

use App\Enums\Catalog\ArticleStatus;
use App\Models\Catalog\Article;
use App\Rules\Catalog\UniqueNormalizedValue;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreArticleRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'min:2', 'max:150'],
            'internal_code' => ['required', 'string', 'max:50', new UniqueNormalizedValue(Article::class, 'internal_code_normalized')],
            'barcode' => ['nullable', 'string', 'max:50', new UniqueNormalizedValue(Article::class, 'barcode_normalized')],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'unit_of_measure_id' => ['required', 'integer', 'exists:units_of_measure,id'],
            'vat_rate_id' => ['required', 'integer', 'exists:vat_rates,id'],
            'status' => ['nullable', 'string', Rule::enum(ArticleStatus::class)],
            'is_online_publishable' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'description' => 'descripción',
            'internal_code' => 'código interno',
            'barcode' => 'código de barras',
            'category_id' => 'categoría',
            'brand_id' => 'marca',
            'unit_of_measure_id' => 'unidad de medida',
            'vat_rate_id' => 'alícuota de IVA',
            'status' => 'estado',
            'is_online_publishable' => 'publicable en canal online',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'description' => is_string($this->input('description')) ? trim($this->input('description')) : $this->input('description'),
            'internal_code' => is_string($this->input('internal_code')) ? trim($this->input('internal_code')) : $this->input('internal_code'),
            'barcode' => $this->blankToNull($this->input('barcode')),
            'brand_id' => $this->blankToNull($this->input('brand_id')),
        ]);
    }

    private function blankToNull(mixed $value): mixed
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        return $value === '' ? null : $value;
    }
}
