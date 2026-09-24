<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class StoreSaleItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * An article comes either by id (search) or by a scanned code (barcode or internal code).
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'article_id' => ['required_without:code', 'nullable', 'integer'],
            'code' => ['required_without:article_id', 'nullable', 'string', 'max:50'],
            'quantity' => ['nullable', 'numeric', 'gt:0', 'max:99999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'article_id' => 'artículo',
            'code' => 'código',
            'quantity' => 'cantidad',
        ];
    }
}
