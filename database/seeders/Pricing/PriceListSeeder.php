<?php

namespace Database\Seeders\Pricing;

use App\Enums\Pricing\PriceListChannel;
use App\Models\Pricing\PriceList;
use Illuminate\Database\Seeder;

class PriceListSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        PriceList::updateOrCreate(
            ['channel' => PriceListChannel::General->value],
            [
                'name' => 'Lista General',
                'description' => 'Lista de precios general vigente por defecto.',
                'channel' => PriceListChannel::General,
                'valid_from' => now()->toDateString(),
                'valid_to' => null,
                'is_active' => true,
            ]
        );
    }
}
