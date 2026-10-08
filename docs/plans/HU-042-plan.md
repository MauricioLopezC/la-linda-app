# HU-042 — Emitir la factura con numeración correlativa por punto de venta

Emite automáticamente la factura A o B con numeración correlativa por punto de venta e IVA discriminado en la misma transacción atómica en que se confirma el cobro de la venta (`EPIC-04`). Congela los datos fiscales del cliente, importes netos, IVA por alícuota y líneas en `invoices` e `invoice_items`.

## Criterios de aceptación (de `product-backlog.md`)

1. **Datos:** tipo (A o B), punto de venta, número, fecha y hora de emisión, datos del cliente al momento de emitir (nombre o razón social, condición frente al IVA, documento y domicilio), detalle de artículos, neto e IVA por alícuota, total, medios de pago, venta y turno de caja de origen.
2. **Validaciones:**
   - La numeración es correlativa por punto de venta y tipo de comprobante, automática, sin saltos ni duplicados, y no editable.
   - Hay una sola factura por venta, y solo se emite sobre una venta confirmada.
3. **Comportamiento:**
   - La factura se emite sola al confirmar el cobro (`EPIC-04`), en la misma operación: si la emisión falla, la venta no se confirma.
   - Queda asociada al turno de caja, igual que la venta.
   - Los datos del cliente y los importes quedan fijos en la factura; un cambio posterior en el cliente no la modifica.
   - La factura A discrimina el IVA por alícuota; la B muestra el total con IVA incluido.
   - La factura es inmutable; su anulación con nota de crédito es `HU-044`.
   - No tiene CAE: es un comprobante interno hasta que se integre ARCA (`SPIKE-01`).
4. **Verificación:** se confirman dos ventas en la Caja 1 y quedan las facturas B 0001-00000001 y 0001-00000002; una venta a un responsable inscripto en la misma caja emite la A 0001-00000001.

## Investigación del código existente

| Artefacto | Ubicación | Propósito |
|---|---|---|
| Migraciones `invoices` e `invoice_items` | `database/migrations/2026_09_28_100011_create_invoices_table.php`, `2026_09_28_100012_create_invoice_items_table.php` | Esquema ya aplicado en `master` (PR #59 de esquema Sprint 4). Tablas `invoices` (con `UNIQUE(point_of_sale_id, type, number)` y `sale_id UNIQUE`) e `invoice_items` (con `UNIQUE(invoice_id, article_id)`). |
| Modelo `Invoice` | `app/Models/Sales/Invoice.php` | Modelo Eloquent para `invoices`, con relaciones a `sale`, `cashSession`, `pointOfSale`, `customer`, `user` e `items`. Requiere métodos helpers para formateo de número (`formattedNumber()`, `voucherLabel()`). |
| Modelo `InvoiceItem` | `app/Models/Sales/InvoiceItem.php` | Modelo Eloquent para líneas de factura congeladas, con relaciones a `invoice` y `article`. |
| Enum `InvoiceType` | `app/Enums/Sales/InvoiceType.php` | Enum con casos `A` y `B`, labels y métodos `forTaxCondition()` y `forCustomer()`. |
| Action `ConfirmSalePayment` | `app/Actions/Sales/ConfirmSalePayment.php` | Confirma el cobro de la venta. Ya cuenta con el gancho: `if (class_exists(IssueInvoice::class)) app(IssueInvoice::class)->handle($lockedSale, $user);` dentro de la transacción de base de datos. |
| Action `IssueInvoice` | `app/Actions/Sales/IssueInvoice.php` | [NEW] Action que adquiere lock sobre el punto de venta, toma el correlativo por tipo, valida reglas (A exige RI con CUIT), congela datos del cliente e importes, e inserta la factura con sus líneas. |
| Configuración de emisor | `config/invoicing.php` | [NEW] Archivo de configuración con datos fiscales de La Linda (razón social, CUIT, domicilio, condición frente al IVA, inicio de actividades, IIBB). |
| Data object `SaleInvoiceData` | `app/Data/Sales/SaleInvoiceData.php` | [NEW] Data object de `spatie/laravel-data` para serializar la factura asociada a la venta con tipos TypeScript. |
| Data object `SaleData` | `app/Data/Sales/SaleData.php` | Expone los datos de la venta al frontend en `show()`. Requiere incluir la propiedad `invoice: ?SaleInvoiceData`. |
| Data object `SaleListData` | `app/Data/Sales/SaleListData.php` | Expone las ventas en el listado `index()`. Requiere exponer `invoice_formatted_number` opcional. |
| Controlador `SaleController` | `app/Http/Controllers/Sales/SaleController.php` | Maneja vistas de venta. Requiere cargar la relación `invoice` en `show()` e `index()`. |
| Vista de detalle `show.tsx` | `resources/js/pages/sales/sales/show.tsx` | Pantalla de atención y cobro. Requiere mostrar en el banner de venta confirmada y en la sección de comprobante la factura emitida con su número correlativo (`0001-00000001`). |
| Vista de listado `index.tsx` | `resources/js/pages/sales/sales/index.tsx` | Pantalla de listado de ventas. Requiere mostrar el comprobante/número de factura en las ventas confirmadas. |
| Factories de Facturación | `database/factories/Sales/InvoiceFactory.php`, `InvoiceItemFactory.php` | Factories existentes que permiten generar facturas y líneas para testing. |

*Nota sobre el diagrama Mermaid de `sprint-backlog-4.md`:* El código y migraciones existentes coinciden exactamente con el DER y la especificación de Sprint 4. No se detectan discrepancias.

## Proposed Changes

### Backend — Config

#### [NEW] `config/invoicing.php`
- Configuración con los datos del emisor requeridos para las facturas y el futuro PDF (HU-043):
  ```php
  <?php

  return [
      'issuer' => [
          'name' => env('INVOICING_ISSUER_NAME', 'Supermercados La Linda S.A.'),
          'cuit' => env('INVOICING_ISSUER_CUIT', '30-71234567-8'),
          'address' => env('INVOICING_ISSUER_ADDRESS', 'Av. San Martín 1234, Salta'),
          'tax_condition' => env('INVOICING_ISSUER_TAX_CONDITION', 'IVA Responsable Inscripto'),
          'activity_start_date' => env('INVOICING_ISSUER_ACTIVITY_START', '01/01/2010'),
          'gross_income' => env('INVOICING_ISSUER_GROSS_INCOME', '30-71234567-8'),
      ],
  ];
  ```

---

### Backend — Model (`Invoice.php`)

#### [MODIFY] `app/Models/Sales/Invoice.php`
- Agregar métodos helper para formatear número de comprobante con padding afín al estándar fiscal argentino:
  - `formattedNumber(): string`: Retorna `sprintf('%04d-%08d', $this->pointOfSale->number ?? 1, $this->number)` (ej. `0001-00000001`).
  - `voucherLabel(): string`: Retorna `"{$this->type->label()} {$this->formattedNumber()}"` (ej. `Factura B 0001-00000001`).

---

### Backend — Action (`IssueInvoice.php`)

#### [NEW] `app/Actions/Sales/IssueInvoice.php`
- Invocada dentro de la transacción de `ConfirmSalePayment` (o individualmente con una venta confirmada).
- Flujo de ejecución:
  1. Validar que la venta esté en estado `SaleStatus::Confirmed`. Si no lo está, lanzar `ValidationException`.
  2. Validar que la venta no tenga ya una factura emitida (`$sale->invoice()->exists()`). Si ya existe, lanzar `ValidationException` ("La venta ya cuenta con una factura emitida.").
  3. Adquirir lock exclusivo sobre el punto de venta para serializar la asignación de números correlativos:
     `$pointOfSale = PointOfSale::query()->whereKey($sale->point_of_sale_id)->lockForUpdate()->firstOrFail();`
  4. Determinar tipo de factura a emitir: `$type = $sale->invoiceType();` (Enum `InvoiceType`).
  5. Si el tipo es `A`: validar defensivamente que el cliente sea `CustomerTaxCondition::ResponsibleInscripto` y que tenga CUIT válido (`id_type === CustomerIdType::Cuit && filled(id_number)`). Si no cumple, lanzar `ValidationException`.
  6. Obtener el siguiente número correlativo para la combinación `(point_of_sale_id, type)`:
     ```php
     $lastNumber = (int) Invoice::query()
         ->where('point_of_sale_id', $pointOfSale->id)
         ->where('type', $type)
         ->lockForUpdate()
         ->max('number');
     $nextNumber = $lastNumber + 1;
     ```
  7. Congelar datos del cliente:
     - `customer_id` => `$sale->customer_id`
     - `customer_name` => `$sale->customer->name`
     - `customer_tax_condition` => `$sale->customer->tax_condition->value`
     - `customer_id_type` => `$sale->customer->id_type?->value`
     - `customer_id_number` => `$sale->customer->id_number`
     - `customer_address` => `$sale->customer->address`
  8. Congelar importes calculados en centavos para consistencia:
     - `net_amount` => `$sale->netAmount()`
     - `vat_amount` => `$sale->vatAmount()`
     - `total_amount` => `$sale->total_amount`
  9. Crear registro en `invoices`:
     - Asociar `sale_id`, `cash_session_id`, `point_of_sale_id`, `type`, `number` ($nextNumber), `issued_at` (now), `user_id`.
  10. Congelar líneas en `invoice_items` a partir de `$sale->items()->with('article')->get()`:
     - Para cada `SaleItem`, crear `InvoiceItem`:
       - `invoice_id` => `$invoice->id`
       - `article_id` => `$item->article_id`
       - `description` => `$item->article->description`
       - `quantity` => `$item->quantity`
       - `unit_price` => `$item->unit_price`
       - `vat_rate` => `$item->vat_rate`
       - `net_amount` => `$item->net_amount`
       - `vat_amount` => `$item->vat_amount`
       - `line_total` => `$item->line_total`
  11. Retornar la `$invoice` creada con sus relaciones cargadas.

---

### Backend — Data Objects

#### [NEW] `app/Data/Sales/SaleInvoiceData.php`
- Data object tipado para enviar datos de la factura al frontend:
  ```php
  namespace App\Data\Sales;

  use App\Models\Sales\Invoice;
  use Spatie\LaravelData\Data;

  class SaleInvoiceData extends Data
  {
      public function __construct(
          public int $id,
          public int $point_of_sale_id,
          public int $point_of_sale_number,
          public string $type,
          public string $type_label,
          public int $number,
          public string $formatted_number,
          public string $voucher_label,
          public string $issued_at,
          public string $issued_at_formatted,
          public string $customer_name,
          public string $customer_tax_condition,
          public ?string $customer_id_type,
          public ?string $customer_id_number,
          public ?string $customer_address,
          public string $net_amount,
          public string $vat_amount,
          public string $total_amount,
          public ?string $user_name,
      ) {}

      public static function fromModel(Invoice $invoice): self
      {
          $invoice->loadMissing(['pointOfSale', 'user']);

          return new self(
              id: $invoice->id,
              point_of_sale_id: $invoice->point_of_sale_id,
              point_of_sale_number: $invoice->pointOfSale->number,
              type: $invoice->type->value,
              type_label: $invoice->type->label(),
              number: $invoice->number,
              formatted_number: $invoice->formattedNumber(),
              voucher_label: $invoice->voucherLabel(),
              issued_at: $invoice->issued_at->toIso8601String(),
              issued_at_formatted: $invoice->issued_at->format('d/m/Y H:i'),
              customer_name: $invoice->customer_name,
              customer_tax_condition: $invoice->customer_tax_condition,
              customer_id_type: $invoice->customer_id_type,
              customer_id_number: $invoice->customer_id_number,
              customer_address: $invoice->customer_address,
              net_amount: $invoice->net_amount,
              vat_amount: $invoice->vat_amount,
              total_amount: $invoice->total_amount,
              user_name: $invoice->user?->name,
          );
      }
  }
  ```

#### [MODIFY] `app/Data/Sales/SaleData.php`
- Agregar propiedad `public ?SaleInvoiceData $invoice` en el constructor de `SaleData`.
- En `fromModel(Sale $sale)`:
  - Cargar relación `invoice.pointOfSale`, `invoice.user`.
  - Mapear `$sale->invoice !== null ? SaleInvoiceData::fromModel($sale->invoice) : null`.

#### [MODIFY] `app/Data/Sales/SaleListData.php`
- Agregar propiedad opcional `public ?string $invoice_formatted_number = null` en `SaleListData`.
- Mapear el número formateado si la relación `invoice` está presente.

---

### Backend — Controller

#### [MODIFY] `app/Http/Controllers/Sales/SaleController.php`
- En `index()`: Eager load `invoice.pointOfSale` en la query paginada.
- En `show()`: Asegurar carga de `invoice.pointOfSale` e `invoice.user`.

---

### Frontend — Páginas

#### [MODIFY] `resources/js/pages/sales/sales/show.tsx`
- En el banner de venta confirmada (`sale.status === 'confirmada'`):
  - Indicar el comprobante emitido: `Factura ${sale.invoice.type} N° ${sale.invoice.formatted_number}` y fecha de emisión.
- En la tarjeta "Comprobante" de información general:
  - Si la factura ya fue emitida (`sale.invoice` presente), mostrar el Badge con `Factura A/B` y el número correlativo `N° 0001-00000001`.
  - Si la venta sigue abierta, mantener el preview condicional con la etiqueta prevista.

#### [MODIFY] `resources/js/pages/sales/sales/index.tsx`
- En la tabla de ventas, en la columna de comprobante o en el estado, mostrar el número de factura cuando esté confirmada (`sale.invoice_formatted_number`).

---

### Tests

#### [NEW] `tests/Feature/Sales/IssueInvoiceTest.php`
- Numeración correlativa automática por punto de venta y tipo:
  - Dos ventas en la Caja 1 a Consumidor Final generan `Factura B 0001-00000001` y `Factura B 0001-00000002`.
  - Una venta en la Caja 1 a Responsable Inscripto genera `Factura A 0001-00000001` (secuencia independiente de B).
  - Una venta en la Caja 2 genera su propia numeración `Factura B 0002-00000001` (secuencia independiente de Caja 1).
- Una sola factura por venta: intentar emitir una segunda factura sobre la misma venta lanza excepción.
- Solo sobre venta confirmada: intentar emitir sobre una venta abierta o descartada es rechazado.
- Inmutabilidad y congelamiento de datos:
  - Cambiar el nombre, domicilio o condición fiscal del cliente después de confirmar no altera los datos guardados en `invoices`.
  - Cambiar la descripción o el precio del artículo en catálogo no altera `invoice_items`.
- Validación de Factura A:
  - Si el cliente es Responsable Inscripto pero no tiene CUIT (o no está asignado), no permite emitir y revierte la confirmación.
- Atomicidad con `ConfirmSalePayment`:
  - Si la emisión de la factura falla, la confirmación de la venta se revierte íntegramente (el estado vuelve a `abierta` y no quedan movimientos de caja ni facturas).
- Desglose de importes:
  - `net_amount`, `vat_amount` y `total_amount` coinciden exactamente con la venta y sus ítems, cumpliendo el check `abs(net + vat - total) < 0.005`.

#### [MODIFY] `tests/Feature/Sales/ConfirmSalePaymentTest.php`
- Verificar que las confirmaciones estándar generan automáticamente la factura correspondiente en la base de datos con sus líneas asociadas.

#### [MODIFY] `tests/Feature/Sales/SaleScreensTest.php`
- Verificar que la pantalla `sales.sales.show` recibe la prop `invoice` con el tipo y número formateado para una venta confirmada.

## Verificación de la Definition of Done

| Criterio | Cómo se verifica |
|---|---|
| Emisión automática al confirmar | `ConfirmSalePayment` ejecuta `IssueInvoice` y deja la factura persistida en BD. |
| Numeración correlativa por punto de venta y tipo | Test con ventas sucesivas en Caja 1 y Caja 2 para tipos A y B verificando números 1, 2, 1, etc. |
| Atomicidad de la transacción | Test forzando error en emisión y comprobando rollback de confirmación y movimientos de caja. |
| Datos y líneas congeladas | Test modificando cliente y artículo posterior a la emisión y comparando contra datos en `invoices` e `invoice_items`. |
| Factura A para RI con CUIT | Test con cliente Responsable Inscripto y verificación de tipo A y CUIT. |
| Restricción de única factura | Intento de emisión duplicada sobre una misma venta rechazado. |
| Visualización en pantalla | Detalle de la venta (`show.tsx`) y listado (`index.tsx`) muestran tipo y número de factura. |
| CI checks en verde | `vendor/bin/pint --dirty --format agent`, `composer run types:check`, `npm run lint:check`, `npm run format:check`, `npm run types:check` y Pest. |

## Verification Plan

### Automated Tests
- Ejecutar tests de emisión de factura: `php artisan test --compact --filter=IssueInvoiceTest`
- Ejecutar suite completa de ventas: `php artisan test --compact --filter=Sales`
- Regenerar tipos de Laravel Data: `npm run types:generate`
- Regenerar rutas Wayfinder si aplica: `php artisan wayfinder:generate`

### Manual Verification
1. Abrir un turno de caja en la Caja 1 e iniciar una nueva venta.
2. Agregar artículos a la venta con cliente Consumidor Final.
3. Cobrar y confirmar la venta: verificar que el mensaje de éxito y la pantalla muestren `Factura B 0001-00000001`.
4. Iniciar una segunda venta con el mismo cliente y confirmar: verificar que emita `Factura B 0001-00000002`.
5. Iniciar una tercera venta, asignar un cliente Responsable Inscripto (con CUIT) y confirmar: verificar que emita `Factura A 0001-00000001`.
6. Consultar el listado de ventas (`/sales`) y verificar que figuren los comprobantes emitidos.

### CI checks
- PHP Pint: `vendor/bin/pint --dirty --format agent`
- PHPStan: `composer run types:check`
- ESLint: `npm run lint:check`
- Prettier: `npm run format:check`
- TypeScript: `npm run types:check`
- Pest: `php artisan test --compact`

## Open Questions
*(No hay preguntas abiertas pendientes: el DER, los modelos, las migraciones y la integración con `ConfirmSalePayment` están completamente especificados y alineados con el sprint backlog y el product backlog).*
