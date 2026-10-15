# EPIC-21 — Correcciones de la PR #105

Plan basado en el informe de revisión y contrastado con el checkout actual. Complementa `EPIC-21-plan.md`; la implementación queda pendiente de aprobación. Alcance: resolver los dos bloqueantes, completar la cobertura señalada y reducir la carga de comprobantes en memoria.

## Criterios de aceptación

Fuente: `docs/backlog/product-backlog.md`, sección EPIC-21.

1. **Datos:** top de artículos vendidos por cantidad e importe sobre ventas confirmadas; artículos activos sin stock o en conflicto indicando depósito y existencia; ranking de compras por proveedor del período y saldo pendiente; cantidad de pedidos web pagados o en preparación pendientes de entrega.
2. **Validaciones:** los rankings respetan fechas, sucursal y canal cuando corresponde.
3. **Comportamiento:** tarjetas con top 5/10 y enlaces a las pantallas operativas.
4. **Verificación:** una venta convierte un artículo en primero del período y un pedido online pagado aparece pendiente en el tablero.

Sprint 5: EPIC-21 está asignada a Pablo y depende de EPIC-20 y EPIC-17. Las Actions de Reporting y la integración de venta que usa esta implementación ya existen en el checkout; este plan corrige la implementación actual, sin reconstruir esas dependencias.

## Investigación del código existente

| Artefacto | Ubicación | Evidencia relevante |
| --- | --- | --- |
| Action operativa | `app/Actions/Reporting/GetDashboardOperationalIndicators.php` | Recorta compras negativas con `max(0, ...)`; carga comprobantes del período y de deuda con `get()` |
| Test del dashboard | `tests/Feature/DashboardTest.php` | Conserva la expectativa obsoleta `operational = null` |
| Tests operativos | `tests/Feature/Reporting/GetDashboardOperationalIndicatorsTest.php` | Falta comprobar la deuda de A y varios filtros y aplicaciones |
| Integración | `tests/Feature/Reporting/DashboardOperationalIntegrationTest.php` | Confirma una venta con `ConfirmSalePayment` y verifica el pedido pendiente |
| Procesamiento por lotes | `app/Actions/Reporting/GetDashboardExpenses.php` | Precedente con `lazyById(500)` y aritmética en centavos |
| Cuenta corriente | `app/Actions/Purchasing/GetSupplierAccountStatement.php` | Suma deuda de tipos que crean saldo, usando los agregados del modelo |
| Saldo de comprobantes | `app/Models/Purchasing/SupplierVoucher.php` | Pagos y NC aplicadas reducen deuda; una NC libre no reduce la factura |
| Contrato de salida | `app/Data/Reporting/DashboardOperationalData.php` y `DashboardSupplierRankingData.php` | Ya admiten listas vacías, contador entero e importes como strings con signo |

Versiones verificadas con `composer show --direct`: Laravel 13.25.0, Pest 5.1.1, Inertia Laravel 3.3.1 y Laravel Data 4.23.0. No se requieren cambios de esquema.

## Proposed Changes

### Tests — contrato del dashboard

#### [MODIFY] `tests/Feature/DashboardTest.php`

Reemplazar `operational = null` por comprobaciones explícitas de `top_articles`, `stock_alerts` y `supplier_ranking` vacíos, y `pending_web_orders_count = 0`. Ejecutar este test primero para comprobar la regresión antes de corregir la expectativa.

### Backend — compras netas y memoria

#### [MODIFY] `app/Actions/Reporting/GetDashboardOperationalIndicators.php`

- Pasar `$purchasesCents` directamente a `centsToMoney()`, conservando su signo.
- Mantener facturas + ND − NC, exclusión de anulados, fechas inclusivas y orden descendente del neto real, incluidos cero y negativos.
- Iterar la consulta de compras mediante `lazyById(500)` en lugar de materializar todos los comprobantes. Acumular por proveedor en centavos y seleccionar después los diez primeros.
- Procesar también los comprobantes de deuda de esos diez proveedores por lotes, conservando `withBalanceAggregates()` y `outstandingAmount()`.
- Mantener los filtros actuales: compras por fecha y, al elegir sucursal, comprobantes de sus depósitos más comprobantes sin depósito; compras independientes del canal. Saldo actual acumulado del proveedor, independiente del período.

Se elige la alternativa por lotes admitida por la revisión porque sigue el precedente de Reporting y conserva la aritmética monetaria existente. Reduce la memoria de modelos hidratados a un lote; el acumulador sigue creciendo con la cantidad de proveedores y se recorren todos los comprobantes elegibles. No equivale a un top 10 calculado íntegramente en SQL.

### Tests — regresiones y filtros

#### [MODIFY] `tests/Feature/Reporting/GetDashboardOperationalIndicatorsTest.php`

1. **Neto negativo:** factura de un período anterior y NC de `2000.00` en el seleccionado; esperar `purchases_total = '-2000.00'`. Verificar orden con proveedores de neto positivo, cero y negativo, y exclusión de anulados.
2. **Saldo con NC libre:** agregar la aserción faltante para A: compras `8000.00`, deuda `10000.00`. Corregir el comentario que dice deuda `8000.00` sin haber aplicado la NC.
3. **Saldo con aplicaciones:** factura de `10000.00`, NC aplicada por `2000.00` y pago válido por `3000.00`: deuda `5000.00`. Verificar que un pago anulado no reduzca deuda y que una factura anterior contribuya al saldo actual. Preparar aplicaciones con los patrones existentes de Purchasing, sin modificar estados manualmente.
4. **Compras por sucursal y canal:** depósitos de A/B y comprobante sin depósito; comprobar inclusión/exclusión e independencia del canal. Incluir comprobantes en ambos extremos del rango y fuera de él.
5. **Stock:** depósitos online y de mostrador en dos sucursales; comprobar canal y combinación con sucursal. Variar fechas y comprobar que las alertas representan stock actual.
6. **Pedidos por sucursal:** cubrir retiro por `pickup_branch_id`, fallback a `sale.pointOfSale.warehouse.branch_id` cuando no hay sucursal de retiro, otra sucursal y precedencia del retiro sobre el fallback. Mantener exclusión por canal mostrador y por estados no pendientes; comprobar independencia de las fechas.
7. **Límite y lotes:** más de diez proveedores con importes distintos para comprobar orden y límite; un caso con más de 500 comprobantes repartidos entre proveedores para detectar errores al cruzar lotes y verificar totales y saldo completos.

Reutilizar factories y precedentes de Purchasing. Elegir datos que fallen si un filtro desaparece; usar datasets cuando eviten duplicación. Mantener el test de integración como comprobación del flujo real.

## Verificación de la Definition of Done

| Criterio | Cómo se verifica |
| --- | --- |
| Contrato vacío actualizado | Test existente de `DashboardTest` |
| Compras con signo | Caso de NC en período con factura anterior y orden de netos |
| Deuda consistente | NC libre, NC aplicada, pagos válidos/anulados y deuda histórica |
| Filtros correspondientes | Casos de sucursal/canal/fechas de cada indicador |
| Ranking y procesamiento completo | Más de diez proveedores y más de un lote |
| Flujo funcional conservado | `DashboardOperationalIntegrationTest` |
| CI sin regresiones | Build y `composer run ci:check` satisfactorios |

## Verification Plan

### Automated Tests

Agregar primero la regresión del importe negativo y comprobar su fallo. Tras corregir, ejecutar:

```bash
php artisan test --compact tests/Feature/DashboardTest.php tests/Feature/Reporting/GetDashboardOperationalIndicatorsTest.php tests/Feature/Reporting/DashboardOperationalIntegrationTest.php
```

### Manual Verification

En un entorno con datos apropiados, consultar un período con NC de una compra anterior y comprobar el importe negativo mostrado y la coherencia del saldo con Cuenta Corriente. Consultar filtros de sucursal y canal para contrastar stock y pedidos.

### CI checks

Antes de modificar código, consultar documentación versionada mediante `search-docs`; ese tool no está expuesto en esta sesión. Si sigue ausente, verificar las APIs en el framework instalado siguiendo la alternativa de `laravel-best-practices`.

Ejecutar `vendor/bin/pint --dirty --format agent`, luego los tests afectados. Generar `npm run build` para disponer del manifest requerido localmente y ejecutar `composer run ci:check`, que incluye generación de tipos/rutas, ESLint, Prettier, TypeScript, Pint, PHPStan y la suite Pest. Revisar el diff para descartar cambios generados ajenos a las correcciones. Al completar la implementación, ejecutar la auditoría `hu-auditor` exigida por `hu-implementer`.

No se ejecutaron tests ni CI durante esta planificación. Este documento no acredita resultados de verificación.
