<?php

namespace App\Http\Requests\Catalog;

use App\Models\Catalog\Category;
use App\Rules\Catalog\UniqueNormalizedValue;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
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
        $parentId = $this->integer('parent_id') ?: null;

        return [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:100',
                new UniqueNormalizedValue(Category::class, 'name_normalized', constraints: [
                    'scope_key' => $parentId ?? 0,
                ]),
            ],
            'parent_id' => ['nullable', 'integer', Rule::exists(Category::class, 'id')->whereNull('parent_id')],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['name' => 'nombre', 'parent_id' => 'categoría padre', 'is_active' => 'estado'];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'parent_id' => in_array($this->input('parent_id'), ['', 'root'], true) ? null : $this->input('parent_id'),
        ]);
    }
}
