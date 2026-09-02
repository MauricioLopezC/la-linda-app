# HU-036 — Registrar un comprobante de proveedor

Módulo de Compras (`Purchasing` / `CMP`): alta inmutable y listado paginado de facturas, notas de crédito y notas de débito emitidas por proveedores. HU-013 ya está integrada y aporta el maestro de proveedores activos del que depende esta historia.

## Refinamiento posterior solicitado

Luego de la primera implementación se incorporan estos ajustes funcionales:

1. El importe total deja de ser un dato ingresado por el usuario: el servidor lo deriva de `net_amount + vat_amount + other_taxes_amount`, y la interfaz lo muestra como solo lectura.
2. El IVA no se ingresa ni se selecciona: para letras `A` y `M`, el sistema calcula automáticamente un 21% de `net_amount`, con redondeo comercial a dos decimales. Para `B` y `C`, el IVA discriminado es `0,00`; en `B`, `net_amount` representa el importe con IVA incluido y, en `C`, una operación sin IVA. Esta HU no admite otras alícuotas.
3. Cada fila del listado tendrá una acción para descargar un PDF interno del registro. El documento indicará que es una constancia del sistema y no reemplaza el comprobante fiscal original del proveedor.
4. El formulario explicará que el punto de venta son los primeros cuatro dígitos del número fiscal del comprobante y que el número son los ocho dígitos correlativos posteriores al guion.
5. La constancia PDF adopta una composición inspirada en el comprobante fiscal de referencia aportado por el usuario: encabezado `ORIGINAL`, emisor a la izquierda, letra en recuadro central, tipo/número/fechas a la derecha, receptor debajo y resumen de importes al pie. La información se obtiene únicamente del registro y la configuración real de La Linda; no se copian datos del PDF de referencia.
6. Los campos editables de importes aplican formato argentino mientras se escribe: punto como separador de miles y coma como separador decimal, manteniendo un valor canónico sin separadores para el envío al servidor.

## Criterios de aceptación (de `product-backlog.md`)

1. **Datos:** proveedor, tipo de comprobante (factura, nota de crédito, nota de débito), letra (A, B, C, M), punto de venta, número, fecha de emisión, fecha de vencimiento, importe neto gravado, IVA, otros tributos (percepciones), importe total, estado.
2. **Validaciones:**
   - la combinación de proveedor, tipo, letra, punto de venta y número de comprobante es única;
   - proveedor, tipo, letra, punto de venta, número, fecha de emisión e importe total son obligatorios;
   - el punto de venta y el número se guardan con la longitud del formulario oficial (4 y 8 dígitos);
   - el importe total debe ser mayor a cero y debe coincidir con la suma de importe neto gravado, IVA y otros tributos;
   - la fecha de emisión no puede ser posterior a la fecha actual;
   - la fecha de vencimiento, cuando se informa, no puede ser anterior a la fecha de emisión;
   - solo se pueden registrar comprobantes de proveedores en estado activo.
3. **Comportamiento:**
   - una factura nace en estado pendiente y con saldo pendiente igual a su importe total;
   - una nota de crédito o de débito nace pendiente de imputar; su efecto sobre el saldo lo aplica HU-054;
   - el estado del comprobante se recalcula automáticamente a partir de su saldo y nunca se carga a mano;
   - un comprobante con imputaciones de pago o de nota asociadas no se elimina: se revierte con una contrapartida;
   - el listado muestra proveedor, tipo, letra, punto de venta y número, fechas, importe, saldo pendiente y estado, y marca como vencido todo comprobante con saldo mayor a cero y fecha de vencimiento pasada.

## Investigación del código existente

| Artefacto | Ubicación | Propósito / patrón a replicar |
| --- | --- | --- |
| Proveedores | `app/Models/Purchasing/Supplier.php` y su migración | Dependencia HU-013 ya mergeada, scope de activos, relación tipada y bloqueo de baja física. |
| Actions de Compras | `app/Actions/Purchasing/CreateSupplier.php` | Writes transaccionales, datos validados, log operativo y controladores delgados. |
| Form Requests | `app/Http/Requests/Purchasing/StoreSupplierRequest.php` | Validación de servidor con arrays de reglas, normalización previa y mensajes en español. |
| Data objects | `app/Data/Purchasing/SupplierData.php` | Shapes de salida con `spatie/laravel-data` y generación de tipos TypeScript. |
| Listado paginado | `app/Actions/Inventory/ConsultStockMovements.php` y `resources/js/pages/inventory/movements/index.tsx` | Eager loading, orden descendente, paginación y tabla responsive. |
| Formulario dedicado | `resources/js/pages/inventory/adjustments/create.tsx` | Página Inertia separada, `useForm`, Cards, shadcn/ui y rutas Wayfinder. |
| Navegación | `resources/js/components/app-sidebar.tsx` | Grupo Compras ya existente con acceso a Proveedores. |

El DER usa provisionalmente `letra`; para respetar la convención documentada de columnas en inglés se implementará `letter` y se actualizará el DER. También se incorporan `imputada_parcial` e `imputada`, mencionados en el texto del DER pero omitidos de su lista de estados.

## Proposed Changes

### Backend — Dominio y base de datos

#### [NEW] `database/migrations/*_create_supplier_vouchers_table.php`

- Crear `supplier_vouchers` con FK restrictiva a `suppliers`, campos fiscales, fechas, importes `decimal(12,2)`, estado, notas y timestamps.
- Declarar CHECK inline y portable para enums, longitudes, importes no negativos, total positivo/coherente y vencimiento no anterior a emisión.
- UNIQUE compuesto por `supplier_id`, `type`, `letter`, `point_of_sale`, `number`; índices sobre emisión y estado.
- No persistir saldo pendiente.

#### [NEW] `app/Enums/Purchasing/SupplierVoucher*.php`

- Enums de tipo, letra y estado con labels españoles y opciones tipadas.
- Ciclo completo: `pendiente`, `pagada_parcial`, `pagada`, `pendiente_imputar`, `imputada_parcial`, `imputada`, `anulada`.

#### [NEW] `app/Models/Purchasing/SupplierVoucher.php`

- Fillable explícito, casts de enums/fechas/decimales y relación `supplier()`.
- `pendingBalance()` derivado sin columna: en HU-036 equivale al total aún no pagado/imputado.
- `isOverdue()` derivado de vencimiento pasado y saldo positivo.

#### [MODIFY] `app/Models/Purchasing/Supplier.php`

- Agregar `vouchers(): HasMany` y usarla en `hasAssociatedRecords()`.

### Backend — Validación, Actions, DTOs y controlador

#### [NEW] `app/Http/Requests/Purchasing/StoreSupplierVoucherRequest.php`

- Normalizar `point_of_sale` y `number` con ceros a la izquierda; aceptar solo dígitos y rechazar exceso de longitud.
- Normalizar importes con coma o punto a strings canónicos de dos decimales.
- Validar proveedor activo, enums, fechas, precisión/rango monetario y clave compuesta única.
- Prohibir `vat_amount`, `total_amount`, `status` y `pending_balance` como input: son valores derivados.
- Calcular IVA al 21% para letras `A` y `M`, con redondeo exacto en centavos; forzarlo a cero para `B` y `C`.

#### [NEW] `app/Actions/Purchasing/ResolveSupplierVoucherStatus.php`

- Resolver en centavos exactos los estados de factura o NC/ND para saldo completo, parcial o cero.

#### [NEW] `app/Actions/Purchasing/CreateSupplierVoucher.php`

- Revalidar proveedor activo dentro de una transacción, calcular el IVA y el total en centavos exactos, derivar estado, persistir campos validados y registrar log.

#### [NEW] `app/Data/Purchasing/SupplierOptionData.php`, `SupplierVoucherOptionData.php`, `SupplierVoucherListData.php`

- Props ligeras y tipadas para selector de proveedor, opciones de enums y filas del listado con saldo/estado/vencimiento derivados.

#### [NEW] `app/Http/Controllers/Purchasing/SupplierVoucherController.php`

- `index`: eager load, orden emisión/ID descendente y 25 elementos por página.
- `create`: proveedores activos, opciones de tipo/letra y fecha actual.
- `store`: delegar al Action, flash toast y redirección al listado.

### Rutas, frontend y Wayfinder

#### [MODIFY] `routes/web.php`

- Agregar únicamente `purchasing.vouchers.index`, `create` y `store`; no exponer edición ni eliminación.

#### [NEW] `resources/js/pages/purchasing/vouchers/index.tsx`

- Tabla responsive con proveedor, comprobante, fechas, importe, saldo, estado y badge de vencido.
- Estado vacío, CTA de alta, formato ARS y paginación reutilizable.
- Acción por fila para descargar la constancia PDF.

#### [NEW] `resources/js/pages/purchasing/vouchers/create.tsx`

- Formulario dedicado con identificación fiscal, fechas, importes discriminados, observaciones, IVA automático y total automático, ambos de solo lectura.
- Defaults factura/A/hoy; padding visual y de servidor; navegación y submit con Wayfinder.
- Ayudas contextuales para punto de venta y número.
- Para `A/M`, mostrar el IVA automático del 21%; para `B/C`, mostrarlo en cero. No incluir selector de alícuota ni permitir editar el IVA.
- Formatear neto y otros tributos en vivo con convención `es-AR` (`1.234.567,89`), sin perder precisión ni alterar el valor canónico usado para IVA, total y submit.

#### [NEW] `config/company.php`

- Centralizar la identidad ficticia aprobada para el proyecto educativo: `Supermercados La Linda S.A.`, CUIT válido `30-71654321-4`, domicilio `Av. Paraguay 2150, Salta Capital, Salta` y condición `IVA Responsable Inscripto`.

#### [NEW] `resources/views/pdf/purchasing/supplier-voucher.blade.php`

- Plantilla A4 de estética fiscal en blanco, negro y grises, con tipografía sans serif compacta y bordes definidos, basada visualmente en la referencia aportada.
- Encabezado con `ORIGINAL`; proveedor emisor a la izquierda; letra destacada en un recuadro central; título dinámico (`Factura`, `Nota de crédito` o `Nota de débito`) con la letra, punto de venta, número y fechas a la derecha.
- Bloque receptor para Supermercados La Linda, seguido por desglose de neto, IVA del 21% cuando corresponda, otros tributos, total, saldo, estado y observaciones.
- No agregar QR, CAE, logo ARCA ni detalle de productos/servicios: HU-036 registra una constancia interna y no almacena esos datos fiscales o renglones.
- Leyenda visible: constancia interna del registro, no comprobante fiscal emitido por el proveedor.

#### [MODIFY] `app/Http/Controllers/Purchasing/SupplierVoucherController.php`

- Agregar descarga PDF con eager loading del proveedor y nombre de archivo fiscal estable.

#### [MODIFY] `routes/web.php`

- Agregar `purchasing.vouchers.pdf` como `GET` autenticado con route model binding.

#### [MODIFY] `composer.json` / `composer.lock`

- Incorporar `barryvdh/laravel-dompdf:^3.1.2`, compatible con Laravel 13 y PHP 8.5, para generar el PDF en el servidor sin depender del navegador del usuario.

#### [MODIFY] `resources/js/components/app-sidebar.tsx`

- Agregar “Comprobantes” al grupo Compras.

#### [MODIFY] `resources/js/lib/utils.ts`

- Incorporar formato monetario ARS reutilizable.

### Datos de demostración y documentación

#### [NEW] `database/factories/Purchasing/SupplierVoucherFactory.php`

- Estados `invoice`, `creditNote`, `debitNote` y `overdue` coherentes.

#### [NEW] `database/seeders/Purchasing/SupplierVoucherSeeder.php`

- Facturas vigentes/vencidas, una NC y una ND, creadas idempotentemente por `CreateSupplierVoucher`.

#### [MODIFY] `database/seeders/DatabaseSeeder.php`

- Ejecutar el seeder después de proveedores.

#### [MODIFY] `docs/backlog/sprint-backlog-2.md`

- Reconciliar `letter` y la lista completa de estados con la decisión tomada.

### Tests

#### [NEW] `tests/Feature/Purchasing/SupplierVoucherTest.php`

- Acceso, alta, normalización, unicidad, validaciones, estados/saldos, listado, vencimiento e inexistencia de rutas mutables.
- IVA y total derivados e imposibles de sobrescribir, reglas por letra y descarga PDF autenticada con contenido esperado.

#### [NEW] `tests/Unit/Purchasing/ResolveSupplierVoucherStatusTest.php`

- Matriz de tipo/saldo para estados completos, parciales y finales.

#### [NEW] `tests/Feature/Purchasing/SupplierVoucherSeederTest.php`

- Idempotencia y datos demo coherentes.

## Verificación de la Definition of Done

| Criterio | Cómo se verifica |
| --- | --- |
| Datos y clave fiscal completos | Migración, Request y tests de persistencia/UNIQUE. |
| Validaciones solo en servidor y UI | Pest para cada regla más errores por campo y suma en vivo. |
| Total automático | Test de cálculo exacto en servidor y campo de solo lectura en UI. |
| Importes legibles | Formato argentino en vivo, TypeScript y build de producción. |
| IVA según letra | Tests `A/M` con cálculo automático fijo del 21% —incluido el redondeo— y `B/C` con IVA cero forzado. |
| Constancia PDF | Test de ruta autenticada, `Content-Type`, firma PDF y datos del comprobante; inspección visual renderizada. |
| Estado y saldo no editables | Resolver, campos prohibidos y ausencia de rutas mutables. |
| Vencimiento derivado | Tests con reloj congelado y badge en Data/UI. |
| Proveedor activo | Rule de existencia acotada y revalidación transaccional. |
| Action aislada | Tests del alta y resolver sin reglas de negocio en controller. |
| DTOs/Wayfinder tipados | `npm run types:generate`, `wayfinder:generate` y `tsc`. |
| Datos de demostración | Test del seeder idempotente. |
| Calidad integral | Pint, PHPStan, Pest, ESLint, Prettier, TypeScript y `composer run ci:check`. |

## Verification Plan

### Automated Tests

- `php artisan test --compact tests/Unit/Purchasing/ResolveSupplierVoucherStatusTest.php`
- `php artisan test --compact tests/Feature/Purchasing/SupplierVoucherTest.php`
- `php artisan test --compact tests/Feature/Purchasing/SupplierVoucherSeederTest.php`
- `php artisan test --compact`

### Manual Verification

1. Registrar factura con `12` / `345` y verificar `0012-00000345`, estado pendiente y saldo igual al total.
2. Registrar NC y ND y verificar estado pendiente de imputar.
3. Probar proveedor inactivo, fecha futura, vencimiento inválido, duplicado, total calculado en cero e intento de enviar IVA/total manuales.
4. Verificar listado, paginación, badge vencido y ambos temas.
5. Confirmar que no existen acciones de editar/eliminar.
6. Verificar que `A/M` muestran IVA automático del 21%, que `B/C` muestran cero y que nunca existe edición ni selección de alícuota.
7. Descargar el PDF desde el listado, abrirlo y revisar contenido, legibilidad y leyenda de constancia interna.
8. Ingresar importes de siete o más cifras y comprobar agrupación automática de miles y decimales con coma.

### CI checks

- `vendor/bin/pint --dirty --format agent`
- `composer run types:check`
- `npm run types:generate`
- `php artisan wayfinder:generate --with-form --no-interaction`
- `npm run lint:check`
- `npm run format:check`
- `npm run types:check`
- `composer run ci:check`

## Decisiones aprobadas

1. Se incorpora `barryvdh/laravel-dompdf:^3.1.2` para ofrecer una descarga PDF directa desde Laravel.
2. El IVA se deriva siempre al 21% para letras `A/M`; para `B/C` se registra en cero. No se ofrece selección de alícuota.
3. Por tratarse de un proyecto educativo, se aprueban datos fiscales ficticios pero coherentes para La Linda en Salta y un encabezado tipográfico sin logotipo.
