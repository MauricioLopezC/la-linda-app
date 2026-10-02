# EPIC-03 — Identificar al cliente y determinar el tipo de comprobante

Identifica al cliente de una venta de mostrador (o lo mantiene como Consumidor Final por defecto) y determina de manera automática el tipo de comprobante correspondiente (Factura A o Factura B) según su condición fiscal frente al IVA, recalculando los precios de los artículos y exhibiendo el comprobante antes del cobro.

## Criterios de aceptación (de `product-backlog.md`)

1. **Datos:** cliente de la venta, su condición frente al IVA, tipo y número de documento, y el tipo de comprobante que corresponde.
2. **Validaciones:**
    - solo se eligen clientes activos, y el cliente solo se cambia en una venta abierta.
    - la factura A exige que el cliente sea responsable inscripto y tenga CUIT cargado.
3. **Comportamiento:**
    - la venta abre con Consumidor Final; se busca otro cliente por nombre o documento.
    - el tipo de comprobante se determina solo según la condición del cliente: responsable inscripto → factura A; consumidor final, monotributo y exento → factura B (confirmado con el PO en Sprint 4: Factura A solo a responsables inscriptos, Factura B al resto).
    - la venta muestra el tipo de comprobante antes de cobrar.
    - cambiar el cliente vuelve a resolver los precios (`HU-041`) y el tipo de comprobante.
4. **Verificación:** una venta a un responsable inscripto muestra factura A; al cambiarla a Consumidor Final pasa a factura B.

> **Contexto de implementación previa (PR #56 / HU-039 / HU-041):**
> La venta básica adelantada en el Sprint 3 implementó la apertura con Consumidor Final por defecto y el cambio de cliente con recalculo de precios de líneas según la lista de precios resultante (`ChangeSaleCustomer`).
> En este Sprint 4, EPIC-03 pasa formalmente de Epic a Historia (5 SP) para incorporar la determinación fiscal del comprobante, la validación estricta de CUIT para Responsables Inscriptos, la búsqueda de clientes por nombre y documento, la visibilidad clara del tipo de comprobante antes de cobrar y el recálculo integrado de comprobante + precios.

## Investigación del código existente

| Artefacto | Ubicación | Propósito |
|---|---|---|
| Enum `InvoiceType` | `app/Enums/Sales/InvoiceType.php` | Define casos `A` y `B`, con método `forTaxCondition(CustomerTaxCondition $taxCondition)` que mapea `ResponsibleInscripto` a `A` y el resto a `B`. |
| Enum `CustomerTaxCondition` | `app/Enums/Customers/CustomerTaxCondition.php` | Casos `ResponsibleInscripto`, `Monotributo`, `ConsumidorFinal`, `Exento` con labels y opciones. |
| Modelo `Customer` | `app/Models/Customers/Customer.php` | Posee `tax_condition`, `id_type`, `id_number`, `formattedIdNumber()`, `priceList`, `is_active`, `is_default`. |
| Modelo `Sale` | `app/Models/Sales/Sale.php` | Pertenece a `Customer`, turno de caja, punto de venta. Posee método `acceptsChanges()` para verificar si admite modificaciones. |
| Action `OpenSale` | `app/Actions/Sales/OpenSale.php` | Abre la venta dentro del turno de caja del cajero asignando por defecto al cliente Consumidor Final (`Customer::default()`). |
| Action `ChangeSaleCustomer` | `app/Actions/Sales/ChangeSaleCustomer.php` | Valida `acceptsChanges()` y `is_active`, asigna nuevo cliente y recalcula precios de todas las líneas con `ResolveArticlePrice`. |
| Request `UpdateSaleCustomerRequest` | `app/Http/Requests/Sales/UpdateSaleCustomerRequest.php` | Valida el payload de actualización de cliente (`customer_id`). |
| Controller `SaleController` | `app/Http/Controllers/Sales/SaleController.php` | Administra la pantalla `show`, el cambio de cliente `updateCustomer`, búsqueda de artículos `searchArticles`, y provee `activeCustomers()`. |
| Data `SaleData` | `app/Data/Sales/SaleData.php` | Provee datos de la venta hacia Inertia `sales/sales/show`. Actualmente incluye `customer_id`, `customer_name` y `customer_price_list_name`, pero no expone los datos fiscales del cliente ni el comprobante. |
| Data `SaleCustomerOptionData` | `app/Data/Sales/SaleCustomerOptionData.php` | DTO con los datos de clientes enviados a la pantalla para el selector. |
| Data `SaleListData` | `app/Data/Sales/SaleListData.php` | DTO del listado de ventas (`sales/sales/index`). |
| Vista `show.tsx` | `resources/js/pages/sales/sales/show.tsx` | Pantalla de venta de mostrador. Cuenta con selector básico `<Select>` de clientes y sección de totales e IVA. |
| Tests existentes | `tests/Feature/Sales/ChangeSaleCustomerTest.php`, `tests/Feature/Sales/SaleScreensTest.php` | Pruebas de cambio de cliente, recálculo de precios y renderizado de la pantalla. |

## Proposed Changes

### Capa Backend — Enums & Modelos

#### [MODIFY] `app/Enums/Sales/InvoiceType.php`
- Agregar método estático helper `forCustomer(Customer $customer): self` que derive el comprobante a partir de `$customer->tax_condition` usando `forTaxCondition(...)`.

#### [MODIFY] `app/Models/Sales/Sale.php`
- Agregar método `invoiceType(): InvoiceType` que devuelva `InvoiceType::forCustomer($this->customer)` (o `InvoiceType::forTaxCondition($this->customer->tax_condition)`).
- Documentar en PHPDoc que el tipo de comprobante de una venta abierta se determina exclusivamente a partir de la condición fiscal de su cliente.

### Capa Backend — Actions & Validación

#### [MODIFY] `app/Actions/Sales/ChangeSaleCustomer.php`
- Mantener las validaciones existentes:
  1. `! $sale->acceptsChanges()` → rechaza si la venta no está abierta o si el turno de caja cerró.
  2. `! $customer->is_active` → rechaza si el cliente seleccionado no está activo.
- Agregar validación específica de Factura A:
  - Si `$customer->tax_condition === CustomerTaxCondition::ResponsibleInscripto`:
    - Verificar que el cliente cuente con CUIT cargado: `$customer->id_type === CustomerIdType::Cuit && ! blank($customer->id_number)`.
    - Si no lo tiene cargado, lanzar `ValidationException::withMessages(['customer_id' => 'La factura A exige que el cliente sea responsable inscripto y tenga CUIT cargado.'])`.
- El proceso conserva su atomicidad transaccional completa: actualiza cliente, recalcula líneas vía `ResolveArticlePrice` y recalcula total de venta.

#### [MODIFY] `app/Http/Requests/Sales/UpdateSaleCustomerRequest.php`
- Reforzar regla de validación:
  `'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')->where('is_active', true)]`.

### Capa Backend — Data Objects

#### [MODIFY] `app/Data/Sales/SaleData.php`
- Agregar propiedades fiscales y de comprobante al DTO de salida:
  - `customer_tax_condition`: string (`$sale->customer->tax_condition->value`)
  - `customer_tax_condition_label`: string (`$sale->customer->tax_condition->label()`)
  - `customer_id_type`: ?string (`$sale->customer->id_type?->value`)
  - `customer_id_type_label`: ?string (`$sale->customer->id_type?->label()`)
  - `customer_id_number`: ?string (`$sale->customer->formattedIdNumber()`)
  - `invoice_type`: string (`$sale->invoiceType()->value`)
  - `invoice_type_label`: string (`$sale->invoiceType()->label()`)

#### [MODIFY] `app/Data/Sales/SaleCustomerOptionData.php`
- Incorporar datos fiscales del cliente en cada opción:
  - `tax_condition`: string
  - `tax_condition_label`: string
  - `id_type`: ?string
  - `id_type_label`: ?string
  - `invoice_type`: string (`InvoiceType::forTaxCondition($customer->tax_condition)->value`)
  - `invoice_type_label`: string (`InvoiceType::forTaxCondition($customer->tax_condition)->label()`)

#### [MODIFY] `app/Data/Sales/SaleListData.php`
- Incorporar en el listado de ventas:
  - `invoice_type`: string (`$sale->invoiceType()->value`)
  - `invoice_type_label`: string (`$sale->invoiceType()->label()`)

### Capa Backend — Controlador & Rutas

#### [MODIFY] `app/Http/Controllers/Sales/SaleController.php`
- Agregar método `searchCustomers(Request $request): JsonResponse`:
  - Recibe query parameter `search`.
  - Filtra clientes activos (`Customer::query()->active()`) buscando por coincidencia insensible a mayúsculas/minúsculas en `name` o en `id_number`.
  - Ordena con `is_default` primero (Consumidor Final) y luego por `name`.
  - Retorna `SaleCustomerOptionData::collect($customers)`.
- En `activeCustomers()`:
  - Asegurar carga de `priceList`.

#### [MODIFY] `routes/web.php`
- Registrar ruta `GET /sales/sales/search-customers` apuntando a `SaleController::class, 'searchCustomers'` con nombre `'sales.sales.search-customers'` dentro del grupo de ventas autenticado.

### Capa Frontend — Pantalla de Venta

#### [MODIFY] `resources/js/pages/sales/sales/show.tsx`
- **Selector / Buscador de Clientes:**
  - Sustituir el `<Select>` estático por un componente Combobox con buscador por nombre y documento utilizando `Popover` + `Command` (`CommandInput`, `CommandList`, `CommandItem`), siguiendo el patrón establecido en compras/comprobantes.
  - El buscador permite tipear nombre (ej. "Distribuidora", "Gómez") o número de CUIT/DNI (ej. "3050...", "2028...").
  - Cada elemento del listado muestra:
    - Nombre del cliente
    - Badge/indicador de comprobante correspondiente (`Factura A` / `Factura B`)
    - Identificación (ej. `CUIT 30-50085862-8` o `DNI ...` o `Sin documento`)
    - Condición frente al IVA (ej. `IVA Responsable Inscripto`, `Consumidor Final`)
    - Lista de precios asignada si aplica
- **Datos Fiscales del Cliente en la Venta:**
  - Mostrar en la tarjeta superior de la venta:
    - Cliente y lista asignada
    - Condición frente al IVA (`sale.customer_tax_condition_label`)
    - Documento (`${sale.customer_id_type_label} ${sale.customer_id_number}`)
- **Visibilidad del Tipo de Comprobante antes de cobrar:**
  - Badge prominente de Comprobante en la cabecera / ficha de datos: `[Factura A]` o `[Factura B]`.
  - En la sección inferior de totales y resumen de venta (inmediatamente donde se ubicará el botón de cobro de EPIC-04):
    - Mostrar cuadro / indicador visible: `Comprobante a emitir: [Badge: Factura A / Factura B]`.
    - Indicar brevemente la justificación fiscal: *"Determinado automáticamente por la condición fiscal del cliente: {sale.customer_tax_condition_label}"*.
- Al cambiar de cliente:
  - Se ejecuta `updateCustomer.url(sale.id)` (PATCH).
  - La pantalla actualiza en vivo el tipo de comprobante, el desglose impositivo y los precios recalculados de las líneas.

### Generación de Tipos & Wayfinder

- Ejecutar `npm run types:generate` para regenerar `resources/js/types/generated.d.ts` con los nuevos campos de `SaleData`, `SaleCustomerOptionData` y `SaleListData`.
- Ejecutar `php artisan wayfinder:generate` para registrar la función cliente `@/actions/App/Http/Controllers/Sales/SaleController.searchCustomers`.

### Tests Automatizados

#### [MODIFY] `tests/Feature/Sales/ChangeSaleCustomerTest.php`
- `test_determina_factura_a_para_responsable_inscripto_con_cuit()`:
  - Asignar cliente Responsable Inscripto con CUIT válido establece el comprobante en Factura A y actualiza precios.
- `test_determina_factura_b_al_cambiar_a_consumidor_final()`:
  - Cambiar de Responsable Inscripto a Consumidor Final transiciona el comprobante de Factura A a Factura B.
- `test_determina_factura_b_para_monotributista_y_exento()`:
  - Clientes con condición Monotributo y Exento determinan Factura B.
- `test_rechaza_responsable_inscripto_sin_cuit_cargado()`:
  - Intentar asignar un cliente con condición Responsable Inscripto pero sin CUIT (`id_number` nulo o `id_type != Cuit`) falla con mensaje de validación: `"La factura A exige que el cliente sea responsable inscripto y tenga CUIT cargado."`.
- `test_rechaza_cliente_inactivo()`:
  - Intentar asignar un cliente con `is_active = false` falla con error de validación.
- `test_rechaza_cambio_de_cliente_en_venta_confirmada()`:
  - Una venta en estado `SaleStatus::Confirmed` rechaza el cambio de cliente.
- `test_rechaza_cambio_de_cliente_en_venta_descartada()`:
  - Ya existente, se mantiene.
- `test_rechaza_cambio_de_cliente_si_turno_de_caja_esta_cerrado()`:
  - Ya existente, se mantiene.

#### [MODIFY] `tests/Feature/Sales/SaleScreensTest.php`
- `test_pantalla_de_venta_expone_datos_fiscales_y_tipo_de_comprobante()`:
  - Verifica que `sales.sales.show` entregue en `sale`: `invoice_type`, `invoice_type_label`, `customer_tax_condition`, `customer_id_number`.
  - Verifica que una venta con Consumidor Final exponga `invoice_type = 'B'` y `invoice_type_label = 'Factura B'`.
  - Verifica que una venta con Responsable Inscripto exponga `invoice_type = 'A'` y `invoice_type_label = 'Factura A'`.
- `test_endpoint_busqueda_de_clientes_filtra_por_nombre_y_documento()`:
  - Invoca `GET /sales/sales/search-customers?search=...` y comprueba coincidencias por nombre y por documento de clientes activos, excluyendo inactivos.

## Verificación de la Definition of Done

| Criterio de Aceptación | Cómo se verifica |
|---|---|
| **Datos:** Cliente, condición IVA, tipo/número documento y tipo comprobante | Inspección de `SaleData`, `SaleCustomerOptionData` y props Inertia en `show.tsx`. |
| **Validación:** Solo clientes activos | Test `test_rechaza_cliente_inactivo` y regla `Rule::exists('customers')->where('is_active', true)`. |
| **Validación:** Solo venta abierta | Test con venta confirmada, descartada y con turno cerrado. |
| **Validación:** Factura A exige RI con CUIT | Test `test_rechaza_responsable_inscripto_sin_cuit_cargado` en `ChangeSaleCustomerTest`. |
| **Comportamiento:** Venta abre con Consumidor Final | Verificado en `OpenSaleTest` (inmutable por defecto). |
| **Comportamiento:** Búsqueda por nombre o documento | Test de `searchCustomers` y combobox interactivo en `show.tsx`. |
| **Comportamiento:** Determinación A (RI) y B (CF, Monotributo, Exento) | Tests de transiciones fiscales en `ChangeSaleCustomerTest`. |
| **Comportamiento:** Muestra comprobante antes de cobrar | Renderizado de badge y bloque de comprobante a emitir en `show.tsx`. |
| **Comportamiento:** Recálculo de precios y comprobante al cambiar cliente | Tests en `ChangeSaleCustomerTest` verificando recálculo integral de líneas, total y comprobante. |
| **Verificación:** RI muestra Factura A; cambio a CF pasa a Factura B | Test de transición completa verificado en Pest y visualmente en la UI. |

## Verification Plan

### Automated Tests
- `php artisan test --compact tests/Feature/Sales/ChangeSaleCustomerTest.php`
- `php artisan test --compact tests/Feature/Sales/SaleScreensTest.php`
- `php artisan test --compact tests/Feature/Sales`

### Manual Verification
1. Abrir caja desde `/sales/cash-sessions/create` si no hay turno abierto.
2. Abrir una nueva venta: verificar que abre con cliente "Consumidor Final" y el badge muestra **"Factura B"**.
3. En el buscador de clientes, tipear el CUIT o nombre de un cliente Responsable Inscripto (ej. "Distribuidora del Norte"):
   - Seleccionarlo: verificar que la venta cambia al cliente, muestra su CUIT y condición "IVA Responsable Inscripto", recalcula los precios de lista y el badge cambia a **"Factura A"**.
4. Volver a seleccionar "Consumidor Final": verificar que el badge vuelve a **"Factura B"** y los precios se re-precian a lista mostrador/general.
5. Seleccionar un monotributista o exento: verificar que muestra **"Factura B"**.

### CI Checks
- `vendor/bin/pint --dirty --format agent`
- `composer run types:check`
- `npm run lint:check`
- `npm run format:check`
- `npm run types:check`
