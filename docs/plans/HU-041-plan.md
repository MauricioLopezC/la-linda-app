# HU-041 — Calcular el precio y los totales de la venta

Cierra los criterios aprobados en el Sprint Planning 4 sobre lo que el PR #56 ya había construido
(precio resuelto por `HU-056`, total de la línea y total de la venta). Depende de `HU-040` y
`HU-056`. Reestimada a 2 SP: el trabajo restante es validar contra los criterios y cerrar las
diferencias.

## Criterios de aceptación (de `product-backlog.md`)

1. **Datos:** por línea, precio unitario, lista de precios de origen y total de la línea; total de la venta.
2. **Validaciones:**
   - el precio lo resuelve `HU-056` según el cliente y el canal mostrador; nunca se carga a mano.
   - un artículo sin precio en ninguna lista aplicable no se agrega a la venta.
   - los importes se suman sin errores de redondeo, también con cantidades decimales.
3. **Comportamiento:**
   - el precio de la línea se fija al agregarla; un cambio posterior en la lista no la modifica.
   - cambiar el cliente vuelve a resolver el precio de todas las líneas (`EPIC-03`).
   - cada línea muestra de qué lista salió su precio.
   - el precio de lista es final con IVA incluido; la venta no discrimina IVA (`HU-063`).
4. **Verificación:** se cargan un artículo con precio en la lista de mostrador y otro que solo tiene
   precio en la lista general, y se comprueban el precio, la lista de origen y el total.

## Diagnóstico del código existente

| Criterio | Estado previo | Dónde |
|---|---|---|
| Precio resuelto por HU-056 | Hecho | `AddArticleToSale` → `ResolveArticlePrice` |
| Nunca se carga a mano | Hecho, sin test | `StoreSaleItemRequest` no acepta `unit_price` |
| Sin precio → no se agrega | Hecho, con test | `SaleItemsTest` |
| Sin errores de redondeo | Hecho, test parcial | `SaleItem::calculateLineTotal`, `Sale::recalculateTotal` (enteros/centavos) |
| Precio fijo al agregar | Hecho; test solo al reescanear | `UpdateSaleItemQuantity` usa el `unit_price` de la línea |
| Cambiar cliente re-precia | Hecho; test en un solo sentido | `ChangeSaleCustomer` |
| Lista de origen por línea | Hecho | `SaleItemData::price_origin_label`, badge en `show.tsx` |
| Precio final con IVA | Hecho | nota en `show.tsx`, `HU-063` |
| Escenario de verificación | Sin test combinado | `SalePriceScenariosTest` |

No hizo falta cambiar esquema, Actions ni frontend.

## Tests agregados

- `SalePriceScenariosTest`: escenario de verificación completo — mostrador ($1.000) + solo general
  ($300) en la misma venta, con precio, lista de origen de cada línea y total $1.300.
- `SaleItemsTest`:
  - un `unit_price` / `price_list_id` / `line_total` enviados en la petición se ignoran.
  - el total de varias líneas pesables es la suma exacta de los totales redondeados a centavos.
  - cambiar la cantidad conserva el precio de la línea aunque la lista haya cambiado.
- `ChangeSaleCustomerTest`: volver de un cliente con lista particular a Consumidor Final re-precia
  desde la lista de mostrador o, si el artículo no está, desde la general.

## Fuera de alcance

- Discriminación del IVA (`HU-063`, ya hecha).
- Tipo de comprobante al cambiar el cliente (`EPIC-03`).
- Cobro (`EPIC-04`).
- Confirmación del PO de que el precio de lista es final con IVA incluido (pregunta abierta para
  el 02/10); el backlog conserva la nota de "pendiente de confirmar".
