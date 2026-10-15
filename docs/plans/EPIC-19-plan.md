# EPIC-19 — Egresos del período

Plan aprobado el 2026-10-15. Completa la base del tablero integrada en master
`f2cc38a`, reutilizando HU-027, HU-028, HU-055 y HU-058.

## Criterios de aceptación

1. Mostrar facturas de proveedor más notas de débito menos notas de crédito del período,
   pagos a proveedores, egresos operativos de caja y desglose por medio de pago.
2. Excluir remitos y comprobantes u órdenes anuladas.
3. Distinguir devengado y desembolsos, con enlaces a pagos y cuentas corrientes.
4. Factura de $100.000 y pago parcial de $40.000: mostrar esos importes por separado;
   al anular el pago, el desembolso vuelve a cero.

## Investigación del código existente

| Artefacto | Ubicación | Uso |
| --- | --- | --- |
| Base del tablero | app/Http/Controllers/Reporting/DashboardController.php | Integrar expenses sin alterar los otros bloques |
| Contrato de egresos | app/Data/Reporting/DashboardExpensesData.php | Conservar los cinco campos existentes |
| Listado de pagos | app/Actions/Purchasing/ListPaymentOrders.php | Reutilizar estados y fecha documental |
| Cuenta corriente | app/Actions/Purchasing/GetSupplierAccountStatement.php | Referencia; su pagado derivado del saldo no representa efectivo |
| Dinero exacto | app/Concerns/ConvertsMoneyToCents.php | Aritmética entera y salida decimal |
| Caja y sucursal | CashMovement, CashSession, PointOfSale, Warehouse | Relación hasta la sucursal mediante el depósito |
| Sección visual | resources/js/pages/dashboard/partials/dashboard-expenses-section.tsx | Completar tarjetas, gráfico y enlaces |

## Cambios previstos

### Backend

- Crear `GetDashboardExpenses::handle(array $filters): DashboardExpensesData` en Reporting.
- `accrued_total`: facturas + ND − NC no anuladas, incluidos comprobantes pagados/imputados.
- `paid_to_suppliers_total`: cabeceras de OP emitidas, sin derivar pagos de saldos ni aplicaciones.
- `cash_expenses_total`: solo movimientos `egreso`; `disbursed_total`: pagos + caja.
- `by_payment_method`: líneas de medios de las OP emitidas. Clave por ID, nombre, importe,
  cantidad de órdenes distintas por medio; incluir medios actualmente inactivos.
- Usar issue_date/date con whereDate y límites inclusivos; created_at de caja con intervalo
  desde inicio del primer día hasta antes del siguiente al último.
- Proveedores y pagos globales; solo caja filtra sucursal; canal no modifica el bloque.
- Leer por lotes y cargar medios anticipadamente. Aritmética en centavos; importes como strings.
- Períodos vacíos devuelven ceros y array vacío. Devengado puede ser negativo.
- Inyectar Action en DashboardController y aplicar Gate view-dashboard a la ruta existente.
- Sin migraciones, endpoints nuevos ni cambios en emisión/anulación.

### Frontend y Wayfinder

- Conservar las cuatro tarjetas y aclarar devengado frente a desembolsos.
- Incorporar shadcn chart/recharts (autorizado) con barras horizontales para pagos a proveedores.
- Ofrecer etiquetas e importes accesibles, estado vacío y temas claro/oscuro.
- Aclarar siempre el alcance global y, con sucursal elegida, la composición mixta del desembolso.
- Enlaces Wayfinder a purchasing.payment-orders.index con fechas/status emitida y a
  purchasing.account-statement.index con fechas y selección de proveedor en destino.
- Mantener filtros y contratos de las otras historias.

## Definition of Done y verificación

| Criterio | Evidencia requerida |
| --- | --- |
| Devengado/pagado correctos | Pest con factura 100.000, pago 40.000 y anulación real |
| Exclusiones y notas | Casos de NC aplicada, remito, anulados, centavos y resultado negativo |
| Fechas independientes | Bordes inclusivos, creación distinta, factura anterior/pago posterior |
| Desglose sin duplicación | Varios medios e imputaciones, medio inactivo, órdenes distintas |
| Caja y filtros | Tipos excluidos, límites horarios, sucursales y canal sin efecto |
| Autorización | Gerente/Administrador permitidos, otros roles rechazados |
| Integración | Props Inertia y serialización; navegación y visualización revisadas |
| Calidad | Tipos generados, pruebas afectadas, CI completo y build |

Ejecutar pruebas de Reporting y Dashboard, regenerar tipos y Wayfinder, aplicar Pint a PHP
modificado, ejecutar `composer run ci:check` y `npm run build`. Revisar visualmente gráfico,
filtros, estados vacíos, enlaces y ambos temas. Cerrar con skill hu-auditor y mutaciones
temporales para verificar las reglas monetarias críticas.

## Decisiones aprobadas y límites

Fecha documental, proveedores/pagos globales y nueva dependencia gráfica aprobados.
Período predeterminado: inicio del mes hasta hoy. Exportación, evolución y resultado neto
pertenecen a EPIC-20/22. No atribuir artificialmente compras a sucursal/canal ni ampliar HU-055
para listar movimientos de caja. Preservar cambios locales; sin commit, rama o push implícitos.
