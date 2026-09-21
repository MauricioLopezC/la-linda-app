<?php

namespace App\Actions\Pricing;

use App\Concerns\GuardsGeneralPriceListCoverage;
use App\Enums\Pricing\PriceListChannel;
use App\Enums\Pricing\PriceListScope;
use App\Models\Pricing\PriceList;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TogglePriceListStatus
{
    use GuardsGeneralPriceListCoverage;

    public function handle(PriceList $priceList): PriceList
    {
        if ($priceList->is_active && $priceList->isInUse()) {
            throw ValidationException::withMessages([
                'price_list' => 'No se puede dar de baja una lista de precios utilizada en ventas registradas.',
            ]);
        }

        if (! $priceList->is_active && $this->overlapsAnotherActiveChannelList($priceList)) {
            throw ValidationException::withMessages([
                'price_list' => 'No se puede reactivar la lista porque el canal ya tiene otra lista activa en ese período.',
            ]);
        }

        $isGeneral = $priceList->channel === PriceListChannel::General;

        return DB::transaction(function () use ($priceList, $isGeneral) {
            $priceList->update([
                'is_active' => ! $priceList->is_active,
            ]);

            if ($isGeneral) {
                $this->assertGeneralCoverageSurvives();
            }

            return $priceList;
        });
    }

    private function overlapsAnotherActiveChannelList(PriceList $priceList): bool
    {
        if ($priceList->scope !== PriceListScope::Canal || $priceList->channel === null) {
            return false;
        }

        return PriceList::query()
            ->forChannel($priceList->channel)
            ->active()
            ->overlapping(
                $priceList->valid_from->toDateString(),
                $priceList->valid_to?->toDateString(),
            )
            ->whereKeyNot($priceList->getKey())
            ->exists();
    }
}
