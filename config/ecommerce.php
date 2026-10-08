<?php

return [
    /*
     * Costo fijo de envío a domicilio para la tienda online (HU-049).
     * Configurable mediante variable de entorno ECOMMERCE_SHIPPING_COST.
     */
    'shipping_cost' => env('ECOMMERCE_SHIPPING_COST', '2500.00'),
];
