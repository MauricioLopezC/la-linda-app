<?php

namespace App\Http\Requests\Purchasing;

use App\Enums\Purchasing\PaymentOrderStatus;
use App\Enums\Purchasing\SupplierVoucherType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListPaymentOrdersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
            'voucher_type' => ['nullable', 'string', Rule::enum(SupplierVoucherType::class)],
            'status' => ['nullable', 'string', Rule::enum(PaymentOrderStatus::class)],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ];
    }
}
