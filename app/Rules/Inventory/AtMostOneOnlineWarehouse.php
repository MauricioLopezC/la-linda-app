<?php

namespace App\Rules\Inventory;

use App\Models\Inventory\Warehouse;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class AtMostOneOnlineWarehouse implements ValidationRule
{
    public function __construct(private readonly ?int $ignoreWarehouseId = null) {}

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value) {
            return;
        }

        $alreadyExists = Warehouse::query()
            ->where('is_online_channel', true)
            ->when($this->ignoreWarehouseId, fn ($query, $ignoreId) => $query->where('id', '!=', $ignoreId))
            ->exists();

        if ($alreadyExists) {
            $fail('Ya existe un depósito marcado como canal online. Solo puede haber uno.');
        }
    }
}
