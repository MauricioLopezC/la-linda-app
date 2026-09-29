# HU-057 — Abrir la caja con el fondo inicial desglosado por denominación

El cajero abre su turno en un punto de venta contando los billetes de cambio por denominación. El
sistema calcula el fondo como la suma, crea el turno (`cash_sessions`), guarda el conteo
(`cash_counts`) y registra el movimiento de apertura en efectivo (`cash_movements`). Es la primera
historia de la Dupla A y bloquea a `HU-039`, `EPIC-04`, `HU-058` y `HU-060`.

**El esquema ya está en `master`** (PR #59): tablas, índices únicos parciales, CHECKs, modelos,
enums y factories. Esta historia es solo Action + HTTP + pantalla + indicador + tests; no lleva
migraciones.

**Relación con la PR #62 (HU-007):** no comparten archivos (la PR toca `Catalog`/`Pricing`,
`ArticleData` y `articles/index.tsx`). Se trabaja desde `master` sin sincronizar; si se mergea
antes, basta un `git rebase master`.

## Criterios de aceptación (de `product-backlog.md`)

1. **Datos:** punto de venta, cajero (usuario logueado), fecha y hora de apertura, conteo por
   denominación (denominación y cantidad), fondo inicial.
2. **Validaciones:**
   - el punto de venta debe estar activo;
   - una caja tiene a lo sumo un turno abierto, y un cajero también;
   - solo billetes de $20.000 a $10 (`CashDenomination`), sin monedas;
   - cantidades enteras ≥ 0;
   - el fondo es la suma de denominación × cantidad; nunca se carga a mano.
3. **Comportamiento:**
   - la apertura crea el turno, cuyo ID identifica ventas y movimientos;
   - el fondo queda registrado como primer movimiento de caja (`apertura`, efectivo);
   - se admite fondo cero;
   - mientras el turno está abierto, el sistema muestra caja y hora de apertura.
4. **Verificación:** 10 × $10.000 + 5 × $2.000 → fondo $110.000; un segundo intento en la misma
   caja se rechaza.

## Investigación del código existente

| Artefacto | Ubicación | Qué aporta |
|---|---|---|
| `CashSession` | `app/Models/Sales/CashSession.php` | Relaciones `counts`, `openingCounts`, `movements`, `scopeOpen()`, `isOpen()`. |
| `CashCount` | `app/Models/Sales/CashCount.php` | `moment` + `denomination` + `quantity`; sin timestamps. |
| `CashMovement` | `app/Models/Sales/CashMovement.php` | Sin `updated_at`; `amount` CHECK `> 0`. |
| `CashDenomination` | `app/Enums/Sales/CashDenomination.php` | 10 billetes, `label()` con formato `$10.000`. |
| `CashCountMoment` / `CashMovementType` / `CashSessionStatus` | `app/Enums/Sales/` | `Opening`, `Opening` (`apertura`), `Open`. |
| `PaymentMethodKind::Cash` | `app/Enums/Sales/PaymentMethodKind.php` | Para encontrar el medio "Efectivo" del movimiento de apertura. |
| Migración `cash_sessions` | `2026_09_28_100001_…` | Índices únicos parciales por punto de venta y por usuario con `status = 'abierta'`. |
| `OpenSale` | `app/Actions/Sales/OpenSale.php` | Ya exige turno abierto del usuario en el punto de venta (lo ajusta `HU-039`). |
| `PointOfSaleController` / `PointOfSaleData` | `app/Http/Controllers/Sales/`, `app/Data/Sales/` | Patrón de controlador y Data a seguir; `PointOfSaleData` se reutiliza para el selector. |
| `HandleInertiaRequests::share()` | `app/Http/Middleware/HandleInertiaRequests.php` | Lugar para compartir el turno abierto con todo el layout. |
| `CashSessionSchemaTest` | `tests/Feature/Sales/CashSessionSchemaTest.php` | Cubre el esquema; los tests nuevos cubren el comportamiento. |

## Decisiones (confirmadas 2026-09-28)

1. **Fondo cero vs. `cash_movements.amount > 0`.** El criterio admite abrir con fondo cero, pero el
   CHECK no permite un movimiento de $0. **Decisión:** con fondo cero no se crea el movimiento de
   apertura; el turno y el conteo (todo en cero) quedan igual, y el efectivo esperado de `HU-058`
   / `HU-060` (suma de movimientos) da cero de todas formas. La alternativa —relajar el CHECK para
   `apertura`— toca una migración compartida por las tres duplas.
2. **Qué medio de pago usa la apertura.** No hay unicidad de `kind`. **Decisión:** el primer medio
   activo con `kind = efectivo` (por `id`); si no existe, la apertura se rechaza con un mensaje
   que pide dar de alta o reactivar el medio "Efectivo". Se encapsula en
   `PaymentMethod::scopeCash()` para que `HU-058` y `EPIC-04` usen la misma regla.
3. **Qué puntos de venta se ofrecen.** Solo los activos y sin turno abierto (los ocupados se
   muestran deshabilitados con el nombre del cajero). La validación real la hace el Action.

## Proposed Changes

### Backend — Model helpers

#### [MODIFY] `app/Models/Sales/CashSession.php`
- `scopeOpenForUser(Builder $query, int $userId)`: el turno abierto de un usuario. Es el contrato
  que usan `HU-039` (tomar el punto de venta del turno) y el indicador del layout.

#### [MODIFY] `app/Models/Sales/PaymentMethod.php`
- `scopeCash(Builder $query)`: activos con `kind = efectivo`, ordenados por `id`.

### Backend — Action

#### [NEW] `app/Actions/Sales/OpenCashSession.php`

`handle(PointOfSale $pointOfSale, array $counts, User $user): CashSession`, donde `$counts` es
`array<int, int>` (denominación → cantidad).

1. Rechaza (`ValidationException`) si el punto de venta está inactivo.
2. Busca el medio efectivo; sin él, rechaza.
3. En `DB::transaction()`:
   - `lockForUpdate()` sobre el punto de venta para serializar aperturas concurrentes;
   - rechaza si el punto de venta ya tiene turno abierto (mensaje con el cajero y la hora) o si
     el usuario ya tiene uno (mensaje con la caja);
   - calcula el fondo con `bcmul`/`bcadd` (o enteros: todas las denominaciones son enteras) sobre
     las 10 denominaciones;
   - crea `CashSession` (`opened_at = now()`, `opening_amount`), un `CashCount` por denominación
     (las 10, incluidas las de cantidad 0, para que el cierre compare contra un conteo completo) y,
     si el fondo es > 0, el `CashMovement` `apertura`.
4. Traduce `UniqueConstraintViolationException` (carrera que pasó el chequeo previo) a la misma
   `ValidationException`, como red de seguridad del índice parcial.

### Backend — HTTP

#### [NEW] `app/Http/Requests/Sales/StoreCashSessionRequest.php`
- `point_of_sale_id`: `required|integer|exists:points_of_sale,id`.
- `counts`: `required|array`, con claves limitadas a los valores de `CashDenomination`
  (`required_array_keys` + validación de que no haya claves extra).
- `counts.*`: `required|integer|min:0|max:100000`.
- `attributes()` en castellano, como los requests hermanos.

#### [NEW] `app/Http/Controllers/Sales/CashSessionController.php`
- `create()`: si el usuario ya tiene turno abierto, redirige a la venta (`sales.sales.index`) con
  aviso; si no, renderiza `sales/cash-sessions/create` con puntos de venta y denominaciones.
- `store(StoreCashSessionRequest, OpenCashSession)`: abre y redirige a `sales.sales.index` con
  `success` "Caja N abierta con un fondo de $X".

#### [MODIFY] `routes/web.php`
```php
Route::prefix('sales/cash-sessions')->name('sales.cash-sessions.')->group(function () {
    Route::get('open', [CashSessionController::class, 'create'])->name('create');
    Route::post('/', [CashSessionController::class, 'store'])->name('store');
});
```

### Backend — Data

#### [NEW] `app/Data/Sales/OpenCashSessionData.php`
Turno abierto para el indicador: `id`, `point_of_sale_id`, `point_of_sale_number`, `branch_name`,
`opened_at` (ISO), `opening_amount`.

#### [NEW] `app/Data/Sales/CashDenominationData.php`
`value` y `label` para armar la grilla sin repetir el enum en TypeScript.

#### [NEW] `app/Data/Sales/CashSessionPointOfSaleData.php` (o extender el uso de `PointOfSaleData`)
Punto de venta + `open_session_user_name` nullable, para deshabilitar los ocupados en el selector.

#### [MODIFY] `app/Http/Middleware/HandleInertiaRequests.php`
Compartir `cashSession` (lazy closure, `null` sin turno) con `OpenCashSessionData`. Una sola query
por request autenticado, sobre índice.

### Frontend

#### [NEW] `resources/js/pages/sales/cash-sessions/create.tsx`
- Selector de punto de venta (`Select` de shadcn), ocupados deshabilitados.
- Grilla (`Table`) denominación × cantidad (`Input type="number" min=0 step=1`), subtotal por fila
  y total en vivo; todas arrancan en 0 (fondo cero permitido).
- Botón "Abrir caja" con `useForm` + Wayfinder (`@/routes/sales/cash-sessions`).
- Revisar `docs/context/design.md` antes de estilar.

#### [NEW] `resources/js/components/cash-session-indicator.tsx`
Badge "Caja N · Sucursal · desde HH:mm" leyendo `usePage().props.cashSession`; sin turno, enlace
"Abrir caja". Se monta en `app-sidebar-header.tsx` para que se vea en todo el sistema.

#### [MODIFY] `resources/js/components/app-sidebar.tsx`
Ítem "Abrir caja" (ícono `Vault` o `Banknote`) en el grupo Ventas.

#### [MODIFY] tipos compartidos de Inertia (`resources/js/types`)
Agregar `cashSession: App.Data.Sales.OpenCashSessionData | null` a las page props compartidas.

## Tests (`tests/Feature/Sales/OpenCashSessionTest.php`)

- **Verificación del PO:** 10 × $10.000 + 5 × $2.000 → `opening_amount = 110000.00`, 10 filas de
  `cash_counts` con `moment = apertura`, un movimiento `apertura` de $110.000 en efectivo.
- Segundo intento en la misma caja (otro usuario) → rechazado, sigue habiendo un solo turno.
- Mismo usuario intentando abrir otra caja → rechazado.
- Punto de venta inactivo → rechazado.
- Fondo cero → turno abierto, sin movimiento de apertura.
- Sin medio efectivo activo → rechazado, nada persistido.
- Request: denominación no vigente (p. ej. `5` o `1`), cantidad negativa, decimal, clave faltante
  → errores de validación; un `opening_amount` enviado a mano se ignora.
- Un turno `cerrada` en la misma caja no impide abrir uno nuevo.
- Pantalla: `create` renderiza con puntos de venta y denominaciones; con turno abierto redirige.
- Prop compartida `cashSession` presente con turno y `null` sin él.
- La constraint de carrera se testea con `inSavepoint()` (regla de `.ai/rules/tests.md`).

## Coordinación con otras historias

El contrato quedó registrado en `.ai/rules/sales.md` (lo leen los agentes al tocar
`app/Actions/Sales/**`) y en el docblock de `CashSession::scopeOpenForUser()`.

- **HU-039 (Dupla B):** usa `CashSession::query()->openForUser($userId)->first()` para tomar el punto de venta del turno y
  el enlace `sales.cash-sessions.create` para "ir a abrir la caja". Mergear esta historia antes.
- **HU-058 / EPIC-04:** reutilizan `PaymentMethod::scopeCash()` y el patrón de creación de
  `CashMovement`.
- **HU-060:** el conteo de apertura queda completo (10 filas) para compararlo con el de cierre.

## Verificación antes de cerrar

`composer run ci:check` (Pint, PHPStan, ESLint, Prettier, tsc, Pest) y `npm run types:generate`
después de crear los Data.
