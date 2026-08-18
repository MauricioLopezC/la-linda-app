<?php

namespace App\Http\Requests\Catalog;

use App\Models\Catalog\UnitOfMeasure;
use App\Rules\Catalog\UniqueNormalizedValue;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUnitOfMeasureRequest extends FormRequest
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
        $unitOfMeasure = $this->route('unit_of_measure');
        $unitOfMeasureId = $unitOfMeasure instanceof UnitOfMeasure ? $unitOfMeasure->id : (int) $unitOfMeasure;

        return [
            'name' => ['required', 'string', 'min:2', 'max:100', new UniqueNormalizedValue(UnitOfMeasure::class, 'name_normalized', $unitOfMeasureId)],
            'abbreviation' => ['required', 'string', 'min:1', 'max:20', new UniqueNormalizedValue(UnitOfMeasure::class, 'abbreviation_normalized', $unitOfMeasureId)],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['name' => 'nombre', 'abbreviation' => 'abreviatura', 'is_active' => 'estado'];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'abbreviation' => is_string($this->input('abbreviation')) ? trim($this->input('abbreviation')) : $this->input('abbreviation'),
        ]);
    }
}
