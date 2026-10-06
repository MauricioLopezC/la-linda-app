# EPIC-13 — Gestionar el carrito de compras

Permite a un cliente autenticado de la tienda online (`/tienda`) agregar artículos a un carrito de compras que se conserva en base de datos entre visitas y sesiones, ver precios vigentes calculados dinámicamente según la cascada de precios, ajustar cantidades o remover artículos, y visualizar subtotales y total general. Los artículos que pierdan precio vigente o sean dados de baja se marcan como no disponibles y se excluyen del total.

**Contexto y dependencias existentes:**
- **Layout y autenticación:** La cuenta de cliente y sesión de tienda online fueron construidas en `EPIC-11` (guard `web`, rol `cliente`, relación `User->customer`).
- **Catálogo de tienda online:** La visualización pública del catálogo en `/tienda` fue construida en `HU-046` (`ConsultOnlineCatalog`, `resources/js/pages/ecommerce/index.tsx`).
- **Esquema de base de datos:** La tabla `cart_items` y el modelo `App\Models\Ecommerce\CartItem` ya fueron creados y migrados en el pull request de esquema de Sprint 4 (`database/migrations/2026_09_28_100014_create_cart_items_table.php`).
- **Motor de precios:** `App\Actions\Pricing\ResolveArticlePrice` (`HU-056`) ya implementa la cascada de precios oficial para canal `Online` y lista particular de clientes.

---

## Criterios de aceptación (de `product-backlog.md`)

1. **Datos:** artículo, cantidad, precio vigente, subtotal por línea y total.
2. **Validaciones:**
   - solo se agregan artículos publicables con precio online;
   - la cantidad es mayor a cero, y entera si la unidad de medida no admite decimales;
   - agregar al carrito requiere sesión iniciada; sin sesión se pide iniciarla y se vuelve al artículo.
3. **Comportamiento:**
   - agregar un artículo que ya está en el carrito suma la cantidad;
   - el carrito se conserva entre visitas;
   - el precio mostrado es el vigente en el momento; se fija recién al confirmar el pedido (`HU-062`);
   - un artículo que dejó de publicarse o quedó sin precio se marca como no disponible y no suma al total;
   - el encabezado de la tienda muestra la cantidad de artículos del carrito.
4. **Verificación:** se agregan tres artículos, se cierra sesión y al volver el carrito sigue igual; se cambia un precio en la lista online y el carrito muestra el precio nuevo.

---

## Investigación del código existente

| Artefacto | Ubicación | Propósito |
|---|---|---|
| `CartItem` | `app/Models/Ecommerce/CartItem.php` | Modelo Eloquent con `customer_id`, `article_id`, `quantity` (cast decimal:3), relaciones `customer` y `article`. |
| `cart_items` (migración) | `database/migrations/2026_09_28_100014_create_cart_items_table.php` | Migración existente con `UNIQUE(customer_id, article_id)` y check `quantity > 0`. |
| `Customer` | `app/Models/Customers/Customer.php` | Modelo de cliente con relación `cartItems(): HasMany`. |
| `User` | `app/Models/User.php` | Modelo de usuario con métodos `isClient(): bool`, `customer(): BelongsTo`. |
| `Article` | `app/Models/Catalog/Article.php` | Modelo con `is_online_publishable`, `status`, relación `unitOfMeasure`. |
| `UnitOfMeasure` | `app/Models/Catalog/UnitOfMeasure.php` | Modelo con atributo booleano `allows_decimal_quantity`. |
| `ResolveArticlePrice` | `app/Actions/Pricing/ResolveArticlePrice.php` | Resuelve el precio unitario vigente aplicando la cascada (particular -> canal Online -> general). Lanza `ArticleNotPricedException` si no tiene precio. |
| `StoreHomeController` | `app/Http/Controllers/Ecommerce/StoreHomeController.php` | Controlador de `/tienda`. |
| `StoreHome` | `resources/js/pages/ecommerce/index.tsx` | Catálogo frontend con botones y controles de cantidad que actualmente disparan un toast simulado. |
| `HandleInertiaRequests` | `app/Http/Middleware/HandleInertiaRequests.php` | Middleware que comparte props globales (`auth`, `flash`, `cashSession`). |
| `AppSidebarHeader` | `resources/js/components/app-sidebar-header.tsx` | Header superior de la aplicación donde se mostrará el badge del carrito. |
| `AppSidebar` | `resources/js/components/app-sidebar.tsx` | Barra lateral con grupos de navegación (`clientNavGroups` para clientes). |

---

## Arquitectura y Decisiones de Diseño

### 1. Descomposición por Partes (Slicing de 8 SP)
Dado el tamaño de la historia (8 SP), se descompone en 4 partes funcionales e incrementales:

* **Parte 1 — Backend Core (Actions, DTOs y Reglas de Dominio):**
  Lógica de negocio pura para agregar (con acumulación), editar cantidad, remover, vaciar y consultar el carrito con resolución de precios en tiempo real y detección de artículos no disponibles. Validaciones de cantidad (positiva y enteros vs pesables).
* **Parte 2 — Rutas, Controlador y Contador Global en Header:**
  Endpoints REST (`tienda.cart.*`), FormRequests de validación, exposición de `cartCount` en `HandleInertiaRequests`, integración del badge del carrito en el header/sidebar y generación de types/Wayfinder.
* **Parte 3 — Integración con el Catálogo (`/tienda`):**
  Conexión de los botones "Añadir al carrito" en la grilla y en el modal de detalle rápido de `/tienda` con feedback instantáneo y redirección fluida a login cuando no hay sesión.
* **Parte 4 — Pantalla del Carrito (`/tienda/carrito`) y Artículos No Disponibles:**
  Página Inertia con tabla interactiva de productos, selectores de cantidad en vivo, eliminación de líneas, alertas visuales para artículos no disponibles excluidos del total, card de resumen y estado vacío.

### 2. Precios Dinámicos y Detección de Artículos No Disponibles
La tabla `cart_items` **no almacena precios**; el precio se fija recién al confirmar el pedido en `HU-062`.
En cada consulta (`GetCustomerCart`), para cada `CartItem`:
1. Se verifica si el artículo sigue activo (`status === ArticleStatus::Active`) y publicable online (`is_online_publishable === true`).
2. Se intenta resolver el precio vigente mediante `ResolveArticlePrice::execute($article, PriceListChannel::Online, $customer)`.
3. Si cualquiera de estos pasos falla:
   - Se marca `is_available = false` y se registra el motivo (`unavailable_reason`).
   - El subtotal de esa línea es `0.00` y **no se suma al total del carrito**.
   - En la interfaz se muestra un badge distintivo "No disponible" con opción de quitarlo del carrito.

### 3. Validación de Cantidades y Decimales
- La cantidad debe ser estrictamente `> 0`.
- Si `UnitOfMeasure->allows_decimal_quantity === false`, la cantidad debe ser un entero exacto (ej. 1, 2, 3). Si se envía un decimal (ej. 1.5 en una unidad por bulto/unidad), la petición se rechaza con error de validación.
- Si `allows_decimal_quantity === true`, se aceptan decimales de hasta 3 dígitos (ej. 0.750 kg).

---

## Proposed Changes

### PARTE 1: Backend Core (Actions, Data Objects y Tests de Dominio)

#### `[NEW]` `app/Actions/Ecommerce/AddArticleToCart.php`
- Inyecta `ResolveArticlePrice`.
- Firma: `execute(Customer $customer, Article $article, float|string $quantity): CartItem`.
- Valida:
  - Artículo activo y publicable online (`$article->status === ArticleStatus::Active && $article->is_online_publishable`).
  - Artículo con precio vigente (prueba `ResolveArticlePrice`; si lanza `ArticleNotPricedException`, lanza `ValidationException`).
  - Cantidad `> 0`.
  - Si la unidad no admite decimales, la cantidad debe ser entera.
- Comportamiento:
  - Si ya existe `CartItem` para ese `customer_id` y `article_id`, suma la cantidad existente: `$item->quantity = bcadd((string)$item->quantity, (string)$quantity, 3)`.
  - Si no existe, crea un nuevo registro.
- Retorna el `CartItem` guardado.

#### `[NEW]` `app/Actions/Ecommerce/UpdateCartItemQuantity.php`
- Firma: `execute(Customer $customer, CartItem $cartItem, float|string $quantity): CartItem`.
- Valida pertenencia del ítem al cliente (`$cartItem->customer_id === $customer->id`).
- Valida cantidad `> 0` y regla de decimales según la UOM del artículo.
- Actualiza y guarda `$cartItem->quantity`.

#### `[NEW]` `app/Actions/Ecommerce/RemoveCartItem.php`
- Firma: `execute(Customer $customer, CartItem $cartItem): void`.
- Valida pertenencia y elimina el registro `$cartItem->delete()`.

#### `[NEW]` `app/Actions/Ecommerce/ClearCart.php`
- Firma: `execute(Customer $customer): void`.
- Elimina todos los ítems del cliente: `$customer->cartItems()->delete()`.

#### `[NEW]` `app/Actions/Ecommerce/GetCustomerCart.php`
- Inyecta `ResolveArticlePrice`.
- Firma: `execute(Customer $customer): CartData`.
- Carga ítems con relaciones: `$customer->cartItems()->with(['article.unitOfMeasure', 'article.category', 'article.brand'])->get()`.
- Para cada ítem:
  - Intenta resolver el precio con `ResolveArticlePrice`.
  - Evalúa `is_available` (activo, publicable y con precio).
  - Calcula subtotal si está disponible (`bcmul` redondeado a 2 decimales).
  - Mapea a `CartItemData`.
- Calcula total general sumando únicamente los subtotales de ítems disponibles.
- Retorna `CartData`.

#### `[NEW]` `app/Data/Ecommerce/CartItemData.php`
- Extiende `Spatie\LaravelData\Data`.
- Atributos:
  - `int $id` (id del CartItem)
  - `int $article_id`
  - `string $article_description`
  - `string $article_internal_code`
  - `string|null $article_barcode`
  - `string|null $article_image_url`
  - `string $unit_of_measure_name`
  - `string $unit_of_measure_abbreviation`
  - `bool $allows_decimals`
  - `string $quantity`
  - `string|null $unit_price`
  - `string $subtotal`
  - `bool $is_available`
  - `string|null $unavailable_reason`

#### `[NEW]` `app/Data/Ecommerce/CartData.php`
- Extiende `Spatie\LaravelData\Data`.
- Atributos:
  - `array<CartItemData> $items`
  - `string $total`
  - `int $lines_count`
  - `string $total_quantity`
  - `bool $has_unavailable_items`

---

### PARTE 2: Rutas, Controlador y Contador en Header

#### `[NEW]` `app/Http/Requests/Ecommerce/AddToCartRequest.php`
- Valida:
  - `article_id`: `['required', 'integer', 'exists:articles,id']`
  - `quantity`: `['required', 'numeric', 'gt:0']`

#### `[NEW]` `app/Http/Requests/Ecommerce/UpdateCartItemQuantityRequest.php`
- Valida:
  - `quantity`: `['required', 'numeric', 'gt:0']`

#### `[NEW]` `app/Http/Controllers/Ecommerce/CartController.php`
- Métodos protegidos por middleware `auth`:
  - `index(GetCustomerCart $action)`: renderiza `ecommerce/cart/index` con `'cart' => $action->execute($customer)`.
  - `store(AddToCartRequest $request, AddArticleToCart $action)`: ejecuta y redirige con mensaje flash.
  - `update(CartItem $cartItem, UpdateCartItemQuantityRequest $request, UpdateCartItemQuantity $action)`: actualiza y redirige `back()`.
  - `destroy(CartItem $cartItem, RemoveCartItem $action)`: elimina y redirige `back()`.
  - `clear(ClearCart $action)`: vacía el carrito y redirige `back()`.

#### `[MODIFY]` `routes/web.php`
- En el grupo `Route::prefix('tienda')->name('tienda.')->group(...)`:
  - Registrar dentro del grupo `Route::middleware('auth')`:
    ```php
    Route::prefix('carrito')->name('cart.')->group(function () {
        Route::get('/', [CartController::class, 'index'])->name('index');
        Route::post('/', [CartController::class, 'store'])->name('store');
        Route::patch('{cart_item}', [CartController::class, 'update'])->name('update');
        Route::delete('{cart_item}', [CartController::class, 'destroy'])->name('destroy');
        Route::delete('/', [CartController::class, 'clear'])->name('clear');
    });
    ```

#### `[MODIFY]` `app/Http/Middleware/HandleInertiaRequests.php`
- Agregar a `share(Request $request)`:
  ```php
  'cartCount' => function () use ($request): int {
      if (! $request->user()?->isClient()) {
          return 0;
      }
      return (int) $request->user()->customer?->cartItems()->count();
  },
  ```

#### `[MODIFY]` `resources/js/types/index.d.ts`
- Agregar `cartCount?: number;` a `SharedData`.

#### `[MODIFY]` `resources/js/components/app-sidebar-header.tsx`
- Para usuarios con rol cliente o navegando la tienda, renderizar un botón con icono `ShoppingCart` y badge con `cartCount` hacia `/tienda/carrito`.

#### `[MODIFY]` `resources/js/components/app-sidebar.tsx`
- En `clientNavGroups`, incluir acceso a `Carrito` con icono `ShoppingCart` y badge si `cartCount > 0`.

---

### PARTE 3: Integración del Catálogo (`/tienda`)

#### `[MODIFY]` `resources/js/pages/ecommerce/index.tsx`
- Conectar `handleAddToCart`:
  - Si el usuario no está logueado: mensaje toast informativo y redirección hacia `/login`.
  - Si está logueado: ejecutar `router.post(store.url(), { article_id, quantity }, { preserveScroll: true, onSuccess: ... })`.
  - Mostrar feedback visual inmediato con `toast.success`.

---

### PARTE 4: Pantalla del Carrito (`/tienda/carrito`)

#### `[NEW]` `resources/js/pages/ecommerce/cart/index.tsx`
- Página Inertia de carrito de compras:
  - Breadcrumbs: `Tienda Online` -> `Carrito de compras`.
  - Estado vacío: cuando `cart.items.length === 0`, icono de carrito vacío, mensaje y botón para ir a `/tienda`.
  - Listado de productos:
    - Tarjeta/Fila por ítem con imagen, descripción, código, UOM.
    - Controles de cantidad: botones `+` y `-` y campo numérico (con debounce o disparo de `router.patch` al modificar).
    - Subtotal por línea.
    - Botón de eliminar con icono `Trash2`.
    - Alerta y badge en artículos con `!is_available`: badge rojo "No disponible", explicación del motivo, subtotal tachado/en cero y botón directo para remover.
  - Card lateral de resumen:
    - Subtotal acumulado de artículos disponibles.
    - Total final.
    - Botón para vaciar carrito (con diálogo de confirmación).
    - Botón "Continuar compra" (preparado para `HU-062`).

---

## Verificación de la Definition of Done

| Criterio | Cómo se verifica |
|---|---|
| Solo artículos publicables con precio online | Test automatizado intenta agregar artículo inactivo o sin precio y recibe error de validación. |
| Cantidad mayor a cero y validación de enteros | Test intenta agregar cantidad 0, negativa o decimal en artículo no pesable; rechaza con validación. |
| Sesión obligatoria | Petición sin autenticación es redirigida a `/login`. |
| Suma acumulativa de cantidades | Test agrega 2 unidades de un artículo y luego 3 del mismo; verifica que existe un solo registro con cantidad 5. |
| Precios vigentes dinámicos | Test agrega un artículo con precio $100; se modifica el precio en la lista a $150; al consultar el carrito el precio es $150. |
| Artículo no disponible se excluye del total | Test agrega 2 artículos; se desactiva uno o se le quita precio; el total del carrito solo suma el artículo activo. |
| Contador en el encabezado | Verificación en middleware y componente frontend del badge con la cantidad de artículos. |

---

## Verification Plan

### Automated Tests
1. `tests/Feature/Ecommerce/Cart/AddToCartTest.php`:
   - Agrega artículo con éxito.
   - Suma cantidad si ya existía.
   - Rechaza sin sesión (redirección a login).
   - Rechaza artículo inactivo o no publicable.
   - Rechaza artículo sin precio en canal online.
   - Valida decimales solo en pesables; rechaza decimales en discretos.
2. `tests/Feature/Ecommerce/Cart/UpdateCartItemTest.php`:
   - Actualiza cantidad con éxito.
   - Rechaza modificar ítem de otro cliente (403/404).
   - Valida cantidad > 0 y enteros en discretos.
3. `tests/Feature/Ecommerce/Cart/RemoveCartItemTest.php`:
   - Elimina ítem del carrito.
   - Vacía carrito por completo (`clear`).
4. `tests/Feature/Ecommerce/Cart/ConsultCartTest.php`:
   - Resuelve precios en tiempo real.
   - Refleja cambio de precio de lista sin tocar la tabla `cart_items`.
   - Marca artículo no disponible si pierde precio o se desactiva.
   - Excluye no disponible del cálculo del total.
   - Prop `cartCount` se comparte correctamente en Inertia.

### Manual Verification
- Iniciar sesión como cliente, entrar a `/tienda`.
- Agregar un producto con cantidad 2.
- Agregar el mismo producto con cantidad 1 -> verificar que el contador muestre 1 línea con cantidad 3.
- Ir a `/tienda/carrito`, verificar precios, subtotales y total.
- Cambiar la cantidad a 5 desde la pantalla del carrito -> verificar recálculo automático.
- Cerrar sesión y volver a iniciar -> verificar que el carrito sigue intacto.

### CI checks
- `vendor/bin/pint --dirty --format agent`
- `composer run types:check`
- `npm run lint:check`
- `npm run format:check`
- `npm run types:check`
- `php artisan test --compact --filter=Cart`

---

## Open Questions
*Ninguna.* Los requerimientos del backlog, las reglas de negocio y el esquema de base de datos están completamente especificados y alineados con las convenciones del proyecto.
