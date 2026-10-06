<?php

namespace App\Http\Controllers\Ecommerce;

use App\Actions\Ecommerce\AddArticleToCart;
use App\Actions\Ecommerce\ClearCart;
use App\Actions\Ecommerce\GetCustomerCart;
use App\Actions\Ecommerce\RemoveCartItem;
use App\Actions\Ecommerce\UpdateCartItemQuantity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ecommerce\AddToCartRequest;
use App\Http\Requests\Ecommerce\UpdateCartItemQuantityRequest;
use App\Models\Catalog\Article;
use App\Models\Ecommerce\CartItem;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    /**
     * Display the customer's cart.
     */
    public function index(Request $request, GetCustomerCart $action): Response
    {
        /** @var User $user */
        $user = $request->user();
        $customer = $user->customer;

        if ($customer === null) {
            abort(403, 'Solo los clientes registrados pueden acceder al carrito de compras.');
        }

        $cart = $action->execute($customer);

        return Inertia::render('ecommerce/cart/index', [
            'cart' => $cart,
        ]);
    }

    /**
     * Add an article to the customer's cart.
     */
    public function store(AddToCartRequest $request, AddArticleToCart $action): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $customer = $user->customer;

        if ($customer === null) {
            abort(403, 'Solo los clientes registrados pueden agregar artículos al carrito.');
        }

        /** @var Article $article */
        $article = Article::findOrFail($request->validated('article_id'));

        $action->execute($customer, $article, $request->validated('quantity'));

        return back()->with('success', "Agregaste '{$article->description}' a tu carrito.");
    }

    /**
     * Update the quantity of an item in the cart.
     */
    public function update(
        CartItem $cartItem,
        UpdateCartItemQuantityRequest $request,
        UpdateCartItemQuantity $action,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $customer = $user->customer;

        if ($customer === null || $cartItem->customer_id !== $customer->id) {
            abort(403, 'No estás autorizado para modificar este artículo del carrito.');
        }

        $action->execute($customer, $cartItem, $request->validated('quantity'));

        return back()->with('success', 'Cantidad actualizada correctamente.');
    }

    /**
     * Remove an item from the customer's cart.
     */
    public function destroy(
        CartItem $cartItem,
        Request $request,
        RemoveCartItem $action,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $customer = $user->customer;

        if ($customer === null || $cartItem->customer_id !== $customer->id) {
            abort(403, 'No estás autorizado para eliminar este artículo del carrito.');
        }

        $action->execute($customer, $cartItem);

        return back()->with('success', 'Artículo eliminado del carrito.');
    }

    /**
     * Clear all items from the customer's cart.
     */
    public function clear(Request $request, ClearCart $action): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $customer = $user->customer;

        if ($customer === null) {
            abort(403, 'Solo los clientes pueden vaciar el carrito.');
        }

        $action->execute($customer);

        return back()->with('success', 'El carrito ha sido vaciado.');
    }
}
