# HU-026 — Ingresar el stock a partir del comprobante recibido

La historia incorpora el Remito como comprobante de proveedor no valorizado y genera, al
registrarlo, un movimiento automático e inmutable de entrada de stock. La implementación parte de
`master` actualizado, que ya contiene HU-017, HU-037 y HU-038.

## Criterios de aceptación (de `product-backlog.md`)

1. **Datos:** el movimiento generado toma depósito de destino, artículos, cantidades, usuario y
   comprobante de origen.
2. **Validaciones:**
   - El movimiento se genera una sola vez por comprobante y no puede duplicarse.
   - Si el comprobante se anula se genera el movimiento inverso, nunca se borra el original.
3. **Comportamiento:**
   - La entrada de stock queda vinculada al comprobante que la originó y desde el historial de
     movimientos se puede navegar hasta él.
   - El movimiento es inmutable como todos los demás.

## Decisiones de alcance confirmadas

- Solo los comprobantes de tipo `remito` generan stock. Las facturas quedan fuera de HU-026.
- Un remito no genera deuda y admite `total_amount`, `unit_price` y `line_total` mayores o iguales
  a cero.
- El remito nace con estado propio `confirmado`; no reutiliza estados contables como
  `pendiente_imputar` o `pagada`.
- Todo remito debe contener al menos un renglón con artículo de catálogo. Puede incluir renglones
  de concepto, pero estos no generan stock.
- La reversión se vincula estructuralmente al movimiento original mediante
  `reversal_of_movement_id`; `notes` queda solo como descripción legible.

### Resolución recomendada del depósito

- **Remito libre:** el usuario selecciona un depósito activo y este es obligatorio.
- **Remito imputado a una OC:** el depósito se deriva de la OC y se muestra en modo de solo
  lectura; no se confía en un valor diferente enviado por el cliente.
- **Remito imputado a varias OCs:** todas deben pertenecer al mismo depósito. Si hay más de uno,
  se rechaza el alta con un mensaje que indica que deben registrarse remitos separados.
- **Remito con renglones imputados y libres:** todos ingresan al depósito derivado de las OCs.
- El `warehouse_id` resuelto se persiste siempre en `supplier_vouchers`, dejando una fuente única
  para el movimiento y la auditoría posterior.

Esta alternativa evita que una recepción contradiga el destino de su OC, permite remitos libres y
mantiene el invariante del inventario de que un movimiento afecta exactamente un depósito.

## Investigación del código existente

| Artefacto | Ubicación | Propósito relevante |
| --- | --- | --- |
| Criterios de HU-026 | `docs/backlog/product-backlog.md` | Fuente de verdad funcional |
| Alcance y DER del Sprint 3 | `docs/backlog/sprint-backlog-3.md` | Define Remito y `supplier_vouchers.warehouse_id` para remitos libres |
| Tipos, letras y estados | `app/Enums/Purchasing/` | Enums nativos usados por modelo, validación y UI |
| Alta de comprobante | `app/Actions/Purchasing/CreateSupplierVoucher.php` | Transacción que crea ítems, imputa OCs y actualiza costos |
| Anulación | `app/Actions/Purchasing/AnnulSupplierVoucher.php` | Transacción que anula y revierte efectos de HU-037/HU-038 |
| Imputación a OCs | `app/Actions/Purchasing/ImputeSupplierVoucherToPurchaseOrders.php` | Valida cantidades y bloquea renglones de OC |
| OC y depósito | `app/Models/Purchasing/PurchaseOrder.php` | Cada OC tiene un `warehouse_id` obligatorio |
| Ajuste de stock | `app/Actions/Inventory/RegisterStockAdjustment.php` | Patrón transaccional de movimiento, balance y locks |
| Ledger de inventario | `app/Models/Inventory/StockMovement.php` y `StockMovementItem.php` | Movimientos inmutables y deltas con signo |
| Tipos automáticos | `app/Models/Inventory/StockMovementType.php` | Ya existe `purchase_entry` y lista `AUTOMATIC_CODES` |
| Historial | `app/Actions/Inventory/ConsultStockMovements.php` | Query paginada con eager loading |
| DTOs | `app/Data/Purchasing/` y `app/Data/Inventory/` | Shapes tipados para las páginas Inertia |
| Formulario de comprobantes | `resources/js/pages/purchasing/vouchers/create.tsx` | Formulario actual con carga libre e imputación a OCs |
| Detalles e historial | `resources/js/pages/purchasing/vouchers/show.tsx`, `resources/js/pages/inventory/` | Destinos del enlace bidireccional |

### Diferencias y extensiones respecto del DER

El DER contempla `supplier_vouchers.warehouse_id`, pero no modela la relación entre el movimiento
original y su reversión. Se agregará `stock_movements.reversal_of_movement_id` por decisión expresa
de esta HU, ya que la auditoría de una anulación no debe depender de texto libre.

Permitir importes cero también requiere relajar los `CHECK` actuales de `supplier_vouchers` y
`supplier_voucher_items`. Como una restricción de una fila de detalle no puede consultar de forma
portable el tipo de su cabecera, la BD aceptará importes `>= 0`; el Form Request mantendrá `> 0`
para facturas y notas y permitirá cero exclusivamente para remitos.

## Proposed Changes

### Backend — Base de datos

#### [NEW] `database/migrations/<timestamp>_revise_supplier_vouchers_for_hu_026.php`

- Reconstruir de forma portable `supplier_vouchers`, siguiendo la migración de HU-036.
- Incorporar `remito` al `CHECK` de `type`, `R`/`X` al de `letter` y `confirmado` al de `status`.
- Cambiar `total_amount` a `decimal(12,2) check (total_amount >= 0)`.
- Agregar `warehouse_id` nullable, FK a `warehouses` con `restrictOnDelete` e índice.
- Copiar todos los datos existentes sin alterar IDs y restaurar la secuencia PostgreSQL.
- Antes de reconstruir, retirar y luego restaurar todas las FKs que hoy referencian la tabla:
  `voucher_applications`, `payment_order_items` y `supplier_voucher_items`.
- Implementar `down()` reversible siempre que no existan remitos incompatibles con el esquema
  anterior.

#### [NEW] `database/migrations/<timestamp>_allow_zero_amounts_for_remito_items.php`

- Reconstruir `supplier_voucher_items` para usar `unit_price >= 0` y `line_total >= 0`.
- Conservar IDs, posiciones, relaciones, índices y la unicidad `(supplier_voucher_id, position)`.
- Retirar y restaurar la FK de `purchase_order_voucher_imputations` durante la reconstrucción.
- Mantener `quantity > 0`: un remito puede no estar valorizado, pero no puede recibir cero unidades.

#### [NEW] `database/migrations/<timestamp>_link_vouchers_and_reversals_to_stock_movements.php`

- Agregar a `stock_movements`:
  - `supplier_voucher_id`, nullable, FK a `supplier_vouchers`, `restrictOnDelete` y `unique`.
  - `reversal_of_movement_id`, nullable, self-FK a `stock_movements`, `restrictOnDelete` y
    `unique`.
- El movimiento original lleva `supplier_voucher_id`; el inverso lleva
  `reversal_of_movement_id`. La reversión navega al comprobante a través del original.
- Las dos restricciones únicas garantizan un ingreso por comprobante y una sola reversión por
  ingreso incluso ante concurrencia.

### Backend — Seed de tipos de movimiento

#### [MODIFY] `app/Models/Inventory/StockMovementType.php`

- Agregar `CODE_PURCHASE_ENTRY_REVERSAL = 'purchase_entry_reversal'`.
- Incluirlo en `AUTOMATIC_CODES` para impedir su selección manual.

#### [MODIFY] `database/seeders/Inventory/StockMovementTypeSeeder.php`

- Registrar idempotentemente el tipo de sistema **Reversión de entrada por compra**, signo `-1`,
  activo y automático.
- Incluir la ejecución de este seeder en la verificación de despliegue para instalaciones ya
  existentes.

### Backend — Enums y modelos

#### [MODIFY] `app/Enums/Purchasing/SupplierVoucherType.php`

- Agregar `Remito = 'remito'`, etiqueta `Remito`, `isRemito()` y
  `generatesStockMovement()` exclusivo para Remito.
- Hacer que `createsPayableBalance()` retorne `false` para Remito y Nota de crédito.

#### [MODIFY] `app/Enums/Purchasing/SupplierVoucherLetter.php`

- Agregar `R` y `X`.
- Incorporar `forVoucherType()` para devolver R/X en remitos y A/B/C/M en los demás tipos.
- R/X no discriminan IVA.

#### [MODIFY] `app/Enums/Purchasing/SupplierVoucherStatus.php`

- Agregar `Confirmed = 'confirmado'` con etiqueta `Confirmado`.

#### [MODIFY] `app/Models/Purchasing/SupplierVoucher.php`

- Agregar `warehouse_id` al modelo y relaciones `warehouse()` y `stockMovement()`.
- Para Remito, `outstandingAmount()` retorna siempre `0.00` y `isOverdue()` retorna `false`.
- Mantener el saldo derivado sin introducir columnas monetarias calculadas.

#### [MODIFY] `app/Models/Inventory/StockMovement.php`

- Agregar `supplier_voucher_id` y `reversal_of_movement_id` al modelo.
- Agregar relaciones `supplierVoucher()`, `reversalOf()` y `reversal()` con tipos explícitos.

### Backend — Resolución de estado y destino

#### [MODIFY] `app/Actions/Purchasing/ResolveSupplierVoucherStatus.php`

- Retornar `Confirmed` inmediatamente para Remito, antes de validar/importar importes.
- Conservar sin cambios la máquina de estados monetarios de facturas y notas.

#### [NEW] `app/Actions/Purchasing/ResolveRemitoWarehouse.php`

- Recibir el tipo, el `warehouse_id` enviado y los renglones del comprobante.
- Para un comprobante que no sea Remito, retornar `null` y rechazar un depósito enviado.
- Para un Remito libre, bloquear y validar que el depósito seleccionado exista y esté activo.
- Para un Remito imputado, cargar y bloquear las OCs de sus renglones, exigir un único
  `warehouse_id` y devolverlo como destino autoritativo.
- Rechazar OCs de depósitos diferentes con error de validación y rechazar cualquier depósito
  enviado que contradiga al derivado.
- La validación de negocio ocurre dentro de la transacción; el Form Request replica los casos
  detectables para dar feedback temprano.

### Backend — Movimientos automáticos

#### [NEW] `app/Actions/Inventory/CreateStockMovementFromVoucher.php`

- Recibir el Remito persistido y el ID explícito del usuario autenticado.
- Exigir tipo Remito, estado Confirmado, depósito y al menos un ítem con artículo.
- Bloquear el comprobante y devolver el movimiento existente si ya fue generado.
- Resolver el tipo `purchase_entry` activo.
- Crear una cabecera con `supplier_voucher_id`, depósito, usuario y nota descriptiva.
- Para cada ítem con artículo, crear un delta positivo con la cantidad del remito, dejar
  `system_quantity` en `null` y actualizar `stock_balances` bajo `lockForUpdate`.
- Reutilizar el patrón de `RegisterStockAdjustment`; toda la operación participa de la
  transacción exterior de alta del comprobante.
- La unicidad de BD queda como última defensa ante dos ejecuciones concurrentes.

#### [NEW] `app/Actions/Inventory/ReverseStockMovementForVoucher.php`

- Bloquear el movimiento original, sus renglones y los balances involucrados.
- Si ya existe `reversal()`, devolverla sin crear otra.
- Crear el movimiento `purchase_entry_reversal` con el mismo depósito y usuario que ejecuta la
  anulación, y `reversal_of_movement_id` apuntando al original.
- Crear deltas exactamente opuestos a los originales y mantener `system_quantity = null`.
- Rechazar la anulación completa si algún saldo quedaría negativo; la transacción no debe dejar
  el comprobante anulado ni balances parciales.

#### [MODIFY] `app/Actions/Purchasing/CreateSupplierVoucher.php`

- Aceptar el ID del usuario desde el controlador.
- Resolver y persistir el depósito antes de crear el Remito.
- Crear ítems e imputaciones como hoy; HU-038 continuará actualizando costos solo para facturas.
- Tras persistir los renglones, invocar `CreateStockMovementFromVoucher` dentro de la misma
  transacción.
- Si falla el stock, hacer rollback del comprobante, imputaciones, costos, movimiento y balances.

#### [MODIFY] `app/Actions/Purchasing/AnnulSupplierVoucher.php`

- Resolver una sola vez el usuario que anula.
- Antes de confirmar la anulación, revertir el movimiento vinculado cuando exista.
- Mantener la reevaluación de OCs y costos agregada por HU-037/HU-038 en la misma transacción.
- Si la reversión dejaría stock negativo, abortar toda la anulación con mensaje de negocio.

### Backend — Validación y HTTP

#### [MODIFY] `app/Http/Requests/Purchasing/StoreSupplierVoucherRequest.php`

- Incorporar `warehouse_id` al payload normalizado.
- Exigir R/X para Remito y A/B/C/M para los demás comprobantes.
- Prohibir vencimiento y asociación a factura para Remito.
- Para Remito, admitir `total_amount`, `unit_price` y `line_total` con mínimo `0`; conservar
  mínimo `0.01` para los demás tipos.
- Exigir al menos un renglón con `article_id` en Remitos.
- Exigir depósito activo en Remitos libres. En imputados, validar que las OCs pertenezcan a un
  único depósito y que un valor enviado no lo contradiga.

#### [MODIFY] `app/Http/Controllers/Purchasing/SupplierVoucherController.php`

- En `create()`, entregar depósitos activos y las opciones de letras ya existentes; no se agrega
  un endpoint JSON nuevo para letras.
- En `store()`, pasar el ID autenticado a la Action.
- En `show()`, eager-load `warehouse` y `stockMovement.reversal`.

#### [MODIFY] `app/Actions/Inventory/ConsultStockMovements.php`

- Eager-load `supplierVoucher` y `reversalOf.supplierVoucher` para evitar N+1 en el historial.

#### [MODIFY] `app/Http/Controllers/Inventory/StockAdjustmentController.php`

- En `show()`, cargar comprobante de origen, movimiento original y reversión.

No se requieren rutas HTTP nuevas: se reutilizan los `show` existentes de comprobantes y
movimientos. Después de los cambios se regenera Wayfinder para mantener los tipos sincronizados.

### Backend — Data objects

#### [MODIFY] `app/Data/Purchasing/SupplierVoucherData.php`

- Agregar depósito y el ID del movimiento de ingreso y de su eventual reversión.

#### [MODIFY] `app/Data/Purchasing/SupplierVoucherListData.php`

- Soportar el nuevo tipo/estado sin tratar el Remito como deuda ni vencido.

#### [MODIFY] `app/Data/Inventory/StockMovementListData.php`

- Agregar `supplier_voucher_id`, número formateado y datos del movimiento original cuando la fila
  sea una reversión.

#### [MODIFY] `app/Data/Inventory/StockMovementDetailData.php`

- Exponer comprobante de origen, `reversal_of_movement_id` y `reversal_movement_id`.

Tras editar los Data objects se ejecuta `npm run types:generate`.

### Frontend — Formulario de comprobantes

#### [MODIFY] `resources/js/pages/purchasing/vouchers/create.tsx`

- Incorporar Remito y filtrar localmente las letras con R/X.
- Para Remito, ocultar vencimiento y asociación de NC; cambiar los labels monetarios a valores
  opcionales/de referencia y admitir cero.
- Mostrar selector de depósito para Remitos libres.
- Al importar OCs, derivar el depósito: mostrarlo de solo lectura y bloquear la selección de OCs
  pertenecientes a otro depósito.
- Si se quitan todas las imputaciones, volver a habilitar la selección manual del depósito.
- Enviar `warehouse_id` con el formulario, manteniendo al servidor como autoridad final.
- Reutilizar `Select`, `Label`, `Input`, `Badge`, `Card` e `InputError` existentes.

### Frontend — Navegación y trazabilidad

#### [MODIFY] `resources/js/pages/purchasing/vouchers/show.tsx`

- Mostrar depósito y badge `Confirmado` para Remito.
- Mostrar enlace Wayfinder al movimiento de ingreso y, si existe, a su reversión.

#### [MODIFY] `resources/js/pages/inventory/adjustments/show.tsx`

- Mostrar enlace Wayfinder al comprobante de origen.
- En una reversión, mostrar también el movimiento original; en el original, mostrar su reversión
  cuando exista.

#### [MODIFY] `resources/js/pages/inventory/movements/index.tsx`

- Agregar columna `Origen`: Remito enlazado, reversión de movimiento enlazada o `Manual`.
- Usar las funciones generadas de `@/routes/purchasing/vouchers` y
  `@/routes/inventory/adjustments`; no hardcodear URLs.

### Tests

#### [NEW] `tests/Feature/Purchasing/RemitoSupplierVoucherTest.php`

- Alta de Remito R/X con importes positivos y con todos los importes en cero.
- Rechazo de letras fiscales, vencimiento, asociación a factura y Remito sin artículos.
- Estado `confirmado`, saldo `0.00`, exclusión de deuda, mora y órdenes de pago.
- Remito libre requiere depósito activo.
- Remito imputado deriva el depósito de la OC.
- Varias OCs del mismo depósito son admitidas; OCs de depósitos distintos se rechazan.
- Un depósito enviado que contradiga la OC se rechaza.
- Facturas y notas conservan importes estrictamente mayores a cero y no generan stock.

#### [NEW] `tests/Feature/Inventory/StockMovementFromVoucherTest.php`

- Alta atómica del ingreso, cabecera, renglones, usuario y balances.
- Las cantidades incluyen renglones imputados y excedentes aceptados tal como quedaron
  persistidos en el Remito; los conceptos libres se excluyen.
- Segunda invocación y ejecuciones concurrentes no duplican el ingreso.
- Una falla revierte comprobante, imputaciones y balances completos.
- Anulación genera un único movimiento inverso, conserva el original y devuelve los balances.
- La FK `reversal_of_movement_id` conecta ambos movimientos.
- La anulación falla atómicamente si el stock disponible es insuficiente.
- No existen rutas de edición o eliminación de movimientos.

#### [MODIFY] Tests existentes de Compras e Inventario

- Actualizar datasets exhaustivos de enums, schema checks, opciones y seeders.
- Cubrir la reconstrucción portable de las tablas y las restricciones únicas de origen/reversión.
- Verificar los enlaces del comprobante, detalle de movimiento e historial Inertia.
- Mantener verdes los casos de HU-037 y HU-038 que comparten `CreateSupplierVoucher` y
  `AnnulSupplierVoucher`.

## Verificación de la Definition of Done

| Criterio | Cómo se verifica |
| --- | --- |
| Depósito, artículos, cantidades, usuario y comprobante | Test integral de alta y assertions de BD |
| Un solo ingreso por comprobante | Test idempotente/concurrente + `UNIQUE(supplier_voucher_id)` |
| Anulación inversa sin borrar original | Tests de reversión y balances |
| Auditoría estructural | FK única `reversal_of_movement_id` y navegación entre filas |
| Enlace desde historial | Assertions de props Inertia y enlaces Wayfinder |
| Inmutabilidad | Ausencia de rutas de update/delete y schema sin `updated_at` |
| Remito sin deuda y con importes cero | Tests de estado, saldo, pagos y validación |
| Atomicidad | Tests de rollback ante fallo de stock o validación |
| Compatibilidad con HU-037/HU-038 | Suites existentes de imputación, costos y cierre de OC |

## Verification Plan

### Automated Tests

- Ejecutar primero:
  - `php artisan test --compact tests/Feature/Purchasing/RemitoSupplierVoucherTest.php`
  - `php artisan test --compact tests/Feature/Inventory/StockMovementFromVoucherTest.php`
- Ejecutar las suites afectadas de comprobantes, imputación, costos, stock, schema, pagos y
  cuenta corriente.
- Ejecutar finalmente `php artisan test --compact`.

### Manual Verification

1. Registrar un Remito libre con valores cero y verificar ingreso al depósito elegido.
2. Registrar un Remito desde una OC y comprobar que el depósito se deriva y no puede cambiarse.
3. Intentar combinar OCs de depósitos distintos y comprobar el rechazo.
4. Navegar Remito → movimiento → Remito desde los detalles y el historial.
5. Anular el Remito y comprobar movimiento inverso, trazabilidad y saldo restaurado.
6. Consumir parte del stock e intentar anular para comprobar rollback y mensaje de stock
   insuficiente.
7. Confirmar que el Remito no aparece como deuda ni como comprobante pagable.

### CI checks

- `vendor/bin/pint --dirty --format agent`
- `composer run types:check`
- `npm run types:generate`
- `php artisan wayfinder:generate --no-interaction`
- `npm run lint:check`
- `npm run format:check`
- `npm run types:check`
- `composer run ci:check`
