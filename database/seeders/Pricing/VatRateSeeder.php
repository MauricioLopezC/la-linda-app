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
        $vatRates = [
            ['description' => 'General (21%)', 'percentage' => 21.0],
            ['description' => 'Reducida (10.5%)', 'percentage' => 10.5],
            ['description' => 'Exenta (0%)', 'percentage' => 0.0],
        ];

        VatRate::unguarded(function () use ($vatRates): void {
            foreach ($vatRates as $vatRate) {
                VatRate::updateOrCreate(
                    ['percentage' => $vatRate['percentage']],
                    [
                        'description' => $vatRate['description'],
                        'description_normalized' => VatRate::normalizeUniqueValue($vatRate['description']),
                        'percentage' => $vatRate['percentage'],
                        'is_active' => true,
                    ]
                );
            }
        });
    }
}
