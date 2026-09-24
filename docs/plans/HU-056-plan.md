# HU-056 — Resolver el precio de venta según el cliente y el canal

Esta historia construye el `Action` central de resolución de precios (`ResolveArticlePrice`) que
aplica la cascada definida en el backlog: lista particular del cliente → lista de canal → lista
general. No tiene pantalla propia: es un servicio interno que invocarán tanto la venta de mostrador
(Sprint 4) como el e-commerce.

HU-022 (`feature/HU-022-asignar-lista-precios-cliente`) ya implementa la columna `price_list_id`
en `customers` y la relación `Customer::priceList()`, pero aún no está mergeada a `master`. Este
plan trabaja sobre esa estructura asumiendo que existirá al momento de ejecutar la HU (el usuario
confirmó que ya la implementó).

## Criterios de aceptación (de `product-backlog.md`)

1. **Datos:** para cada línea de venta se recibe artículo, cliente (o Consumidor Final) y canal
   (`mostrador` u `online`); el resultado es el precio unitario a cobrar junto con la lista de
   precios de origen.
2. **Validaciones:**
   - La resolución sigue una precedencia estricta: 1) lista `particular` asignada al cliente,
     si está activa y vigente; 2) lista de canal vigente para el canal de la operación (`mostrador`
     u `online`); 3) lista de canal `general` vigente.
   - Solo se consideran listas activas y vigentes a la fecha de la operación; una lista futura o
     vencida se descarta como si no existiera.
   - Si el artículo no tiene precio en ninguna lista aplicable según la cascada, la operación se
     rechaza con un error explícito; nunca se cobra a precio cero o estimado.
3. **Comportamiento:**
   - La resolución vive en un único Action interno (`ResolveArticlePrice`) invocado tanto desde la
     venta de mostrador como desde el circuito de e-commerce.
   - El resultado deja registrada la lista de origen del precio aplicado, para que la línea de
     venta quede trazable.
   - Un cliente sin lista particular asignada usa la lista vigente de su canal, y solo cae a la
     lista general si el canal no tiene una lista propia vigente.
4. **Verificación:** se simula una venta con un cliente con lista propia, otra de mostrador con un
   cliente sin lista propia, y otra por canal online sin lista propia, y se comprueba que cada una
   toma el precio de la lista que corresponde según la cascada.

## Investigación del código existente

| Artefacto | Ubicación | Propósito |
|---|---|---|
| `PriceList` | `app/Models/Pricing/PriceList.php` | Modelo principal. Ya tiene `scopeActive()`, `scopeCurrentlyValid()`, `scopeForChannel()`. La combinación de ambos scopes da las listas activas y vigentes que necesita la cascada. |
| `PriceListItem` | `app/Models/Pricing/PriceListItem.php` | Precio de un artículo en una lista. UNIQUE(`price_list_id`, `article_id`), `price > 0`. |
| `PriceListScope` | `app/Enums/Pricing/PriceListScope.php` | `Canal` / `Particular` — discrimina los dos ejes. |
| `PriceListChannel` | `app/Enums/Pricing/PriceListChannel.php` | `General` / `Mostrador` / `Online`. |
| `Customer` (HU-022) | `feature/HU-022-asignar-lista-precios-cliente` | Agrega `price_list_id` nullable + `priceList(): BelongsTo`. |
| `ConsultPriceListArticles` | `app/Actions/Pricing/ConsultPriceListArticles.php` | Action de referencia: `buildQuery()` + `applyFilters()`, patrón a seguir. |
| `PriceListData` | `app/Data/Pricing/PriceListData.php` | Data object de referencia para shape y `fromModel()`. |
| `PriceListTest` | `tests/Feature/Pricing/PriceListTest.php` | Helper `generalPriceList()` reutilizable en los tests nuevos. |

**Nota de scopes:** `PriceList::scopeActive()` filtra `is_active = true`; `scopeCurrentlyValid()`
filtra el rango de fechas (sin importar el flag). La cascada necesita **ambos** combinados:
`->active()->currentlyValid()`. Los métodos ya existen — no hace falta agregar nada al modelo.

## Proposed Changes

### Backend — Exception

#### [NEW] `app/Exceptions/Pricing/ArticleNotPricedException.php`

Excepción de dominio lanzada cuando el artículo no tiene precio en ninguna lista de la cascada.
Extiende `\RuntimeException`. Se usa para rechazar la operación con un mensaje explícito en vez de
retornar cero.

```php
namespace App\Exceptions\Pricing;

class ArticleNotPricedException extends \RuntimeException
{
    public function __construct(int $articleId, int $priceListsChecked)
    {
        parent::__construct(
            "El artículo #{$articleId} no tiene precio en ninguna lista aplicable ({$priceListsChecked} listas consultadas)."
        );
    }
}
```

### Backend — Data object

#### [NEW] `app/Data/Pricing/ResolvedPriceData.php`

Resultado de la resolución: precio unitario + lista de origen. Se usa como valor de retorno del
Action para que el caller no deba acceder al `PriceListItem` directamente.

```php
namespace App\Data\Pricing;

use App\Models\Pricing\PriceList;
use App\Models\Pricing\PriceListItem;
use Spatie\LaravelData\Data;

class ResolvedPriceData extends Data
{
    public function __construct(
        public readonly string $unit_price,     // decimal string (e.g. "1250.50")
        public readonly int $price_list_id,
        public readonly string $price_list_name,
        public readonly string $price_list_scope, // 'canal' | 'particular'
    ) {}

    public static function fromItem(PriceListItem $item): self
    {
        /** @var PriceList $list */
        $list = $item->priceList;

        return new self(
            unit_price: $item->price,
            price_list_id: $list->id,
            price_list_name: $list->name,
            price_list_scope: $list->scope->value,
        );
    }
}
```

> **Por qué Data y no solo un array:** el resultado se pasará eventualmente como prop de Inertia
> a la línea de venta, y el generador de tipos TypeScript lo tomará automáticamente.

### Backend — Action

#### [NEW] `app/Actions/Pricing/ResolveArticlePrice.php`

Action interno, sin ruta HTTP directa. Recibe artículo, cliente opcional y canal; retorna
`ResolvedPriceData` o lanza `ArticleNotPricedException`.

**Algoritmo de la cascada:**

```
1. Si $customer no es null y tiene una lista particular activa y vigente
   → buscar precio del artículo en esa lista → si existe, retornar
2. Buscar lista de canal activa y vigente para $channel (scope=canal, channel=$channel)
   → buscar precio del artículo → si existe, retornar
3. Buscar lista general activa y vigente (scope=canal, channel=general)
   → buscar precio del artículo → si existe, retornar
4. Lanzar ArticleNotPricedException
```

**Firma:**

```php
namespace App\Actions\Pricing;

use App\Data\Pricing\ResolvedPriceData;
use App\Enums\Pricing\PriceListChannel;
use App\Exceptions\Pricing\ArticleNotPricedException;
use App\Models\Catalog\Article;
use App\Models\Customers\Customer;
use App\Models\Pricing\PriceListItem;

class ResolveArticlePrice
{
    /**
     * Resolve the unit price for one sale line.
     *
     * Cascade: 1) customer's particular list  2) channel list  3) general list.
     *
     * @throws ArticleNotPricedException
     */
    public function execute(
        Article $article,
        PriceListChannel $channel,  // Mostrador | Online (never General, caller validates)
        ?Customer $customer = null,
    ): ResolvedPriceData
```

Internamente, tres métodos privados:
- `resolveFromParticular(Article, Customer): ?PriceListItem` — carga eager `customer->priceList`
  si no está cargado, filtra active+currentlyValid+particular.
- `resolveFromChannel(Article, PriceListChannel): ?PriceListItem` — `PriceList::active()->currentlyValid()->forChannel($channel)` + join a `price_list_items`.
- `resolveFromGeneral(Article): ?PriceListItem` — igual pero `forChannel(PriceListChannel::General)`.

Cada uno usa una subconsulta eficiente en una sola query por paso (JOIN `price_list_items` en la
misma consulta de `PriceList`), no carga la lista completa para luego buscar en sus items.

### Backend — Tests

#### [NEW] `tests/Feature/Pricing/ResolveArticlePriceTest.php`

Tests de la cascada completa. Cubrir los tres escenarios del criterio de Verificación y los casos
de rechazo:

| Escenario | Descripción |
|---|---|
| `resuelve desde lista particular del cliente` | Cliente con lista particular activa+vigente con precio del artículo → retorna precio de esa lista |
| `resuelve desde lista de canal mostrador` | Cliente sin lista particular, canal mostrador, lista mostrador activa+vigente → retorna precio del canal |
| `resuelve desde lista de canal online` | Sin lista particular, canal online, lista online activa+vigente → retorna precio del canal |
| `cae a lista general cuando el canal no tiene lista propia` | Sin lista particular, canal mostrador sin lista, lista general activa → retorna precio general |
| `rechaza si el artículo no tiene precio en ninguna lista` | Ninguna lista tiene el artículo → lanza `ArticleNotPricedException` |
| `ignora lista particular vencida` | Lista particular con `valid_to` anterior a hoy → no la resuelve, cae al canal |
| `ignora lista particular inactiva` | Lista particular con `is_active=false` → no la resuelve, cae al canal |
| `ignora lista de canal futura` | Lista de canal con `valid_from` en el futuro → no la resuelve, cae a general |
| `cliente null usa la lista del canal` | `$customer = null` (Consumidor Final) → salta directo al paso 2 |

Los tests **no pasan por HTTP**: instancian el Action directamente (`new ResolveArticlePrice()`)
para probar la lógica de dominio sin overhead de request. Se apoya en:
- `PriceList::factory()` con estados existentes (`forChannel()`, factory de `PriceListItem`)
- `Customer::factory()` con un estado nuevo `withPriceList(PriceList $list)` que setea
  `price_list_id` — verificar si la factory de HU-022 ya lo incluye antes de crearlo.

## Verificación de la Definition of Done

| Criterio | Cómo se verifica |
|---|---|
| Cascada de precios correcta (particular → canal → general) | Tests `ResolveArticlePriceTest` — 3 escenarios de la CA de Verificación |
| Listas vencidas/futuras/inactivas descartadas | Tests de casos límite (3 tests adicionales) |
| `ArticleNotPricedException` cuando no hay precio | Test de rechazo explícito |
| `Consumidor Final` (null customer) usa lista del canal | Test con `$customer = null` |
| Resultado incluye `price_list_id` para trazabilidad | `ResolvedPriceData` tiene el campo; test verifica su valor |
| No hay pantalla nueva (Action interno) | Solo backend: no hay ruta HTTP ni controlador |
| Pint sin errores | `vendor/bin/pint --dirty --format agent` |
| PHPStan sin errores | `composer run types:check` |
| Tests pasan | `php artisan test --compact --filter=ResolveArticlePriceTest` |

## Verification Plan

### Automated Tests

```bash
php artisan test --compact --filter=ResolveArticlePriceTest
```

Los tests instancian `ResolveArticlePrice` directamente (sin HTTP). Requieren que la migración de
HU-022 (`add_price_list_id_to_customers_table`) esté presente (se asume que la branch de HU-022
se mergea antes de ejecutar esta HU, o que los tests corren sobre esa branch).

### Manual Verification

No aplica: el Action no tiene endpoint propio. La verificación manual real llegará cuando se lo
invoque desde la pantalla de venta (HU-039/HU-040, Sprint 4).

### CI checks

```bash
vendor/bin/pint --dirty --format agent
composer run types:check
npm run types:generate   # solo si se agrega ResolvedPriceData
```

## Open Questions

Ninguna — la HU está completamente especificada y la estructura del código existente cubre todos
los conceptos que necesita.
