<?php

namespace App\Http\Requests\Reporting;

use App\Enums\Sales\SaleChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DashboardFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $today = today();

        $this->merge([
            'date_from' => $this->input('date_from') ?? $today->copy()->startOfMonth()->toDateString(),
            'date_to' => $this->input('date_to') ?? $today->toDateString(),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'channel' => ['nullable', 'string', Rule::enum(SaleChannel::class)],
        ];
    }

    /**
     * Validated filters with their defaults resolved: the period defaults to the current month,
     * from its first day up to today, and a missing channel means every channel.
     *
     * @return array{date_from: string, date_to: string, branch_id: ?int, channel: ?string}
     */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'date_from' => $validated['date_from'],
            'date_to' => $validated['date_to'],
            'branch_id' => isset($validated['branch_id']) ? (int) $validated['branch_id'] : null,
            'channel' => $validated['channel'] ?? null,
        ];
    }
}
