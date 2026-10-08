<?php

namespace App\Actions\Ecommerce;

use App\Enums\Ecommerce\DeliveryMethod;
use App\Enums\Ecommerce\WebOrderStatus;
use App\Models\Customers\Customer;
use App\Models\Ecommerce\CartItem;
use App\Models\Ecommerce\WebOrder;
use App\Models\Organization\Branch;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turn the customer's cart into an online order picked up at a branch (HU-062).
 *
 * Prices are resolved again and frozen in the order lines, the order is born pending payment
 * and the cart is emptied, all in one transaction. Delivery to an address (HU-049) and the
 * online payment (HU-050) build on top of this order.
 */
class PlaceWebOrder
{
    /**
     * Two customers placing orders at the same instant may compute the same next number; the
     * UNIQUE index rejects the second one, which is then retried with a fresh number.
     */
    private const int MAX_ATTEMPTS = 3;

    public function __construct(
        private readonly ResolveCartLine $resolveCartLine,
        private readonly ClearCart $clearCart,
    ) {}

    /**
     * @throws ValidationException
     */
    public function execute(
        Customer $customer,
        DeliveryMethod $deliveryMethod,
        ?Branch $pickupBranch = null,
        ?string $shippingAddress = null,
        ?string $shippingNotes = null,
        ?string $notes = null,
    ): WebOrder {
        $attempt = 0;

        while (true) {
            $attempt++;

            try {
                return DB::transaction(fn (): WebOrder => $this->place(
                    $customer,
                    $deliveryMethod,
                    $pickupBranch,
                    $shippingAddress,
                    $shippingNotes,
                    $notes,
                ));
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt >= self::MAX_ATTEMPTS) {
                    throw $exception;
                }
            }
        }
    }

    /**
     * @throws ValidationException
     */
    private function place(
        Customer $customer,
        DeliveryMethod $deliveryMethod,
        ?Branch $pickupBranch,
        ?string $shippingAddress,
        ?string $shippingNotes,
        ?string $notes,
    ): WebOrder {
        /** @var Collection<int, CartItem> $cartItems */
        $cartItems = CartItem::query()
            ->where('customer_id', $customer->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($cartItems->isEmpty()) {
            throw ValidationException::withMessages([
                'cart' => 'Tu carrito está vacío: agregá artículos antes de confirmar el pedido.',
            ]);
        }

        $cartItems->load('article');

        $lines = [];
        $unavailable = [];
        $itemsAmount = 0.0;

        foreach ($cartItems as $item) {
            $line = $this->resolveCartLine->execute($item->article, $customer);
            $price = $line['price'];

            if ($price === null) {
                $unavailable[] = "{$item->article->description} (".mb_strtolower((string) $line['unavailable_reason']).')';

                continue;
            }

            $lineTotal = round((float) $item->quantity * (float) $price->unit_price, 2);
            $itemsAmount += $lineTotal;

            $lines[] = [
                'article_id' => $item->article_id,
                'quantity' => $item->quantity,
                'unit_price' => $price->unit_price,
                'price_list_id' => $price->price_list_id,
                'line_total' => number_format($lineTotal, 2, '.', ''),
            ];
        }

        if ($unavailable !== []) {
            throw ValidationException::withMessages([
                'cart' => 'No se puede confirmar el pedido porque hay artículos no disponibles: '
                    .implode(', ', $unavailable).'. Quitalos del carrito para continuar.',
            ]);
        }

        $amount = number_format($itemsAmount, 2, '.', '');

        if ($deliveryMethod === DeliveryMethod::Pickup) {
            if ($pickupBranch === null || ! Branch::query()->active()->whereKey($pickupBranch->id)->exists()) {
                throw ValidationException::withMessages([
                    'pickup_branch_id' => 'La sucursal de retiro elegida no está activa.',
                ]);
            }

            $shippingAddress = null;
            $shippingNotes = null;
            $shippingCost = '0.00';
            $totalAmount = $amount;
        } else {
            $cleanShippingAddress = filled($shippingAddress) ? trim((string) $shippingAddress) : '';

            if ($cleanShippingAddress === '') {
                throw ValidationException::withMessages([
                    'shipping_address' => 'El domicilio de entrega es obligatorio para envíos a domicilio.',
                ]);
            }

            $pickupBranch = null;
            $shippingAddress = $cleanShippingAddress;
            $shippingNotes = filled($shippingNotes) ? trim((string) $shippingNotes) : null;
            $shippingCost = number_format((float) config('ecommerce.shipping_cost', '2500.00'), 2, '.', '');
            $totalAmount = number_format($itemsAmount + (float) $shippingCost, 2, '.', '');
        }

        $order = WebOrder::create([
            'number' => (int) WebOrder::query()->max('number') + 1,
            'customer_id' => $customer->id,
            'status' => WebOrderStatus::Pending,
            'delivery_method' => $deliveryMethod,
            'pickup_branch_id' => $pickupBranch?->id,
            'shipping_address' => $shippingAddress,
            'shipping_notes' => $shippingNotes,
            'items_amount' => $amount,
            'shipping_cost' => $shippingCost,
            'total_amount' => $totalAmount,
            'notes' => filled($notes) ? trim($notes) : null,
            'placed_at' => now(),
        ]);

        $order->items()->createMany($lines);

        $this->clearCart->execute($customer);

        return $order->load('items');
    }
}
