# Glosario del dominio

Términos específicos del negocio de La Linda y su significado exacto en este sistema.
El objetivo es evitar ambigüedad cuando una palabra cotidiana tiene un significado particular aquí
(ej. "cliente" podría no ser lo mismo que "usuario" o "socio").

## Cómo usar este fichero

- Un término por entrada, en orden alfabético.
- Si el término corresponde a un modelo Eloquent, indicarlo entre paréntesis.
- Si dos términos se confunden fácilmente, aclarar la diferencia explícitamente.

## Términos

<!-- Ejemplo:
### Cliente (`Customer`)
Persona o empresa que consume el servicio. No confundir con `User`, que es cualquier persona con acceso al sistema (incluye staff interno).
-->

### Lista de precios (`PriceList`)
Cabecera que agrupa los precios de venta de un conjunto de artículos con una vigencia propia
(`valid_from` / `valid_to`). No contiene los precios en sí: esos viven en `price_list_items`
(HU-012). Toda lista es **de canal** o **particular**; son dos ejes distintos y no intercambiables.

### Lista de canal (`PriceList` con `scope = canal`)
Responde a **"¿por dónde se vende?"**. Es el precio base de un canal de venta y lleva `channel`
(`mostrador`, `online` o `general`). Como el canal tiene que poder resolver un único precio, **no
puede haber dos listas de canal activas del mismo canal con periodos superpuestos**. Para cambiar
los precios de un canal se le pone fecha de fin a la vigente y se crea la sucesora a partir del día
siguiente.

### Lista general (`PriceList` con `scope = canal` y `channel = general`)
La lista de canal de respaldo: el último escalón de la cascada de resolución de precios (HU-056),
el que se aplica cuando el canal de la operación no tiene lista propia vigente. El sistema
**garantiza que nunca quede descubierta** desde la fecha actual en adelante: no se la puede
desactivar, ni ponerle fecha de fin, ni cambiarle el canal si con eso el canal `general` quedaría
sin cobertura. No confundir con "lista particular": `general` es un canal, no un tipo de lista.

### Lista particular (`PriceList` con `scope = particular`)
Responde a **"¿a quién se le vende?"**. Es un precio preferencial (ej. "Mayorista") que se aplica
solo a los clientes que la tengan asignada (HU-022) y que tiene precedencia sobre la lista del
canal. No lleva `channel`: el precio preferencial de un cliente es el mismo compre por mostrador o
por la tienda online. Varias listas particulares pueden convivir entre sí y con las de canal sobre
el mismo periodo, porque no se resuelven por canal sino por asignación explícita.

### Precio de lista (`PriceListItem`)
El precio de venta de **un** artículo en **una** lista (`price_list_items`, HU-012). La unicidad es
por lista, así que el mismo artículo puede valer distinto en mostrador, en online y en una lista
particular sin conflicto. Un artículo "sin precio" en una lista es la **ausencia de fila**, no un
precio en cero: el precio siempre es mayor a cero.

Solo se puede tarifar un artículo activo. El artículo que se desactiva *después* de haber sido
tarifado conserva su fila para que el precio obsoleto se pueda quitar, pero no se puede editar.

### Estado vs. vigencia de una lista
Son dos cosas independientes. El **estado** (`is_active`) es una decisión manual: la lista se usa o
no se usa. La **vigencia** es calculada a partir de las fechas y la fecha actual: `Vigente`,
`Futura` o `Vencida`. Una lista puede estar activa pero vencida, o inactiva pero dentro de su
periodo. Para que la cascada de precios la considere tiene que estar **activa y vigente** a la vez.
