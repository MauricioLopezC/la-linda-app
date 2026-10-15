<?php

namespace App\Actions\Ecommerce;

use App\Enums\Ecommerce\WebOrderStatus;
use App\Models\Ecommerce\WebOrder;
use App\Models\Inventory\StockMovementType;
use App\Models\Inventory\Warehouse;

class ResolveWebOrderWarehouse
{
    public function execute(WebOrder $order): ?Warehouse
    {
        $movement = $order->sale?->stockMovement;
        if ($movement?->type->code === StockMovementType::CODE_WEB_ORDER_DELIVERY) {
            return $movement->warehouse;
        }

        if ($order->status === WebOrderStatus::Delivered) {
            return null;
        }

        $saleWarehouse = $order->sale?->pointOfSale?->warehouse;
        if ($saleWarehouse !== null && $saleWarehouse->is_active && $saleWarehouse->is_online_channel) {
            return $saleWarehouse;
        }

        return Warehouse::query()->online()->active()->first();
    }
}
