# HU-046 — Publicar el catálogo en la tienda online

El cliente final recorre el catálogo de artículos publicados por el supermercado en la tienda online (`/tienda`), viendo su imagen placeholder, descripción, marca, unidad de medida y precio vigente resuelto según el canal online o su lista particular asignada. Es la primera historia del módulo de tienda online (`Ecommerce`), desbloquea a `EPIC-13` (Carrito de compras), `HU-047` (Filtros avanzados y orden) y `HU-048` (Disponibilidad online).

**Contexto y dependencias existentes:**
- El layout público y las rutas de la tienda fueron establecidos en `EPIC-11` (rama `feature/EPIC-11-registrarse-e-iniciar-sesion-cliente-tienda-online`).
- La cascada de resolución de precios (`HU-056`) ya está implementada en `App\Actions\Pricing\ResolveArticlePrice`.
- Los atributos `is_online_publishable`, `status` y las relaciones con `category`, `brand`, `unitOfMeasure` y `priceListItems` ya existen en el modelo `Article`.

## Criterios de aceptación (de `product-backlog.md`)

1. **Datos:** por artículo, imagen (un placeholder hasta que entre `HU-032`), descripción, marca, unidad de medida y precio.
2. **Validaciones:**
   - solo se muestran artículos activos, marcados como publicables en el canal online y con precio para ese canal;
   - el precio lo resuelve `HU-056` con el canal online; si el cliente inició sesión, se aplica su lista particular.
3. **Comportamiento:**
   - el catálogo se recorre sin iniciar sesión;
   - se navega por categoría y se busca por descripción, con resultados paginados;
   - no hay calificaciones, estrellas ni reseñas de productos;
   - la interfaz está pensada para el cliente final y se valida en desktop.
4. **Verificación:** un artículo publicable con precio online aparece con ese precio; uno no publicable o sin precio no aparece; un cliente con lista particular ve su precio.

## Investigación del código existente

| Artefacto | Ubicación | Qué aporta / Propósito |
|---|---|---|
| `Article` | `app/Models/Catalog/Article.php` | Modelo principal con scopes `active()`, atributo `is_online_publishable`, relaciones `category`, `brand`, `unitOfMeasure`, `priceListItems`. |
| `ResolveArticlePrice` | `app/Actions/Pricing/ResolveArticlePrice.php` | Acción que implementa la cascada estricta de `HU-056`: lista particular del cliente -> lista de canal `Online` -> lista `General`. Lanza `ArticleNotPricedException` si no tiene precio. |
| `ResolvedPriceData` | `app/Data/Pricing/ResolvedPriceData.php` | DTO que devuelve `unit_price`, `price_list_id`, `price_list_name`, `price_list_scope`. |
| `PriceList` / `PriceListItem` | `app/Models/Pricing/` | Modelos de listas e ítems de precio. Scopes `active()`, `currentlyValid()`, `forChannel()`. |
| `Category` | `app/Models/Catalog/Category.php` | Modelo de categorías con `parent_id`, scope `active()`, relación `children()`. |
| `Customer` | `app/Models/Customers/Customer.php` | Modelo de cliente con relación `priceList` para listas particulares. |
| `User` | `app/Models/User.php` | Modelo autenticado con relación `customer` para el guard de la tienda. |
| `StoreHomeController` | `app/Http/Controllers/Ecommerce/StoreHomeController.php` | Controlador que atiende `GET /tienda` (`tienda.home`). |
| `StoreHome` | `resources/js/pages/ecommerce/index.tsx` | Página Inertia principal de la tienda online. |
| `TablePagination` | `resources/js/components/table-pagination.tsx` | Componente reutilizable de paginación de la app. |
| `CategoryData` | `app/Data/Catalog/CategoryData.php` | DTO existente para enviar categorías a la vista. |

## Análisis Profundo de la Lógica de Backend

### 1. Cascada de Precios y Filtrado a Nivel de Base de Datos
Un desafío clave es evitar que la paginación tenga huecos o totales incorrectos si un artículo publicable no tiene precio. La paginación de Laravel cuenta (`COUNT(*)`) y pagina (`LIMIT/OFFSET`) a nivel SQL.
Para garantizar que **solo se cuenten y paginen artículos que efectivamente tienen precio resuelto en la cascada**:
- `ConsultOnlineCatalog` identifica en tiempo de consulta los IDs de las listas de precios vigentes y aplicables al usuario actual:
  1. Si hay cliente logueado y tiene lista particular asignada, activa y vigente (`validityStatus() === Vigente`), se incluye su `price_list_id`.
  2. Si existe lista activa y vigente para el canal `PriceListChannel::Online`, se incluye su `id`.
  3. Si existe lista activa y vigente general `PriceListChannel::General`, se incluye su `id`.
- Se aplica el filtro Eloquent:
  ```php
  $query->whereHas('priceListItems', function (Builder $q) use ($applicableListIds) {
      $q->whereIn('price_list_id', $applicableListIds);
  });
  ```
- Si no hay ninguna lista aplicable (ej. tienda sin listas configuradas o todas vencidas), la Action retorna inmediatamente un `LengthAwarePaginator` vacío con `total = 0`, evitando consultas innecesarias.
- Esto garantiza que el `total()` del paginador y la cantidad por página sean **100% exactos** antes de hidratar los modelos.

### 2. Resolución de Precios con `ResolveArticlePrice` (`HU-056`)
- Cada artículo en la página actual (16 ítems) se mapea con `->through(...)` invocando `ResolveArticlePrice::execute($article, PriceListChannel::Online, $customer)`.
- Esto garantiza que la regla de negocio de precios **no se duplica ni se desacopla**: la tienda online usa exactamente la misma Action que utiliza la venta y el futuro carrito.
- `ResolveArticlePrice` devuelve un `ResolvedPriceData` que incluye:
  - `unit_price`: string decimal (ej. `"1250.00"`).
  - `price_list_name`: nombre de la lista de donde provino el precio.
  - `price_list_scope`: `'particular'` o `'canal'`.
- A partir de esto, se calcula `is_particular_price = ($priceData->price_list_scope === 'particular')`, lo que permite a la vista mostrar un badge distintivo "Precio preferencial" si el cliente cuenta con un precio acordado.

### 3. Navegación por Categorías y Jerarquías
- El modelo `Category` admite subcategorías mediante `parent_id`.
- Cuando el usuario navega por una categoría `category_id`:
  - Si selecciona una categoría padre (ej. "Lácteos"), se buscan también sus subcategorías directas (`where('id', $id)->orWhere('parent_id', $id)`).
  - La consulta de artículos filtra con `whereIn('articles.category_id', $categoryIds)`.
  - Se asegura además que la categoría del artículo esté activa (`whereHas('category', fn ($q) => $q->active())`).

### 4. Búsqueda por Descripción
- La búsqueda es case-insensitive:
  ```php
  $search = mb_strtolower(trim($filters['search']));
  $query->whereRaw('LOWER(articles.description) LIKE ?', ["%{$search}%"]);
  ```

### 5. Prevención de N+1 Queries
- La consulta de `Article` eager-loadea `['category', 'brand', 'unitOfMeasure']`.
- Con una página de 16 productos, la consulta realiza queries mínimas e indexadas, manteniendo tiempos de respuesta inferiores a 10-15 ms.

---

## Proposed Changes

### Backend — Action

#### `[NEW]` `app/Actions/Ecommerce/ConsultOnlineCatalog.php`
- Invokable o método `execute(array $filters = [], ?Customer $customer = null, int $perPage = 16): LengthAwarePaginator`.
- Inyecta `ResolveArticlePrice`.
- Implementa:
  - `resolveApplicablePriceListIds(?Customer $customer): array`
  - `buildQuery(array $filters, array $applicableListIds): Builder`
  - `applyFilters(Builder $query, array $filters): Builder`
  - Paginación y transformación mediante `->through(...)` retornando `OnlineCatalogArticleData`.

### Backend — Data Objects

#### `[NEW]` `app/Data/Ecommerce/OnlineCatalogArticleData.php`
- Extiende `Spatie\LaravelData\Data` para generar tipos TypeScript en `resources/js/types/generated.d.ts`.
- Propiedades:
  - `id`: `int`
  - `description`: `string`
  - `brand_name`: `?string`
  - `unit_of_measure_name`: `string`
  - `unit_of_measure_abbreviation`: `string`
  - `category_id`: `int`
  - `category_name`: `string`
  - `price`: `string` (valor crudo, ej. `"1250.00"`)
  - `formatted_price`: `string` (formateado argentino, ej. `"$ 1.250,00"`)
  - `price_list_name`: `string`
  - `price_list_scope`: `string` (`canal` | `particular`)
  - `is_particular_price`: `bool`
  - `allows_decimals`: `bool`
- Método fábrica: `fromArticleAndPrice(Article $article, ResolvedPriceData $priceData): self`.

### Backend — Request

#### `[NEW]` `app/Http/Requests/Ecommerce/ConsultOnlineCatalogRequest.php`
- Form Request para validar los parámetros de filtro de `/tienda`:
  - `search`: `['nullable', 'string', 'max:100']`
  - `category_id`: `['nullable', 'integer', 'exists:categories,id']`
  - `page`: `['nullable', 'integer', 'min:1']`
- Método `prepareForValidation`:
  - Si `category_id` viene como `'all'` o string vacío `''`, se normaliza a `null` antes de validar.

### Backend — Controller

#### `[MODIFY]` `app/Http/Controllers/Ecommerce/StoreHomeController.php`
- Método `index(ConsultOnlineCatalogRequest $request, ConsultOnlineCatalog $action): Response`:
  - Detecta si el usuario está autenticado y si tiene un cliente asociado (`$request->user()?->customer`).
  - Obtiene los filtros validados:
    - `search`: string o null.
    - `category_id`: int o null.
  - Ejecuta `ConsultOnlineCatalog::execute($filters, $customer, perPage: 16)`.
  - Obtiene categorías activas ordenadas por nombre: `Category::query()->active()->orderBy('name')->get()`.
  - Renderiza `ecommerce/index` con las props:
    - `articles`: paginador con `OnlineCatalogArticleData`.
    - `categories`: `CategoryData::collect($categories)`.
    - `filters`: `['search' => (string) ($filters['search'] ?? ''), 'category_id' => $filters['category_id'] ? (string) $filters['category_id'] : 'all']`.

### Frontend — Página

#### `[MODIFY]` `resources/js/pages/ecommerce/index.tsx`
- Tipado con `App.Data.Ecommerce.OnlineCatalogArticleData` y `App.Data.Catalog.CategoryData`.
- Banner de bienvenida con resumen de la tienda.
- Sección interactiva del catálogo:
  - Barra de búsqueda por descripción con input, botón de búsqueda y botón de reset.
  - Selector/chips de navegación por categoría con opción "Todas las categorías".
  - Contador de resultados ("Mostrando X–Y de Z productos").
  - Grilla de tarjetas (Desktop-first: 4 columnas en desktop grande, 3 en mediano, 2 en pequeño):
    - Placeholder de imagen estilizado con ícono representativo de producto/empaque (`Package` de Lucide, fondo neutro cálido agradable, borde sutil).
    - Badges superiores: categoría y badge destacado "Precio preferencial" cuando `is_particular_price` es true.
    - Marca del producto (texto sutil en mayúsculas).
    - Descripción del artículo con alineación fija (`line-clamp-2 min-h-[3rem]`).
    - Unidad de medida (ej. "Precio por kg" o "Precio por unidad").
    - Precio vigente destacado (`$ X.XXX,XX`).
    - Sin estrellas, calificaciones ni reseñas (exclusión explícita del PO).
  - Estado vacío cuando no hay coincidencias con botón para limpiar filtros.
  - Paginación integrada con `TablePagination` manteniendo el estado y parámetros en la URL vía Wayfinder `home.url()`.

### Wayfinder & Types

- Ejecutar `npm run types:generate` tras crear `OnlineCatalogArticleData`.
- Ejecutar `php artisan wayfinder:generate` para asegurar que las rutas queden sincronizadas.

### Tests

#### `[NEW]` `tests/Feature/Ecommerce/OnlineCatalogTest.php`
- Test 1: Un cliente no logueado (invitado) puede ver el catálogo en `/tienda` con artículos publicables y precio online.
- Test 2: Artículos inactivos (`status = Inactive`) NO aparecen en el catálogo online.
- Test 3: Artículos con `is_online_publishable = false` NO aparecen en el catálogo online.
- Test 4: Artículos sin precio asignado en ninguna lista aplicable (canal online ni general) NO aparecen en el catálogo online.
- Test 5: Un artículo con precio en lista `Online` se muestra con ese precio.
- Test 6: Un artículo sin precio en lista `Online`, pero con precio en lista `General`, se muestra con el precio de la lista general como fallback.
- Test 7: Cuando un cliente inicia sesión y tiene una lista particular asignada y vigente, ve su precio particular para los artículos tarifados en esa lista.
- Test 8: Un cliente logueado con lista particular ve el precio online normal para artículos no incluidos en su lista particular.
- Test 9: Si la lista particular del cliente está vencida o inactiva, se ignora silenciosamente y se aplica el canal online.
- Test 10: La búsqueda por descripción filtra los artículos correctamente ignorando mayúsculas/minúsculas.
- Test 11: El filtro por categoría muestra los artículos de esa categoría y sus subcategorías hijas.
- Test 12: Artículos en categorías inactivas no aparecen en el catálogo.
- Test 13: La paginación funciona correctamente dividiendo en páginas y conservando filtros sin incluir en el total artículos no publicables ni sin precio.

## Verificación de la Definition of Done

| Criterio | Cómo se verifica |
|---|---|
| Criterios de aceptación de `product-backlog.md` | Tests automáticos en `OnlineCatalogTest.php` cubriendo datos, validaciones y comportamiento. |
| Validación en servidor mediante Form Request | `ConsultOnlineCatalogRequest` valida los query params. |
| Reglas de negocio en `app/Actions/Ecommerce` | `ConsultOnlineCatalog` encapsula la lógica de consulta y resolución de precios. |
| Respuestas tipadas en `app/Data/Ecommerce` | `OnlineCatalogArticleData` tipado con TypeScript Transformers. |
| Tests de Pest en verde | `php artisan test --compact --filter=OnlineCatalogTest`. |
| PHPStan sin errores | `composer run types:check`. |
| Pint, ESLint, Prettier, tsc en verde | `vendor/bin/pint --dirty --format agent`, `npm run lint:check`, `npm run format:check`, `npm run types:check`. |
| Sin estrellas ni calificaciones | Interfaz limpia sin sistema de reviews/estrellas según pedido de PO. |

## Verification Plan

### Automated Tests
- Ejecutar la suite de tests de la tienda:
  `php artisan test --compact --filter=OnlineCatalogTest`
- Ejecutar la suite completa para confirmar que no hay regresiones:
  `php artisan test --compact`

### Manual Verification
1. Ingresar a `http://localhost:8000/tienda` como usuario invitado.
2. Verificar la grilla con artículos que tienen precio online, placeholder de imagen, descripción, marca, unidad y precio.
3. Probar la búsqueda por descripción y navegación por categoría.
4. Iniciar sesión con un cliente con lista particular asignada y verificar que el precio cambia al preferencial.
5. Comprobar que artículos inactivos o sin precio no se muestran.

### CI checks
- `vendor/bin/pint --dirty --format agent`
- `composer run types:check`
- `npm run types:generate`
- `npm run types:check`
- `npm run lint:check`
- `npm run format:check`
