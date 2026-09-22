# HU-038 — Actualizar el último costo y cerrar la orden cubierta

**Módulo:** Compras (`Purchasing` · `CMP-05`) · **Estimación:** 3 SP · **Estado:** Planificación · **Sprint:** 3 · **Alcance:** `CMP-05` · **Depende de:** HU-037, HU-015

---

## 1. Resumen del contenido y de lo que se va a realizar y cómo se va a realizar la HU-038

### Contexto y Necesidad
En el circuito de compras de *Supermercados La Linda*, actualmente el registro de facturas de proveedores no actualiza de manera automática el costo unitario pactado con cada proveedor en el catálogo (`article_supplier.last_cost`), exigiendo mantenimiento manual o desactualizando la referencia de costos de reposición. Asimismo, las órdenes de compra (OC) imputadas requieren una gestión manual o incompleta para transicionar su estado una vez que la mercadería ha sido completamente recibida.

### Objetivo de la HU-038
Automatizar dos comportamientos clave del módulo de Compras:
1. **Actualización del Último Costo:** Al registrar un comprobante de compra valorizado (Factura), el sistema actualizará de manera automática e inmediata el campo `last_cost` en la relación artículo-proveedor (`article_supplier`) para cada artículo facturado cuyo precio unitario sea mayor a cero.
2. **Cierre Automático y Reversión de la Orden de Compra:** Al imputarse recepciones a renglones de una OC (HU-037), el sistema evaluará si el saldo pendiente de todas sus líneas es cero. Si la cobertura es total, la orden pasará automáticamente a estado `cumplida`. Si la cobertura es parcial, permanecerá en `emitida` reflejando su saldo pendiente actualizado. Si posteriormente se anula un comprobante que había cumplido la orden, la OC retornará automáticamente a estado `emitida`.

### Cómo se va a realizar
La solución se implementa a nivel de backend mediante dos **Actions** de dominio invocables e independientes bajo `App\Actions\Purchasing`:
- `UpdateLastPurchaseCost`: orquesta la actualización y recálculo de costos en `article_supplier` según el orden de registro de las facturas.
- `EvaluatePurchaseOrderFulfillment`: centraliza y unifica la evaluación de saldos pendientes y transición de estados de las órdenes de compra.

Ambas acciones se integran de forma atómica dentro de las transacciones de base de datos de:
- `CreateSupplierVoucher` (al registrar el comprobante y asentar las imputaciones).
- `ImputeSupplierVoucherToPurchaseOrders` (al vincular recepciones contra OCs).
- `AnnulSupplierVoucher` (al anular comprobantes, revirtiendo estados de OC y recalculando costos al comprobante previo).

---

## 2. Criterios de Aceptación (de `product-backlog.md`)

1. **Datos:** Último costo de compra conocido por artículo y proveedor (HU-015), estado de la orden de compra.
2. **Validaciones:** El último costo se toma del comprobante registrado, nunca se carga manualmente.
3. **Comportamiento:**
   - Al registrar el comprobante se actualiza el último costo de compra de cada artículo para ese proveedor.
   - La orden pasa a estado `cumplida` cuando todas sus líneas quedan totalmente cubiertas.
   - Una orden cubierta solo parcialmente permanece `emitida`, con su pendiente actualizado.

---

## 3. Persistencia y Dominio

### Esquema de Base de Datos y Modelos Involucrados

No se requieren nuevas migraciones ni tablas adicionales, ya que el modelo de datos fue preparado en las historias predecesoras:

1. **Tabla pivote `article_supplier` (HU-015):**
   - Columnas: `id`, `article_id`, `supplier_id`, `supplier_article_code`, `supplier_article_code_normalized`, `last_cost`, `notes`, `created_at`, `updated_at`.
   - Restricciones:
     - `UNIQUE(article_id, supplier_id)`
     - `UNIQUE(supplier_id, supplier_article_code_normalized)`
     - `CHECK (last_cost is null or last_cost > 0)`
   - Modelo: `App\Models\Catalog\ArticleSupplier` (Pivot).

2. **Tabla `purchase_orders` y `purchase_order_items` (HU-033 / HU-037):**
   - Estado: `PurchaseOrderStatus` (`Draft = 'borrador'`, `Issued = 'emitida'`, `Fulfilled = 'cumplida'`, `Cancelled = 'cancelada'`).
   - Renglones: `PurchaseOrderItem` con relación a `imputations` (`purchase_order_voucher_imputations`).
   - Métodos dinámicos de dominio (regla de saldo derivado, nunca columna fija):
     - `quantityReceived()`: $\sum \text{quantity\_received}$ de comprobantes no cancelados.
     - `quantityPending()`: $\max(0, \text{quantity} - \text{quantityReceived()})$.
     - `isFullyReceived()`: true cuando `quantityPending() <= 0.0001`.
   - En `PurchaseOrder`:
     - `isFullyReceived()`: true cuando **todas** sus líneas cumplen `isFullyReceived()`.

3. **Tabla `supplier_vouchers` y `supplier_voucher_items` (HU-036 / HU-037):**
   - Tipos: `SupplierVoucherType` (`Invoice = 'factura'`, `CreditNote = 'nota_credito'`, `DebitNote = 'nota_debito'`).
   - `SupplierVoucherItem`: posee `article_id` (nullable para líneas conceptuales), `quantity`, `unit_price`, `line_total`.

---

## 4. Cambios de la Implementación

### Backend — Nuevas Actions (`app/Actions/Purchasing/`)

#### [NEW] `app/Actions/Purchasing/UpdateLastPurchaseCost.php`
- **Responsabilidad:** Actualizar el costo en `article_supplier` a partir de un comprobante valorizado.
- **Lógica de negocio:**
  - Verifica que `$voucher->type->isInvoice()`.
  - Itera sobre `$voucher->items`:
    - Si `article_id === null`: ignora (línea conceptual: flete, ajuste, etc.).
    - El alta normal exige `unit_price > 0`; no se plantea un caso de precio cero porque la validación actual lo rechaza.
    - Bloquea la relación en `article_supplier` con `lockForUpdate()`.
    - Si existe la relación `(article_id, supplier_id)`:
      - Actualiza `last_cost = $item->unit_price` y marca timestamp de actualización. Si un mismo artículo aparece varias veces en la factura, prevalece el último renglón (`position` mayor).
    - Si NO existe la relación previa:
      - Rechaza el alta con un error de validación que indique que primero se debe asociar el artículo con el proveedor y registrar su código propio. No sustituirlo por `article.internal_code`.
    - Aplica también a facturas libres, sin imputación a una OC. La última factura **registrada** fija el costo, aunque tenga una fecha de emisión anterior a otra factura ya cargada.
  - Método `recalculateForAnnulledVoucher(SupplierVoucher $voucher): void`:
    - Al anularse una factura, para cada artículo facturado busca la última factura **registrada** no anulada (`supplier_vouchers.id desc`) de ese proveedor y artículo; dentro de esa factura prevalece el último renglón (`position desc`). Esto evita que anular una factura antigua cambie el costo fijado por otra posterior.
    - Si existe factura vigente: actualiza `last_cost` con dicho `unit_price`.
    - Si no existe ninguna factura vigente: retorna `last_cost` a `null`. El costo manual anterior no puede reconstruirse porque el esquema actual no conserva su origen.

#### [NEW] `app/Actions/Purchasing/EvaluatePurchaseOrderFulfillment.php`
- **Responsabilidad:** Evaluar el estado de una o varias órdenes de compra y aplicar la transición correspondiente.
- **Lógica de negocio:**
  - `handle(PurchaseOrder|int $order): PurchaseOrder`:
    - Carga la orden con `lockForUpdate()`.
    - Si la orden está en estado terminal o inmutable (`isCancelled()` o `isDraft()`): no la modifica.
    - Si la orden está `Issued` (`'emitida'`) y `isFullyReceived()` es `true`:
      - Transiciona `status` a `PurchaseOrderStatus::Fulfilled` (`'cumplida'`).
      - Registra log de auditoría.
    - Si la orden está `Fulfilled` (`'cumplida'`) y `isFullyReceived()` es `false`:
      - Transiciona `status` a `PurchaseOrderStatus::Issued` (`'emitida'`).
      - Registra log de auditoría.
    - Si la orden está `Issued` y su cobertura es parcial:
      - Mantiene el estado `Issued`.
  - `handleMany(array $orderIds): void`:
    - Itera sobre la lista de órdenes afectadas asegurando atomicidad y evaluación individual.

### Backend — Modificaciones a Componentes Existentes

#### [MODIFY] `app/Actions/Purchasing/CreateSupplierVoucher.php`
- Inyectar `UpdateLastPurchaseCost`.
- Invocar `$this->updateLastPurchaseCost->handle($voucher)` dentro de la transacción atómica, garantizando que todo comprobante valorizado recién emitido actualice los costos inmediatamente.

#### [MODIFY] `app/Actions/Purchasing/ImputeSupplierVoucherToPurchaseOrders.php`
- Inyectar `EvaluatePurchaseOrderFulfillment`.
- Reemplazar el bloque provisorio inline de evaluación por `$this->evaluateFulfillment->handleMany(array_keys($affectedOrders))`.

#### [MODIFY] `app/Actions/Purchasing/AnnulSupplierVoucher.php`
- Inyectar `EvaluatePurchaseOrderFulfillment` y `UpdateLastPurchaseCost`.
- Reemplazar el bloque inline de reapertura de OCs por `$this->evaluateFulfillment->handleMany($affectedOrders->pluck('id')->all())`.
- Invocar `$this->updateLastPurchaseCost->recalculateForAnnulledVoucher($voucher)` cuando se anule una Factura.

#### [MODIFY] Asociación artículo-proveedor (HU-015)
- Quitar `last_cost` de los formularios de alta/edición de la asociación en las fichas de artículo y proveedor, y de los Form Requests y Actions correspondientes. El código propio del proveedor y las observaciones siguen editables.
- Conservar los costos ya almacenados hasta que una factura los reemplace. Al anularse la última factura vigente, aplicar la regla de recálculo anterior.

---

## 5. Alta, Consulta y Anulación

| Operación | Comportamiento HU-038 |
| :--- | :--- |
| **Alta de Comprobante (Factura)** | 1. Exige una asociación previa para cada artículo facturado; admite facturas sin OC.<br>2. Valida e inserta comprobante e imputaciones.<br>3. `UpdateLastPurchaseCost` toma el `unit_price` de cada artículo y actualiza `article_supplier.last_cost`.<br>4. `EvaluatePurchaseOrderFulfillment` revisa las OCs imputadas: si cubrió el 100%, la OC pasa a `cumplida`; si es parcial, queda `emitida`. |
| **Alta de Comprobante (No Factura)** | En Notas de Crédito y Notas de Débito no se altera `last_cost`. Las OCs imputadas se evalúan normalmente respecto a cantidades físicas. El tipo Remito pertenece a HU-026 y todavía no existe en este checkout; se integrará cuando esté disponible. |
| **Consulta de Órdenes de Compra** | La consulta de OC (en listado y detalle `/purchasing/orders/{order}`) ya expone el badge de estado `cumplida` / `emitida` y el desglose de ítems (`quantity`, `quantity_received`, `quantity_pending`, `quantity_excess`). No requiere modificaciones, el estado se reflejará fielmente. |
| **Consulta de Catálogo de Artículos** | Las pestañas y modales de proveedores en la ficha del artículo (`/catalog/articles`) y artículos en la ficha de proveedor (`/purchasing/suppliers`) reflejan de inmediato el `last_cost` actualizado sin intervención del usuario. |
| **Anulación de Comprobante (`AnnulSupplierVoucher`)** | 1. El comprobante pasa a `cancelada`.<br>2. Las imputaciones dejan de computar en `quantityReceived()`.<br>3. `EvaluatePurchaseOrderFulfillment` detecta que la OC vuelve a tener pendiente y transiciona la OC de `cumplida` a `emitida`.<br>4. Si era factura, `recalculateForAnnulledVoucher` restituye `last_cost` a la factura previa válida. |

---

## 6. Rutas e Interfaz

- **Rutas (`routes/web.php`):** No se requieren rutas nuevas. La HU-038 es 100% de automatización de lógica de negocio del lado del servidor (backend orchestration). Se activa automáticamente a través de las rutas existentes:
  - `POST /purchasing/vouchers` (`purchasing.vouchers.store`): dispara la actualización de costo y el cierre de OCs.
  - `POST /purchasing/vouchers/{voucher}/annul` (`purchasing.vouchers.annul`): dispara la reapertura de OCs y reversión de costos.
- **Interfaz (Inertia + React):** No se requieren nuevas pantallas. Las vistas construidas en HU-015 y HU-037 consumen los campos de estado y costo existentes. Sí se deben modificar los diálogos de asociación artículo-proveedor de ambas fichas para retirar la carga/edición manual de `last_cost`.

---

## 7. Pruebas y Aceptación

Suite de pruebas automatizadas construida con **Pest PHP**:

### Archivo 1: `tests/Feature/Purchasing/UpdateLastPurchaseCostTest.php`
- `test`: Registrar una factura valorizada actualiza `article_supplier.last_cost` con el precio unitario del artículo.
- `test`: Registrar una factura con un artículo no asociado previamente al proveedor falla con validación y no crea el comprobante.
- `test`: Una factura libre, sin OC, actualiza el costo del artículo asociado al proveedor.
- `test`: Comprobantes que no son factura (Nota de Crédito, Nota de Débito) no alteran `last_cost`.
- `test`: Las líneas conceptuales (sin `article_id`) no alteran `last_cost`; el precio unitario cero ya es rechazado por `StoreSupplierVoucherRequest`.
- `test`: Múltiples facturas actualizan el costo según el orden de registro, incluso si sus fechas de emisión no coinciden con ese orden.
- `test`: Si la factura tiene varios renglones del mismo artículo, prevalece el precio del último renglón.
- `test`: Al anular una factura, el `last_cost` se recalcula y revierte al precio de la factura válida previa (o `null` si no hay otra).
- `test`: Anular una factura antigua no cambia el costo fijado por una factura posterior vigente.
- `test`: No se puede cargar ni editar manualmente `last_cost` en las asociaciones artículo-proveedor.

### Archivo 2: `tests/Feature/Purchasing/EvaluatePurchaseOrderFulfillmentTest.php`
- `test`: Una orden de compra con múltiples ítems pasa a `cumplida` cuando todas sus líneas quedan cubiertas al 100%.
- `test`: Una orden de compra permanece en estado `emitida` cuando la cobertura de sus líneas es parcial.
- `test`: Una orden de compra con excedente en pesables (ej. carnicería) salda el pendiente y pasa a `cumplida`.
- `test`: La anulación del comprobante que había cumplido la orden de compra retorna la orden a estado `emitida`.
- `test`: La anulación de un comprobante en una orden con cobertura parcial mantiene la orden en `emitida` actualizando su saldo pendiente.
- `test`: Órdenes en estado `borrador` o `cancelada` no son modificadas por la evaluación de cumplimiento.

---

## 8. Supuestos y Decisiones Fijadas

1. **Exclusividad de Facturas para Fijar Costo:** Se fija la decisión de que únicamente los comprobantes con `type === 'factura'` son considerados válidos para fijar el costo de compra de referencia. Ni las notas de crédito ni los remitos valorizan el catálogo.
2. **Prevalencia del Precio Facturado:** Si un artículo figura con un precio unitario en la orden de compra y la factura ingresa con un precio unitario diferente acordado (mayor o menor), el `last_cost` que prevalece es el de la **factura efectivamente registrada**, respetando el criterio de aceptación "el último costo se toma del comprobante registrado".
3. **Persistencia Derivada de Saldos:** No se crean columnas fijas de saldo en la base de datos para la OC; los saldos siguen calculándose en tiempo real mediante agregados de imputaciones no canceladas, lo que garantiza inmunidad ante inconsistencias en concurrencia o anulaciones.
4. **Asociación previa obligatoria:** Una factura con artículo del catálogo requiere que ese artículo ya esté asociado al proveedor con su código propio. Si falta la asociación, se rechaza el alta de la factura de forma atómica. No se inventa un código de proveedor a partir del código interno.
5. **Atomicidad Transaccional:** Toda la orquestación ocurre dentro de la transacción `DB::transaction()` de creación/anulación del comprobante con bloqueos pesimistas `lockForUpdate()`.
6. **Costo automático y legado:** La carga manual de `last_cost` se retira de las dos fichas y de sus endpoints. Los valores preexistentes permanecen hasta ser sustituidos por una factura; si luego se anula la última factura vigente y no hay otra, el costo queda en `null` porque no hay historial fiable del valor manual anterior.
7. **Orden de registro:** «Último costo» significa el de la factura registrada más recientemente, con independencia de `issue_date`. Dentro de una misma factura, prevalece el último renglón del artículo.
8. **Dependencia de HU-026:** El tipo Remito aún no está implementado en este checkout. La HU-038 debe funcionar con los tipos existentes y admitir su integración cuando HU-026 incorpore el Remito.

---

## 9. Verificación de la Definition of Done

| Criterio de Aceptación | Cómo se Verifica |
| :--- | :--- |
| **Actualización de costo por comprobante** | Test Pest de alta de Factura comprobando `article_supplier.last_cost == unit_price`. |
| **Costo nunca se carga a mano** | Tests de rechazo de `last_cost` en los endpoints de asociación artículo-proveedor e inspección de los formularios de ambas fichas; el comprobante solo recibe `unit_price`. |
| **Artículo sin asociación previa** | Test Pest de alta de factura que verifica error de validación y ausencia de escritura parcial. |
| **Orden de registro y anulación** | Tests Pest de facturas cargadas fuera del orden de sus fechas, artículos repetidos y anulación de factura antigua o vigente. |
| **OC pasa a `cumplida` al completarse** | Test Pest de imputación total verificando cambio de status a `cumplida`. |
| **OC permanece `emitida` si cobertura es parcial** | Test Pest de imputación parcial verificando que el status sigue siendo `emitida` y el pendiente disminuye. |
| **Reversión a `emitida` ante anulación** | Test Pest de anulación de comprobante cumplidor verificando que la OC vuelve a `emitida`. |
| **Reversión de costo ante anulación** | Test Pest de anulación de factura verificando recálculo del `last_cost` a la factura previa. |
| **CI Checks limpios** | Ejecución exitosa de Pint, PHPStan (`types:check`), ESLint (`lint:check`), Prettier (`format:check`), TypeScript (`types:check`) y Pest. |
