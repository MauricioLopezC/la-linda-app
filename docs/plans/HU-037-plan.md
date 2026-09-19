# HU-037 — Imputar el comprobante a una o varias órdenes de compra

**Módulo:** Compras (`Purchasing` · `CMP-05`) · **Estimación:** 5 SP · **Estado:** Planificación · **Sprint:** 3 · **Depende de:** HU-036, HU-033

Permite vincular un comprobante de proveedor (Factura, Remito o Nota de Débito) con una o varias órdenes de compra emitidas del mismo proveedor, detallando las cantidades recibidas por cada artículo. El sistema controla en tiempo real las cantidades pedidas, recibidas y pendientes de cada orden, admite entregas parciales, permite la aceptación de mercadería excedente (ej. pesables en carnicería o granel) registrándola con exactitud contable y de inventario, y habilita comprobantes libres sin orden previa.

---

## Criterios de Aceptación (de `product-backlog.md`)

1. **Datos:** Órdenes de compra imputadas, y detalle con artículo y cantidad recibida.
2. **Validaciones:**
   - Un comprobante puede imputarse a una o varias órdenes y una orden puede recibir uno o varios comprobantes (relación N:N).
   - Solo se pueden imputar órdenes del mismo proveedor y en estado `emitida`.
   - La cantidad recibida imputada a la orden salda el pendiente sin superar el límite presupuestario de la orden de compra.
   - Solo se pueden recibir artículos que figuren en alguna de las órdenes imputadas (cuando se imputa a OCs).
3. **Comportamiento:** Por cada orden imputada se muestra lo pedido, lo ya recibido en comprobantes anteriores y lo que queda pendiente.
4. **Verificación:** Se imputa un comprobante a dos órdenes del mismo proveedor y se comprueba que el pendiente de cada una queda correctamente descontado.
5. **Regla de negocio para pesables y excedentes:** Cuando el proveedor entrega una cantidad superior a la pendiente de la OC (habitual en carnicería, verdulería y pesables donde no se pueden fraccionar piezas exactas), el sistema permite aceptar el excedente. La cantidad imputada salda el renglón de la OC hasta cubrir su saldo pendiente (sin alterar el presupuesto original), y la diferencia se registra como excedente aceptado (`quantity_excess`). Tanto lo imputado como el excedente ingresan al stock físico real y se totalizan en el comprobante a pagar al proveedor.

---

## Investigación del Código Existente

| Componente | Archivo de Referencia | Patrón a Replicar |
| :--- | :--- | :--- |
| **Modelos & Tablas Relacionadas** | `app/Models/Purchasing/PurchaseOrder.php`, `PurchaseOrderItem.php`, `SupplierVoucher.php`, `SupplierVoucherItem.php` | Esquema transaccional con montos decimales y estados mediante enums (`PurchaseOrderStatus`, `SupplierVoucherStatus`). |
| **Acciones de Creación de Comprobantes** | `app/Actions/Purchasing/CreateSupplierVoucher.php`, `AssociateCreditNoteToInvoice.php` | Mutaciones atómicas con `DB::transaction()` y `lockForUpdate()`, delegando reglas de negocio específicas en acciones dedicadas. |
| **DTOs (Data Objects)** | `app/Data/Purchasing/PurchaseOrderData.php`, `PurchaseOrderItemData.php`, `SupplierVoucherData.php` | Clases tipadas `Spatie\LaravelData\Data` para transportar información hacia Inertia y Wayfinder. |
| **Form Requests** | `app/Http/Requests/Purchasing/StoreSupplierVoucherRequest.php` | Validación estricta en el servidor con mensajes en español y sanitización de datos. |
| **UI de Comprobantes** | `resources/js/pages/purchasing/vouchers/create.tsx` | Selector reactivo de proveedor, tabla dinámica de ítems con autocompletado y cálculo reactivo de subtotales. |
| **UI de Órdenes de Compra** | `resources/js/pages/purchasing/orders/show.tsx` | Visualización en tarjetas y tablas con badges de estado, desglose de ítems e historial. |

---

## Decisiones de Arquitectura y Diseño de Datos

1. **Nueva Tabla Pivote `purchase_order_voucher_imputations`**:
   Conforme al DER formal de `sprint-backlog-3.md`:
   - `id`: bigint primary key.
   - `purchase_order_item_id`: FK a `purchase_order_items`, `onDelete('restrict')`.
   - `supplier_voucher_item_id`: FK a `supplier_voucher_items`, `onDelete('cascade')`.
   - `quantity_received`: `decimal(12, 3) check (quantity_received > 0)` (cantidad aplicada a saldar la OC, hasta el saldo pendiente).
   - `quantity_excess`: `decimal(12, 3) not null default 0 check (quantity_excess >= 0)` (excedente físico aceptado).
   - `created_at`: timestamp inmutable (`timestamps = false`, solo `created_at`).
   - Restricción de unicidad: `UNIQUE(purchase_order_item_id, supplier_voucher_item_id)`.

2. **Cálculo Derivado de Cantidades y Excedentes**:
   - Siguiendo la regla de oro del proyecto (ver `.ai/rules/purchasing.md` sobre saldos derivados), las cantidades de un renglón de orden de compra no se persisten como columnas fijas, sino que se derivan de las imputaciones vigentes:
     - `quantityReceived()` = $\sum \text{quantity\_received}$ de comprobantes no anulados (`status != 'cancelada'`).
     - `quantityPending()` = $\max(0, \text{quantity} - \text{quantityReceived()})$.
     - `quantityExcess()` = $\sum \text{quantity\_excess}$ de comprobantes no anulados.
   - Esto garantiza que si un comprobante se anula (`AnnulSupplierVoucher`), el pendiente de la orden se restaura automáticamente en tiempo real sin riesgo de desincronizaciones.

3. **Manejo Integral de Excedentes (Pesables / Carnicería)**:
   - Si el proveedor entrega $104.250$ kg de un artículo que tenía $100.000$ kg pendientes en la OC:
     - En el renglón del comprobante (`supplier_voucher_items`): `quantity = 104.250`. Se calcula el total a pagar y el stock real sobre los $104.250$ kg.
     - En la imputación (`purchase_order_voucher_imputations`): `quantity_received = 100.000` (cubre la OC al 100%) y `quantity_excess = 4.250` (audita el excedente aceptado).
     - La OC pasa a cumplida sin saldos negativos y el inventario recibe las $104.250$ unidades reales.
   - En la interfaz de alta, si la cantidad ingresada supera el pendiente, el sistema muestra un indicador visual informativo (ej. `⚠️ Excedente: +4.25 kg`) y permite la carga fluida sin bloquear al operador.

4. **Imputación Opcional y Flexible**:
   - Si no se seleccionan órdenes de compra, el comprobante se registra libremente (compra directa, factura de servicios o remito directo).
   - Si se seleccionan órdenes de compra, el usuario puede importar los renglones pendientes con un solo clic o elegir qué renglones imputar.

5. **Transición a Estado `Cumplida` (Preparación para HU-038)**:
   - Se incorpora el método `isFullyReceived()` en `PurchaseOrder`.
   - Se agrega el caso `Fulfilled = 'cumplida'` en `PurchaseOrderStatus`.

---

## Proposed Changes

### Backend — Base de Datos & Migraciones

#### [NEW] `database/migrations/2026_09_19_000000_create_purchase_order_voucher_imputations_table.php`
- Crea la tabla `purchase_order_voucher_imputations` con:
  - `purchase_order_item_id` (FK a `purchase_order_items`, `restrictOnDelete`)
  - `supplier_voucher_item_id` (FK a `supplier_voucher_items`, `cascadeOnDelete`)
  - `quantity_received` (`decimal(12, 3) check (quantity_received > 0)`)
  - `quantity_excess` (`decimal(12, 3) default 0 check (quantity_excess >= 0)`)
  - `created_at` (timestamp inmutable)
  - `UNIQUE(purchase_order_item_id, supplier_voucher_item_id)`

### Backend — Modelos & Enums

#### [NEW] [`app/Models/Purchasing/PurchaseOrderVoucherImputation.php`](file:///c:/Chiara/Proyectos/la-linda-app/app/Models/Purchasing/PurchaseOrderVoucherImputation.php)
- Modelo con relaciones `belongsTo(PurchaseOrderItem)` y `belongsTo(SupplierVoucherItem)`.

#### [MODIFY] [`app/Enums/Purchasing/PurchaseOrderStatus.php`](file:///c:/Chiara/Proyectos/la-linda-app/app/Enums/Purchasing/PurchaseOrderStatus.php)
- Agregar caso `Fulfilled = 'cumplida'` (`'Cumplida'`).

#### [MODIFY] [`app/Models/Purchasing/PurchaseOrderItem.php`](file:///c:/Chiara/Proyectos/la-linda-app/app/Models/Purchasing/PurchaseOrderItem.php)
- Relación `imputations(): HasMany`.
- Métodos `quantityReceived(): string`, `quantityPending(): string`, `quantityExcess(): string`.

#### [MODIFY] [`app/Models/Purchasing/PurchaseOrder.php`](file:///c:/Chiara/Proyectos/la-linda-app/app/Models/Purchasing/PurchaseOrder.php)
- Relaciones con imputaciones y comprobantes vinculados.
- Método `isFullyReceived(): bool`.
- Scope `issued(Builder $query)` para filtrar OCs imputables.

#### [MODIFY] [`app/Models/Purchasing/SupplierVoucherItem.php`](file:///c:/Chiara/Proyectos/la-linda-app/app/Models/Purchasing/SupplierVoucherItem.php)
- Relación `imputations(): HasMany`.
- Relación `purchaseOrderItems(): BelongsToMany`.

### Backend — Lógica de Negocio (Actions)

#### [NEW] [`app/Actions/Purchasing/ImputeSupplierVoucherToPurchaseOrders.php`](file:///c:/Chiara/Proyectos/la-linda-app/app/Actions/Purchasing/ImputeSupplierVoucherToPurchaseOrders.php)
- Valida que:
  - Todas las OCs referenciadas pertenezcan al mismo proveedor del comprobante.
  - Todas las OCs estén en estado `emitida`.
  - El artículo del renglón del comprobante coincida con el artículo del renglón de la OC.
- Desglosa la cantidad recibida:
  - `quantity_received` = $\min(\text{cantidad ingresada}, \text{saldo pendiente})$.
  - `quantity_excess` = $\max(0, \text{cantidad ingresada} - \text{saldo pendiente})$.
- Persiste los registros en `purchase_order_voucher_imputations`.

#### [MODIFY] [`app/Actions/Purchasing/CreateSupplierVoucher.php`](file:///c:/Chiara/Proyectos/la-linda-app/app/Actions/Purchasing/CreateSupplierVoucher.php)
- Integra la ejecución de `ImputeSupplierVoucherToPurchaseOrders` dentro de la transacción atómica.

### Backend — DTOs & Controladores

#### [NEW] [`app/Data/Purchasing/PurchaseOrderImputableData.php`](file:///c:/Chiara/Proyectos/la-linda-app/app/Data/Purchasing/PurchaseOrderImputableData.php)
- DTO para serializar órdenes de compra elegibles con sus renglones e importes pendientes.

#### [MODIFY] [`app/Data/Purchasing/PurchaseOrderItemData.php`](file:///c:/Chiara/Proyectos/la-linda-app/app/Data/Purchasing/PurchaseOrderItemData.php)
- Incorpora campos `quantity_received`, `quantity_pending` y `quantity_excess`.

#### [MODIFY] [`app/Data/Purchasing/PurchaseOrderData.php`](file:///c:/Chiara/Proyectos/la-linda-app/app/Data/Purchasing/PurchaseOrderData.php)
- Incorpora lista de comprobantes imputados con número, tipo, fecha, cantidad imputada y excedente.

#### [MODIFY] [`app/Http/Requests/Purchasing/StoreSupplierVoucherRequest.php`](file:///c:/Chiara/Proyectos/la-linda-app/app/Http/Requests/Purchasing/StoreSupplierVoucherRequest.php)
- Acepta `purchase_order_item_id` opcional en cada ítem de `items.*`.

#### [MODIFY] [`app/Http/Controllers/Purchasing/SupplierVoucherController.php`](file:///c:/Chiara/Proyectos/la-linda-app/app/Http/Controllers/Purchasing/SupplierVoucherController.php)
- Agrega endpoint JSON `associablePurchaseOrders(Request $request)` para consultar las OCs emitidas del proveedor con saldos pendientes.

### Rutas (`routes/web.php`)

- Ruta `GET /purchasing/vouchers/associable-purchase-orders` (`purchasing.vouchers.associable-purchase-orders`).

### Frontend (Inertia + React)

#### [MODIFY] [`resources/js/pages/purchasing/vouchers/create.tsx`](file:///c:/Chiara/Proyectos/la-linda-app/resources/js/pages/purchasing/vouchers/create.tsx)
- Al elegir un proveedor, consulta reactivamente las OCs emitidas con saldo pendiente.
- Selector de órdenes de compra con resumen de ítems pendientes y botón para precargar automáticamente los renglones.
- Indicador visual en la fila del ítem mostrando la OC imputada y detección de excedente (ej. `⚠️ Excedente: +4.25 kg`) informando que se ingresará al stock y a la factura sin bloquear el guardado.

#### [MODIFY] [`resources/js/pages/purchasing/orders/show.tsx`](file:///c:/Chiara/Proyectos/la-linda-app/resources/js/pages/purchasing/orders/show.tsx)
- Columnas en tabla de artículos: **Pedido**, **Recibido**, **Excedente** y **Pendiente**.
- Sección con el historial de comprobantes imputados a la orden.

---

## Verificación de la Definition of Done

| Criterio de Aceptación / DoD | Cómo se verifica |
| :--- | :--- |
| Imputación N:N comprobante ↔ OCs | Test: un comprobante imputando renglones de 2 OCs distintas. |
| Validación de proveedor idéntico | Test: rechazo con 422 si se intenta imputar una OC de otro proveedor. |
| Validación de estado emitida | Test: rechazo con 422 si la OC está en borrador o cancelada. |
| Aceptación y registro de excedente | Test: ingreso de 104 kg para una orden de 100 kg registra 100 en `quantity_received`, 4 en `quantity_excess`, y salda la orden a 0 pendiente. |
| Comprobante libre sin OC | Test: alta exitosa de comprobante sin ninguna OC vinculada. |
| Reversión automática por anulación | Test: anular el comprobante restaura el pendiente de la OC y descuenta el excedente. |
| Visualización en consulta de OC | Verificación en `orders/show.tsx` de columnas Pedido, Recibido, Excedente y Pendiente. |
| Calidad de código y Linters | `vendor/bin/pint`, `composer run types:check`, `npm run types:check`, `npm run lint:check`, `npm run format:check`. |

---

## Verification Plan

### Automated Tests
- Archivo de pruebas de Feature: `tests/Feature/Purchasing/PurchaseOrderVoucherImputationTest.php`:
  1. `test('can impute a voucher to a single purchase order item')`
  2. `test('can impute a single voucher to multiple purchase orders from the same supplier')`
  3. `test('multiple vouchers can partially impute the same purchase order')`
  4. `test('cannot impute a purchase order from a different supplier')`
  5. `test('cannot impute a purchase order that is not in issued status')`
  6. `test('accepts surplus quantities by capping order receipt and recording excess')`
  7. `test('can register a free voucher without any purchase order imputation')`
  8. `test('annulling a voucher restores the pending quantity of the imputed purchase order')`
  9. `test('purchase order show displays requested, received, excess and pending quantities')`
- Ejecución:
  ```powershell
  php artisan test --compact --filter=PurchaseOrderVoucherImputationTest
  ```

### Manual Verification
1. Consultar una OC emitida por 100 kg en `/purchasing/orders/{id}`.
2. Ir a `/purchasing/vouchers/create`, seleccionar el proveedor de la orden.
3. Importar el artículo de la orden e ingresar 104.25 kg recibidos.
4. Constatar la notificación visual de excedente (+4.25 kg) y guardar el comprobante.
5. Volver a la orden de compra: comprobar que muestra 100 kg pedidos, 100 kg recibidos, 4.25 kg de excedente y 0 kg pendientes.
6. Anular el comprobante y verificar que el pendiente de la orden vuelve a 100 kg.

### CI Checks
- `vendor/bin/pint --format agent`
- `composer run types:check`
- `npm run types:check`
- `npm run lint:check`
- `npm run format:check`
- `php artisan test --compact`

---

## Open Questions

*No quedan preguntas abiertas.* La regla para pesables y excedentes ha quedado formalizada en los documentos de alcance (`product-backlog.md` y `sprint-backlog-3.md`) y en este plan técnico.
