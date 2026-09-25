<?php

namespace Database\Seeders\Pricing;

use App\Enums\Pricing\PriceListChannel;
use App\Enums\Pricing\PriceListScope;
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
            ['name_normalized' => 'lista general'],
            [
                'name' => 'Lista General',
                'description' => 'Lista de precios base del sistema. Es la última de la cascada, por eso no lleva fecha de fin.',
                'scope' => PriceListScope::Canal,
                'channel' => PriceListChannel::General,
                'valid_from' => now()->toDateString(),
                'valid_to' => null,
                'is_active' => true,
            ]
        );

        PriceList::updateOrCreate(
            ['name_normalized' => 'lista mostrador'],
            [
                'name' => 'Lista Mostrador',
                'description' => 'Precios de la venta de mostrador. Los artículos que no figuran acá toman el precio de la Lista General.',
                'scope' => PriceListScope::Canal,
                'channel' => PriceListChannel::Mostrador,
                'valid_from' => now()->toDateString(),
                'valid_to' => null,
                'is_active' => true,
            ]
        );

        PriceList::updateOrCreate(
            ['name_normalized' => 'mayorista'],
            [
                'name' => 'Mayorista',
                'description' => 'Lista particular para clientes mayoristas. Tiene precedencia sobre la lista del canal.',
                'scope' => PriceListScope::Particular,
                'channel' => null,
                'valid_from' => now()->toDateString(),
                'valid_to' => null,
                'is_active' => true,
            ]
        );
    }
}
