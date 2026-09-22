<?php

namespace App\Concerns;

use App\Models\Pricing\PriceList;
use Illuminate\Validation\ValidationException;

/**
 * Shared invariant for the actions that can leave the general channel uncovered.
 */
trait GuardsGeneralPriceListCoverage
{
    /**
     * @throws ValidationException
     */
    private function assertGeneralCoverageSurvives(): void
    {
        if (PriceList::generalCoverageIsContinuous()) {
            return;
        }

        throw ValidationException::withMessages([
            'price_list' => 'El canal general quedaría sin lista de precios. Siempre tiene que haber una lista general activa y vigente, sin fecha de fin o con una sucesora que tome la posta al día siguiente.',
        ]);
    }
}
