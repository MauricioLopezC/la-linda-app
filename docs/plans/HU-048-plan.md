# HU-048 — Mostrar la disponibilidad online e impedir la compra sin stock

Calcula la disponibilidad real de los artículos para la venta online descontando las unidades comprometidas en pedidos web pagados o en preparación pendientes de entrega en depósito, impidiendo la sobreventa y la compra sin stock en catálogo, carrito y checkout.

## Criterios de aceptación (de `product-backlog.md`)

1. **Datos:** por cada artículo publicado en la tienda online, cantidad disponible para la venta online (`available_quantity`), calculada como la existencia actual en el depósito asignado al canal online (`warehouses.is_online_channel = true`) **menos** las unidades comprometidas en pedidos web ya pagados o en preparación pendientes de salida de depósito (`web_orders.status IN ('pagado', 'en_preparacion')`), e indicador de disponibilidad (`disponible`, `ultimas_unidades`, `sin_stock`).
2. **Validaciones:**
   - no se puede agregar al carrito ni incrementar una línea por encima de la cantidad disponible online (`available_quantity`) del artículo.
   - al confirmar el pedido desde el carrito (`HU-062` / `PlaceWebOrder`), se vuelve a verificar dentro de la transacción la disponibilidad de cada renglón: si otro cliente agotó o comprometió el stock antes, la confirmación se rechaza nombrando el artículo afectado y la cantidad disponible real.
3. **Comportamiento:**
   - como en el canal online el stock físico recién se descuenta cuando el depósito entrega el pedido (`EPIC-17`), descontar las unidades comprometidas en pedidos `pagado` y `en_preparacion` impide que dos clientes compren la misma mercadería mientras aguarda despacho.
   - en el catálogo público (`/tienda`) y en el detalle del artículo se muestra su estado de disponibilidad y, cuando está `sin_stock` (`available_quantity <= 0`), el botón `"Agregar al carrito"` queda deshabilitado con la leyenda `"Sin stock"`.
   - si un artículo que ya estaba en el carrito queda sin stock suficiente antes de confirmar, el carrito informa la cantidad máxima disponible y bloquea el paso al pago hasta ajustar o quitar la línea.
4. **Verificación:** un artículo tiene 3 unidades en el depósito online; el Cliente A compra y paga 2 unidades (el pedido queda `pagado`, aún sin entregarse en depósito); el Cliente B entra a la tienda, ve que solo queda 1 unidad disponible y si intenta comprar 2 unidades el sistema lo rechaza.

---

## Investigación del código existente

| Artefacto | Ubicación | Propósito |
|---|---|---|
| Modelo `Warehouse` | `app/Models/Inventory/Warehouse.php` | Modelo de depósito físico con `is_online_channel` e `is_active`. Ya cuenta con `scopeOnline()` y `scopeActive()`. |
| Migración `warehouses` | `database/migrations/2026_08_16_161533_create_warehouses_table.php` | Tabla `warehouses`. En el sprint backlog se acordó incorporar `UNIQUE INDEX warehouses_single_active_online_channel (is_online_channel) WHERE is_online_channel AND is_active` para blindar un único depósito web activo. |
| Regla `AtMostOneOnlineWarehouse` | `app/Rules/Inventory/AtMostOneOnlineWarehouse.php` | Valida que no haya más de un depósito online; debe ajustarse para verificar depósitos activos (`is_active = true`). |
| Action `ToggleWarehouseStatus` | `app/Actions/Inventory/ToggleWarehouseStatus.php` | Activa/desactiva un depósito. Debe impedir reactivar un depósito online si ya existe otro online activo. |
| Modelo `StockBalance` | `app/Models/Inventory/StockBalance.php` | Almacena `quantity` (`decimal:3`) por `article_id` y `warehouse_id`. |
| Modelo `WebOrder` y `WebOrderItem` | `app/Models/Ecommerce/WebOrder.php`, `WebOrderItem.php` | Pedidos web con `status` (`WebOrderStatus::Paid`, `InPreparation`, etc.) y líneas de artículos con `quantity` (`decimal:3`). |
| Factory `WebOrderFactory` | `database/factories/Ecommerce/WebOrderFactory.php` | Ya incluye states `paid()`, `inPreparation()` y `delivered()`. |
| Action `ConsultOnlineCatalog` | `app/Actions/Ecommerce/ConsultOnlineCatalog.php` | Pagina los artículos publicados en la tienda online. Debe integrar el cálculo de disponibilidad en lote para no generar N+1 queries. |
| Action `AddArticleToCart` | `app/Actions/Ecommerce/AddArticleToCart.php` | Agrega artículos al carrito. Debe validar que `cantidad_actual_en_carrito + nueva_cantidad <= available_quantity`. |
| Action `UpdateCartItemQuantity` | `app/Actions/Ecommerce/UpdateCartItemQuantity.php` | Actualiza la cantidad de un ítem en el carrito. Debe validar que no supere `available_quantity`. |
| Action `GetCustomerCart` | `app/Actions/Ecommerce/GetCustomerCart.php` | Obtiene el carrito del cliente resolviendo precios y disponibilidad. Debe marcar `is_available = false` y detallar `unavailable_reason` si el stock cayó por debajo de lo solicitado. |
| Action `PlaceWebOrder` | `app/Actions/Ecommerce/PlaceWebOrder.php` | Confirma el pedido web en una transacción atómica. Debe bloquear concurrentemente los artículos (`lockForUpdate()` sobre `Article`), verificar disponibilidad y rechazar si hubo sobreventa. |
| Data Object `OnlineCatalogArticleData` | `app/Data/Ecommerce/OnlineCatalogArticleData.php` | Estructura para el catálogo online. Debe sumar `available_quantity`, `availability_status` y `availability_label`. |
| Data Object `CartItemData` | `app/Data/Ecommerce/CartItemData.php` | Estructura de ítem de carrito. Debe sumar `available_quantity` y `availability_status`. |
| Páginas Frontend | `resources/js/pages/ecommerce/index.tsx`, `cart/index.tsx` | Vistas de catálogo y carrito. En catálogo muestra badges y deshabilita "Añadir"; en carrito muestra aviso de stock insuficiente y bloquea el botón hacia checkout. |

### Notas de diseño y mitigación de concurrencia

1. **Invariante de base de datos:** El índice condicional `warehouses_single_active_online_channel` en `warehouses (is_online_channel) WHERE is_online_channel AND is_active` garantiza a nivel motor que nunca existan dos depósitos online activos simultáneos.
2. **Sin `lockForUpdate()` en agregados (Regla `.ai/rules/actions.md`):** En `PlaceWebOrder`, para evitar condiciones de carrera entre dos clientes intentando comprar las últimas unidades, se aplica un bloqueo pesimista ordenado sobre las filas de los artículos del carrito (`Article::query()->whereIn('id', $articleIds)->orderBy('id')->lockForUpdate()->get()`). Esto serializa las confirmaciones en PostgreSQL y SQLite sin invocar `FOR UPDATE` sobre `sum()`, permitiendo recalcular la disponibilidad de manera aislada y atómica.

---

## Proposed Changes

### Base de datos

#### [NEW] `database/migrations/2026_10_15_120000_add_single_active_online_warehouse_unique_index.php`
- Agrega el índice único parcial:
  ```php
  DB::statement('create unique index warehouses_single_active_online_channel on warehouses (is_online_channel) where is_online_channel and is_active');
  ```
- En `down()`:
  ```php
  DB::statement('drop index if exists warehouses_single_active_online_channel');
  ```

---

### Backend — Enums & Config

#### [NEW] `app/Enums/Ecommerce/OnlineAvailabilityStatus.php`
- Enum con respaldo string:
  - `Available = 'disponible'` (etiqueta: "Disponible")
  - `LastUnits = 'ultimas_unidades'` (etiqueta: "Últimas unidades")
  - `OutOfStock = 'sin_stock'` (etiqueta: "Sin stock")
- Método `label(): string`.

#### [MODIFY] `config/ecommerce.php`
- Agregar umbral configurable para últimas unidades:
  ```php
  'low_stock_threshold' => (float) env('ECOMMERCE_LOW_STOCK_THRESHOLD', 5.0),
  ```

---

### Backend — Actions

#### [NEW] `app/Actions/Ecommerce/GetOnlineAvailability.php`
- Encapsula el cálculo de disponibilidad online de acuerdo al criterio del negocio:
  - Resuelve el depósito online activo (`Warehouse::query()->online()->active()->first()`).
  - Si no hay depósito online activo, retorna `available_quantity = '0.000'`, estado `sin_stock`.
  - Método `forArticle(Article|int $article): OnlineAvailabilityResult`:
    - Obtiene la existencia física en `stock_balances` (`warehouse_id = $onlineWarehouse->id`).
    - Suma las cantidades en `web_order_items` de pedidos con `status IN ('pagado', 'en_preparacion')`.
    - `available_quantity = max(0.0, physical_balance - committed_quantity)`.
    - Determina `status`: `sin_stock` ($\le 0$), `ultimas_unidades` ($\le \text{threshold}$), o `disponible`.
  - Método `forArticles(array|Collection $articles): array<int, OnlineAvailabilityResult>`:
    - Ejecuta en **2 consultas agrupadas** (una para `stock_balances` y otra para `web_order_items`) para optimizar el catálogo sin N+1.
- Clase de resultado `OnlineAvailabilityResult`:
  - `availableQuantity: string` (formato `decimal:3`)
  - `numericQuantity: float`
  - `status: OnlineAvailabilityStatus`
  - `isAvailable: bool`

#### [MODIFY] `app/Actions/Ecommerce/ConsultOnlineCatalog.php`
- Inyecta `GetOnlineAvailability`.
- Para los artículos de la página actual (`$paginator->items()`), invoca `GetOnlineAvailability::forArticles(...)` y pasa la disponibilidad resultante a `OnlineCatalogArticleData::fromArticleAndPrice(...)`.

#### [MODIFY] `app/Actions/Ecommerce/AddArticleToCart.php`
- Inyecta `GetOnlineAvailability`.
- Obtiene la disponibilidad online del artículo (`$availability = $this->getOnlineAvailability->forArticle($article)`).
- Si `!$availability->isAvailable` o `$availability->numericQuantity <= 0`, lanza `ValidationException` con mensaje `"El artículo no se encuentra disponible por falta de stock."`.
- Calcula la cantidad acumulada si el cliente ya tenía el artículo en el carrito:
  `$totalRequested = (float) $existingItem->quantity + $numericQuantity`.
- Si `$totalRequested > $availability->numericQuantity`, lanza `ValidationException`:
  `"La cantidad total solicitada ({$totalRequested}) supera el stock disponible en la tienda online ({$availability->availableQuantity})."`.

#### [MODIFY] `app/Actions/Ecommerce/UpdateCartItemQuantity.php`
- Inyecta `GetOnlineAvailability`.
- Obtiene la disponibilidad online del artículo.
- Si `$numericQuantity > $availability->numericQuantity`, lanza `ValidationException`:
  `"La cantidad solicitada ({$numericQuantity}) supera el stock disponible en la tienda online ({$availability->availableQuantity})."`.

#### [MODIFY] `app/Actions/Ecommerce/GetCustomerCart.php`
- Inyecta `GetOnlineAvailability`.
- En el recorrido de `$cartItems`:
  - Obtiene la disponibilidad del artículo.
  - Si la cantidad requerida en el carrito supera la disponibilidad (`(float) $item->quantity > $availability->numericQuantity`):
    - Se marca `$isAvailable = false`.
    - Se asigna `$unavailableReason = $availability->numericQuantity <= 0 ? 'Sin stock disponible' : "Stock insuficiente (disponible: {$availability->availableQuantity} {$item->article->unitOfMeasure->abbreviation})"`.
    - Se activa `$hasUnavailable = true`.
  - Pasa `available_quantity` y `availability_status` a `CartItemData::fromModel(...)`.

#### [MODIFY] `app/Actions/Ecommerce/PlaceWebOrder.php`
- Inyecta `GetOnlineAvailability`.
- Dentro de la transacción atómica:
  - Extrae los `article_id` de todos los ítems del carrito.
  - Aplica un bloqueo pesimista en orden sobre los artículos:
    ```php
    Article::query()->whereIn('id', $articleIds)->orderBy('id')->lockForUpdate()->get();
    ```
  - Recalcula la disponibilidad online de cada artículo.
  - Si para algún artículo `$item->quantity > $availability->numericQuantity`, lanza `ValidationException`:
    ```php
    throw ValidationException::withMessages([
        'cart' => "No se puede confirmar el pedido porque no hay stock suficiente para: {$item->article->description} (solicitado: {$item->quantity}, disponible: {$availability->availableQuantity}).",
    ]);
    ```

#### [MODIFY] `app/Actions/Inventory/ToggleWarehouseStatus.php`
- Al intentar activar un depósito con `is_online_channel = true`, verifica si ya existe otro depósito online activo. Si existe, lanza `ValidationException` con mensaje claro `"Ya existe un depósito activo asignado al canal online."`.

#### [MODIFY] `app/Rules/Inventory/AtMostOneOnlineWarehouse.php`
- Considera solo depósitos activos (`where('is_active', true)`), en coherencia con el índice parcial y `ToggleWarehouseStatus`.

---

### Backend — Data Objects

#### [MODIFY] `app/Data/Ecommerce/OnlineCatalogArticleData.php`
- Agrega propiedades:
  - `public string $available_quantity`
  - `public string $availability_status` (`'disponible' | 'ultimas_unidades' | 'sin_stock'`)
  - `public string $availability_label`
- Actualiza `fromArticleAndPrice(...)` para recibir `OnlineAvailabilityResult`.

#### [MODIFY] `app/Data/Ecommerce/CartItemData.php`
- Agrega propiedades:
  - `public string $available_quantity`
  - `public string $availability_status`
- Actualiza `fromModel(...)` con los nuevos campos.

---

### Frontend

#### [MODIFY] `resources/js/pages/ecommerce/index.tsx`
- En `ArticleCard`:
  - Mostrar badge de disponibilidad según `article.availability_status`:
    - `disponible`: Badge sutil / verde ("Disponible").
    - `ultimas_unidades`: Badge advertencia / ámbar ("Últimas unidades").
    - `sin_stock`: Badge destructivo / rojo ("Sin stock").
  - Si `article.availability_status === 'sin_stock'`:
    - Deshabilitar el botón de compra con texto `"Sin stock"`.
    - Deshabilitar el stepper de cantidades.
  - Limitar el incremento del stepper al stock disponible (`qty >= parseFloat(article.available_quantity)`).
- En el modal de vista rápida (`Dialog`):
  - Incorporar la visualización del badge de disponibilidad.
  - Deshabilitar botón de añadir y stepper si está sin stock.

#### [MODIFY] `resources/js/pages/ecommerce/cart/index.tsx`
- Si un ítem tiene `!item.is_available` por falta de stock:
  - Mostrar el badge y la leyenda `"Stock insuficiente (máximo disponible: X)"` o `"Sin stock disponible"`.
  - El botón hacia checkout permanece bloqueado mientras existan ítems no disponibles (`cart.has_unavailable_items`).

---

## Verificación de la Definition of Done

| Criterio | Cómo se verifica |
|---|---|
| Cálculo exacto de disponibilidad | Test unitario verificando que `available = stock_balance - (pagado + en_preparacion)`. |
| Pedidos pendientes no restan y entregados no duplican descuento | Test que comprueba que un pedido `pendiente` no resta disponibilidad, y un pedido `entregado` ya tiene el stock físico descontado sin descontar dos veces. |
| Bloqueo al agregar al carrito | Test feature intentando agregar más unidades de las disponibles o agregar con stock 0; respuesta 422 con error de validación. |
| Bloqueo al actualizar cantidad en carrito | Test feature intentando hacer patch de cantidad por encima de la disponible; respuesta 422. |
| Advertencia y bloqueo en carrito | Test feature donde un artículo en carrito se queda sin stock y el carrito lo expone como `is_available = false` con su razón de indisponibilidad. |
| Bloqueo por concurrencia en checkout | Test feature donde el Cliente A paga las últimas unidades y la confirmación del Cliente B en `PlaceWebOrder` es rechazada con el detalle del artículo y stock real. |
| Unicidad de depósito online activo | Test que comprueba el rechazo a nivel índice y a nivel validación si se intenta crear o activar un segundo depósito online activo. |
| UI de catálogo y carrito | Verificación en navegador y tests de componentes de badges y botones deshabilitados. |

---

## Verification Plan

### Automated Tests
- `php artisan test --compact --filter=OnlineAvailabilityTest`
- `php artisan test --compact --filter=CartAvailabilityTest`
- `php artisan test --compact --filter=OnlineWarehouseUniqueTest`
- `php artisan test --compact`

### CI Checks
- `vendor/bin/pint --dirty --format agent`
- `composer run types:check`
- `npm run types:generate`
- `npm run lint:check`
- `npm run format:check`
- `npm run types:check`

---

## Open Questions

1. **Umbral de últimas unidades:** Se define por defecto un umbral de $\le 5$ unidades (configurable con `ECOMMERCE_LOW_STOCK_THRESHOLD=5`). ¿Te parece adecuado o preferís otro valor (ej. 3 unidades)?
2. **Visualización de la cantidad numérica exacta en el catálogo:** Cuando un producto tiene `ultimas_unidades`, ¿preferís mostrar solo la leyenda *"Últimas unidades"* o indicar el número exacto (ej. *"¡Solo quedan 3 unidades!"*)?
