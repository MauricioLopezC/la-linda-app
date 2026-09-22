---
paths:
  - 'app/Models/Pricing/**'
---

# Pricing

## Listas de precios: el canal y el cliente son dos ejes distintos
`price_lists.scope` separa dos preguntas que no son intercambiables:

- `canal` — "¿por dónde se vende?". Lleva `channel` (`mostrador`, `online`, `general`). Como el canal tiene que resolver un único precio, dos listas de canal activas del mismo canal no pueden tener periodos superpuestos. El canal `general` es el respaldo de la cascada (HU-056) y no puede quedar descubierto desde hoy en adelante: ver `PriceList::generalCoverageIsContinuous()`, que recorre la cadena de listas generales activas y admite una sucesora que arranque el día siguiente.
- `particular` — "¿a quién se le vende?". `channel` siempre null. Se aplica solo a los clientes que la tengan asignada (HU-022) y puede superponerse libremente con otras listas, porque no se resuelve por canal.

No agregar valores nuevos a `PriceListChannel` para modelar segmentos de cliente (mayorista, distribuidor, etc.): eso es una lista `particular`. Mezclar los dos ejes en `channel` fue el bug original de HU-011, que hacía inalcanzable el paso 1 de la cascada de precios.
