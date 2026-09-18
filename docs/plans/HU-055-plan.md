# HU-055 — Consultar el listado de pagos y egresos del período

Construye la vista de solo lectura de órdenes de pago con filtros combinables,
detalle de comprobantes afectados y exportación CSV/Excel. Depende de HU-027
(emisión de órdenes de pago, ya mergeada en `master`) y de HU-036
(comprobantes de proveedor, ya implementada en `purchasing/vouchers`).

## Criterios de aceptación (de `product-backlog.md`)

1. **Datos:** por cada orden de pago y comprobante, fecha, proveedor, tipo de
   comprobante, medio de pago, importe y estado; y el total de egresos del período.
2. **Validaciones:**
   - El listado es de solo lectura.
   - El total de egresos suma los pagos imputados en el rango de fechas
     seleccionado, **no** los importes de comprobantes todavía impagos.
3. **Comportamiento:**
   - Filtros combinables por rango de fechas, proveedor, tipo de comprobante,
     medio de pago y estado.
   - Desde cada pago se ve el detalle de los comprobantes afectados y el importe
     imputado a cada uno.
   - El listado es exportable a CSV y Excel.
4. **Verificación:** se filtra por proveedor y por un rango de fechas y se
   comprueba que el total de egresos coincide con la suma de los pagos listados.

## Investigación del código existente

| Artefacto | Ubicación | Propósito |
|---|---|---|
| `PaymentOrder` | `app/Models/Purchasing/PaymentOrder.php` | Header de la orden; tiene `date`, `supplier_id`, `total_amount`, `status` (Issued/Cancelled) |
| `PaymentOrderItem` | `app/Models/Purchasing/PaymentOrderItem.php` | Línea de imputación: `supplier_voucher_id` + `amount_applied` |
| `PaymentOrderMethod` | `app/Models/Purchasing/PaymentOrderMethod.php` | Medios de pago: `payment_method_id` + `amount` |
| `PaymentOrderStatus` | `app/Enums/Purchasing/PaymentOrderStatus.php` | `Issued` / `Cancelled` |
| `PaymentOrderData` | `app/Data/Purchasing/PaymentOrderData.php` | Shape completo (detalle) ya existente con items y métodos |
| `PaymentOrderItemData` | `app/Data/Purchasing/PaymentOrderItemData.php` | Shape de ítem de pago (balance del comprobante) |
| `PaymentOrderMethodData` | `app/Data/Purchasing/PaymentOrderMethodData.php` | Shape de medio de pago |
| `SupplierVoucherListData` | `app/Data/Purchasing/SupplierVoucherListData.php` | Referencia para shape liviano de listado |
| `SupplierVoucherController::index` | `app/Http/Controllers/Purchasing/SupplierVoucherController.php:33` | Patrón de filtros con `when()`, paginación 25, `withQueryString()` |
| `PaymentOrderController` | `app/Http/Controllers/Purchasing/PaymentOrderController.php` | Tiene `create`, `store`, `pdf`, `destroy`, `invoices` — se le agregará `index`, `exportCsv`, `exportExcel` |
| `vouchers/index.tsx` | `resources/js/pages/purchasing/vouchers/index.tsx` | Referencia directa de UI: filtros, selects, tabla, paginación, layout/breadcrumbs |
| Rutas actuales | `routes/web.php:143-150` | `purchasing.payment-orders.*` |

## Decisiones tomadas
- **Exportación Excel y CSV:** Se instala `openspout/openspout` para generar `.xlsx` de manera eficiente en streaming sin dependencias nativas pesadas, y streaming directo para CSV.
- **UX Detalle:** Fila expandible con animación/toggle y subtabla con comprobantes afectados (tipo, número, importe imputado) y medios de pago, manteniendo además el botón de descarga PDF de la OP existente.

## Proposed Changes

### Backend — Dependencias
- Instalar `openspout/openspout` con composer.

### Backend — Action
#### [NEW] `app/Actions/Purchasing/ListPaymentOrders.php`
- Action invocable que ejecuta la consulta con filtros:
  - Filtros: `date_from`, `date_to`, `supplier_id`, `payment_method_id`, `voucher_type`, `status`.
  - Calcula `total_egresses` en centavos (`ConvertsMoneyToCents`) sobre órdenes `emitida` (excluye `anulada` y comprobantes impagos, según regla).
  - Retorna `['orders' => LengthAwarePaginator, 'total_egresses' => string]`.
  - Provee método para obtener query sin paginar para exports.

### Backend — Data Objects
#### [NEW] `app/Data/Purchasing/PaymentOrderListItemData.php`
- Datos de comprobante imputado: `supplier_voucher_id`, `voucher_type`, `voucher_type_label`, `voucher_number`, `amount_applied`.

#### [NEW] `app/Data/Purchasing/PaymentOrderListData.php`
- Datos de fila de orden de pago: `id`, `order_number`, `supplier_id`, `supplier_name`, `date`, `date_formatted`, `total_amount`, `status`, `status_label`, `payment_methods_summary`, `items` (array de `PaymentOrderListItemData`), `payment_methods` (array con medio e importe).

### Backend — Form Request
#### [NEW] `app/Http/Requests/Purchasing/ListPaymentOrdersRequest.php`
- Validación de parámetros de filtro y exportación.

### Backend — Controller
#### [MODIFY] `app/Http/Controllers/Purchasing/PaymentOrderController.php`
- Agregar `index`: renderiza `purchasing/payment-orders/index` con Inertia.
- Agregar `exportCsv`: descarga CSV con streaming.
- Agregar `exportExcel`: descarga XLSX con openspout.

### Backend — Rutas
#### [MODIFY] `routes/web.php`
- Agregar `index`, `export/csv`, `export/excel` dentro del prefijo `purchasing/payment-orders`.

### Frontend — Página
#### [NEW] `resources/js/pages/purchasing/payment-orders/index.tsx`
- Vista interactiva de consulta de pagos y egresos con:
  - Tarjeta de filtros combinables (rango de fechas, proveedor, tipo de comprobante, medio de pago, estado).
  - Badge y total destacado de egresos del período seleccionado.
  - Tabla de solo lectura con columnas clave.
  - Expansión de filas para ver comprobantes afectados e importes imputados.
  - Menú de exportación (CSV y Excel).
  - Acciones secundarias como descarga de PDF.

### Wayfinder & Tipos
- Ejecutar `php artisan wayfinder:generate` y `npm run types:generate`.

### Tests
#### [NEW] `tests/Feature/Purchasing/ListPaymentOrdersTest.php`
- Pruebas completas con Pest:
  - Listado básico paginado.
  - Filtros combinables (fechas, proveedor, tipo comprobante, medio de pago, estado).
  - Verificación del total de egresos: solo órdenes `emitida`, excluye `anulada`, coincide exactamente con la suma de los pagos listados.
  - Comprobantes afectados incluidos en cada orden.
  - Exportación CSV y Excel.
  - Control de acceso / autenticación.

## Verificación de la Definition of Done
| Criterio | Cómo se verifica |
|---|---|
| Datos completos en cada pago | Test + UI |
| Solo lectura | UI no permite edición |
| Total egresos suma pagos imputados en rango de fecha | Tests unitarios / feature |
| Filtros combinables | Tests con combinaciones de filtros |
| Detalle de comprobantes afectados | Test de estructura de datos + UI |
| Exportable CSV y Excel | Tests de respuesta HTTP y headers |
| Verificación por proveedor y fecha | Test específico de verificación |
