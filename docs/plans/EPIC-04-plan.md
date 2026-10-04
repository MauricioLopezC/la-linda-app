# EPIC-04 — Cobrar la venta con uno o varios medios de pago

Permite al cajero registrar el cobro de una venta de mostrador abierta en su turno de caja, distribuyendo el importe total entre uno o varios medios de pago activos (efectivo, tarjeta, billetera virtual, transferencia u otro), calculando el vuelto en efectivo, generando un movimiento de caja (`venta`) por cada medio utilizado, y dejando la venta confirmada e inmutable de manera atómica dentro de una transacción de base de datos. Además, integra el selector de clase (`kind`) en el ABM de medios de pago.

## Criterios de aceptación (de `product-backlog.md`)

1. **Datos:** por cada medio usado, medio de pago e importe; en efectivo, además, importe entregado por el cliente y vuelto.
2. **Validaciones:**
    - solo medios de pago activos.
    - la suma de los importes coincide con el total de la venta; solo el efectivo puede recibir de más, y la diferencia es el vuelto.
    - no se cobra una venta sin líneas, descartada o ya confirmada.
3. **Comportamiento:**
    - cada medio usado genera un movimiento de caja de tipo venta en el turno, vinculado a la venta.
    - en efectivo el movimiento registra el importe de la venta (lo que queda en la caja), no lo entregado.
    - al completar el cobro la venta pasa a confirmada y queda inmutable.
    - cobro, confirmación y egreso de stock (`EPIC-06`) ocurren en una sola operación: si algo falla, no queda nada a medias.
    - cada medio de pago tiene una clase (efectivo, tarjeta, billetera virtual, transferencia u otro) que ordena el arqueo de `HU-060`.
    - al confirmar se emite la factura (`HU-042`) en la misma operación.
    - no se carga el número de cupón de cada cobro con tarjeta: el POSNET se rinde con el total y el número de lote al cerrar (`HU-060`).
4. **Verificación:** se cobra una venta de $8.500 con $5.000 de débito y $10.000 en efectivo; el vuelto es $6.500 y quedan dos movimientos de caja, de $5.000 y de $3.500.

> **Pasa de Epic a Historia en el Sprint Planning 4 (2026-09-27).** Que las ventas sean movimientos de caja es indicación del PO (26/09): "Vayan por esa entidad: las ventas también son movimientos de caja". **Respuesta del PO (2026-09-28):** del POSNET alcanza con el total y el número de lote al cerrar la caja; se saca la referencia opcional por cobro.

---

## Investigación del código existente

| Artefacto | Ubicación | Propósito |
|---|---|---|
| Modelo `Sale` | `app/Models/Sales/Sale.php` | Estado `status` ('abierta', 'confirmada', 'descartada'), `confirmed_at`, `total_amount`, relación `cashMovements()`, métodos `isOpen()`, `acceptsChanges()`. |
| Modelo `PaymentMethod` | `app/Models/Sales/PaymentMethod.php` | Campo `kind` (cast a `PaymentMethodKind`), scopes `active()` y `cash()`, método `isInUse()`. |
| Enum `PaymentMethodKind` | `app/Enums/Sales/PaymentMethodKind.php` | Casos `Cash` ('efectivo'), `Card` ('tarjeta'), `VirtualWallet` ('billetera_virtual'), `Transfer` ('transferencia'), `Other` ('otro'). Métodos `givesChange()`, `toOptions()`. |
| Modelo `CashMovement` | `app/Models/Sales/CashMovement.php` | Movimiento inmutable de caja. Tipos en `CashMovementType`. Columnas `cash_session_id`, `type`, `payment_method_id`, `amount`, `sale_id`, `tendered_amount`, `user_id`. |
| Enum `CashMovementType` | `app/Enums/Sales/CashMovementType.php` | Casos `Opening` ('apertura'), `Sale` ('venta'), `Income` ('ingreso'), `Expense` ('egreso'). |
| Modelo `CashSession` | `app/Models/Sales/CashSession.php` | Turno de caja abierto del usuario. Scope `openForUser($userId)`. Relación `sales()` y `cashMovements()`. |
| Migración `add_kind_to_payment_methods` | `database/migrations/2026_09_28_100003_add_kind_to_payment_methods_table.php` | Ya agregó la columna `kind` con CHECK y actualizó los medios existentes. |
| Migración `revise_sales_for_cash_sessions` | `database/migrations/2026_09_28_100004_revise_sales_for_cash_sessions.php` | Agregó estado `confirmada` y timestamp `confirmed_at`. |
| Migración `create_cash_movements_table` | `database/migrations/2026_09_28_100005_create_cash_movements_table.php` | Crea tabla `cash_movements` con `tendered_amount` y vinculación a `sales`. |
| Controller `SaleController` | `app/Http/Controllers/Sales/SaleController.php` | Métodos `show()`, `index()`, `store()`, `updateCustomer()`, `discard()`. |
| Controller `PaymentMethodController` | `app/Http/Controllers/Sales/PaymentMethodController.php` | ABM de medios de pago (`index`, `store`, `update`, `toggleStatus`). |
| Data `SaleData` | `app/Data/Sales/SaleData.php` | DTO hacia Inertia de `sales/sales/show`. Falta exponer pagos registrados (`payments`), `confirmed_at` y cálculo de vuelto. |
| Data `PaymentMethodData` | `app/Data/Sales/PaymentMethodData.php` | DTO de medios de pago. Falta exponer `kind` y `kind_label`. |
| Vista `show.tsx` | `resources/js/pages/sales/sales/show.tsx` | Pantalla de venta. Actualmente sólo muestra carga de artículos y totales, sin panel de cobro ni vista de venta confirmada. |
| Vista `index.tsx` (Payment Methods) | `resources/js/pages/sales/payment-methods/index.tsx` | ABM de medios de pago sin selector ni columna de `kind`. |
| Reglas de arquitectura | `.ai/rules/sales.md`, `.ai/rules/inventory.md`, `.ai/rules/data.md` | Convención de sesiones de caja, uso de centavos para dinero, DTOs de salida, transacción atómica. |

---

## Proposed Changes

### 1. ABM de Medios de Pago (Backend y Frontend)

#### [MODIFY] `app/Http/Requests/Sales/StorePaymentMethodRequest.php`
- Agregar validación para `kind`:
  `'kind' => ['required', 'string', Rule::enum(PaymentMethodKind::class)]`
- Agregar atributo en `attributes()`: `'kind' => 'clase'`.

#### [MODIFY] `app/Http/Requests/Sales/UpdatePaymentMethodRequest.php`
- Agregar validación para `kind`:
  `'kind' => ['required', 'string', Rule::enum(PaymentMethodKind::class)]`
- Agregar atributo en `attributes()`: `'kind' => 'clase'`.

#### [MODIFY] `app/Actions/Sales/CreatePaymentMethod.php`
- Asignar `kind`: `PaymentMethodKind::from((string) $data['kind'])`.

#### [MODIFY] `app/Actions/Sales/UpdatePaymentMethod.php`
- Validar que si `$paymentMethod->isInUse()` y `$paymentMethod->kind->value !== $data['kind']`, lanzar `ValidationException::withMessages(['kind' => 'No se puede modificar la clase de un medio de pago que ya ha sido utilizado en operaciones registradas.'])`.
- Actualizar `kind`: `PaymentMethodKind::from((string) $data['kind'])`.

#### [MODIFY] `app/Data/Sales/PaymentMethodData.php`
- Agregar propiedades:
  - `public string $kind`
  - `public string $kind_label`
- En `fromModel`:
  `kind: $paymentMethod->kind->value`,
  `kind_label: $paymentMethod->kind->label()`.

#### [MODIFY] `app/Http/Controllers/Sales/PaymentMethodController.php`
- En `index()`: pasar `'kinds' => PaymentMethodKind::toOptions()`.

#### [MODIFY] `resources/js/pages/sales/payment-methods/index.tsx`
- Recibir prop `kinds: { value: string; label: string }[]`.
- Agregar `kind` al tipo `PaymentMethodFormData` (default: `'efectivo'`).
- En la tabla de listado: agregar columna `Clase` mostrando un `<Badge variant="outline">` con `paymentMethod.kind_label`.
- En los diálogos de Crear y Editar: agregar selector `<Select>` para elegir la clase (`kind`) con sus opciones correspondientes.

---

### 2. Capa Backend — Action `ConfirmSalePayment` y Evento

#### [NEW] `app/Actions/Sales/ConfirmSalePayment.php`
- Encapsula la lógica del cobro de la venta atómicamente dentro de `DB::transaction(...)`:
  1. **Validaciones de la venta:**
     - La venta debe estar en estado abierta: `$sale->isOpen()`. Si está confirmada (`SaleStatus::Confirmed`), rechaza: "La venta ya ha sido confirmada.". Si está descartada (`SaleStatus::Discarded`), rechaza: "No se puede cobrar una venta descartada.".
     - La venta debe tener al menos una línea: `$sale->items()->exists()`. Si no tiene líneas, rechaza: "No se puede cobrar una venta sin artículos.".
     - El turno de caja de la venta debe estar abierto: `$sale->cash_session_id !== null` y `$sale->cashSession->status === CashSessionStatus::Open`. Si está cerrado, rechaza: "El turno de caja de la venta no se encuentra abierto.".
     - Bloqueo pesimista con `lockForUpdate()` para prevenir concurrencia y doble confirmación.
  2. **Validaciones de los medios e importes:**
     - `$payments` no puede estar vacío: "Debe ingresar al menos un medio de pago.".
     - Verificar que cada `payment_method_id` exista y esté activo (`is_active = true`). Si está inactivo o no existe, rechaza: "El medio de pago '{nombre}' no está activo o no existe.".
     - Por cada renglón de pago:
       - `amount` debe ser > 0.
       - Si el medio **no es efectivo** (`kind !== PaymentMethodKind::Cash`), no puede tener `tendered_amount > amount`: "Solo el efectivo puede recibir un importe mayor al cobrado.".
       - Si el medio **es efectivo** (`kind === PaymentMethodKind::Cash`):
         - Si se indica `tendered_amount`, debe ser `>= amount`: "El importe entregado en efectivo no puede ser menor al importe a cobrar.". La diferencia `$tendered_amount - $amount` es el vuelto. Si no se indica `tendered_amount`, se asume pago exacto (`tendered_amount = null` o igual a `amount`).
     - **Suma de importes:** Sumar los importes (`amount`) convirtiendo a centavos con `ConvertsMoneyToCents`. Debe coincidir exactamente con `$sale->total_amount`:
       `if ($totalPaymentsCents !== $totalSaleCents)` → rechaza con error: "La suma de los importes ($X) no coincide con el total de la venta ($Y).".
  3. **Generación de Movimientos de Caja:**
     - Por cada renglón de pago, crea un `CashMovement`:
       - `cash_session_id`: `$sale->cash_session_id`
       - `type`: `CashMovementType::Sale` ('venta')
       - `payment_method_id`: `$payment['payment_method_id']`
       - `amount`: `$payment['amount']` (lo que queda en la caja; en efectivo, el importe de la venta, no lo entregado)
       - `sale_id`: `$sale->id`
       - `tendered_amount`: para efectivo, lo entregado por el cliente si se especificó; `null` para otros medios
       - `reason`: `null`
       - `user_id`: `$user->id`
       - `created_at`: `now()`
  4. **Confirmación de la venta:**
     - Actualizar la venta:
       `$sale->update(['status' => SaleStatus::Confirmed, 'confirmed_at' => now()])`
  5. **Integración con EPIC-06 y HU-042:**
     - Si la clase `\App\Actions\Inventory\CreateStockMovementFromSale` existe (implementada en EPIC-06), invocarla pasándole `$sale` y `$user->id`.
     - Si la clase `\App\Actions\Sales\IssueInvoice` existe (implementada en HU-042), invocarla pasándole `$sale` y `$user`.
     - Disparar el evento sincrónico `SaleConfirmed($sale, $user)`. Si cualquiera de estas operaciones falla, la transacción se aborta por completo.
  6. Retorna `$sale` fresco.

#### [NEW] `app/Events/Sales/SaleConfirmed.php`
- Evento estándar de Laravel que transporta `public Sale $sale` y `public User $user`.

---

### 3. Capa Backend — Request, Data Objects & Controlador

#### [NEW] `app/Http/Requests/Sales/ConfirmSalePaymentRequest.php`
- Valida la estructura del payload de cobro:
  - `'payments'` => `['required', 'array', 'min:1']`
  - `'payments.*.payment_method_id'` => `['required', 'integer', 'exists:payment_methods,id']`
  - `'payments.*.amount'` => `['required', 'numeric', 'gt:0']`
  - `'payments.*.tendered_amount'` => `['nullable', 'numeric', 'gt:0']`
- Atributos personalizados en español.

#### [NEW] `app/Data/Sales/SalePaymentData.php`
- DTO de Spatie Data para exponer cada cobro registrado:
  - `id`: int
  - `payment_method_id`: int
  - `payment_method_name`: string
  - `payment_method_kind`: string
  - `payment_method_kind_label`: string
  - `amount`: string
  - `tendered_amount`: ?string
  - `change_amount`: ?string (calculado: `$tendered_amount - $amount`)
  - `created_at`: string
  - `created_at_formatted`: string

#### [MODIFY] `app/Data/Sales/SaleData.php`
- Incorporar en el constructor y en `fromModel()`:
  - `public ?string $confirmed_at`
  - `public ?string $confirmed_at_formatted`
  - `/** @var array<int, SalePaymentData> */ public array $payments`
  - `public ?string $total_tendered`
  - `public ?string $change_amount`
- Cargar `cashMovements.paymentMethod` en `loadMissing` y mapear los pagos de tipo `venta`.

#### [MODIFY] `app/Http/Controllers/Sales/SaleController.php`
- En `show(Sale $sale)`:
  - Cargar y pasar `activePaymentMethods`:
    `'activePaymentMethods' => PaymentMethodData::collect(PaymentMethod::query()->active()->orderBy('name')->get())`
- Agregar método `confirmPayment(ConfirmSalePaymentRequest $request, Sale $sale, ConfirmSalePayment $action): RedirectResponse`:
  - Invoca `$action->handle($sale, $request->validated('payments'), $request->user())`.
  - Redirige `back()->with('success', 'Venta cobrada y confirmada correctamente.')`.

---

### 4. Capa Rutas & Wayfinder

#### [MODIFY] `routes/web.php`
- Dentro del grupo `sales.sales.`:
  - `Route::post('{sale}/confirm-payment', [SaleController::class, 'confirmPayment'])->name('confirm-payment');`
- Ejecutar `php artisan wayfinder:generate` y `npm run types:generate`.

---

### 5. Capa Frontend — Pantalla de Venta (`resources/js/pages/sales/sales/show.tsx`)

#### [MODIFY] `resources/js/pages/sales/sales/show.tsx`
- Recibir prop `activePaymentMethods: App.Data.Sales.PaymentMethodData[]`.
- Estado para el panel de cobro:
  - Lista dinámica de líneas de cobro: `payment_method_id`, `amount`, `tendered_amount`.
  - Cálculos en vivo:
    - `totalSale`: importe total de la venta.
    - `totalPayments`: suma de los `amount` asignados.
    - `remaining`: saldo pendiente (`totalSale - totalPayments`).
    - `vuelto`: cálculo de vuelto para las líneas en efectivo (`tendered_amount - amount`).
  - Atajo **"⚡ Todo en efectivo"**:
    - Selecciona automáticamente el medio con `kind === 'efectivo'`.
    - Asigna el monto total de la venta a ese renglón.
    - Si el usuario ingresa un importe entregado mayor, muestra el vuelto en tiempo real.
  - Agregar / quitar renglones de medios de pago:
    - Permite cobro mixto (ej. Débito $5.000 + Efectivo $3.500).
    - El nuevo renglón se precarga con el saldo restante.
  - Validaciones de UI antes de enviar:
    - Botón "Confirmar cobro" deshabilitado si `remaining !== 0`, si no hay artículos cargados, si el efectivo entregado es menor al cobrado, o si hay medios repetidos/vacíos.
  - Envío mediante `router.post(confirmPayment.url(sale.id), { payments }, ...)` con notificación toast de éxito.
- **Vista de Venta Confirmada:**
  - Cuando `sale.status === 'confirmada'`:
    - Banner visual de estado confirmada con fecha y hora.
    - Ocultar scanner, buscador de artículos y botones de edición/eliminación/descarte.
    - Mostrar panel **"Cobro registrado"**:
      - Tabla con cada medio de pago utilizado, su clase (Badge), el importe cobrado, y en efectivo el entregado y vuelto.
      - Resumen de totales cobrados y vuelto entregado.
    - Botón de acción rápida: "Abrir nueva venta" (crear venta en el turno actual) y "Volver al listado".

---

## Verificación de la Definition of Done

| Criterio | Cómo se verifica |
|---|---|
| Por cada medio usado, medio e importe; en efectivo importe entregado y vuelto | Test en `ConfirmSalePaymentTest` y visualización en la tabla de cobros de `show.tsx`. |
| Solo medios de pago activos | Test rechazando medio inactivo en `ConfirmSalePaymentTest`. El selector de UI solo lista activos. |
| La suma de los importes coincide con el total de la venta; solo efectivo puede recibir de más | Tests de validación: suma insuficiente/excesiva rechazada; medio no efectivo con excedente rechazado; efectivo con entregado menor a cobrado rechazado; cálculo de vuelto correcto. |
| No se cobra venta sin líneas, descartada o ya confirmada | Tests unitarios para cada caso en `ConfirmSalePaymentTest`. Botón deshabilitado en UI. |
| Cada medio genera un `cash_movement` tipo `venta` vinculado a la venta | Aserciones en BD: `CashMovement::where('sale_id', $sale->id)->where('type', 'venta')->get()`. |
| En efectivo el movimiento registra el importe de la venta (lo que queda en caja), no lo entregado | Test de verificación: venta de $8.500 con $10.000 entregados crea movimiento por $3.500 y guarda `tendered_amount = 10000`. |
| Venta confirmada queda inmutable | `$sale->acceptsChanges()` retorna `false`; intentos de modificar líneas o cliente fallan. |
| Operación atómica (cobro, confirmación, stock y factura) | `DB::transaction(...)` envuelve todo; test de rollback verificando que si algo falla dentro de la transacción, no se guarda ningún movimiento ni cambia el estado de la venta. |
| Cada medio tiene `kind` que ordena el arqueo | Selector en ABM de medios de pago probado en `PaymentMethodTest`; persistencia de `kind`. |
| No se carga número de cupón de tarjeta | El formulario de cobro no solicita datos de cupón/lote. |
| Verificación del PO: Venta de $8.500 con $5.000 débito y $10.000 efectivo -> vuelto $6.500 y 2 movimientos ($5.000 y $3.500) | Test específico `test('verificacion del po: cobra venta mixta con vuelto y genera dos movimientos')` emulando el escenario exacto. |

---

## Verification Plan

### Automated Tests
- `php artisan test --compact tests/Feature/Sales/ConfirmSalePaymentTest.php`
- `php artisan test --compact tests/Feature/Sales/PaymentMethodTest.php`
- `php artisan test --compact tests/Feature/Sales`

### Manual Verification
1. Ingresar con usuario cajero con turno de caja abierto.
2. Ir a "Medios de pago" (`/sales/payment-methods`), crear y editar un medio verificando el selector de "Clase" (`kind`).
3. Ir a una venta abierta con artículos por un total de $8.500.
4. Probar atajo "Todo en efectivo" y verificar el cálculo de vuelto en vivo.
5. Probar cobro mixto: agregar Tarjeta Débito por $5.000 y Efectivo por $3.500 con entregado $10.000.
6. Verificar que el saldo restante sea $0 y el vuelto calculado sea $6.500.
7. Confirmar el cobro:
   - Verificar redirección a la venta en modo sólo lectura.
   - Verificar badge "Confirmada".
   - Verificar panel "Cobro registrado" con los dos movimientos, importes y vuelto.
   - Verificar que no se puedan editar ni borrar artículos ni cambiar el cliente.

### CI Checks
- `vendor/bin/pint --dirty --format agent`
- `composer run types:check`
- `npm run types:generate`
- `npm run types:check`
- `npm run lint:check`
- `npm run format:check`
- `composer run ci:check`

---

## Open Questions

Ninguna bloqueante. Se dejan registradas las decisiones arquitectónicas acordadas:
1. **Integración con EPIC-06 y HU-042:** Al ser historias dependientes que se implementan en tareas posteriores del sprint (EPIC-06 y HU-042), `ConfirmSalePayment` establece la invocación dinámica / evento sincrónico dentro de la transacción. Cuando se implementen `CreateStockMovementFromSale` e `IssueInvoice`, se integrarán automáticamente sin romper el contrato atómico.
2. **Atajo de efectivo:** Provee el flujo más rápido para cobros simples habituales de mostrador sin requerir cálculos manuales por parte del cajero.
3. **Inmutabilidad de clase en medios en uso:** Se bloquea el cambio de `kind` si un medio ya tiene movimientos registrados, preservando la integridad histórica de los arqueos de caja.
