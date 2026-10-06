# EPIC-06 — Descontar el stock automáticamente al confirmar la venta

Permite que la confirmación de una venta de mostrador (cobro confirmado en caja, EPIC-04) descuente automáticamente el stock de los artículos involucrados en el depósito del punto de venta, registrando un movimiento inmutable de sistema ("Salida por Venta") vinculado a la venta. Si algún artículo no dispone de existencia suficiente en el sistema, la venta no se bloquea: se confirma igual, las existencias pasan a ser negativas y las líneas afectadas quedan marcadas como "conflicto de stock" con el snapshot del stock previo (`system_quantity`), para su posterior regularización física mediante movimientos manuales (HU-017).

## Criterios de aceptación (de `product-backlog.md`)

1. **Datos:** el movimiento generado toma el tipo de sistema "Salida por Venta", el depósito del punto de venta, los artículos y cantidades de la venta, el usuario y la venta de origen.
2. **Validaciones:**
    - el movimiento se genera una sola vez por venta, al confirmarla.
    - la falta de existencia en el sistema no bloquea la venta: si el artículo está en la caja, se vende.
3. **Comportamiento:**
    - si una línea deja la existencia negativa, el movimiento se registra igual y la línea queda marcada como conflicto de stock, con la existencia que el sistema tenía antes de la venta.
    - el conflicto se resuelve después sobre la salida por venta, regularizando la existencia con un movimiento manual (`HU-017`); los movimientos manuales siguen sin poder dejar existencias negativas.
    - el movimiento es inmutable y queda vinculado a la venta; desde el historial de stock se navega hasta ella.
    - las ventas abiertas o descartadas no mueven stock.
4. **Verificación:** se confirma una venta y se comprueba el egreso en las existencias del depósito de la caja y en el historial de movimientos; se vende un artículo con existencia cero y la venta se confirma, con la línea marcada como conflicto.

> **Dependencia corregida (Sprint Planning 4):** dependía de `HU-042` (factura), pero el egreso ocurre al confirmar la venta con el cobro, que existe antes que la factura.
> **Respuesta del PO (2026-09-28):** si el sistema dice que no hay stock pero el artículo está en la caja, la venta no se bloquea; el conflicto se resuelve en la salida por venta.

---

## Investigación del código existente

| Artefacto | Ubicación | Propósito |
|---|---|---|
| Modelo `Sale` | `app/Models/Sales/Sale.php` | Entidad de venta. Posee relación `pointOfSale` (con su `warehouse_id`), `items`, `status` y relación `stockMovement()` (`hasOne(StockMovement::class)`). |
| Action `ConfirmSalePayment` | `app/Actions/Sales/ConfirmSalePayment.php` | Orquesta la confirmación atómica del cobro (EPIC-04). Ya invoca condicionalmente `app(CreateStockMovementFromSale::class)->handle($lockedSale, $user->id)` dentro de la transacción de confirmación. |
| Action `CreateStockMovementFromVoucher` | `app/Actions/Inventory/CreateStockMovementFromVoucher.php` | Referencia y patrón para movimientos automáticos inmutables generados por otros módulos (`purchase_entry`). |
| Modelo `StockMovement` | `app/Models/Inventory/StockMovement.php` | Cabecera del movimiento de inventario. Posee `sale_id`, relaciones `sale()`, `items()`, `warehouse()`, `type()`, `user()`. Inmutable (`UPDATED_AT = null`). |
| Modelo `StockMovementItem` | `app/Models/Inventory/StockMovementItem.php` | Renglón de movimiento con delta con signo (`quantity`, `decimal:3`) y balance previo (`system_quantity`, `decimal:3`). |
| Modelo `StockMovementType` | `app/Models/Inventory/StockMovementType.php` | Constante `CODE_SALE_EXIT = 'sale_exit'`, `sign = -1`, `isAutomatic() = true`. Seeder `StockMovementTypeSeeder`. |
| Modelo `StockBalance` | `app/Models/Inventory/StockBalance.php` | Saldo actual por artículo y depósito (`decimal:3`). |
| Migración `remove_quantity_check` | `database/migrations/2026_09_28_100009_remove_quantity_check_from_stock_balances.php` | Ya ejecutada en la base. Eliminó el `CHECK (quantity >= 0)` permitiendo balances negativos por venta. |
| Migración `add_sale_id` | `database/migrations/2026_09_28_100010_add_sale_id_to_stock_movements_table.php` | Ya ejecutada en la base. Añadió `sale_id` (nullable, único y con FK restrict) a `stock_movements`. |
| Action `RegisterStockAdjustment` | `app/Actions/Inventory/RegisterStockAdjustment.php` | Acción de movimientos manuales. Ya valida explícitamente en PHP (`$newQuantity < 0`) impidiendo saldos negativos para ajustes manuales. |
| Action `ConsultArticleKardex` | `app/Actions/Inventory/ConsultArticleKardex.php` | Consulta el kardex con sumatoria acumulativa. Requiere seleccionar `system_quantity` y cargar la relación `sale`. |
| Action `ConsultStockMovements` | `app/Actions/Inventory/ConsultStockMovements.php` | Consulta el historial de movimientos de inventario. Requiere eager load de `sale`. |
| Data objects | `app/Data/Inventory/` y `app/Data/Sales/` | DTOs de salida (`StockMovementListData`, `KardexEntryData`, `StockMovementDetailData`, `StockBalanceData`, `SaleData`). |
| Vistas React | `resources/js/pages/inventory/` y `resources/js/pages/sales/` | `movements/index.tsx`, `adjustments/show.tsx`, `stocks/index.tsx`, `sales/show.tsx`. |

---

## Proposed Changes

### Backend — Actions

#### [NEW] `app/Actions/Inventory/CreateStockMovementFromSale.php`
- Acción invocable encargada de generar el movimiento de egreso de stock ("Salida por Venta") a partir de una venta confirmada.
- **Validaciones e Idempotencia:**
  - Bloqueo de la venta mediante `lockForUpdate()`.
  - Verifica si ya existe un `StockMovement` con `sale_id = $sale->id`; si existe, lo retorna directamente garantizando idempotencia.
  - Valida que la venta esté en estado `SaleStatus::Confirmed` (ventas abiertas o descartadas lanzan excepción).
  - Valida que el punto de venta de la venta tenga un depósito asignado (`$sale->pointOfSale->warehouse_id`).
  - Valida que la venta contenga artículos (`$sale->items->isNotEmpty()`).
- **Creación de cabecera:**
  - Obtiene el tipo de movimiento activo `StockMovementType::CODE_SALE_EXIT` (`sign = -1`).
  - Crea `StockMovement` con:
    - `stock_movement_type_id`: ID del tipo "Salida por Venta".
    - `warehouse_id`: ID del depósito del punto de venta (`$sale->pointOfSale->warehouse_id`).
    - `sale_id`: `$sale->id`.
    - `notes`: `"Salida automática por venta #{$sale->id}"`.
    - `user_id`: `$userId`.
    - `created_at`: `now()`.
- **Procesamiento de renglones y existencias:**
  - Para cada artículo vendido en la venta:
    - Asegura la existencia de la fila en `stock_balances` (`insertOrIgnore`).
    - Bloquea la fila del saldo con `lockForUpdate()`.
    - Toma la existencia previa en el sistema: `$systemQuantity = (float) $balance->quantity`.
    - Deriva el delta con signo: `$delta = round($movementType->sign * (float) $item->quantity, 3)` (negativo).
    - Calcula el nuevo saldo: `$newQuantity = round($systemQuantity + $delta, 3)` (sin bloquear si resulta menor a cero).
    - Crea `StockMovementItem` con:
      - `stock_movement_id`: ID del movimiento.
      - `article_id`: ID del artículo.
      - `quantity`: `sprintf('%.3f', $delta)`.
      - `system_quantity`: `sprintf('%.3f', $systemQuantity)`.
    - Actualiza `StockBalance` con `quantity: sprintf('%.3f', $newQuantity)`.
- Retorna el modelo `StockMovement` creado.

#### [MODIFY] `app/Actions/Sales/ConfirmSalePayment.php`
- `ConfirmSalePayment` ya contiene la invocación condicional `app(CreateStockMovementFromSale::class)->handle($lockedSale, $user->id)` dentro de su transacción. Al existir la clase, el flujo queda activo automáticamente sin cambios destructivos. Se confirmará la compatibilidad exacta de tipos y parámetros.

#### [MODIFY] `app/Actions/Inventory/ConsultArticleKardex.php`
- Incluir `stock_movement_items.system_quantity` en la subconsulta `select(...)` del kardex.
- Agregar `'stockMovement.sale'` en la carga ansiosa (`with(...)`).

#### [MODIFY] `app/Actions/Inventory/ConsultStockMovements.php`
- Agregar `'sale'` a la lista de relaciones ansiosas (`with(...)`) para que el listado de movimientos acceda a la venta de origen.

---

### Backend — Modelos

#### [MODIFY] `app/Models/Inventory/StockMovementItem.php`
- Agregar método auxiliar de dominio:
  ```php
  public function hasStockConflict(): bool
  {
      return $this->system_quantity !== null
          && round((float) $this->system_quantity + (float) $this->quantity, 3) < 0;
  }
  ```

#### [MODIFY] `app/Models/Inventory/StockMovement.php`
- Agregar método auxiliar:
  ```php
  public function hasStockConflict(): bool
  {
      return $this->items->contains(fn (StockMovementItem $item) => $item->hasStockConflict());
  }
  ```

---

### Backend — Data Objects (DTOs)

#### [MODIFY] `app/Data/Inventory/StockMovementListData.php`
- Agregar propiedades:
  - `public ?int $sale_id`
  - `public bool $has_conflict`
- En `fromModel`:
  - `sale_id: $movement->sale_id`
  - `has_conflict: $movement->items->some(fn ($item) => $item->system_quantity !== null && round((float) $item->system_quantity + (float) $item->quantity, 3) < 0)`

#### [MODIFY] `app/Data/Inventory/KardexEntryData.php`
- Agregar propiedades:
  - `public ?int $sale_id`
  - `public ?string $system_quantity`
  - `public bool $is_conflict`
- En `fromModel`:
  - `sale_id: $movement->sale_id`
  - `system_quantity: $item->system_quantity !== null ? sprintf('%.3f', (float) $item->system_quantity) : null`
  - `is_conflict: $item->system_quantity !== null && round((float) $item->system_quantity + (float) $item->quantity, 3) < 0`

#### [MODIFY] `app/Data/Inventory/StockMovementItemDetailData.php`
- Agregar propiedades:
  - `public ?string $system_quantity`
  - `public bool $is_conflict`
- En `fromModel`:
  - `system_quantity: $item->system_quantity !== null ? sprintf('%.3f', (float) $item->system_quantity) : null`
  - `is_conflict: $item->system_quantity !== null && round((float) $item->system_quantity + (float) $item->quantity, 3) < 0`

#### [MODIFY] `app/Data/Inventory/StockMovementDetailData.php`
- Agregar propiedades:
  - `public ?int $sale_id`
  - `public bool $has_conflict`
- En `fromModel`:
  - `sale_id: $movement->sale_id`
  - `has_conflict: $movement->items->some(fn ($item) => $item->system_quantity !== null && round((float) $item->system_quantity + (float) $item->quantity, 3) < 0)`

#### [MODIFY] `app/Data/Inventory/StockBalanceData.php`
- Agregar propiedad:
  - `public bool $is_negative`
- En `fromModel`:
  - `is_negative: (float) $stock->quantity < 0`

#### [MODIFY] `app/Data/Sales/SaleData.php`
- Agregar propiedad:
  - `public ?int $stock_movement_id`
- En `fromModel`:
  - Cargar ansiosamente `'stockMovement'`
  - `stock_movement_id: $sale->stockMovement?->id`

---

### Frontend — Páginas y Componentes

#### [MODIFY] `resources/js/pages/inventory/movements/index.tsx`
- **Listado general de movimientos:**
  - Si `movement.has_conflict` es `true`, renderizar Badge de advertencia: `Conflicto de stock`.
  - En la columna de comprobante/detalle: si `movement.sale_id` está presente, mostrar enlace `<Link href={showSale({ sale: movement.sale_id })}>Venta #{movement.sale_id}</Link>`.
- **Vista de Kardex:**
  - Si `entry.is_conflict` es `true`, mostrar Badge de advertencia `Conflicto de stock` indicando existencia previa `Stock previo: {entry.system_quantity}`.
  - Si `entry.sale_id` está presente, mostrar enlace hacia la venta.

#### [MODIFY] `resources/js/pages/inventory/adjustments/show.tsx`
- En la sección "Origin Voucher / Reversal Information":
  - Si `movement.sale_id` está presente, mostrar tarjeta: `Comprobante de origen: Venta #{movement.sale_id}` con enlace directo a la venta.
- Si `movement.has_conflict` es `true`:
  - Mostrar banner destacado de advertencia indicando que el movimiento contiene líneas con conflicto de stock y que se regulariza mediante un movimiento manual de inventario (HU-017).
- En la tabla de artículos:
  - Para renglones con `item.is_conflict`: resaltar la fila y mostrar badge de conflicto con el stock previo en el sistema.

#### [MODIFY] `resources/js/pages/inventory/stocks/index.tsx`
- En la tabla de existencias:
  - Si `stock.is_negative` o `stock.quantity < 0`, resaltar el valor en color de advertencia/destructivo y mostrar Badge `Existencia negativa` o `En conflicto`.

#### [MODIFY] `resources/js/pages/sales/sales/show.tsx`
- En la vista de venta confirmada:
  - Si `sale.stock_movement_id` existe, mostrar en el encabezado/información de la venta un enlace directo al comprobante de stock: `Movimiento de stock #{sale.stock_movement_id}` vinculando a `showAdjustment({ stock_movement: sale.stock_movement_id })`.

---

## Verificación de la Definition of Done

| Criterio | Cómo se verifica |
|---|---|
| Datos del movimiento generado: tipo "Salida por Venta", depósito del punto de venta, artículos, cantidades, usuario y venta de origen. | Test automatizado verificando atributos de `StockMovement` y `StockMovementItem` creados al confirmar una venta. |
| Generación única (idempotencia): una sola vez por venta al confirmarla. | Test automatizado invocando la confirmación y la acción por duplicado, verificando que existe exactamente 1 movimiento y que los saldos no se descuentan doble. |
| Venta sin stock no se bloquea: existencia cero o negativa permite confirmar la venta. | Test confirmando venta de un artículo con stock 0.000; la venta pasa a `confirmada` y el balance queda en `-cantidad`. |
| Línea con saldo negativo queda marcada como conflicto con la existencia previa (`system_quantity`). | Test verificando que el ítem del movimiento guarda `system_quantity = 0.000`, delta negativo y es detectado como conflicto (`is_conflict = true`). |
| Movimientos manuales (`RegisterStockAdjustment`) siguen sin poder dejar existencias negativas. | Test de `RegisterStockAdjustment` intentando restar más de la existencia disponible, verificando que se rechaza con `ValidationException` sin la red del CHECK de base de datos. |
| Inmutabilidad y vinculación venta ↔ movimiento de stock. | Verificación de relación en base de datos (`sale_id`) y enlaces bidireccionales en el frontend (historial a venta y venta a movimiento). |
| Ventas abiertas o descartadas no mueven stock. | Test verificando que una venta abierta o descartada no genera movimiento en `StockMovement`. |

---

## Verification Plan

### Automated Tests
Ejecutar la suite específica mediante Pest:
- `php artisan test --compact tests/Feature/Inventory/StockMovementFromSaleTest.php` (suite nueva para EPIC-06).
- `php artisan test --compact tests/Feature/Sales/ConfirmSalePaymentTest.php` (verificar que los tests de cobro de EPIC-04 siguen pasando e integran el egreso de stock).
- `php artisan test --compact tests/Feature/Inventory/StockAdjustmentTest.php` (verificar que los ajustes manuales siguen rechazando saldos negativos).
- `php artisan test --compact tests/Feature/Inventory/StockMovementHistoryTest.php` (verificar consultas de historial y kardex con las nuevas columnas y enlaces).

### Manual Verification
1. Iniciar sesión como cajero con turno de caja abierto en una sucursal con depósito asignado.
2. Abrir una venta, agregar un artículo con stock conocido (ej. 10 unidades) y confirmar el cobro total. Comprobar que en Existencias (`/inventory/stocks`) el stock disminuyó a 9, y en el Historial (`/inventory/movements`) aparece la "Salida por Venta" con enlace a la venta.
3. Abrir una nueva venta, agregar un artículo con stock 0 y confirmar el cobro. Comprobar que la venta se confirma con éxito, el saldo queda en -1 (resaltado en rojo en Existencias), y el movimiento muestra el badge "Conflicto de stock" con el stock previo en 0.
4. Navegar desde el comprobante de movimiento hasta la venta, y desde la venta confirmada hasta el comprobante de movimiento.

### CI checks
- `vendor/bin/pint --format agent`
- `composer run types:check`
- `npm run types:generate`
- `npm run lint:check`
- `npm run format:check`
- `npm run types:check`
- `php artisan test --compact`
