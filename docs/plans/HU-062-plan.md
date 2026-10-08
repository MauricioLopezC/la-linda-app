# HU-062 — Confirmar el pedido desde el carrito (5 SP)

Permite al cliente autenticado de la tienda confirmar lo que armó en el carrito eligiendo una
sucursal de retiro. Al confirmar se re-resuelven los precios, quedan congelados en el pedido, el
pedido nace pendiente de pago y el carrito se vacía. El cliente consulta sus pedidos en "Mis
pedidos".

## Criterios de aceptación (de `product-backlog.md`)

1. **Datos:** número de pedido, cliente, modalidad de entrega (`HU-049`), observaciones, fecha y
   hora, estado; detalle con artículo, cantidad, precio unitario, lista de origen y subtotal; total.
2. **Validaciones:**
   - el carrito tiene al menos un artículo;
   - si algún artículo quedó no disponible, la confirmación se rechaza y el mensaje lo nombra;
   - el número de pedido es correlativo, único, automático y no editable;
   - la sucursal de retiro está activa.
3. **Comportamiento:**
   - los precios se resuelven de nuevo al confirmar y quedan fijos en el pedido;
   - el pedido nace pendiente de pago; la modalidad de entrega la agrega `HU-049` y el pago online
     `HU-050`;
   - al confirmar, el carrito se vacía;
   - el cliente consulta sus pedidos y su detalle en "Mis pedidos", y no ve los de otros clientes.
4. **Verificación:** se confirma un pedido, se cambia después un precio en la lista online y el
   pedido conserva el precio original; el carrito queda vacío.

## Contexto existente

- **Esquema ya migrado** (PR de esquema del Sprint 4): `web_orders`, `web_order_items`, modelos
  `WebOrder` / `WebOrderItem`, enums `WebOrderStatus` / `DeliveryMethod` y factories. Ya tienen las
  columnas de HU-049/HU-050 con defaults (retiro, envío 0). **No se agregan migraciones.**
- **Carrito (EPIC-13):** `GetCustomerCart` resuelve precios y disponibilidad; `ClearCart` vacía.
- **Precios (HU-056):** `ResolveArticlePrice` devuelve precio y `price_list_id` de origen.
- **Cuenta de cliente (EPIC-11):** `User->customer`, patrón de `CartController`.

> **Desvío DER ↔ código:** el DER del Sprint 4 describe `customer_accounts` como login de la tienda,
> pero EPIC-11 lo implementó con `users.role` + `users.customer_id`. Manda el código; el DER queda
> para corregir (fuera de alcance de esta HU).

## Parte 1 — Backend

1. **`app/Actions/Ecommerce/PlaceWebOrder.php`** —
   `execute(Customer $customer, Branch $pickupBranch, ?string $notes): WebOrder`, en una
   `DB::transaction`:
   - bloquea los `cart_items` del cliente (`lockForUpdate`) para evitar doble confirmación;
   - carrito vacío → `ValidationException` "Tu carrito está vacío.";
   - re-resuelve cada línea (canal Online) con las mismas reglas de disponibilidad que
     `GetCustomerCart` (activo, publicable online, con precio); si alguna no está disponible
     rechaza nombrando los artículos;
   - valida que la sucursal esté activa;
   - número correlativo `max(number) + 1`, respaldado por el `UNIQUE`; ante
     `UniqueConstraintViolationException` reintenta (Postgres no admite `FOR UPDATE` con
     agregados);
   - crea el `WebOrder` (pendiente, retiro, `items_amount = total_amount`, `shipping_cost = 0`,
     `placed_at = now()`) y los `WebOrderItem` con `unit_price`, `price_list_id` y `line_total`
     congelados;
   - vacía el carrito.
   - La evaluación de disponibilidad de una línea se extrae a una clase compartida
     (`ResolveCartLine`) para no duplicar reglas entre `GetCustomerCart` y `PlaceWebOrder`.
2. **`app/Http/Requests/Ecommerce/PlaceWebOrderRequest.php`** — `pickup_branch_id` requerido y
   existente activo; `notes` nullable, string, máx. 500.
3. **Data (`app/Data/Ecommerce/`)** — `WebOrderListData`, `WebOrderData` + `WebOrderItemData`,
   `PickupBranchOptionData`. `npm run types:generate`.

## Parte 2 — Rutas y controladores (grupo `tienda`, middleware `auth`)

| Ruta | Controlador | Qué hace |
|---|---|---|
| `GET /tienda/checkout` (`tienda.checkout.show`) | `CheckoutController@show` | Resumen del carrito + sucursales activas; carrito vacío → redirige al carrito |
| `POST /tienda/checkout` (`tienda.checkout.store`) | `CheckoutController@store` | Ejecuta `PlaceWebOrder` y redirige al detalle del pedido con flash de éxito |
| `GET /tienda/mis-pedidos` (`tienda.orders.index`) | `WebOrderController@index` | Pedidos del cliente, paginados, más nuevos primero |
| `GET /tienda/mis-pedidos/{webOrder}` (`tienda.orders.show`) | `WebOrderController@show` | Detalle de solo lectura; 403 si no es del cliente |

Autorización con el patrón de `CartController` (usuario con cliente y dueño del pedido). Rutas
consumidas con Wayfinder.

## Parte 3 — Frontend (`resources/js/pages/ecommerce/`)

- **Carrito:** "Confirmar pedido" navega a `/tienda/checkout` (deshabilitado si hay no
  disponibles).
- **`checkout/show.tsx`:** resumen de líneas y total, selector de sucursal de retiro, observaciones,
  botón "Confirmar pedido"; muestra los errores del servidor.
- **`orders/index.tsx`** y **`orders/show.tsx`:** "Mis pedidos" (número, fecha, estado, total) y
  detalle. Tras confirmar se aterriza en el detalle con el número y el toast de éxito.
- Enlace "Mis pedidos" en el header de la tienda y la navegación del cliente.
- Componentes shadcn existentes; estilos según `docs/context/design.md`.

## Parte 4 — Tests (Pest)

- **`tests/Feature/Ecommerce/PlaceWebOrderActionTest.php`:** confirmación crea pedido + líneas con
  lista de origen y total, estado pendiente; carrito vacío tras confirmar; carrito vacío rechazado;
  artículo sin precio / no publicable rechazado nombrándolo y sin tocar el carrito; sucursal
  inactiva rechazada; números correlativos; **precio congelado ante un cambio posterior de la lista
  online**.
- **`tests/Feature/Ecommerce/WebOrderHttpTest.php`:** checkout y confirmación por HTTP; "Mis
  pedidos" solo muestra los propios; detalle ajeno → 403; sin sesión → login; usuario interno sin
  cliente → 403.

## Cierre

Pint, `composer run types:check`, `npm run lint:check`, `npm run format:check`,
`npm run types:check` y los tests de Ecommerce; verificación manual del flujo carrito → checkout
→ pedido en el navegador.

## Fuera de alcance

Envío a domicilio y costo (HU-049), pago con Mercado Pago (HU-050), conversión a venta (EPIC-15),
descuento de stock.
