# HU-036 — Registrar y consultar comprobantes de proveedor con detalle

**Versión:** 2.0 · **Módulo:** CMP · **Estimación:** 8 SP · **Dependencias:** HU-013, HU-008

## Objetivo y decisiones confirmadas

Registrar facturas, notas de crédito y notas de débito recibidas, conservando la cabecera y la transcripción completa de sus ítems. La consulta posterior muestra exactamente esos datos en una pantalla con apariencia de formulario de solo lectura.

- El importe total se transcribe del documento; no se calculan ni almacenan neto, IVA u otros impuestos.
- La suma de ítems no debe coincidir con el total. La diferencia solo se muestra como control informativo, sin una alerta explicativa.
- Facturas y ND nacen pendientes y constituyen deuda propia. Las NC nacen disponibles para una futura aplicación de HU-054.
- Saldo y estado son derivados. No existen entradas manuales para ellos.
- El comprobante confirmado y sus ítems son inmutables: no hay edición, eliminación ni PDF interno.
- La anulación conserva todos los datos y registra motivo, usuario y fecha.
- Esta HU no vincula órdenes de compra, no actualiza costos y no genera stock.
- Los comprobantes heredados se conservan aunque no tengan detalle; la consulta lo informa sin inventar ítems.
- El importe total, el precio unitario y el importe de cada ítem se presentan y editan con formato argentino (`1.234.567,89`) y se normalizan antes de persistirlos; la cantidad admite como máximo dos decimales.
- Los artículos se obtienen mediante búsqueda remota por código, descripción o código de barras, con resultados limitados; el formulario no precarga el catálogo completo.

## Implementación

1. **Persistencia**
   - Reconstruir `supplier_vouchers` eliminando los tres importes fiscales y agregando auditoría de anulación.
   - Mantener la identidad única proveedor + tipo + letra + punto de venta + número y los controles de fechas/importes.
   - Crear `supplier_voucher_items` con posición, artículo opcional, descripción y unidad históricas, cantidad, precio e importe positivos.
   - Preservar los comprobantes y relaciones preexistentes durante la migración.

2. **Dominio y casos de uso**
   - Modelar la relación cabecera-detalle y calcular suma de ítems, diferencia y saldo sin persistirlos.
   - Registrar cabecera y detalle en una transacción, revalidando proveedor y artículos activos bajo bloqueo.
   - Anular solo si no existen aplicaciones ni órdenes de pago vigentes, guardando la auditoría.
   - Corregir el tratamiento de ND para que su saldo se pague directamente y no incremente otra factura.

3. **HTTP y consulta**
   - Validar y normalizar la cabecera y todos los ítems mediante Form Requests.
   - Exponer una búsqueda autenticada de artículos activos, con un máximo de 20 resultados.
   - Listar con búsqueda, proveedor, tipo, estado, rango de emisión y filtro de vencidos.
   - Exponer alta, listado, detalle y anulación; omitir rutas de edición, borrado y PDF.

4. **Interfaz Inertia/React**
   - Formulario dinámico para artículos y conceptos sin artículo, con autocompletado remoto.
   - Control visible de suma de ítems, total transcripto y diferencia no bloqueante.
   - Pantalla de detalle de solo lectura con cabecera y tabla de todos los ítems guardados.
   - Modal de anulación con motivo obligatorio.

5. **Verificación**
   - Cubrir alta de los tres tipos, validaciones, snapshots históricos, conceptos, diferencia admitida, unicidad, consulta, filtros, saldos, inmutabilidad y anulación.
   - Ejecutar Pint, Pest, análisis estático y controles de frontend aplicables.

## Resultado manual esperado

Al guardar un comprobante se redirige directamente a su detalle. Esa pantalla presenta los valores recién almacenados como campos de solo lectura y una tabla con sus ítems; no ofrece editar, eliminar ni generar PDF.
