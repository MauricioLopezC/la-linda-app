# HU-047 — Buscar, filtrar y ordenar artículos en la tienda online

Permite al cliente final en la tienda online (`/tienda`) explorar el catálogo con herramientas avanzadas de descubrimiento: filtrado por marca activa con artículos publicados, filtrado por rango de precios (mínimo y máximo) calculado sobre el precio efectivo de la cascada de `HU-056`, ordenamiento por relevancia, precio y nombre alfabético, visualización de etiquetas (*chips*) activas removibles y persistencia completa de parámetros en URL y paginación.

Extiende la base entregada por `HU-046` y se apoya en la resolución de precios de `App\Actions\Pricing\ResolveArticlePrice`.

---

## Criterios de aceptación (de `product-backlog.md`)

1. **Datos:** criterios de filtrado del catálogo en `/tienda` (búsqueda por texto `search`, categoría o subcategoría `category_id`, marca `brand_id`, rango de precios `min_price` y `max_price`) y criterio de ordenamiento (`sort`: `relevance` por defecto, `price_asc` de menor a mayor precio, `price_desc` de mayor a menor precio, `name_asc` alfabético A–Z, `name_desc` alfabético Z–A), junto con el listado de marcas activas que tienen artículos publicados en el canal online.
2. **Validaciones:**
   - `min_price` y `max_price`, cuando se informan, deben ser numéricos mayores o iguales a cero; si se informan ambos, `max_price >= min_price`.
   - `brand_id` y `category_id` deben corresponder a registros activos existentes.
   - el filtrado por rango de precios y el ordenamiento por menor/mayor precio se aplican sobre el **precio efectivo resuelto para el visitante actual** según la cascada de `HU-056` (lista particular del cliente si inició sesión y tiene una asignada, o lista del canal online / general).
3. **Comportamiento:**
   - los filtros de búsqueda, categoría/subcategoría, marca y rango de precios son combinables entre sí y con el selector de ordenamiento.
   - los filtros activos y el criterio de ordenamiento se mantienen sincronizados en los query params de la URL (Inertia `router.get` con `preserveState`) y se conservan al cambiar de página en la paginación.
   - la interfaz muestra los filtros activos como etiquetas (*chips*) removibles individualmente, el contador total de artículos encontrados y una acción `"Limpiar filtros"` para restablecer el catálogo completo.
4. **Verificación:** en `/tienda` se filtra por una categoría y una marca, se define un rango de precio mínimo y máximo y se ordena por `"Menor precio"`; se comprueba que solo aparecen los artículos que cumplen todas las condiciones ordenados por su precio vigente de menor a mayor, y que al limpiar los filtros vuelve el listado original.

---

## Investigación del código existente

| Artefacto | Ubicación | Propósito / Qué aporta |
|---|---|---|
| `ConsultOnlineCatalog` | `app/Actions/Ecommerce/ConsultOnlineCatalog.php` | Acción que construye la consulta base para el catálogo online con `whereHas('priceListItems')`, `whereHas('category')` y resuelve precios por item con `ResolveArticlePrice`. |
| `ConsultOnlineCatalogRequest` | `app/Http/Requests/Ecommerce/ConsultOnlineCatalogRequest.php` | Form Request que valida los parámetros de entrada de `/tienda` (`search`, `category_id`, `page`). |
| `StoreHomeController` | `app/Http/Controllers/Ecommerce/StoreHomeController.php` | Controlador que recibe el request, ejecuta la acción y renderiza `ecommerce/index` con Inertia. |
| `ResolveArticlePrice` | `app/Actions/Pricing/ResolveArticlePrice.php` | Resuelve el precio unitario aplicando la cascada estricta `HU-056`: lista particular del cliente -> lista canal online -> lista general. |
| `PriceList` / `PriceListItem` | `app/Models/Pricing/` | Modelos de listas de precio. Contienen scopes `active()`, `currentlyValid()`, `forChannel()`. |
| `Brand` / `BrandData` | `app/Models/Catalog/Brand.php` / `app/Data/Catalog/BrandData.php` | Modelo y Data object de marcas con atributo `is_active` y normalización de unicidad. |
| `Category` / `CategoryData` | `app/Models/Catalog/Category.php` / `app/Data/Catalog/CategoryData.php` | Modelo y Data object de categorías con jerarquía `parent_id`. |
| `StoreHome` | `resources/js/pages/ecommerce/index.tsx` | Página principal de la tienda online con grilla de artículos, banners y preview modal. |
| `TablePagination` | `resources/js/components/table-pagination.tsx` | Componente de paginación reutilizable que emite `onPageChange`. |
| Primitivas shadcn/ui | `resources/js/components/ui/` | `Select` (`select.tsx`), `Badge` (`badge.tsx`), `Input` (`input.tsx`), `Button` (`button.tsx`). |

*No hay discrepancias con el esquema de base de datos ni con el backlog del Sprint 5. Todas las tablas y relaciones necesarias ya están presentes en `master`.*

---

## Proposed Changes

### Backend — Form Request

#### `[MODIFY]` `app/Http/Requests/Ecommerce/ConsultOnlineCatalogRequest.php`
- Ampliar las reglas de validación:
  - `search`: `['nullable', 'string', 'max:100']`.
  - `category_id`: `['nullable', 'integer', Rule::exists('categories', 'id')->where('is_active', true)]`.
  - `brand_id`: `['nullable', 'integer', Rule::exists('brands', 'id')->where('is_active', true)]`.
  - `min_price`: `['nullable', 'numeric', 'min:0']`.
  - `max_price`: `['nullable', 'numeric', 'min:0', Rule::when($this->filled('min_price'), ['gte:min_price'])]`.
  - `sort`: `['nullable', 'string', Rule::in(['relevance', 'price_asc', 'price_desc', 'name_asc', 'name_desc'])]`.
  - `page`: `['nullable', 'integer', 'min:1']`.
- Ampliar `prepareForValidation()`:
  - Convertir `'all'` o string vacío en `null` para `category_id` y `brand_id`.
  - Convertir string vacío en `null` para `min_price` y `max_price`.
  - Asignar por defecto `'relevance'` si `sort` viene vacío o nulo.
- Agregar mensajes de error en español descriptivos para `min_price`, `max_price`, `brand_id` y `sort`.

---

### Backend — Action

#### `[MODIFY]` `app/Actions/Ecommerce/ConsultOnlineCatalog.php`
- Hacer público `resolveApplicablePriceListIds(?Customer $customer): array` o incorporar un método `getAvailableBrands(array $applicableListIds): Collection` para consultar las marcas que tienen artículos activos, marcados como publicables online, con categoría activa y con precio vigente en la cascada.
- Agregar método `getEffectivePriceSubquery(array $orderedListIds): Builder` que genera una subconsulta correlacionada SQL sobre `price_list_items`:
  ```sql
  SELECT price_list_items.price
  FROM price_list_items
  WHERE price_list_items.article_id = articles.id
    AND price_list_items.price_list_id IN (...)
  ORDER BY CASE price_list_items.price_list_id
    WHEN ? THEN 1
    WHEN ? THEN 2
    ...
    ELSE 99
  END ASC
  LIMIT 1
  ```
  Esto garantiza que tanto SQLite como PostgreSQL evalúen exactamente el mismo precio que `ResolveArticlePrice` sin inconsistencias entre filtros, paginación y visualización.
- Ampliar `buildQuery(array $filters, array $applicableListIds): Builder`:
  - Recibe `$filters` con las nuevas claves: `brand_id`, `min_price`, `max_price`, `sort`.
  - Invoca `applyFilters()` y `applySorting()`.
- En `applyFilters()`:
  - Mantener filtros existentes de `search` y `category_id`.
  - Si `brand_id` está presente: `$query->where('articles.brand_id', (int) $filters['brand_id'])`.
  - Si `min_price` está presente: `$query->where($this->getEffectivePriceSubquery($applicableListIds), '>=', (float) $filters['min_price'])`.
  - Si `max_price` está presente: `$query->where($this->getEffectivePriceSubquery($applicableListIds), '<=', (float) $filters['max_price'])`.
- En `applySorting()`:
  - `'relevance'` (por defecto): `->orderBy('articles.description')->orderBy('articles.id')`.
  - `'price_asc'`: `->orderBy($this->getEffectivePriceSubquery($applicableListIds), 'asc')->orderBy('articles.description')->orderBy('articles.id')`.
  - `'price_desc'`: `->orderBy($this->getEffectivePriceSubquery($applicableListIds), 'desc')->orderBy('articles.description')->orderBy('articles.id')`.
  - `'name_asc'`: `->orderBy('articles.description', 'asc')->orderBy('articles.id')`.
  - `'name_desc'`: `->orderBy('articles.description', 'desc')->orderBy('articles.id')`.

---

### Backend — Controller

#### `[MODIFY]` `app/Http/Controllers/Ecommerce/StoreHomeController.php`
- Extraer del request validado todos los filtros:
  ```php
  $filters = [
      'search' => $request->query('search'),
      'category_id' => $request->filled('category_id') ? (int) $request->query('category_id') : null,
      'brand_id' => $request->filled('brand_id') ? (int) $request->query('brand_id') : null,
      'min_price' => $request->filled('min_price') ? $request->query('min_price') : null,
      'max_price' => $request->filled('max_price') ? $request->query('max_price') : null,
      'sort' => (string) ($request->query('sort') ?: 'relevance'),
  ];
  ```
- Obtener `$applicableListIds = $action->resolveApplicablePriceListIds($customer)`.
- Obtener `$brands = $action->getAvailableBrands($applicableListIds)`.
- Si `$articles->isEmpty()` y cualquier filtro está activo (`search`, `category_id`, `brand_id`, `min_price`, `max_price`), obtener `$suggestedArticles` ejecutando `$action->execute([], $customer, perPage: 8)->items()`.
- Pasar a `Inertia::render('ecommerce/index')`:
  - `'articles' => $articles`
  - `'suggestedArticles' => $suggestedArticles`
  - `'categories' => CategoryData::collect($categories)`
  - `'brands' => BrandData::collect($brands)`
  - `'filters' => [...]` (valores formateados como string para el frontend).

---

### Frontend — Componentes y Páginas

#### `[NEW]` `resources/js/pages/ecommerce/components/catalog-filters.tsx`
- Componente especializado para aislar la lógica de filtros y evitar conflictos de merge con `HU-048`.
- Elementos:
  1. **Buscador:** Input con icono, botón de limpiar búsqueda rápida y botón "Buscar".
  2. **Selectores de filtro en barra flexible/grilla:**
     - Selector de Categorías (manteniendo soporte a todas o por categoría).
     - Selector de Marca (dropdown shadcn `Select` con opción "Todas las marcas" + listado de marcas activas disponibles).
     - Rango de Precios: inputs numéricos con prefijo `$` para Mínimo y Máximo, con botón de aplicar rango o submit con Enter.
     - Selector de Ordenamiento: dropdown shadcn `Select` con las 5 opciones:
       - `relevance`: "Más relevantes"
       - `price_asc`: "Menor precio"
       - `price_desc`: "Mayor precio"
       - `name_asc`: "Nombre (A - Z)"
       - `name_desc`: "Nombre (Z - A)"
  3. **Barra de Chips Activos (*removibles individualmente*):**
     - Chip de Búsqueda: `"Búsqueda: ${search}"` + botón `X`.
     - Chip de Categoría: `"Categoría: ${categoryName}"` + botón `X`.
     - Chip de Marca: `"Marca: ${brandName}"` + botón `X`.
     - Chip de Precio: `"Precio: $${min} - $${max}"` (o `"Precio mín: $${min}"` / `"Precio máx: $${max}"`) + botón `X`.
     - Botón `"Limpiar filtros"` que restablece el catálogo completo.
  4. **Contador de Artículos:**
     - Indicador claro: `"X artículos disponibles"` o `"X artículos encontrados"` junto a badge de `"Resultados filtrados"`.

#### `[MODIFY]` `resources/js/pages/ecommerce/index.tsx`
- Importar y montar `<CatalogFilters />`.
- Actualizar `Props` para recibir `brands: App.Data.Catalog.BrandData[]` y las nuevas propiedades en `filters`.
- Función centralizada `applyFilters(newFilters)` que construye los query params y llama a `router.get(home.url({ query: cleanQuery }), {}, { preserveState: true, preserveScroll: true })`.
- Mantener todos los query params activos en `changePage(page)` al paginar.

---

### Tests

#### `[MODIFY]` `tests/Feature/Ecommerce/OnlineCatalogTest.php`
- Incorporar nuevos tests específicos para HU-047:
  1. `catalog can be filtered by active brand`: solo devuelve artículos de la marca seleccionada.
  2. `inactive brands or brands without online published articles are not included in available brands list`: lista de marcas para el frontend solo incluye marcas activas con artículos válidos.
  3. `catalog can be filtered by price range for guest visitor`: aplica sobre el precio resuelto del canal online / general.
  4. `catalog can be filtered by price range for logged in customer with particular price list`: comprueba que el filtro toma el precio preferencial acordado (`HU-056`).
  5. `catalog can be ordered by price ascending and descending`: verifica el orden de menor a mayor y de mayor a menor precio efectivo.
  6. `catalog can be ordered by name alphabetically A-Z and Z-A`.
  7. `all filters and sorting can be combined simultaneously`: búsqueda por texto + categoría + marca + rango de precio + ordenamiento por menor precio.
  8. `catalog filters validate min_price and max_price correctly`: rechaza negativos y `max_price < min_price`.
  9. `catalog filters validate brand_id exists and is active`: rechaza marcas inactivas o inexistentes.
  10. `pagination preserves all active filter and sort query parameters`.

---

## Verificación de la Definition of Done

| Criterio | Cómo se verifica |
|---|---|
| Filtro por búsqueda, categoría, marca y precio combinables | Test de feature combinando los 5 parámetros y verificando que el query resultante devuelva la intersección exacta. |
| Ordenamiento por precio y nombre (asc y desc) | Tests unitarios y de feature comprobando el orden exacto de los artículos en la colección paginada. |
| Precio efectivo de HU-056 para rangos y orden | Tests con artículos con distintos precios en canal general, canal online y lista particular del cliente. |
| Marcas activas con artículos publicados | Test que verifica que una marca inactiva o sin artículos en la tienda online no aparezca en `$page->has('brands')`. |
| Chips activos removibles y "Limpiar filtros" | Verificación visual y pruebas de navegación en frontend asegurando que cada chip remueve su parámetro y "Limpiar filtros" reasigna `/tienda`. |
| Parámetros conservados en paginación | Test de feature navegando a `page=2` con filtros y verificando la persistencia de los query strings. |
| Calidad de código y linters | `vendor/bin/pint --dirty --format agent`, `composer run types:check`, `npm run lint:check`, `npm run format:check`, `npm run types:check`, y suite Pest. |

---

## Verification Plan

### Automated Tests
- Ejecutar la suite completa de catálogo online:
  ```bash
  php artisan test --compact tests/Feature/Ecommerce/OnlineCatalogTest.php
  ```

### Manual Verification
1. Ingresar como visitante a `/tienda`.
2. Seleccionar una marca (ej. "Arcor") y una categoría (ej. "Almacén").
3. Ingresar un rango de precios (ej. Mínimo 500, Máximo 2000) y ordenar por "Menor precio".
4. Verificar que aparezcan los chips activos correspondientes y el contador de artículos.
5. Hacer click en la "X" del chip de marca y comprobar que se mantiene el filtro de precio y categoría.
6. Hacer click en "Limpiar filtros" y comprobar que regresa el catálogo general completo.
7. Cambiar de página con filtros aplicados y comprobar que la URL preserva todos los parámetros.

### CI checks
- PHP Pint: `vendor/bin/pint --dirty --format agent`
- PHPStan: `composer run types:check`
- ESLint: `npm run lint:check`
- Prettier: `npm run format:check`
- TypeScript: `npm run types:check`
- Pest: `php artisan test --compact`

---

## Open Questions

*No hay preguntas abiertas. La historia se encuentra completamente detallada en los criterios de aceptación de `product-backlog.md` y alineada al sprint backlog 5.*
