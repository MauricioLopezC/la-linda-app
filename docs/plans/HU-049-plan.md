# HU-049 — Elegir la modalidad de entrega y calcular el costo de envío

Permite al cliente de la tienda online elegir entre retiro en sucursal (sin costo adicional) y envío a domicilio (con costo fijo configurable por entorno) durante el checkout (`ECO-05`). El domicilio se precarga desde los datos del cliente pero puede modificarse para el pedido puntual sin alterar su ficha. El costo de envío queda congelado en el pedido al confirmarlo y el checkout muestra el desglose transparente de subtotal, costo de envío y total final.

Esta historia amplía el flujo de confirmación implementado en `HU-062`.

## Criterios de aceptación (de `product-backlog.md`)

1. **Datos:** modalidad de entrega (retiro en sucursal o envío a domicilio), sucursal de retiro, domicilio de entrega e indicaciones, costo de envío, total del pedido con el envío.
2. **Validaciones:**
   - El retiro exige una sucursal activa; el envío exige un domicilio de entrega.
   - El costo de envío nunca lo carga el cliente.
3. **Comportamiento:**
   - El retiro en sucursal no tiene costo; el envío a domicilio suma un costo fijo único (configurable por entorno en `config/ecommerce.php`).
   - El domicilio de entrega se precarga con el del cliente y se puede cambiar para ese pedido sin modificar su cuenta.
   - El costo de envío queda fijo en el pedido al confirmarlo.
   - El checkout muestra el subtotal de artículos, el envío y el total antes de confirmar.
4. **Verificación:** se confirma un pedido con envío a domicilio y su total es artículos más costo de envío; otro con retiro no suma envío.

## Investigación del código existente

| Artefacto | Ubicación | Propósito |
|---|---|---|
| Migración `web_orders` | `database/migrations/2026_09_28_100015_create_web_orders_table.php` | Esquema ya aplicado en `master` (Sprint 4). Contiene columnas `delivery_method` (`CHECK in ('retiro', 'envio')`), `pickup_branch_id` (nullable, foreignId branches), `shipping_address` (nullable), `shipping_notes` (nullable), `items_amount`, `shipping_cost` (default 0), `total_amount` (`CHECK items_amount + shipping_cost`). **No se requieren migraciones nuevas.** |
| Modelo `WebOrder` | `app/Models/Ecommerce/WebOrder.php` | Modelo Eloquent de pedidos online. Ya tiene cast de `delivery_method` a `DeliveryMethod::class`, fillable completo y relaciones `customer`, `pickupBranch`, `items`. |
| Enum `DeliveryMethod` | `app/Enums/Ecommerce/DeliveryMethod.php` | Enum string con casos `Pickup = 'retiro'` y `Shipping = 'envio'`, y método `label()` ("Retiro en sucursal", "Envío a domicilio"). |
| Modelo `Customer` | `app/Models/Customers/Customer.php` | Modelo del cliente. Posee atributo `$address` que se usará para precargar el domicilio en el checkout. |
| Action `PlaceWebOrder` | `app/Actions/Ecommerce/PlaceWebOrder.php` | Confirma el pedido desde el carrito atómicamente. Actualmente solo soporta retiro en sucursal hardcodeado. Requiere recibir la modalidad y datos de entrega, validar según la modalidad y calcular/congelar el costo de envío configurado. |
| Controlador `CheckoutController` | `app/Http/Controllers/Ecommerce/CheckoutController.php` | Renderiza el checkout (`show`) y procesa la confirmación (`store`). Requiere pasar a la vista el domicilio predeterminado del cliente y el costo de envío configurado, y procesar la modalidad de entrega en `store`. |
| Form Request `PlaceWebOrderRequest` | `app/Http/Requests/Ecommerce/PlaceWebOrderRequest.php` | Valida la confirmación del pedido. Requiere validar `delivery_method`, `pickup_branch_id` condicional a retiro, `shipping_address` condicional a envío, y `shipping_notes`. Protege que el cliente no envíe ni sobreescriba `shipping_cost`. |
| Data object `WebOrderData` | `app/Data/Ecommerce/WebOrderData.php` | DTO de salida de pedidos online para el cliente. Requiere agregar los campos `shipping_address` y `shipping_notes`. |
| Vista de Checkout | `resources/js/pages/ecommerce/checkout/show.tsx` | Pantalla de confirmación del pedido. Requiere selector visual entre "Retiro en sucursal" y "Envío a domicilio", inputs condicionales según modalidad (sucursal vs. domicilio precargado/editable e indicaciones), y resumen interactivo con subtotal, costo de envío y total. |
| Vista de Detalle de Pedido | `resources/js/pages/ecommerce/orders/show.tsx` | Pantalla de "Mis pedidos" del cliente. Requiere mostrar el domicilio de entrega y las indicaciones si la modalidad fue envío a domicilio. |
| Factory `WebOrderFactory` | `database/factories/Ecommerce/WebOrderFactory.php` | Ya cuenta con estado `shipping(string $shippingCost = '2500.00')`. |

*Nota sobre el diagrama Mermaid de `sprint-backlog-4.md`:* El código y las migraciones existentes coinciden exactamente con el DER de `sprint-backlog-4.md`. No hay discrepancias en la base de datos.

## Proposed Changes

### Backend — Configuración

#### [NEW] `config/ecommerce.php`
- Archivo de configuración para variables del módulo de e-commerce.
- Define el costo fijo de envío a domicilio:
  ```php
  <?php

  return [
      /*
       * Costo fijo de envío a domicilio para la tienda online (HU-049).
       * Configurable mediante variable de entorno ECOMMERCE_SHIPPING_COST.
       */
      'shipping_cost' => env('ECOMMERCE_SHIPPING_COST', '2500.00'),
  ];
  ```

#### [MODIFY] `.env.example`
- Agregar la clave de ejemplo:
  ```env
  ECOMMERCE_SHIPPING_COST=2500.00
  ```

---

### Backend — Action

#### [MODIFY] `app/Actions/Ecommerce/PlaceWebOrder.php`
- Actualizar la firma de `execute()` para soportar modalidad de entrega:
  ```php
  public function execute(
      Customer $customer,
      DeliveryMethod $deliveryMethod,
      ?Branch $pickupBranch = null,
      ?string $shippingAddress = null,
      ?string $shippingNotes = null,
      ?string $notes = null,
  ): WebOrder
  ```
- Lógica en el método privado `place()`:
  - **Validación de modalidad:**
    - Si `$deliveryMethod === DeliveryMethod::Pickup`:
      - Validar que `$pickupBranch` no sea nulo y esté activo (`Branch::query()->active()->whereKey($pickupBranch->id)->exists()`). En caso contrario, lanzar `ValidationException::withMessages(['pickup_branch_id' => ...])`.
      - Asignar `$shippingAddress = null`, `$shippingNotes = null`.
      - Asignar `$shippingCost = '0.00'`.
      - Asignar `$totalAmount = $itemsAmount`.
    - Si `$deliveryMethod === DeliveryMethod::Shipping`:
      - Validar que `$shippingAddress` contenga un texto no vacío (`filled($shippingAddress)`). Si es nulo o vacío, lanzar `ValidationException::withMessages(['shipping_address' => 'El domicilio de entrega es obligatorio para envíos a domicilio.'])`.
      - Asignar `$pickupBranch = null`.
      - Asignar `$shippingAddress = trim($shippingAddress)`.
      - Asignar `$shippingNotes = filled($shippingNotes) ? trim($shippingNotes) : null`.
      - Leer `$shippingCost = number_format((float) config('ecommerce.shipping_cost', '2500.00'), 2, '.', '')`.
      - Calcular `$totalAmount = number_format((float) $itemsAmount + (float) $shippingCost, 2, '.', '')`.
      - **Importante:** no modificar el campo `address` del modelo `$customer` en la base de datos (criterio: "se puede cambiar para ese pedido sin modificar su cuenta").
  - **Creación de `WebOrder`:**
    - Persistir los campos:
      - `delivery_method => $deliveryMethod`
      - `pickup_branch_id => $pickupBranch?->id`
      - `shipping_address => $shippingAddress`
      - `shipping_notes => $shippingNotes`
      - `items_amount => $amount`
      - `shipping_cost => $shippingCost`
      - `total_amount => $totalAmount`
      - `notes => filled($notes) ? trim($notes) : null`

---

### Backend — Form Request

#### [MODIFY] `app/Http/Requests/Ecommerce/PlaceWebOrderRequest.php`
- Actualizar `prepareForValidation()` para fijar `delivery_method` en `retiro` si no fue provisto (retrocompatibilidad).
- Reglas de validación:
  ```php
  public function rules(): array
  {
      return [
          'delivery_method' => ['required', Rule::enum(DeliveryMethod::class)],
          'pickup_branch_id' => [
              Rule::requiredIf($this->input('delivery_method') === DeliveryMethod::Pickup->value),
              'nullable',
              'integer',
              Rule::exists('branches', 'id')->where('is_active', true),
          ],
          'shipping_address' => [
              Rule::requiredIf($this->input('delivery_method') === DeliveryMethod::Shipping->value),
              'nullable',
              'string',
              'max:255',
          ],
          'shipping_notes' => ['nullable', 'string', 'max:255'],
          'notes' => ['nullable', 'string', 'max:500'],
      ];
  }
  ```
- Mensajes en español claros y directos para cada fallo de validación.

---

### Backend — Controlador

#### [MODIFY] `app/Http/Controllers/Ecommerce/CheckoutController.php`
- En `show()`:
  - Obtener el costo de envío configurado:
    `$shippingCost = number_format((float) config('ecommerce.shipping_cost', '2500.00'), 2, '.', '');`
  - Pasar a la vista:
    - `cart => $cart`
    - `branches => PickupBranchOptionData::collect($branches)`
    - `default_shipping_address => $customer->address`
    - `shipping_cost => $shippingCost`
    - `formatted_shipping_cost => '$ '.number_format((float) $shippingCost, 2, ',', '.')`
- En `store()`:
  - Resolver `deliveryMethod = DeliveryMethod::from($request->validated('delivery_method'))`.
  - Si es `Pickup`, resolver `$branch = Branch::findOrFail($request->validated('pickup_branch_id'))`. Si es `Shipping`, `$branch = null`.
  - Invocar `placeWebOrder->execute(...)` pasando `customer`, `deliveryMethod`, `pickupBranch`, `shipping_address`, `shipping_notes` y `notes`.

---

### Backend — Data Objects

#### [MODIFY] `app/Data/Ecommerce/WebOrderData.php`
- Agregar propiedades:
  ```php
  public ?string $shipping_address,
  public ?string $shipping_notes,
  ```
- Asignarlas en `fromModel()`:
  ```php
  shipping_address: $order->shipping_address,
  shipping_notes: $order->shipping_notes,
  ```
- Regenerar tipos con `npm run types:generate`.

---

### Frontend — Páginas

#### [MODIFY] `resources/js/pages/ecommerce/checkout/show.tsx`
- Extender el tipo de Props:
  ```ts
  type Props = {
    cart: CartData;
    branches: PickupBranch[];
    default_shipping_address: string | null;
    shipping_cost: string;
    formatted_shipping_cost: string;
  };
  ```
- Extender `form` de Inertia:
  ```ts
  const form = useForm({
    delivery_method: 'retiro',
    pickup_branch_id: branches.length === 1 ? String(branches[0].id) : '',
    shipping_address: default_shipping_address ?? '',
    shipping_notes: '',
    notes: '',
  });
  ```
- Selector de modalidad de entrega con dos tarjetas/opciones claramente diferenciadas:
  1. **Retiro en sucursal:** badge "Sin costo", icono de tienda/map-pin. Al seleccionarlo despliega el combo de sucursales disponibles.
  2. **Envío a domicilio:** badge con el valor de `formatted_shipping_cost`, icono de camión de entrega. Al seleccionarlo despliega los campos:
     - Domicilio de entrega (obligatorio, input con placeholder, mensaje aclarando que no modifica el domicilio del perfil).
     - Indicaciones para la entrega (opcional, ej. "Depto 2B, tocar timbre").
- Resumen lateral:
  - Subtotal de artículos: `cart.formatted_total`
  - Fila de Entrega:
    - Si `delivery_method === 'retiro'`: "Retiro en sucursal" → "Sin costo"
    - Si `delivery_method === 'envio'`: "Envío a domicilio" → `formatted_shipping_cost`
  - Total:
    - Calculado dinámicamente:
      - Si retiro: `cart.total`
      - Si envío: `(Number(cart.total) + Number(shipping_cost))` formateado en moneda argentina.
- Deshabilitar el botón de confirmar si `form.processing`, `cart.has_unavailable_items`, o si es retiro y no hay sucursales disponibles.

#### [MODIFY] `resources/js/pages/ecommerce/orders/show.tsx`
- En la tarjeta de modalidad de entrega:
  - Si `order.delivery_method === 'retiro'`: mantiene el renderizado de sucursal y dirección de la sucursal.
  - Si `order.delivery_method === 'envio'`:
    - Muestra icono de entrega a domicilio (`Truck`).
    - Muestra Domicilio de entrega: `order.shipping_address`.
    - Si tiene `order.shipping_notes`, muestra las indicaciones de entrega con icono descriptivo.
  - Muestra `order.notes` si existen observaciones generales.
  - El desglose de importes ya muestra `order.formatted_shipping_cost` y `order.formatted_total_amount`.

---

### Tests

#### [MODIFY] `tests/Feature/Ecommerce/PlaceWebOrderActionTest.php`
- Actualizar los tests que ejecutan `$this->action->execute(...)` para enviar explícitamente `DeliveryMethod::Pickup, $this->branch`.
- Agregar tests unitarios/feature para:
  1. `places a pending delivery order with the configured shipping cost and frozen shipping address`: pedido con `DeliveryMethod::Shipping`, verifica que `pickup_branch_id` es null, `shipping_address` y `shipping_notes` se guardan, `shipping_cost` coincide con el configurado y `total_amount` es la suma exacta.
  2. `keeps the frozen shipping cost even if the configuration changes later`: tras confirmar, se altera `config(['ecommerce.shipping_cost' => '8000.00'])`, y `fresh()` del pedido conserva el costo original.
  3. `does not alter customer account address when a different shipping address is used`: cliente con dirección "Calle Original 123", pedido con envío a "Calle Temporal 456", verificar que `$customer->fresh()->address` sigue siendo "Calle Original 123".
  4. `rejects a delivery order without shipping address`: falla con `ValidationException` en `shipping_address`.
  5. `rejects a pickup order without an active pickup branch`: falla con `ValidationException` en `pickup_branch_id`.

#### [MODIFY] `tests/Feature/Ecommerce/WebOrderHttpTest.php`
- Probar endpoint `GET /tienda/checkout`:
  - Retorna `default_shipping_address`, `shipping_cost` y `formatted_shipping_cost`.
- Probar endpoint `POST /tienda/checkout`:
  - Confirmar con modalidad `retiro` → crea pedido con retiro y `shipping_cost = 0`.
  - Confirmar con modalidad `envio` → crea pedido con envío, domicilio, notas y `shipping_cost` configurado.
  - Validación: enviar con `envio` sin `shipping_address` retorna error en la sesión para `shipping_address`.
  - Validación: enviar con `retiro` sin `pickup_branch_id` retorna error en la sesión para `pickup_branch_id`.
  - Protección: si el cliente envía un valor arbitrario en el payload intentando modificar el costo de envío, este es ignorado y se aplica siempre el costo de configuración.
- Probar endpoint `GET /tienda/mis-pedidos/{order}`:
  - Muestra correctamente los datos de envío (`shipping_address` y `shipping_notes`) para un pedido con modalidad de envío.

---

## Verificación de la Definition of Done

| Criterio | Cómo se verifica |
|---|---|
| Elección entre retiro y envío en checkout | El formulario de `tienda.checkout.show` ofrece ambas opciones y adapta los campos según la elección. |
| Retiro exige sucursal activa | Validación en `PlaceWebOrderRequest` y en `PlaceWebOrder`. Test automatizado con sucursal inactiva o nula. |
| Envío exige domicilio | Validación en `PlaceWebOrderRequest` y en `PlaceWebOrder`. Test automatizado con domicilio ausente o en blanco. |
| Costo de envío congelado y nunca cargado por el cliente | `PlaceWebOrder` lee `config('ecommerce.shipping_cost')`, ignora cualquier input del usuario. Test que valida persistencia y congelamiento ante cambio de config posterior. |
| Domicilio precargado y editable sin modificar cuenta | Se precarga con `$customer->address`. Al confirmarse con otra dirección, el pedido la guarda pero `$customer->fresh()->address` se mantiene intacto. |
| Checkout muestra subtotal, envío y total antes de confirmar | La vista `checkout/show.tsx` recalcula en vivo el total y muestra el costo de envío según la modalidad elegida. |
| Visualización en detalle del pedido | La vista `orders/show.tsx` muestra la dirección de entrega e indicaciones para pedidos con envío. |
| Suite de pruebas automatizadas en verde | Todos los tests de `PlaceWebOrderActionTest` y `WebOrderHttpTest` pasan sin errores. |
| CI checks limpios | Pint, PHPStan (`types:check`), ESLint (`lint:check`), Prettier (`format:check`), TypeScript (`types:check`). |

## Verification Plan

### Automated Tests
- Ejecutar tests unitarios y de integración de e-commerce:
  ```powershell
  php artisan test --compact --filter=WebOrder
  php artisan test --compact --filter=PlaceWebOrderActionTest
  php artisan test --compact --filter=WebOrderHttpTest
  ```

### Manual Verification
1. Iniciar sesión como cliente con domicilio configurado.
2. Agregar artículos al carrito e ingresar a `/tienda/checkout`.
3. Constatar que por defecto está seleccionado "Retiro en sucursal" y el total no incluye costo de envío.
4. Cambiar a "Envío a domicilio":
   - Constatar que el costo de envío se agrega al desglose y el total se actualiza en pantalla.
   - Constatar que el domicilio aparece precargado con el del cliente.
5. Modificar el domicilio agregando indicaciones y confirmar el pedido.
6. En la pantalla de confirmación (`/tienda/mis-pedidos/{id}`), verificar que figure la modalidad "Envío a domicilio", el domicilio especificado, las indicaciones y el total correcto.
7. Verificar en la base de datos o perfil del cliente que su dirección registrada no fue alterada.

### CI checks
- `vendor/bin/pint --format agent`
- `composer run types:check`
- `npm run types:generate`
- `npm run lint:check`
- `npm run format:check`
- `npm run types:check`

## Open Questions

*No se detectan preguntas abiertas bloqueantes:* El backlog y el sprint backlog definen claramente que el costo de envío es un valor fijo único configurable por entorno (`ECOMMERCE_SHIPPING_COST`, default 2500.00) y que no hay envío gratis. El esquema de base de datos ya está 100% migrado y compatible con el DER acordado.
