<?php

namespace App\Http\Requests\Sales;

use App\Models\Inventory\Warehouse;
use App\Models\Sales\PointOfSale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdatePointOfSaleRequest extends FormRequest
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
            'number' => ['required', 'integer', 'min:1'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
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
            'number' => 'número',
            'warehouse_id' => 'depósito',
            'is_active' => 'estado',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $number = $this->input('number');
            $warehouseId = $this->input('warehouse_id');

            if (! $number || ! $warehouseId) {
                return;
            }

            $branchId = Warehouse::find((int) $warehouseId)?->branch_id;

            if (! $branchId) {
                return;
            }

            /** @var PointOfSale|int|string|null $pointOfSale */
            $pointOfSale = $this->route('point_of_sale');
            $pointOfSaleId = $pointOfSale instanceof PointOfSale ? $pointOfSale->id : $pointOfSale;

            $duplicateExists = PointOfSale::query()
                ->join('warehouses', 'warehouses.id', '=', 'points_of_sale.warehouse_id')
                ->where('warehouses.branch_id', $branchId)
                ->where('points_of_sale.number', $number)
                ->when($pointOfSaleId, fn ($query, $id) => $query->where('points_of_sale.id', '!=', $id))
                ->exists();

            if ($duplicateExists) {
                $validator->errors()->add('number', 'Ya existe un punto de venta con ese número en la sucursal seleccionada.');
            }
        });
    }
}
