# HU-033 — Emitir y consultar órdenes de compra

Construye la emisión, edición en borrador, consulta y PDF de órdenes de compra con detalle de artículos, absorbiendo HU-034, HU-035 y HU-024 tras la reestructuración del Sprint 2. Depende de HU-013 (Proveedores) y HU-005 (Depósitos).

## Criterios de aceptación (de `product-backlog.md`)

1. **Datos:** número de orden, proveedor, depósito de destino, condición de pago, fecha de emisión, fecha esperada de entrega, observaciones, estado, y detalle con artículo, cantidad, precio unitario pactado y subtotal.
2. **Validaciones:**
   - Proveedor y depósito de destino obligatorios y activos.
   - La fecha esperada de entrega no puede ser anterior a la fecha de emisión.
   - Cantidad y precio unitario mayores a cero.
   - Un artículo no puede repetirse dentro de la misma orden.
   - No se puede emitir una orden sin al menos un artículo.
   - El total se calcula como la suma de los subtotales; no se desagregan ni calculan impuestos.
3. **Comportamiento:**
   - La orden puede guardarse como `borrador` y modificarse mientras permanezca en ese estado.
   - Al emitir pasa a `emitida`, su cabecera y detalle quedan inmutables y no afecta el stock.
   - Una orden emitida puede cancelarse, pero no editarse ni eliminarse.
   - El listado permite filtrar por proveedor, estado, depósito y rango de fechas, y abrir el detalle.
   - La orden emitida se imprime o descarga en PDF con identificación de La Linda, proveedor, artículos, cantidades, precios y total.
4. **Verificación:** se emite una orden con varios artículos, se comprueba el total, se descarga su PDF y se verifica que ya no pueda editarse.

## Investigación del código existente

| Artefacto | Ubicación | Propósito |
|---|---|---|
| `Supplier` | `app/Models/Purchasing/Supplier.php` | Proveedor emisor con `scopeActive()`. |
| `Warehouse` | `app/Models/Inventory/Warehouse.php` | Depósito destino con `scopeActive()`. |
| `Article` | `app/Models/Catalog/Article.php` | Artículos para los renglones de compra con `scopeActive()`. |
| `ConvertsMoneyToCents` | `app/Concerns/ConvertsMoneyToCents.php` | Cálculos monetarios exactos sin pérdidas de precisión. |
| `SupplierVoucherController` | `app/Http/Controllers/Purchasing/SupplierVoucherController.php` | Referencia de controladores de compras, opciones y generación de PDF con DomPDF. |
| `config('company')` | `config/company.php` | Datos corporativos de Supermercados La Linda S.A. para membretes. |

## Proposed Changes

### Backend — Migraciones y Modelos
- `[NEW]` `database/migrations/2026_09_06_180000_create_purchase_orders_table.php`: cabecera de OC con checks inline (`status`, `expected_delivery_date`, `total_amount`), foreign keys a `suppliers` y `warehouses`, índice único en `order_number`.
- `[NEW]` `database/migrations/2026_09_06_180001_create_purchase_order_items_table.php`: renglones con checks inline (`quantity > 0`, `unit_price > 0`, `line_total > 0`), índice único `(purchase_order_id, article_id)`.
- `[NEW]` `app/Enums/Purchasing/PurchaseOrderStatus.php`: enum backed string (`borrador`, `emitida`, `cancelada`) con `label(): string`.
- `[NEW]` `app/Models/Purchasing/PurchaseOrder.php`: modelo con casts, relations (`supplier`, `warehouse`, `items`, `user`, `cancelledBy`), mutadores y métodos de transición (`canBeEdited()`, `canBeIssued()`, `canBeCancelled()`).
- `[NEW]` `app/Models/Purchasing/PurchaseOrderItem.php`: modelo para renglones con cálculo de subtotal.
- `[NEW]` `database/factories/Purchasing/PurchaseOrderFactory.php` & `PurchaseOrderItemFactory.php`.

### Backend — Actions y Requests
- `[NEW]` `app/Actions/Purchasing/CreatePurchaseOrder.php`: genera correlativo `OC-000001`, valida reglas, persiste cabecera e items atómicamente y calcula total.
- `[NEW]` `app/Actions/Purchasing/UpdatePurchaseOrder.php`: solo permitido en estado `borrador`.
- `[NEW]` `app/Actions/Purchasing/IssuePurchaseOrder.php`: pasa a `emitida`, verifica que tenga al menos un renglón, bloquea edición.
- `[NEW]` `app/Actions/Purchasing/CancelPurchaseOrder.php`: pasa a `cancelada`, guarda motivo y usuario cancelador.
- `[NEW]` `app/Http/Requests/Purchasing/StorePurchaseOrderRequest.php`, `UpdatePurchaseOrderRequest.php`, `CancelPurchaseOrderRequest.php`.

### Backend — Data Objects y Controlador
- `[NEW]` `app/Data/Purchasing/PurchaseOrderData.php`, `PurchaseOrderItemData.php`, `PurchaseOrderListData.php`.
- `[NEW]` `app/Http/Controllers/Purchasing/PurchaseOrderController.php`: `index`, `create`, `store`, `show`, `edit`, `update`, `issue`, `cancel`, `pdf`.
- `[MODIFY]` `routes/web.php`: registrar rutas `purchasing.orders.*`.

### PDF
- `[NEW]` `resources/views/pdf/purchasing/purchase-order.blade.php`: plantilla Blade con datos de La Linda, depósito, proveedor, tabla de artículos, subtotales y total sin IVA.
- `[NEW]` `resources/css/pdf/purchase-order.css`: estilos de impresión para PDF.

### Frontend
- `[NEW]` `resources/js/pages/purchasing/orders/index.tsx`: listado paginado con filtros por proveedor, estado, depósito, rango de fechas y buscador.
- `[NEW]` `resources/js/pages/purchasing/orders/form.tsx`: formulario reactivo para crear y editar orden en borrador, con agregador de renglones, cálculo en vivo y validación de artículos únicos.
- `[NEW]` `resources/js/pages/purchasing/orders/show.tsx`: vista de detalle de solo lectura con acciones (emitir, cancelar con diálogo de motivo, imprimir/descargar PDF).
- `[MODIFY]` `resources/js/components/app-sidebar.tsx`: enlace "Órdenes de Compra" bajo menú "Compras".

### Tests
- `[NEW]` `tests/Feature/Purchasing/PurchaseOrderTest.php`: suite completa con Pest para todas las reglas de negocio y endpoints.

## Verificación de la Definition of Done

| Criterio | Cómo se verifica |
|---|---|
| Criterios de aceptación de `product-backlog.md` | Tests de Pest en `PurchaseOrderTest` |
| Validaciones en servidor | Rechazo con errores 422 en Form Requests y Actions |
| Lógica en Actions | Transacciones atómicas en `app/Actions/Purchasing/` |
| Inmutabilidad tras emisión | Intento de edición o borrado en `emitida`/`cancelada` retorna error |
| Ausencia de cálculo impositivo | Total = suma exacta de renglones |
| PDF emitido por La Linda | Endpoint `pdf()` con DomPDF devuelve archivo descargable |
| Checks CI en verde | `composer run ci:check` |
