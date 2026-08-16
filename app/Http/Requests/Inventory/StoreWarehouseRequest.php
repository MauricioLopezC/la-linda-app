<?php

namespace App\Http\Requests\Inventory;

use App\Rules\Inventory\AtMostOneOnlineWarehouse;
use Illuminate\Foundation\Http\FormRequest;

class StoreWarehouseRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:3', 'max:100'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'is_online_channel' => ['nullable', 'boolean', new AtMostOneOnlineWarehouse],
            'is_active' => ['nullable', 'boolean'],
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
            'name' => 'nombre',
            'branch_id' => 'sucursal',
            'is_online_channel' => 'canal online',
            'is_active' => 'estado',
        ];
    }
}
