<?php

namespace App\Http\Requests\Inventory;

use App\Models\Inventory\Warehouse;
use App\Rules\Inventory\AtMostOneOnlineWarehouse;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWarehouseRequest extends FormRequest
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
        /** @var Warehouse|int|string|null $warehouse */
        $warehouse = $this->route('warehouse');
        $warehouseId = $warehouse instanceof Warehouse ? $warehouse->id : $warehouse;

        return [
            'name' => ['required', 'string', 'min:3', 'max:100'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'is_online_channel' => ['nullable', 'boolean', new AtMostOneOnlineWarehouse($warehouseId ? (int) $warehouseId : null)],
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
