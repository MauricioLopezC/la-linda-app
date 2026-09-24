<?php

namespace App\Http\Requests\Purchasing;

use App\Enums\Purchasing\SupplierVoucherType;
use App\Models\Purchasing\Supplier;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssociablePurchaseOrdersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'supplier_id' => [
                'required',
                'integer',
                Rule::exists((new Supplier)->getTable(), 'id')->where(
                    fn (Builder $query): Builder => $query->where('is_active', true)
                ),
            ],
            'type' => [
                'required',
                Rule::in([SupplierVoucherType::Invoice->value, SupplierVoucherType::Remito->value]),
            ],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'supplier_id' => 'proveedor',
            'type' => 'tipo de comprobante',
        ];
    }

    public function voucherType(): SupplierVoucherType
    {
        return SupplierVoucherType::from((string) $this->validated('type'));
    }
}
