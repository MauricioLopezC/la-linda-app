# EPIC-15 — Procesar el pedido pagado como una venta online con su factura

Convierte automáticamente un pedido web pagado (`WebOrder` en estado `pagado` tras confirmación de Mercado Pago) en una venta online confirmada (`Sale` con `channel = 'online'`) y su correspondiente factura fiscal (`Invoice` A o B) correlativa en una única transacción atómica, sin descontar stock físico del depósito hasta la entrega (EPIC-17). Expone la factura y la descarga de su PDF tanto en "Mis pedidos" del cliente como en la vista interna de ventas.

## Criterios de aceptación (de `product-backlog.md`)

1. **Datos:** pedido web en estado `pagado`, venta asociada (`sales.web_order_id` único, `channel = 'online'`, `cash_session_id = null`, `point_of_sale_id` asociado al depósito del canal online) y factura emitida (`A` o `B` según la condición fiscal del cliente).
2. **Validaciones:**
   - un pedido web genera a lo sumo una venta y una factura (idempotente ante notificaciones repetidas del webhook de Mercado Pago).
   - solo se procesa cuando el pago fue acreditado (`status = 'pagado'`).
   - el punto de venta utilizado corresponde al depósito activo asignado al canal online (`warehouses.is_online_channel = true`).
   - integridad de canal: toda venta con `channel = 'online'` tiene `web_order_id IS NOT NULL` y toda venta con `channel = 'mostrador'` tiene `web_order_id IS NULL`.
3. **Comportamiento:**
   - al acreditarse el pago del pedido web (`MarkWebOrderAsPaid`), dentro de la misma transacción atómica se ejecuta `ConvertPaidWebOrderToSale`:
     1) crea la `Sale` en estado `confirmada` con `channel = 'online'`, `cash_session_id = null`, `user_id = null`, `customer_id = $webOrder->customer_id`, `shipping_amount = $webOrder->shipping_cost` y los precios congelados en `web_order_items` (respetando la alícuota de IVA de cada artículo).
     2) si el pedido incluye costo de envío (`shipping_cost > 0`), lo incorpora en el total de la venta (`sales.shipping_amount`) y como renglón de concepto "Envío a domicilio" en la factura (`article_id = null`, al 21% de IVA contenido) para que el total facturado coincida al centavo con lo cobrado por Mercado Pago.
     3) emite la `Invoice` correlativa reutilizando `IssueInvoice`.
   - **el pago del pedido online NO descuenta el stock físico en ese instante**: por definición de negocio del PO en la Review del Sprint 4, la mercadería sigue en el depósito hasta que el encargado de depósito arma el pedido y registra su entrega/despacho (`EPIC-17`), momento en el cual recién se ejecuta la baja de stock.
   - las ventas online no generan movimientos en `cash_movements` (que representan el arqueo de cajas físicas de mostrador), sino que su cobro queda registrado por el pago acreditado del pedido web (`mp_payment_id` y `paid_amount`).
   - desde el detalle del pedido web (tanto en el panel interno como en "Mis pedidos" del cliente) queda disponible su factura asociada para consultar o descargar en PDF.
4. **Verificación:** se acredita el pago de un pedido web con envío a domicilio; se comprueba que en una sola operación quedan creadas la venta online confirmada y la factura con los artículos más el renglón de envío por el total exacto pagado, mientras que la existencia física del depósito aún no baja hasta que el depósito registre la entrega en `EPIC-17`.

---

## Investigación del código existente

| Artefacto | Ubicación | Propósito |
|---|---|---|
| Migraciones D1 Sprint 5 | `database/migrations/2026_10_12_100003_revise_sales_for_online_orders.php`, `2026_10_12_100004_revise_invoices_for_online_sales.php` | Esquema ya mergeado en D1: `sales.web_order_id` (FK único nullable), `sales.shipping_amount`, CHECKs de canal, `invoices.cash_session_id` y `user_id` nullables, e `invoice_items.article_id` nullable. |
| Modelo `Sale` | `app/Models/Sales/Sale.php` | Maneja la venta: ya cuenta con relación `webOrder(): BelongsTo`, `channel`, `shipping_amount`. Requiere actualizar `netAmount()`, `vatAmount()` y `getVatBreakdown()` para contemplar el IVA 21% del costo de envío cuando `shipping_amount > 0`. |
| Modelo `Invoice` e `InvoiceItem` | `app/Models/Sales/Invoice.php`, `InvoiceItem.php` | Modelos de facturas. `InvoiceItem.article_id` ya es nullable. `Invoice::vatBreakdown()` agrupa por string `vat_rate`. |
| Modelo `Warehouse` | `app/Models/Inventory/Warehouse.php` | Depósitos físicos. Tiene columna booleana `is_online_channel`. Requiere scope helper `scopeOnline()`. |
| Modelo `PointOfSale` | `app/Models/Sales/PointOfSale.php` | Puntos de venta asociados a un depósito (`warehouse_id`). |
| Modelo `WebOrder` | `app/Models/Ecommerce/WebOrder.php` | Pedidos online. Ya cuenta con relación `sale(): HasOne`. |
| Action `MarkWebOrderAsPaid` | `app/Actions/Ecommerce/MarkWebOrderAsPaid.php` | Marca el pedido como pagado. Debe invocar a `ConvertPaidWebOrderToSale` dentro de la misma transacción atómica garantizando idempotencia. |
| Action `IssueInvoice` | `app/Actions/Sales/IssueInvoice.php` | Emite la factura fiscal. Debe ajustarse su firma a `handle(Sale $sale, ?User $user = null): Invoice` para admitir ventas online sin usuario, y generar el renglón "Envío a domicilio" (`article_id = null`, 21% IVA) cuando `sale.shipping_amount > 0`. |
| Action `GenerateInvoicePdf` | `app/Actions/Sales/GenerateInvoicePdf.php` | [NEW] Encapsula la generación del PDF con DomPDF para reutilizarse tanto en ventas de mostrador (`SaleController::invoicePdf`) como en pedidos online del cliente (`WebOrderController::invoicePdf`), formateando correctamente los pagos según canal (Mercado Pago vs. cash movements). |
| Action `ConvertPaidWebOrderToSale` | `app/Actions/Ecommerce/ConvertPaidWebOrderToSale.php` | [NEW] Orquesta la resolución del depósito online, su punto de venta activo, la creación de la venta confirmada, la copia de líneas con alícuotas de IVA y la emisión de la factura vía `IssueInvoice`. Idempotente: si ya existe venta para ese `web_order_id`, la retorna directamente. |
| Data object `SaleInvoiceData` | `app/Data/Sales/SaleInvoiceData.php` | Estructura de la factura emitida serializable hacia Inertia/TypeScript. |
| Data object `WebOrderData` | `app/Data/Ecommerce/WebOrderData.php` | Detalle del pedido para el cliente. Requiere incorporar `public ?SaleInvoiceData $invoice = null`. |
| Data object `SaleData` y `SaleListData` | `app/Data/Sales/SaleData.php`, `SaleListData.php` | Datos de ventas expuestos en vista interna. Incorporan `web_order_id`, `shipping_amount` y `channel`. |
| Controlador `WebOrderController` | `app/Http/Controllers/Ecommerce/WebOrderController.php` | Controlador de "Mis pedidos". Carga la relación `sale.invoice` y expone el nuevo endpoint `invoicePdf`. |
| Vista cliente `show.tsx` | `resources/js/pages/ecommerce/orders/show.tsx` | Detalle de pedido online del cliente. Muestra la factura emitida y los botones de visualización y descarga de PDF. |
| Vista interna `show.tsx` e `index.tsx` | `resources/js/pages/sales/sales/show.tsx`, `index.tsx` | Vista interna de ventas. Soporta ventas de canal online (muestra costo de envío, referencia al pedido web, estado de facturación y botones de PDF). |
| Seeder `PointOfSaleSeeder` | `database/seeders/Sales/PointOfSaleSeeder.php` | Falta sembrar el punto de venta para "Depósito E-commerce". Se subsana en este plan. |

### Discrepancia detectada con el DER / Backlog

- **Columna `is_online_channel` vs `is_ecommerce`:**
  El backlog y diagramas mencionan `warehouses.is_ecommerce = true`. Sin embargo, la columna real creada en la migración de Sprint 1 es `warehouses.is_online_channel`. Siguiendo las directrices del proyecto (`AGENTS.md` y convención de código como fuente de verdad), se utiliza `is_online_channel`. Se añade el método/scope `scopeOnline(Builder $query)` en `Warehouse` para máxima claridad.

---

## Proposed Changes

### Backend — Actions

#### [NEW] `app/Actions/Ecommerce/ConvertPaidWebOrderToSale.php`
- Invokable action: `public function execute(WebOrder $order): Sale`
- Lógica de ejecución:
  1. **Idempotencia:** Verifica si ya existe una venta para `$order->id` (`Sale::where('web_order_id', $order->id)->first()`). Si existe, asegura que tenga factura (invocando a `IssueInvoice` si estuviera pendiente) y retorna `$sale->load(['invoice', 'items.article'])`.
  2. **Validación de estado:** Requiere que `$order->status === WebOrderStatus::Paid` (o lanza `InvalidArgumentException`).
  3. **Resolución de depósito y punto de venta:**
     - Busca el depósito activo con `is_online_channel = true`:
       `Warehouse::query()->where('is_online_channel', true)->where('is_active', true)->firstOrFail()`.
     - Resuelve el punto de venta activo de dicho depósito:
       `PointOfSale::query()->where('warehouse_id', $warehouse->id)->where('is_active', true)->firstOrFail()`.
  4. **Creación de `Sale` confirmada:**
     - `point_of_sale_id`: ID del punto de venta online resuelto.
     - `channel`: `SaleChannel::Online`.
     - `cash_session_id`: `null`.
     - `web_order_id`: `$order->id`.
     - `price_list_id`: `$order->items->first()?->price_list_id ?? $order->customer->price_list_id`.
     - `customer_id`: `$order->customer_id`.
     - `user_id`: `null`.
     - `opened_at`: `$order->placed_at`.
     - `status`: `SaleStatus::Confirmed`.
     - `confirmed_at`: `$order->paid_at ?? now()`.
     - `shipping_amount`: `$order->shipping_cost`.
     - `total_amount`: `$order->total_amount`.
  5. **Creación de `SaleItem`s:**
     - Para cada item en `$order->items`:
       - Copia `quantity`, `unit_price`, `price_list_id`, `line_total`.
       - Obtiene la alícuota de IVA del artículo: `$item->article->vatRate`.
       - Asigna `vat_rate_id` y `vat_rate` (el modelo `SaleItem` calcula `net_amount` y `vat_amount` automáticamente en su hook de guardado).
  6. **Sin baja de stock:**
     - No genera movimientos en `stock_movements` ni modifica `stock_balances` (se delega a `EPIC-17` al momento de la entrega/despacho).
  7. **Emisión de la factura:**
     - Invoca `IssueInvoice::handle($sale, null)`.
  8. Retorna la venta con sus relaciones cargadas.

#### [MODIFY] `app/Actions/Ecommerce/MarkWebOrderAsPaid.php`
- Inyecta `ConvertPaidWebOrderToSale $convertPaidWebOrderToSale`.
- En la transacción atómica:
  - Cuando se actualiza el pedido a `WebOrderStatus::Paid`:
    ejecuta `$this->convertPaidWebOrderToSale->execute($lockedOrder)`.
  - En el caso de replay idempotente (`$lockedOrder->status === WebOrderStatus::Paid && $lockedOrder->mp_payment_id === $paymentId`):
    también invoca `$this->convertPaidWebOrderToSale->execute($lockedOrder)` para garantizar que la venta y su factura estén emitidas antes de retornar.

#### [MODIFY] `app/Actions/Sales/IssueInvoice.php`
- Modifica la firma para admitir usuario nulo: `public function handle(Sale $sale, ?User $user = null): Invoice`.
- Al crear el comprobante `Invoice`:
  - `user_id`: `$user?->id`.
  - `cash_session_id`: `$lockedSale->cash_session_id`.
- Cuando `$lockedSale->shipping_amount > 0`:
  - Calcula el desglose del costo de envío al 21% de IVA mediante `SaleItem::calculateVatBreakdown($lockedSale->shipping_amount, '21.00')`.
  - Suma el neto del envío al neto de los artículos para `invoices.net_amount`.
  - Suma el IVA del envío al IVA de los artículos para `invoices.vat_amount`.
  - Agrega un renglón en `invoice_items`:
    - `article_id`: `null`
    - `description`: `'Envío a domicilio'`
    - `quantity`: `'1.000'`
    - `unit_price`: `$lockedSale->shipping_amount`
    - `vat_rate`: `'21.00'`
    - `net_amount`: `$shippingBreakdown['net_amount']`
    - `vat_amount`: `$shippingBreakdown['vat_amount']`
    - `line_total`: `$lockedSale->shipping_amount`
- Garantiza que `net_amount + vat_amount == total_amount` coincida exactamente con el pago de Mercado Pago.

#### [NEW] `app/Actions/Sales/GenerateInvoicePdf.php`
- Invokable action: `public function handle(Invoice $invoice, bool $download = false): HttpResponse`.
- Extrae la lógica de renderizado PDF de `SaleController::invoicePdf`:
  - Si `$invoice->sale->channel === SaleChannel::Online` y existe `webOrder`, presenta como medio de pago `"Mercado Pago"` con el importe acreditado y el ID de pago.
  - Si es de mostrador, mapea las cobranzas desde `$invoice->sale->cashMovements`.
  - Carga la vista `pdf.sales.invoice` con CSS, emisor, datos fiscales, desglose de IVA y líneas.
  - Retorna `stream` o `download` según `$download`.

### Backend — Modelos

#### [MODIFY] `app/Models/Sales/Sale.php`
- Actualizar `netAmount(): string`: suma de items más el neto de `shipping_amount` (al 21%) si `shipping_amount > 0`.
- Actualizar `vatAmount(): string`: suma de items más el IVA de `shipping_amount` (al 21%) si `shipping_amount > 0`.
- Actualizar `getVatBreakdown(): array`: incorpora el neto, IVA y total del renglón de envío en la categoría de alícuota 21% cuando `shipping_amount > 0`.

#### [MODIFY] `app/Models/Inventory/Warehouse.php`
- Agregar scope `scopeOnline(Builder $query): Builder => $query->where('is_online_channel', true);`.

### Backend — Data Objects

#### [MODIFY] `app/Data/Ecommerce/WebOrderData.php`
- Agregar propiedad `public ?SaleInvoiceData $invoice = null`.
- En `fromModel(WebOrder $order)`:
  - Si `$order->relationLoaded('sale') && $order->sale?->invoice !== null`, mapear `SaleInvoiceData::fromModel($order->sale->invoice)`.

#### [MODIFY] `app/Data/Sales/SaleData.php`
- Agregar propiedades:
  - `public ?int $web_order_id = null`
  - `public ?string $web_order_number = null`
  - `public string $shipping_amount = '0.00'`
- Mapear estos campos en `fromModel`.

#### [MODIFY] `app/Data/Sales/SaleListData.php`
- Agregar propiedades:
  - `public string $channel = 'mostrador'`
  - `public string $channel_label = 'Mostrador'`
- Mapear en `fromModel`.

### Backend — Controladores y Rutas

#### [MODIFY] `app/Http/Controllers/Ecommerce/WebOrderController.php`
- En `show(Request $request, WebOrder $webOrder)`:
  - Eager load: `$webOrder->load(['pickupBranch', 'items.article.unitOfMeasure', 'items.priceList', 'sale.invoice.pointOfSale']);`.
- Agregar método `invoicePdf(Request $request, WebOrder $webOrder, GenerateInvoicePdf $action): HttpResponse`:
  - Valida pertenencia del pedido al cliente autenticado.
  - Valida que exista la venta y su factura emitida (abort 404 si aún no fue emitida).
  - Ejecuta `$action->handle($webOrder->sale->invoice, $request->boolean('download'))`.

#### [MODIFY] `app/Http/Controllers/Sales/SaleController.php`
- Refactorizar `invoicePdf(Request $request, Sale $sale, GenerateInvoicePdf $action): HttpResponse`:
  - Delega en `$action->handle($sale->invoice, $request->boolean('download'))`.

#### [MODIFY] `routes/web.php`
- Dentro de `Route::prefix('tienda')->middleware('auth')->prefix('mis-pedidos')->name('orders.')`:
  - Agregar antes del wildcard `{web_order}`:
    `Route::get('{web_order}/factura/pdf', [WebOrderController::class, 'invoicePdf'])->name('invoice.pdf');`

### Database — Seeders

#### [MODIFY] `database/seeders/Sales/PointOfSaleSeeder.php`
- Sembrar el Punto de Venta N.º 1 para el depósito `Depósito E-commerce` (`is_online_channel = true`) si existe.

### Frontend — Pantallas y Componentes

#### [MODIFY] `resources/js/pages/ecommerce/orders/show.tsx`
- Recibe `order: App.Data.Ecommerce.WebOrderData`.
- Cuando `order.invoice` no es nulo:
  - En la cabecera / badge de estado, mostrar badge con `order.invoice.voucher_label`.
  - Mostrar botones "Ver factura" y "Descargar factura (PDF)" con enlaces directos a la ruta de Wayfinder `invoicePdf.url(order.id)`.
  - En la tarjeta de totales, mostrar el desglose de IVA y el comprobante fiscal asociado.

#### [MODIFY] `resources/js/pages/sales/sales/show.tsx`
- En ventas con `sale.channel === 'online'`:
  - Mostrar badge `"Canal Online"` y enlace al pedido web asociado.
  - Mostrar renglón de "Costo de envío" en el resumen si `shipping_amount > 0`.
  - Ocultar botones de cobranza manual de caja y turno de cajero.
  - Mantener visible el visor y descarga de factura `<InvoicePdfButtons saleId={sale.id} />`.

#### [MODIFY] `resources/js/pages/sales/sales/index.tsx`
- Mostrar badge identificador de canal (`"Mostrador"` vs `"Online"`) y formatear adecuadamente la columna de Turno de caja cuando es `null`.

---

## Verificación de la Definition of Done

| Criterio | Cómo se verifica |
|---|---|
| Pedido pagado con retiro genera venta + factura exacta | Test en Pest: pedido con `DeliveryMethod::Pickup` pagado genera `Sale` online con `cash_session_id = null`, `user_id = null`, items con alícuotas e `Invoice` tipo B correlativa por el importe exacto. |
| Pedido pagado con envío genera línea "Envío a domicilio" | Test en Pest: pedido con `shipping_cost > 0` genera venta con `shipping_amount` y factura con línea "Envío a domicilio" (article_id = null, IVA 21%) sumando al centavo el total de Mercado Pago. |
| Factura A para Responsable Inscripto | Test en Pest: cliente con condición `IVA Responsable Inscripto` y CUIT válido genera Factura A con IVA discriminado. |
| Idempotencia ante reintentos | Test en Pest: invocar `MarkWebOrderAsPaid` / `ConvertPaidWebOrderToSale` dos veces consecutivas con el mismo pago genera una sola venta y una sola factura. |
| Existencias de stock intactas | Test en Pest: tras acreditarse el pago, las existencias en `stock_balances` del depósito online no sufren ningún decremento. |
| Descarga de PDF de factura | Test en Pest: cliente puede descargar el PDF de su factura en `/tienda/mis-pedidos/{order}/factura/pdf`; otro cliente recibe 403; vista interna también permite descarga. |
| Cobertura y tipos | `php artisan test --compact`, `composer run types:check`, `npm run types:generate`, `npm run types:check`, `npm run lint:check`, `npm run format:check`. |

---

## Verification Plan

### Automated Tests
- `php artisan test --compact --filter=ConvertPaidWebOrderToSaleTest`: pruebas unitarias y de integración de la acción y emisión de factura.
- `php artisan test --compact --filter=MarkWebOrderAsPaidTest`: integración con el flujo de pago.
- `php artisan test --compact --filter=MercadoPagoWebhookTest`: integración con la notificación webhook de Mercado Pago.
- `php artisan test --compact --filter=WebOrderInvoicePdfTest`: verificación de descarga de PDF por cliente y seguridad de acceso.
- `php artisan test --compact --filter=IssueInvoiceTest`: asegurar que las pruebas existentes de facturación continúen en verde.

### Manual Verification
1. Ingresar como cliente a la tienda online (`/tienda`), agregar artículos al carrito y completar checkout con envío a domicilio.
2. Simular o confirmar el pago en Mercado Pago sandbox.
3. Verificar que el pedido transiciona a `pagado`, genera automáticamente la venta online con canal `online` y la factura correspondiente.
4. En "Mis pedidos" (`/tienda/mis-pedidos/{order}`), verificar que aparece la factura emitida con su botón "Descargar factura" y que el PDF se genera con todos los ítems más el renglón "Envío a domicilio".
5. En la vista interna (`/sales/sales`), verificar que la venta figura confirmada con canal online y su factura asociada disponible.
6. Verificar en Inventario / Stock que las existencias del depósito online siguen completas (no se descontó stock).

### CI checks
- Formateo PHP: `vendor/bin/pint --format agent`
- Tipos PHP: `composer run types:check`
- Generación de tipos TS: `npm run types:generate`
- Tipos TS: `npm run types:check`
- Linter frontend: `npm run lint:check`
- Formateador frontend: `npm run format:check`
- Suite de pruebas: `php artisan test --compact`

---

## Open Questions / Decisiones tomadas

1. **Nombre de columna de depósito online:** El backlog menciona `warehouses.is_ecommerce = true`, pero la base de datos real tiene `warehouses.is_online_channel`. Se respeta la base de datos existente y se provee el scope `scopeOnline()`.
2. **Punto de venta del canal online:** `PointOfSaleSeeder` no tenía un punto de venta asignado a `Depósito E-commerce`. Se añade en el seeder para que exista `number = 1` en ese depósito.
3. **Precio de lista en la venta online:** Al crear la `Sale`, se toma `$order->items->first()?->price_list_id ?? $order->customer->price_list_id` para dejar registrado el origen de precios, mientras que cada línea mantiene su precio y alícuota congelados.
