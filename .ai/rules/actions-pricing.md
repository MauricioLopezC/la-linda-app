---
paths:
  - 'app/Actions/Pricing/**'
---

# Actions Pricing

## La pantalla de carga de precios consulta Artículos, no PriceListItems
`ConsultPriceListArticles` (HU-012) tiene como query base `Article` con un LEFT JOIN a `price_list_items` filtrado por la lista, no `PriceListItem`. Un artículo sin precio es la **ausencia** de fila, así que partir de `price_list_items` haría inalcanzable el filtro "artículos sin precio asignado" que pide el CA.

Alcance de filas: artículos activos **∪** artículos que ya tienen precio en esa lista. Un artículo desactivado después de haber sido tarifado sigue visible (badge "Artículo inactivo", input deshabilitado) para que su precio obsoleto se pueda quitar; si el query fuera solo activos, ese precio quedaría invisible y la cascada de HU-056 lo seguiría resolviendo.
