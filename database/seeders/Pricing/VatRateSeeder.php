<?php

namespace Database\Seeders\Pricing;

use App\Models\Pricing\VatRate;
use Illuminate\Database\Seeder;

class VatRateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        VatRate::unguarded(function (): void {
            foreach ([
                ['description' => 'Exenta', 'percentage' => 0],
                ['description' => 'Reducida', 'percentage' => 10.5],
                ['description' => 'General', 'percentage' => 21],
            ] as $vatRate) {
                VatRate::firstOrCreate(
                    ['description_normalized' => VatRate::normalizeUniqueValue($vatRate['description'])],
                    ['description' => $vatRate['description'], 'percentage' => $vatRate['percentage'], 'is_active' => true],
                );
            }
        });
    }
}
