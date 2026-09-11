<?php

namespace App\Http\Requests\Purchasing;

use App\Models\Purchasing\Supplier;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssociableInvoicesRequest extends FormRequest
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
        ];
    }
}
