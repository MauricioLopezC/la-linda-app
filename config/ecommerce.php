<?php

return [
    /*
     * Costo fijo de envío a domicilio para la tienda online (HU-049).
     * Configurable mediante variable de entorno ECOMMERCE_SHIPPING_COST.
     */
    'shipping_cost' => env('ECOMMERCE_SHIPPING_COST', '2500.00'),

    /*
     * Umbral máximo de unidades disponibles para considerar un producto como "Últimas unidades" (HU-048).
     * Configurable mediante variable de entorno ECOMMERCE_LOW_STOCK_THRESHOLD.
     */
    'low_stock_threshold' => (float) env('ECOMMERCE_LOW_STOCK_THRESHOLD', 5.0),
];
