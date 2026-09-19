<?php

namespace App\Actions\Pricing;

use App\Enums\Pricing\PriceListChannel;
use App\Models\Pricing\PriceList;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdatePriceList
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(PriceList $priceList, array $data): PriceList
    {
        $isActive = isset($data['is_active']) ? (bool) $data['is_active'] : $priceList->is_active;

        if ($priceList->is_active && ! $isActive && $priceList->isInUse()) {
            throw ValidationException::withMessages([
                'price_list' => 'No se puede dar de baja una lista de precios utilizada en ventas registradas.',
            ]);
        }

        $wasGeneral = $priceList->channel === PriceListChannel::General;

        return DB::transaction(function () use ($priceList, $data, $isActive, $wasGeneral) {
            $priceList->update([
                'name' => (string) $data['name'],
                'description' => $data['description'] ?? null,
                'channel' => $data['channel'],
                'valid_from' => $data['valid_from'],
                'valid_to' => $data['valid_to'] ?? null,
                'is_active' => $isActive,
            ]);

            if ($wasGeneral && ! $this->hasActiveVigenteGeneralList()) {
                throw ValidationException::withMessages([
                    'price_list' => 'Debe existir siempre al menos una lista general activa y vigente.',
                ]);
            }

            return $priceList;
        });
    }

    private function hasActiveVigenteGeneralList(): bool
    {
        return PriceList::query()
            ->where('channel', PriceListChannel::General)
            ->active()
            ->vigente()
            ->exists();
    }
}
