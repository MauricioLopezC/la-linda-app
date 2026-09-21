<?php

namespace App\Actions\Pricing;

use App\Concerns\GuardsGeneralPriceListCoverage;
use App\Enums\Pricing\PriceListChannel;
use App\Enums\Pricing\PriceListScope;
use App\Models\Pricing\PriceList;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdatePriceList
{
    use GuardsGeneralPriceListCoverage;

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
        $scope = PriceListScope::from((string) $data['scope']);

        return DB::transaction(function () use ($priceList, $data, $isActive, $scope, $wasGeneral) {
            $priceList->update([
                'name' => (string) $data['name'],
                'description' => $data['description'] ?? null,
                'scope' => $scope,
                'channel' => $scope === PriceListScope::Canal ? $data['channel'] : null,
                'valid_from' => $data['valid_from'],
                'valid_to' => $data['valid_to'] ?? null,
                'is_active' => $isActive,
            ]);

            if ($wasGeneral || $priceList->channel === PriceListChannel::General) {
                $this->assertGeneralCoverageSurvives();
            }

            return $priceList;
        });
    }
}
