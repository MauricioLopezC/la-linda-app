<?php

namespace App\Http\Requests\Sales;

use App\Enums\Sales\CashDenomination;
use App\Models\Sales\CashSession;
use Illuminate\Foundation\Http\FormRequest;

class CloseCashSessionRequest extends FormRequest
{
    /**
     * Only the cashier who opened the session closes it (until roles arrive with HU-004).
     */
    public function authorize(): bool
    {
        $cashSession = $this->route('cashSession');

        return $cashSession instanceof CashSession && $cashSession->user_id === $this->user()?->id;
    }

    /**
     * Expected amounts and the cash declared amount are not inputs: CloseCashSession derives
     * them from the movements and from the bill count.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $denominations = array_map(fn (CashDenomination $denomination): int => $denomination->value, CashDenomination::cases());

        return [
            'counts' => ['required', 'array:'.implode(',', $denominations), 'required_array_keys:'.implode(',', $denominations)],
            'counts.*' => ['required', 'integer', 'min:0', 'max:100000'],
            'declarations' => ['nullable', 'array'],
            'declarations.*' => ['array:declared_amount,batch_reference'],
            'declarations.*.declared_amount' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'declarations.*.batch_reference' => ['nullable', 'string', 'max:50'],
            'closing_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'counts' => 'conteo de billetes',
            'counts.*' => 'cantidad de billetes',
            'declarations.*.declared_amount' => 'importe declarado',
            'declarations.*.batch_reference' => 'número de lote',
            'closing_notes' => 'observación',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'counts.array' => 'Solo se cuentan billetes de $20.000 a $10.',
        ];
    }
}
