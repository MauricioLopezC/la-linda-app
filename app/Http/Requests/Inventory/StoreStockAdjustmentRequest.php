<?php

namespace App\Http\Requests\Inventory;

use App\Models\Catalog\Article;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'stock_movement_type_id' => [
                'required',
                'integer',
                Rule::exists('stock_movement_types', 'id')->where('is_active', true),
            ],
            'notes' => [
                'required',
                'string',
                'min:3',
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
            'items.*.quantity' => [
                'required',
                'numeric',
                'gt:0',
                'max:999999999.999',
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
            'stock_movement_type_id' => 'tipo de movimiento',
            'notes' => 'observaciones',
            'items' => 'artículos a ajustar',
            'items.*.article_id' => 'artículo',
            'items.*.quantity' => 'cantidad',
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
            'notes.required' => 'Las observaciones son obligatorias: dejá asentado el motivo del movimiento.',
            'items.required' => 'Debe agregar al menos un artículo para ajustar.',
            'items.min' => 'Debe agregar al menos un artículo para ajustar.',
            'items.*.article_id.distinct' => 'No se puede repetir el mismo artículo en el ajuste.',
            'items.*.quantity.gt' => 'La cantidad que entra o sale debe ser mayor que cero.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $items = $this->input('items', []);
            if (! is_array($items)) {
                return;
            }

            $articleIds = array_filter(array_column($items, 'article_id'));
            if (empty($articleIds)) {
                return;
            }

            $articles = Article::query()
                ->with('unitOfMeasure')
                ->whereIn('id', $articleIds)
                ->get()
                ->keyBy('id');

            foreach ($items as $index => $item) {
                if (! is_array($item) || ! isset($item['article_id'], $item['quantity']) || ! is_numeric($item['quantity'])) {
                    continue;
                }

                $article = $articles->get((int) $item['article_id']);
                if (! $article) {
                    continue;
                }

                $qty = (float) $item['quantity'];
                if (! $article->allowsDecimalQuantity() && fmod($qty, 1.0) != 0.0) {
                    $unitName = $article->unitOfMeasure->name;
                    $validator->errors()->add(
                        "items.{$index}.quantity",
                        "El artículo '{$article->description}' se contabiliza por {$unitName} entera y no admite cantidades fraccionarias o decimales."
                    );
                }
            }
        });
    }
}
