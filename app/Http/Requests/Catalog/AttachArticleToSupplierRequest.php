<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AttachArticleToSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'article_id' => ['required', 'integer', 'exists:articles,id'],
            'supplier_article_code' => ['required', 'string', 'min:1', 'max:100'],
            'last_cost' => ['nullable', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'article_id' => 'artículo',
            'supplier_article_code' => 'código del proveedor',
            'last_cost' => 'último costo',
            'notes' => 'observaciones',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'supplier_article_code' => is_string($this->input('supplier_article_code'))
                ? trim($this->input('supplier_article_code'))
                : $this->input('supplier_article_code'),
            'notes' => is_string($this->input('notes'))
                ? trim($this->input('notes'))
                : $this->input('notes'),
        ]);
    }
}
