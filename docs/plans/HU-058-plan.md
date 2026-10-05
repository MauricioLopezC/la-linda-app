# HU-058 — Registrar ingresos y egresos de dinero en la caja

Durante el turno de caja ocurren movimientos de efectivo distintos de las ventas: gastos menores (ej. artículos de limpieza), retiros periódicos por seguridad o refuerzos de cambio de tesorería. Esta historia permite al cajero registrar ingresos y egresos de efectivo con motivo obligatorio dentro de su turno abierto, garantizando que el efectivo esperado de la caja refleje cada movimiento y que ningún egreso supere el efectivo disponible.

Depende de **HU-057** (Turno de caja y apertura), la cual ya está mergeada e implementada en `master`.

---

## Criterios de aceptación (de `product-backlog.md`)

1. **Datos:** turno de caja, tipo (ingreso o egreso), importe, motivo, usuario, fecha y hora.
2. **Validaciones:**
   - Importe mayor a cero y motivo obligatorio.
   - Solo se registran en un turno abierto.
   - Un egreso no puede superar el efectivo disponible según el sistema.
3. **Comportamiento:**
   - Se registran en efectivo.
   - Son inmutables: un error se corrige con el movimiento contrario.
   - El turno muestra el listado de sus movimientos (apertura, ventas, ingresos y egresos) y el efectivo esperado.
4. **Verificación:** se registra un egreso de $3.000 por la compra de artículos de limpieza y el efectivo esperado del turno baja $3.000.

---

## Investigación del código existente

| Artefacto | Ubicación | Propósito / Qué aporta |
|---|---|---|
| `CashSession` | `app/Models/Sales/CashSession.php` | Modelo del turno de caja. Relación `movements()`, `scopeOpenForUser()`, `isOpen()`. Se agregarán métodos auxiliares para el cálculo del efectivo esperado (`expectedCash()`). |
| `CashMovement` | `app/Models/Sales/CashMovement.php` | Modelo del movimiento de caja. Tabla `cash_movements` ya creada en `master` con CHECKs para `amount > 0`, `type in ('apertura', 'venta', 'ingreso', 'egreso')`, motivo obligatorio para ingreso/egreso, y sin `updated_at` (inmutable). |
| `CashMovementType` | `app/Enums/Sales/CashMovementType.php` | Enum con `Opening`, `Sale`, `Income`, `Expense`. Métodos `sign()` (+1 o -1) y `requiresReason()`. |
| `PaymentMethod` | `app/Models/Sales/PaymentMethod.php` | Scope `cash()` que obtiene el primer medio activo con `kind = efectivo`. Utilizado para asociar el medio a los movimientos de caja. |
| `OpenCashSession` | `app/Actions/Sales/OpenCashSession.php` | Referencia de Action del módulo `Sales` para transacciones, validaciones y creación de movimientos `apertura`. |
| `CashSessionController` | `app/Http/Controllers/Sales/CashSessionController.php` | Controlador actual con `create` y `store`. Se ampliará con `show`, `current` y `storeMovement`. |
| `CashSessionIndicator` | `resources/js/components/cash-session-indicator.tsx` | Indicador del turno abierto en el layout. Se actualizará para enlazar directamente al detalle del turno actual. |
| `app-sidebar.tsx` | `resources/js/components/app-sidebar.tsx` | Navegación lateral. Se enlazará al turno de caja actual. |

---

## Proposed Changes

### Backend — Model helpers

#### [MODIFY] `app/Models/Sales/CashSession.php`
- Agregar método `expectedCash(): string`: calcula en base a la base de datos la suma con signo de todos los movimientos de efectivo (`payment_method.kind = 'efectivo'`) de la sesión (`apertura`, `venta`, `ingreso` suman; `egreso` resta). Retorna string formateado con 2 decimales (ej. `'107000.00'`).
- Agregar método `movementsSummary(): array`: desglosa los totales del turno por concepto:
  - `opening_amount`: fondo inicial.
  - `sales_cash_amount`: ventas cobradas en efectivo.
  - `income_amount`: ingresos manuales.
  - `expense_amount`: egresos manuales.
  - `expected_cash`: efectivo esperado total.

---

### Backend — Action

#### [NEW] `app/Actions/Sales/RegisterCashMovement.php`
Action invocable que encapsula el caso de uso con reglas de negocio:
- Firma: `handle(CashSession $cashSession, array $data, ?int $userId = null): CashMovement`
- Flujo en `DB::transaction()`:
  1. Bloqueo de la sesión con `lockForUpdate()`.
  2. Valida que el turno esté abierto (`$session->isOpen()`); si está cerrado, lanza `ValidationException` indicando que no se admiten movimientos en turnos cerrados.
  3. Valida que el tipo sea `ingreso` o `egreso` (`CashMovementType::Income` o `CashMovementType::Expense`).
  4. Valida que el importe sea mayor a cero (`bccomp($amount, '0.00', 2) > 0`).
  5. Valida que el motivo no esté vacío (`trim($reason) !== ''`).
  6. Obtiene el medio de pago efectivo (`PaymentMethod::query()->cash()->first()`). Si no existe, lanza excepción de validación.
  7. Si el tipo es `egreso`:
     - Calcula el efectivo disponible actual con `$session->expectedCash()`.
     - Si `$amount > $currentCash` (`bccomp($amount, $currentCash, 2) === 1`), lanza `ValidationException` indicando que el egreso no puede superar el efectivo disponible según el sistema.
  8. Crea el registro en `cash_movements` con `type`, `amount`, `reason`, `payment_method_id` y `user_id`.
  9. Retorna el movimiento creado.

---

### Backend — HTTP & Validation

#### [NEW] `app/Http/Requests/Sales/StoreCashMovementRequest.php`
- Reglas:
  - `type`: `['required', 'string', Rule::in(['ingreso', 'egreso'])]`
  - `amount`: `['required', 'numeric', 'gt:0', 'max:999999999.99']`
  - `reason`: `['required', 'string', 'min:3', 'max:255']`
- Atributos traducidos en castellano (`tipo de movimiento`, `importe`, `motivo`).

#### [MODIFY] `app/Http/Controllers/Sales/CashSessionController.php`
- `show(CashSession $cashSession)`:
  - Carga relaciones: `pointOfSale.warehouse.branch`, `user`, `movements.paymentMethod`, `movements.user`, `movements.sale`.
  - Retorna `Inertia::render('sales/cash-sessions/show', ...)` pasando `CashSessionData` con el detalle del turno, listado de movimientos y resumen de totales.
- `current(Request $request)`:
  - Busca el turno abierto del usuario logueado (`CashSession::query()->openForUser($userId)->first()`).
  - Si tiene turno abierto, redirige a `sales.cash-sessions.show`.
  - Si no tiene turno abierto, redirige a `sales.cash-sessions.create` con mensaje informativo.
- `storeMovement(StoreCashMovementRequest $request, CashSession $cashSession, RegisterCashMovement $action)`:
  - Ejecuta el Action `$action->handle($cashSession, $request->validated())`.
  - Retorna redirect back con toast flash de éxito: `"Ingreso/Egreso de $X registrado exitosamente."`.

#### [MODIFY] `routes/web.php`
```php
Route::prefix('sales/cash-sessions')->name('sales.cash-sessions.')->group(function () {
    Route::get('open', [CashSessionController::class, 'create'])->name('create');
    Route::post('/', [CashSessionController::class, 'store'])->name('store');
    Route::get('current', [CashSessionController::class, 'current'])->name('current');
    Route::post('{cashSession}/movements', [CashSessionController::class, 'storeMovement'])->name('movements.store');
    Route::get('{cashSession}', [CashSessionController::class, 'show'])->name('show');
});
```

---

### Backend — Data Objects

#### [NEW] `app/Data/Sales/CashMovementData.php`
Propiedades para representar cada movimiento del listado:
- `id`: int
- `cash_session_id`: int
- `type`: string (`apertura`, `venta`, `ingreso`, `egreso`)
- `type_label`: string (`Apertura`, `Venta`, `Ingreso`, `Egreso`)
- `sign`: int (`1` o `-1`)
- `payment_method_name`: string
- `amount`: string
- `sale_id`: ?int
- `reason`: ?string
- `user_name`: string
- `created_at`: string (ISO)
- `created_at_formatted`: string (`d/m/Y H:i`)

#### [NEW] `app/Data/Sales/CashSessionTotalsData.php`
Resumen financiero del turno:
- `opening_amount`: string
- `sales_cash_amount`: string
- `income_amount`: string
- `expense_amount`: string
- `expected_cash`: string

#### [NEW] `app/Data/Sales/CashSessionData.php`
Datos completos para la pantalla del turno:
- `id`: int
- `point_of_sale_id`: int
- `point_of_sale_number`: int
- `branch_name`: string
- `user_id`: int
- `user_name`: string
- `status`: string (`abierta` / `cerrada`)
- `status_label`: string
- `is_open`: bool
- `opened_at`: string
- `opened_at_formatted`: string
- `closed_at`: ?string
- `closed_at_formatted`: ?string
- `closing_notes`: ?string
- `totals`: CashSessionTotalsData
- `movements`: array<CashMovementData>

---

### Frontend

#### [NEW] `resources/js/pages/sales/cash-sessions/show.tsx`
Pantalla del turno de caja:
- **Cabecera:** Información del turno (Caja N°, Sucursal, Cajero, Estado, Apertura, Cierre si aplica) y botón "Registrar movimiento" (habilitado solo si `is_open === true`).
- **Cards de totales / KPI:**
  - Fondo inicial
  - Ventas en efectivo
  - Ingresos
  - Egresos
  - **Efectivo esperado** (destacado)
- **Modal de registro (`Dialog` de shadcn):**
  - Selector de tipo: Ingreso / Egreso.
  - Input de importe numérico (`step="0.01"`), validado contra 0.
  - En caso de Egreso, advertencia visual del efectivo disponible máximo.
  - Input/textarea de motivo (obligatorio).
  - Envío mediante `useForm` a `movements.store`.
- **Tabla de movimientos (`Table` de shadcn):**
  - Columnas: Fecha y hora, Tipo (con badge verde/rojo según signo), Medio de pago, Concepto / Motivo, Usuario, Importe (+ / -).
  - Estado vacío descriptivo si solo está la apertura o sin movimientos.

#### [MODIFY] `resources/js/components/cash-session-indicator.tsx`
- El badge del turno abierto en el header superior se convierte en un enlace interactivo al turno actual (`sales.cash-sessions.show`).

#### [MODIFY] `resources/js/components/app-sidebar.tsx`
- En el grupo "Ventas", el ítem "Abrir caja" / "Turno de caja" apunta a `sales.cash-sessions.current` para que un cajero con turno abierto acceda a su turno en un clic, o a la pantalla de apertura si no tiene turno.

#### [MODIFY] `resources/js/pages/sales/sales/index.tsx`
- Los enlaces de `Turno #X` en la tabla de ventas dirigen a `sales.cash-sessions.show`.
- Si el usuario tiene turno abierto, se agrega un botón secundario "Mi turno de caja".

---

## Verificación de la Definition of Done

| Criterio | Cómo se verifica |
|---|---|
| Datos de ingreso/egreso (turno, tipo, importe, motivo, usuario, fecha y hora) | Test unitario y de integración que verifica persistencia exacta de columnas en `cash_movements`. |
| Importe mayor a cero y motivo obligatorio | Tests que envían importe 0, negativo o motivo vacío/nulo y esperan errores de validación. |
| Solo en turno abierto | Test que intenta registrar un movimiento en una sesión cerrada y verifica rechazo. |
| Egreso no supera efectivo disponible | Test que intenta registrar un egreso mayor al disponible y comprueba `ValidationException`. Test de egreso igual al disponible que se aprueba. |
| Registro en efectivo | Test que comprueba que el movimiento se guarda con `payment_method_id` del efectivo. |
| Inmutabilidad | Verificación de que no existen rutas ni métodos de update/delete para `cash_movements`. |
| Turno muestra movimientos y efectivo esperado | Test de renderizado Inertia de `sales/cash-sessions/show` con apertura, ingresos, egresos y cálculo del efectivo esperado. |
| Verificación del backlog: egreso $3.000 artículos de limpieza baja $3.000 el efectivo | Test específico que abre con $10.000, registra egreso de $3.000 por artículos de limpieza y valida que el esperado baja a $7.000. |

---

## Verification Plan

### Automated Tests
- `tests/Feature/Sales/RegisterCashMovementTest.php`:
  - Registro de ingreso aumenta el efectivo esperado.
  - Registro de egreso disminuye el efectivo esperado.
  - Escenario de verificación del criterio: egreso de $3.000 por "artículos de limpieza" reduce el efectivo esperado en $3.000.
  - Egreso rechazado si supera el efectivo disponible.
  - Egreso exacto igual al efectivo disponible permitido (queda en $0).
  - Rechazo de importe <= 0.
  - Rechazo de motivo vacío.
  - Rechazo de movimiento en turno cerrado.
  - Movimiento asigna el método de pago en efectivo y usuario autenticado.
- `tests/Feature/Sales/CashSessionShowTest.php`:
  - `GET /sales/cash-sessions/{id}` renderiza la vista show con movimientos y resumen.
  - `POST /sales/cash-sessions/{id}/movements` crea el movimiento y redirige con mensaje flash.
  - `GET /sales/cash-sessions/current` redirige a la sesión abierta o a crear.

### Manual Verification
1. Iniciar sesión como usuario interno con rol cajero.
2. Abrir turno de caja con fondo inicial de $10.000 (10 billetes de $1.000).
3. Hacer clic en el indicador del turno o menú "Turno de caja" para ver la pantalla del turno.
4. Presionar "Registrar movimiento", seleccionar "Egreso", ingresar $3.000 y motivo "Compra de artículos de limpieza".
5. Confirmar: verificar que el egreso aparece en la tabla con -$3.000 y el efectivo esperado se actualiza a $7.000.
6. Intentar registrar un egreso de $8.000: verificar que la interfaz y el backend impiden la operación por saldo insuficiente.
7. Registrar un ingreso de $5.000 por "Refuerzo de cambio": verificar que el efectivo esperado sube a $12.000.

### CI checks
- `vendor/bin/pint --dirty --format agent`
- `composer run types:check`
- `npm run types:generate`
- `npm run lint:check`
- `npm run format:check`
- `npm run types:check`
- `php artisan test --compact --filter=Cash`
