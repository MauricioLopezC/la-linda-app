<?php

namespace App\Actions\Pricing;

use App\Enums\Pricing\PriceListChannel;
use App\Models\Pricing\PriceList;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TogglePriceListStatus
{
    public function handle(PriceList $priceList): PriceList
    {
        if ($priceList->is_active && $priceList->isInUse()) {
            throw ValidationException::withMessages([
                'price_list' => 'No se puede dar de baja una lista de precios utilizada en ventas registradas.',
            ]);
        }

        $isGeneral = $priceList->channel === PriceListChannel::General;

        return DB::transaction(function () use ($priceList, $isGeneral) {
            $priceList->update([
                'is_active' => ! $priceList->is_active,
            ]);

            if ($isGeneral && ! $this->hasActiveVigenteGeneralList()) {
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
