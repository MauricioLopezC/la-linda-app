# HU-050 — Pagar el pedido con Mercado Pago en sandbox

Permite al cliente de la tienda online pagar su pedido en línea mediante la pasarela de pagos Mercado Pago en modo sandbox (`ECO-06`). Al confirmar el pedido o al reintentar el pago desde "Mis pedidos", el sistema genera una preferencia de pago en la API de Mercado Pago y redirige al checkout sandbox. La acreditación del pago se gestiona exclusivamente a través de un webhook público e idempotente que consulta la API de Mercado Pago para verificar importe y referencia del pedido, sin confiar jamás en los parámetros de la redirección del navegador.

Esta historia amplía el flujo de pedidos online completado en `HU-062` y `HU-049`.

## Criterios de aceptación (de `product-backlog.md`)

1. **Datos:** estado de pago del pedido, identificador de la preferencia y del pago en Mercado Pago, importe pagado, fecha y hora de acreditación.
2. **Validaciones:**
   - El importe que se cobra es el total del pedido con el envío; el cliente no lo modifica.
   - Solo el cliente dueño del pedido puede pagarlo, y un pedido pagado no se vuelve a pagar.
   - El pago se da por acreditado solo después de consultarlo en la API de Mercado Pago; nunca por los parámetros de la redirección.
   - Una misma notificación recibida dos veces no produce efectos duplicados.
3. **Comportamiento:**
   - Al confirmar el pedido el cliente va al checkout de Mercado Pago en modo sandbox.
   - El pedido nace pendiente de pago; con el pago aprobado pasa a pagado con su fecha de acreditación.
   - Si el pago se rechaza o se abandona, el pedido sigue pendiente y se puede reintentar desde "Mis pedidos".
   - "Mis pedidos" muestra el estado de pago de cada pedido.
   - Las credenciales de Mercado Pago se configuran por entorno y nunca se exponen en el frontend.
   - El pedido pagado todavía no se convierte en venta (`EPIC-15`).
4. **Verificación:** con la tarjeta de prueba aprobada el pedido queda pagado; con la rechazada queda pendiente y se reintenta con éxito.

## Investigación del código existente

| Artefacto | Ubicación | Propósito |
|---|---|---|
| Migración `web_orders` | `database/migrations/2026_09_28_100015_create_web_orders_table.php` | Esquema ya aplicado en `master` (Sprint 4). Contiene columnas `status` (`CHECK in ('pendiente', 'pagado')`), `mp_preference_id` (nullable), `mp_payment_id` (nullable, único), `paid_at` (nullable, con check de coherencia: presente si y solo si `status = 'pagado'`). **Falta persistir el importe pagado exigido por el criterio "Datos".** |
| Modelo `WebOrder` | `app/Models/Ecommerce/WebOrder.php` | Modelo Eloquent de pedidos. Ya tiene fillable para `mp_preference_id`, `mp_payment_id`, `paid_at`, `status`, cast `status => WebOrderStatus::class`, `paid_at => datetime`. Debe sumar `paid_amount` cuando exista la migración nueva. |
| Enum `WebOrderStatus` | `app/Enums/Ecommerce/WebOrderStatus.php` | Enum string con casos `Pending = 'pendiente'` y `Paid = 'pagado'` y etiquetas legibles. |
| Factory `WebOrderFactory` | `database/factories/Ecommerce/WebOrderFactory.php` | Ya incluye estado `paid()` que setea `status = 'pagado'`, `mp_payment_id` único y `paid_at = now()`. |
| Action `PlaceWebOrder` | `app/Actions/Ecommerce/PlaceWebOrder.php` | Crea el pedido pendiente y congela importes y modalidad de entrega (HU-062 y HU-049). Devuelve la instancia de `WebOrder`. |
| Controlador `CheckoutController` | `app/Http/Controllers/Ecommerce/CheckoutController.php` | Gestiona el checkout. En `store()`, tras confirmar el pedido con `PlaceWebOrder`, debe delegar la creación de la preferencia en Mercado Pago y redirigir al checkout sandbox. |
| Data object `WebOrderData` | `app/Data/Ecommerce/WebOrderData.php` | DTO de salida de pedidos online para el cliente. Requiere exponer `paid_amount`, `formatted_paid_amount`, `paid_at_formatted`, `mp_preference_id` y `mp_payment_id`. |
| Data object `WebOrderListData` | `app/Data/Ecommerce/WebOrderListData.php` | DTO de salida para el listado "Mis pedidos". Ya incluye `status` y `status_label`. |
| Vista de Checkout | `resources/js/pages/ecommerce/checkout/show.tsx` | Confirmación del pedido. El botón de confirmación envía el formulario por POST; con la respuesta de redirección externa de Inertia es derivado al sandbox de Mercado Pago. |
| Vista Detalle de Pedido | `resources/js/pages/ecommerce/orders/show.tsx` | Pantalla de detalle de pedido en "Mis pedidos". Requiere mostrar información del pago si está pagado, y botón de acción "Pagar pedido" / "Reintentar pago" si está pendiente. |
| Configuración de Servicios | `config/services.php` | Archivo centralizado para credenciales de terceros. Requiere sección `mercadopago`. |
| Patrón de cliente online | `CheckoutController`, `WebOrderController`, `User::customer` | La tienda usa el guard `auth` existente: el usuario autenticado tiene `role = Cliente` y `customer_id`. Los nuevos controladores deben reutilizar el helper local `customerOf($request)` / `$request->user()->customer`, no introducir un guard distinto. |
| Configuración de Middleware | `bootstrap/app.php` | Configuración de middleware de Laravel 13. Requiere excluir de la validación CSRF la ruta del webhook de Mercado Pago (`webhooks/mercadopago`). |

*Nota sobre el esquema de base de datos:* El DER de `sprint-backlog-4.md` incluyó `mp_preference_id`, `mp_payment_id`, `paid_at` y `status`, pero omitió una columna para el "importe pagado" que sí está en los criterios de aceptación de `product-backlog.md`. La implementación debe agregar `paid_amount` para no perder el importe confirmado por Mercado Pago; este desvío queda documentado porque el backlog funcional tiene prioridad para el criterio de Datos.

---

## Proposed Changes

### Backend — Configuración y Entorno

#### [MODIFY] `config/services.php`
- Agregar la configuración para Mercado Pago:
  ```php
  'mercadopago' => [
      'access_token' => env('MERCADO_PAGO_ACCESS_TOKEN'),
      'public_key' => env('MERCADO_PAGO_PUBLIC_KEY'),
      'sandbox' => env('MERCADO_PAGO_SANDBOX', true),
      'base_url' => env('MERCADO_PAGO_BASE_URL', 'https://api.mercadopago.com'),
  ],
  ```

#### [MODIFY] `.env.example`
- Declarar variables de entorno de Mercado Pago (credenciales sandbox por defecto):
  ```env
  MERCADO_PAGO_ACCESS_TOKEN=
  MERCADO_PAGO_PUBLIC_KEY=
  MERCADO_PAGO_SANDBOX=true
  MERCADO_PAGO_BASE_URL=https://api.mercadopago.com
  ```

### Backend — Migraciones y Modelo

#### [NEW] `database/migrations/<timestamp>_add_paid_amount_to_web_orders_table.php`
- Crear con `php artisan make:migration add_paid_amount_to_web_orders_table --table=web_orders --no-interaction`.
- Agregar `paid_amount` como importe efectivamente acreditado por Mercado Pago:
  ```php
  $table->rawColumn('paid_amount', 'decimal(12, 2) check (paid_amount is null or paid_amount > 0)')->nullable()->after('mp_payment_id');
  ```
- Mantenerlo nullable para pedidos pendientes. No intentar recrear el `CHECK` complejo de `paid_at` en SQLite/Postgres dentro de esta HU; la coherencia de presencia se valida en `MarkWebOrderAsPaid` y tests.

#### [MODIFY] `app/Models/Ecommerce/WebOrder.php`
- Agregar `paid_amount` al atributo `Fillable`.
- Agregar cast `paid_amount => 'decimal:2'`.
- Documentar la propiedad en el PHPDoc del modelo.

#### [MODIFY] `database/factories/Ecommerce/WebOrderFactory.php`
- En el estado `paid()`, setear `paid_amount` con el `total_amount` del pedido:
  ```php
  public function paid(): static
  {
      return $this->state(fn (array $attributes): array => [
          'status' => WebOrderStatus::Paid,
          'mp_payment_id' => (string) fake()->unique()->numerify('##########'),
          'paid_amount' => $attributes['total_amount'] ?? $attributes['items_amount'],
          'paid_at' => now(),
      ]);
  }
  ```

---

### Backend — Actions

#### [NEW] `app/Actions/Ecommerce/CreateMercadoPagoPreference.php`
- **Propósito:** Generar una preferencia de pago en la API REST de Mercado Pago (`POST https://api.mercadopago.com/checkout/preferences`) utilizando el cliente HTTP nativo de Laravel (`Http::withToken(...)`).
- **Entrada:** `WebOrder $order`
- **Lógica:**
  1. Validar que el pedido esté en estado pendiente (`$order->status === WebOrderStatus::Pending`).
  2. Validar que `config('services.mercadopago.access_token')` esté configurado; si falta, lanzar `RuntimeException` con mensaje operable para el equipo.
  3. Cargar relaciones necesarias dentro de la Action para que los controladores no dependan de haberlas precargado: `$order->loadMissing('customer', 'items.article')`.
  4. Construir la lista de ítems a partir de los artículos del pedido:
     ```php
     $items = $order->items->map(fn (WebOrderItem $item) => [
         'id' => (string) $item->article_id,
         'title' => $item->article->description,
         'quantity' => (float) $item->quantity,
         'unit_price' => (float) $item->unit_price,
         'currency_id' => 'ARS',
     ])->values()->all();
     ```
  5. Si el pedido posee costo de envío (`bccomp($order->shipping_cost, '0.00', 2) === 1`), incorporar el ítem de envío para garantizar exactitud centavo a centavo:
     ```php
     $items[] = [
         'id' => 'shipping',
         'title' => 'Costo de envío a domicilio',
         'quantity' => 1,
         'unit_price' => (float) $order->shipping_cost,
         'currency_id' => 'ARS',
     ];
     ```
  6. Configurar URLs de retorno (`back_urls`):
     - `success`: `route('tienda.orders.payment-return', ['web_order' => $order->id, 'status' => 'approved'])`
     - `pending`: `route('tienda.orders.payment-return', ['web_order' => $order->id, 'status' => 'pending'])`
     - `failure`: `route('tienda.orders.payment-return', ['web_order' => $order->id, 'status' => 'rejected'])`
  7. Configurar `external_reference = (string) $order->id`.
  8. Configurar `notification_url = route('webhooks.mercadopago')`.
  9. Configurar `auto_return = 'approved'`.
  10. Configurar datos del pagador (`payer`): nombre y email del usuario cliente asociado al pedido.
  11. Realizar la petición HTTP con `Http::baseUrl(config('services.mercadopago.base_url'))->withToken(...)->acceptJson()->asJson()->timeout(10)->connectTimeout(3)->retry([100, 500, 1000])->post('/checkout/preferences', $payload)`.
  12. Ante fallo de la API, lanzar excepción controlada (`RuntimeException`) incluyendo status HTTP y `mp_preference_id` si existiera, sin loguear tokens.
  13. Extraer `id` de la preferencia y persistirlo en `$order->update(['mp_preference_id' => $preferenceId])`.
  14. Retornar array tipado por PHPDoc con `id`, `init_point`, `sandbox_init_point` y `redirect_url`, donde `redirect_url` sea `sandbox_init_point` si `services.mercadopago.sandbox` es true, y `init_point` en caso contrario.

#### [NEW] `app/Actions/Ecommerce/MarkWebOrderAsPaid.php`
- **Propósito:** Marcar atómicamente un pedido online como pagado, garantizando idempotencia estricta ante notificaciones duplicadas.
- **Entrada:** `WebOrder $order`, `string $paymentId`, `string $paidAmount`, `Carbon $paidAt`
- **Lógica:**
  1. Usar `App\Concerns\ConvertsMoneyToCents` para comparar importes como centavos, no con floats.
  2. Verificar que `$paidAmount` coincida con `$order->total_amount`; si no coincide, lanzar `ValidationException` o excepción de dominio que el webhook convierta en respuesta 422.
  3. Si `$order->status === WebOrderStatus::Paid`:
     - Si `$order->mp_payment_id === $paymentId`: retornar el `$order` sin realizar cambios (idempotencia ante webhook duplicado).
     - Si tiene otro payment ID: registrar advertencia o lanzar excepción por inconsistencia.
  4. Ejecutar dentro de `DB::transaction()`:
     - Bloquear la fila del pedido con `lockForUpdate()`.
     - Re-verificar si ya fue pagado mientras se esperaba el lock.
     - Actualizar atributos:
       ```php
       $order->update([
           'status' => WebOrderStatus::Paid,
           'mp_payment_id' => $paymentId,
           'paid_amount' => $paidAmount,
           'paid_at' => $paidAt,
       ]);
       ```
  5. No convertir el pedido en venta (cumplimiento explícito del criterio de aceptación: la conversión a venta pertenece a `EPIC-15`).
  6. Retornar el `$order` actualizado.

---

### Backend — Data Objects

#### [MODIFY] `app/Data/Ecommerce/WebOrderData.php`
- Incorporar propiedades para el estado del pago:
  ```php
  public ?string $paid_amount,
  public ?string $formatted_paid_amount,
  public ?string $paid_at_formatted,
  public ?string $mp_preference_id,
  public ?string $mp_payment_id,
  ```
- Mapear en `fromModel()`:
  ```php
  paid_amount: $order->paid_amount,
  formatted_paid_amount: $order->paid_amount === null ? null : self::money($order->paid_amount),
  paid_at_formatted: $order->paid_at?->format('d/m/Y H:i'),
  mp_preference_id: $order->mp_preference_id,
  mp_payment_id: $order->mp_payment_id,
  ```
- Regenerar tipos de TypeScript con `npm run types:generate`.

---

### Backend — Controladores

#### [MODIFY] `app/Http/Controllers/Ecommerce/CheckoutController.php`
- En `store()`:
  - Tras invocar `PlaceWebOrder`:
  - Invocar `CreateMercadoPagoPreference::execute($order)` para generar la preferencia.
  - Redirigir al cliente a Mercado Pago usando `Inertia::location($preference['redirect_url'])`.
  - En caso de error de conexión con Mercado Pago: capturar la excepción y redirigir a `tienda.orders.show` con mensaje flash explicativo informando que el pedido fue confirmado y puede pagarse desde "Mis pedidos".
  - Ajustar el return type del método si hace falta, porque `Inertia::location()` devuelve una respuesta HTTP externa, no un `RedirectResponse` tradicional.

#### [NEW] `app/Http/Controllers/Ecommerce/WebOrderPaymentController.php`
- **Propósito:** Permitir al cliente reintentar el pago de un pedido pendiente desde "Mis pedidos" (`POST /tienda/mis-pedidos/{web_order}/pagar`).
- **Método `store(Request $request, WebOrder $webOrder, CreateMercadoPagoPreference $createPreference)`:**
  1. Resolver el cliente con el mismo patrón que `CheckoutController` / `WebOrderController`: `$request->user()` debe existir y tener `customer`; si no, abortar 403.
  2. Validar autorización: el usuario autenticado debe ser el dueño del pedido (`$webOrder->customer_id === $customer->id`), abortar con 403 si no lo es.
  3. Validar que el pedido esté pendiente: si `$webOrder->status === WebOrderStatus::Paid`, redirigir con advertencia "El pedido ya se encuentra pagado.".
  4. Invocar `CreateMercadoPagoPreference::execute($webOrder)`.
  5. Redirigir al checkout de Mercado Pago vía `Inertia::location($preference['redirect_url'])`.

#### [NEW] `app/Http/Controllers/Ecommerce/WebOrderPaymentReturnController.php`
- **Propósito:** Mostrar la página informativa de retorno del checkout de Mercado Pago (`GET /tienda/mis-pedidos/{web_order}/retorno`).
- **Método `show(Request $request, WebOrder $webOrder)`:**
  1. Validar pertenencia del pedido al cliente autenticado (403 si no pertenece).
  2. Recargar `$webOrder->fresh()`.
  3. Determinar el estado visual según el parámetro de ruta/query `status` ('approved', 'pending', 'rejected') y el estado real del pedido.
  4. **Seguridad estricta:** Este controlador **nunca** marca el pedido como pagado basándose en los parámetros de retorno; solo muestra información al cliente.
  5. Renderizar `ecommerce/orders/payment-return` con los datos del pedido y el estado resultante.

#### [NEW] `app/Http/Controllers/Ecommerce/MercadoPagoWebhookController.php`
- **Propósito:** Endpoint público sin autenticación de sesión para procesar notificaciones webhook de Mercado Pago (`POST /webhooks/mercadopago`).
- **Lógica de `__invoke(Request $request, MarkWebOrderAsPaid $markWebOrderAsPaid)`:**
  1. Extraer identificador del recurso y tópico:
     ```php
     $type = $request->input('type') ?? $request->input('topic') ?? $request->query('topic');
     $paymentId = $request->input('data.id') ?? $request->input('id') ?? $request->query('id');
     ```
  2. Si el tópico no es de tipo `payment` o no se recibe `paymentId`, responder HTTP 200 OK inmediatamente (descarte seguro de pings y eventos no relevantes).
  3. Consultar a la API de Mercado Pago: `GET https://api.mercadopago.com/v1/payments/{paymentId}` con el Access Token en el header `Authorization: Bearer ...`.
  4. Si la API retorna error transitorio o 5xx, devolver código 500 para permitir reintento de Mercado Pago. Si retorna 404 para un pago inexistente, responder 200 con log de descarte para evitar reintentos infinitos por datos inválidos.
  5. Extraer datos del pago:
     - `external_reference`: identificador del pedido.
     - `transaction_amount`: importe cobrado.
     - `status`: estado del pago (`approved`, `rejected`, `in_process`, etc.).
     - `date_approved`: fecha de acreditación.
  6. Validaciones de integridad:
     - Buscar el pedido: `WebOrder::find($payment['external_reference'])`. Si no existe, responder 404 o 200 con log.
     - Normalizar `transaction_amount` a money string (`number_format((float) $payment['transaction_amount'], 2, '.', '')`).
     - Verificar que el importe pagado coincida exactamente con el total del pedido usando `ConvertsMoneyToCents`, no comparación de floats. Si no coincide, rechazar y responder 422.
  7. Si el estado es `approved`:
     - Invocar `MarkWebOrderAsPaid::execute($order, (string) $payment['id'], $paidAmount, Carbon::parse($payment['date_approved']))`.
  8. Responder HTTP 200 con `['status' => 'ok']`.

---

### Rutas y Middleware

#### [MODIFY] `bootstrap/app.php`
- Excluir la ruta del webhook de la verificación CSRF:
  ```php
  $middleware->validateCsrfTokens(except: [
      'webhooks/mercadopago',
  ]);
  ```

#### [MODIFY] `routes/web.php`
- Registrar ruta de webhook pública:
  ```php
  Route::post('webhooks/mercadopago', MercadoPagoWebhookController::class)->name('webhooks.mercadopago');
  ```
- En el grupo `Route::prefix('tienda')->middleware('auth')->group(...)`:
  - Dentro de `Route::prefix('mis-pedidos')->name('orders.')->group(...)`:
    ```php
    Route::post('{web_order}/pagar', [WebOrderPaymentController::class, 'store'])->name('pay');
    Route::get('{web_order}/retorno', [WebOrderPaymentReturnController::class, 'show'])->name('payment-return');
    ```
  - Declararlas antes de `Route::get('{web_order}', [WebOrderController::class, 'show'])->name('show')` para que los segmentos literales `pagar` y `retorno` no queden absorbidos por la ruta wildcard.

### Wayfinder

#### [GENERATE] `resources/js/actions/**` y `resources/js/routes/**`
- Después de modificar rutas, correr:
  ```powershell
  php artisan wayfinder:generate --no-interaction
  ```
- En React, importar funciones generadas en vez de hardcodear URLs:
  - `pay` desde `@/routes/tienda/orders` o el helper que genere Wayfinder para `tienda.orders.pay`.
  - `paymentReturn` solo si la vista necesita construir links de retorno.

---

### Frontend — Páginas y Componentes

#### [MODIFY] `resources/js/pages/ecommerce/orders/show.tsx`
- En el encabezado y tarjeta de resumen:
  - Si `order.status === 'pagado'`:
    - Mostrar fecha/hora de acreditación: `Acreditado el {order.paid_at_formatted}`.
    - Mostrar importe acreditado: `{order.formatted_paid_amount}`.
    - Mostrar identificador del pago: `Pago MP N.º {order.mp_payment_id}`.
  - Si `order.status === 'pendiente'`:
    - Mostrar tarjeta destacada de aviso de pago pendiente con botón principal **"Pagar con Mercado Pago"** o **"Reintentar pago"**.
    - Al hacer clic, enviar `router.post(pay.url(order.id))` o usar `<Link method="post" as="button" href={pay(order.id)}>` con el helper Wayfinder generado, con feedback visual de carga.

#### [MODIFY] `resources/js/pages/ecommerce/orders/index.tsx`
- En la tabla de "Mis pedidos":
  - Mantener la columna "Estado" con Badge `default` (verde/primario) para `pagado` y `secondary` para `pendiente`.
  - Para pedidos pendientes, incluir botón de acción rápida "Pagar" o mantener el acceso directo al detalle para abonar.

#### [NEW] `resources/js/pages/ecommerce/orders/payment-return.tsx`
- Vista Inertia que recibe `{ order: WebOrderData, status: 'approved' | 'pending' | 'rejected' }`.
- No muta estado del cliente: comunica claramente la situación del pago:
  - **Aprobado:** Icono de éxito (verde), mensaje "¡Muchas gracias! Tu pago está siendo procesado por Mercado Pago.", detalle del número de pedido y botón para volver a "Mis pedidos".
  - **Pendiente:** Icono informativo (amarillo/azul), mensaje "Tu pago se encuentra pendiente de acreditación. Apenas Mercado Pago confirme el pago, verás tu pedido como pagado.", botón para volver a "Mis pedidos".
  - **Rechazado:** Icono de alerta (rojo), mensaje "No pudimos procesar tu pago. Tu pedido sigue guardado como pendiente para que puedas reintentar el pago.", botón "Reintentar pago" y botón "Volver a Mis pedidos".

---

### Tests

#### [NEW] `tests/Feature/Ecommerce/CreateMercadoPagoPreferenceTest.php`
- Prueba la Action `CreateMercadoPagoPreference`:
  - `generates preference with order items and shipping cost for pending order`: verifica que envía los ítems correctos, el ítem de envío, `external_reference = order_id`, URLs de retorno y guarda `mp_preference_id` en el pedido.
  - `rejects generating preference for an already paid order`: lanza excepción si el pedido ya está pagado.

#### [NEW] `tests/Feature/Ecommerce/MarkWebOrderAsPaidTest.php`
- Prueba la Action `MarkWebOrderAsPaid`:
  - `marks pending order as paid with payment id amount and timestamp`: actualiza estado, importe pagado, fecha de pago e ID de Mercado Pago.
  - `is idempotent when called multiple times with the same payment id`: segunda ejecución con el mismo ID no produce errores ni altera `paid_at`.
  - `rejects payment when paid amount does not match order total`: no permite marcar pagado con importe distinto.
  - `rejects paying order with a different payment id if already paid`: valida consistencia.

#### [MODIFY] `tests/Feature/Ecommerce/EcommerceSchemaTest.php`
- Ajustar `an order is paid exactly when it has a payment time` o agregar un test específico para verificar que el estado `paid()` incluye `paid_amount` y que el importe pagado queda persistido.

#### [NEW] `tests/Feature/Ecommerce/MercadoPagoWebhookTest.php`
- Prueba el endpoint `POST /webhooks/mercadopago` con `Http::fake()`:
  - `approves payment when webhook notification arrives and api confirms approved payment`: consulta API, valida importe y referencia, ejecuta `MarkWebOrderAsPaid` y responde 200 OK.
  - `does not mark order as paid when payment status is rejected or pending`: responde 200 OK y el pedido se mantiene en `pendiente`.
  - `handles duplicate notification idempotently without errors`: dos POST seguidos con el mismo `payment_id` responden 200 OK y el pedido queda pagado una sola vez.
  - `rejects payment when transaction amount does not match order total`: no marca como pagado y responde con error si el importe recibido difiere del total del pedido.
  - `ignores non-payment notifications gracefully`: pings u otros tipos responden 200 OK sin afectar pedidos.

#### [NEW] `tests/Feature/Ecommerce/WebOrderPaymentHttpTest.php`
- Prueba los endpoints HTTP de la tienda online con `Http::fake()`:
  - `redirects to mercado pago sandbox upon checkout order confirmation`: tras confirmar checkout, redirige a `sandbox_init_point`.
  - `allows customer to retry payment for a pending order`: cliente dueño reintenta pago y es redirigido a Mercado Pago sandbox.
  - `prevents other customers from paying someone else order`: usuario ajeno recibe 403 Forbidden.
  - `prevents re-paying an already paid order`: pedido pagado no permite reintentar el pago.
  - `displays return page without modifying order status directly`: la vista de retorno muestra estado pero no altera la base de datos.

---

## Verificación de la Definition of Done

| Criterio | Cómo se verifica |
|---|---|
| Credenciales en `.env` / `config/services.php` sin exponer al frontend | `config/services.php` contiene credenciales; ningún prop de Inertia ni componente React recibe el access token. |
| Importe pagado persistido | Migración, modelo, factory, `WebOrderData` y tests cubren `paid_amount` como dato separado del total congelado del pedido. |
| Creación de preferencia con ítems, envío y referencia externa | `CreateMercadoPagoPreference` probado con `Http::fake()`, validando payload enviado a Mercado Pago. |
| Redirección al checkout sandbox al confirmar pedido | `CheckoutController::store()` redirige vía `Inertia::location()` al `redirect_url`, que en sandbox toma `sandbox_init_point`. |
| Webhook público sin CSRF | Ruta excluida en `bootstrap/app.php`; recibe POSTs externos sin token CSRF. |
| Consulta a la API de Mercado Pago en webhook | Webhook nunca asume datos del payload inicial; invoca `GET /v1/payments/{id}` antes de validar. |
| Verificación de importe y referencia del pedido | Comparación exacta por centavos de `transaction_amount` con `order->total_amount` y `external_reference` con `order->id`. |
| Idempotencia estricta ante notificaciones duplicadas | Notificación doble con mismo `mp_payment_id` responde 200 sin efectos secundarios. |
| El pedido nace pendiente y pasa a pagado solo con pago aprobado | El webhook solo ejecuta `MarkWebOrderAsPaid` si `status === 'approved'`. Con rechazo sigue pendiente. |
| Reintento de pago desde "Mis pedidos" | Botón en `orders/show.tsx` y endpoint `tienda.orders.pay` permiten pagar pedidos pendientes. |
| Seguridad de autorización y pedidos ya pagados | Test confirma 403 para pedidos de otros clientes y rechazo si el pedido ya está pagado. |
| El pedido pagado no se convierte en venta (EPIC-15) | Verificación en código de que no se crean registros en `sales` ni `stock_movements`. |
| Páginas de retorno informativas sin marcar pago | `payment-return.tsx` no muta estado; explica la situación y ofrece enlaces a "Mis pedidos". |
| Suite de tests en verde | Tests unitarios y HTTP con `Http::fake()` corriendo y pasando en Pest. |
| CI checks limpios | Pint, PHPStan (`types:check`), ESLint (`lint:check`), Prettier (`format:check`), TypeScript (`types:check`). |

---

## Verification Plan

### Automated Tests
- Ejecutar la suite completa de tests de pago de Mercado Pago y pedidos online:
  ```powershell
  php artisan test --compact --filter=MercadoPago
  php artisan test --compact --filter=WebOrderPayment
  php artisan test --compact --filter=MarkWebOrderAsPaid
  php artisan test --compact --filter=PlaceWebOrderActionTest
  php artisan test --compact tests/Feature/Ecommerce/WebOrderHttpTest.php
  ```

### Manual Verification
1. Configurar credenciales de prueba en `.env`: `MERCADO_PAGO_ACCESS_TOKEN` y `MERCADO_PAGO_PUBLIC_KEY`.
2. Iniciar sesión como cliente y armar un carrito de compras.
3. Avanzar al checkout y confirmar el pedido con entrega a domicilio o retiro.
4. Verificar que el navegador es redirigido a la pantalla de pago de Mercado Pago Sandbox.
5. Simular pago aprobado con tarjeta de prueba de Mercado Pago:
   - Verificar recepción del webhook local o retorno.
   - En "Mis pedidos", verificar que el pedido pase a "Pagado", con su fecha de acreditación y su ID de pago.
6. Realizar otro pedido y simular pago rechazado / abandonar checkout:
   - Verificar que el pedido permanece en estado "Pendiente de pago".
   - Ingresar a "Mis pedidos" -> "Ver pedido" y presionar el botón "Pagar con Mercado Pago" para reintentar exitosamente el pago.

### CI checks
- `vendor/bin/pint --format agent`
- `php artisan wayfinder:generate --no-interaction`
- `composer run types:check`
- `npm run types:generate`
- `npm run lint:check`
- `npm run format:check`
- `npm run types:check`

---

## Open Questions

*No se detectan preguntas abiertas bloqueantes:* El único faltante detectado era técnico y quedó incorporado al plan como `paid_amount`. El backlog y el PO establecieron explícitamente que todo pedido online se paga con Mercado Pago, que se utiliza el entorno sandbox para pruebas, y que la conversión a venta y factura se posterga para `EPIC-15`.
