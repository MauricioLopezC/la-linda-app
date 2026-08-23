# Product Backlog - Supermercados La Linda

Fuente de verdad del Product Backlog. Se edita acá, en markdown. El Excel para el
Product Owner se genera a pedido a partir de este documento.

- Metodología: Scrum, 6 sprints de 2 semanas, equipo de 6 personas.
- **El orden es la prioridad.** No hay campo de prioridad: para repriorizar se mueve el ítem.
- **No hay campo de entrega, y no hay ningún documento que reparta los ítems entre sprints por
  adelantado.** El plan de entrega es este orden. Qué entra en cada sprint lo confirma el Product
  Owner en el planning y queda registrado en `sprint-backlog-<n>.md`; cuando un ítem entra a un
  sprint se le agrega `**Sprint:** N`. La tabla sprint por sprint que vivía en la sección 7 de
  `LaLindaAlcanceV1.md` se eliminó el 2026-08-11: contradecía lo que el PO había pedido arrancar
  (artículos y stock) y ya había inducido a error. La sección 7 ahora describe **cómo** se
  planifica, no **qué** va en cada sprint.
- **Una sola lista ordenada, sin agrupar.** El agrupamiento por MMF se eliminó el 2026-08-11:
  reproducía el corte de entregas de la V1 y un cuarto de los ítems quedaba bajo un título que
  no los describía. Los MMF reales se identifican al cerrar cada sprint, no por adelantado.
- Estimación en **story points**, escala de Fibonacci.
- **Capacidad de referencia: 30 a 40 SP por sprint (indicación del PO).** Con 6 sprints eso da
  entre 180 y 240 SP. El backlog estimado suma **350 SP**, entre 1,5 y 1,9 veces la capacidad.
  El desvío se informa al PO para que decida el recorte; **no se disimula bajando las
  estimaciones**, porque los story points miden tamaño relativo y la capacidad es un hecho aparte.
- **Ningún ítem supera los 8 SP.** Un ítem de 13 es un tercio de la capacidad del sprint y
  arrastra el sprint entero si se atasca. Los siete ítems de 13 SP se dividieron el 2026-08-11:
  la trazabilidad está en la tabla que sigue al índice.
- **Criterio de estimación:** el primer ítem que construye un patrón técnico se estima más caro
  que los que después lo reusan. Por eso el primer listado con filtros combinables y exportación
  (`HU-009`) vale 5 y los que vienen después (`HU-028`, `HU-053`) valen 3. `HU-016` y `HU-018`
  valen 2 desde el 2026-08-22, cuando la exportación salió de sus criterios hacia `HU-053`.
- **Alcance** referencia las macrofuncionalidades de `LaLindaAlcanceV1.md` (`ADM-01`, `STK-04`, ...).
- Los criterios de aceptación siguen la estructura *Datos / Validaciones / Comportamiento / Verificación*.
- El nivel de detalle decrece a propósito: los ítems del final del backlog llevan un solo criterio
  con lo que ya define el alcance y el resto queda para el refinamiento previo a su sprint.
- **`HU-004` no es precondición de nada (PO, 2026-08-11).** El profesor pidió arrancar por
  artículos y stock, así que las historias de catálogo, parámetros, proveedores y clientes ya
  no dependen de roles y permisos: les alcanza con que exista un usuario logueado, que lo
  provee el starter kit. Los permisos se aplican cuando `HU-004` se construya.
- **Fuera del backlog por acuerdo con el PO (2026-08-11):** el entorno de trabajo y el layout
  base se resuelven antes del Sprint 1, y el login y el cambio de contraseña los provee el
  starter kit de Laravel. Por eso no hay ítems para `SEG-02` ni habilitadores de arranque.
- **Corrección del PO a mitad de Sprint 1 (2026-08-22):** insistió en priorizar exclusivamente
  artículos y stock -lo que incluye depósitos y movimientos entre depósitos-. En consecuencia:
  `HU-005` se dividió y la parte de puntos de venta pasó a `HU-051`; `HU-007` se dividió en
  alícuotas de IVA (se queda con el ID `HU-007`) y medios de pago (pasó a `HU-052`), porque
  ninguna de las dos hace falta para artículos ni para stock. Las tres bajaron de prioridad y se
  reubicaron justo antes de la primera historia que realmente las necesita, mismo criterio ya
  usado con las listas de precios: `HU-051` antes de `HU-039`, `HU-007` antes de `HU-041` y
  `HU-052` antes de `EPIC-04`. `HU-008` deja de depender de `HU-007`: la alícuota de IVA pasa a
  opcional en el artículo hasta que esa historia entre; `HU-041` sí depende de `HU-007`, porque
  ahí es donde la alícuota se usa por primera vez para calcular algo.
- Texto con acentos (corregido el 2026-08-11; heredaba del CSV una convención sin acentos).

## Índice

68 ítems, **350 story points estimados**, más la reserva de estabilización del Sprint 6
(`HAB-03`, sin estimar a propósito).

| # | ID | Título | Módulo | SP | Estado |
|---|----|--------|--------|----|--------|
| 1 | [HU-005](#hu-005) | Administrar sucursales y depósitos | ADM | 3 | Pendiente |
| 2 | [HU-006](#hu-006) | Administrar categorías, marcas y unidades de medida | ADM | 5 | Pendiente |
| 3 | [HU-031](#hu-031) | Administrar los parámetros de stock | ADM | 2 | Pendiente |
| 4 | [HU-008](#hu-008) | Administrar el catálogo de artículos | ART | 5 | Pendiente |
| 5 | [HU-032](#hu-032) | Registrar la imagen de un artículo | ART | 3 | Pendiente |
| 6 | [HU-009](#hu-009) | Buscar artículos en el catálogo | ART | 5 | Pendiente |
| 7 | [HU-010](#hu-010) | Importar el catálogo desde un archivo CSV | ART | 8 | Pendiente |
| 8 | [HU-011](#hu-011) | Administrar listas de precios | PRE | 5 | Pendiente |
| 9 | [HU-012](#hu-012) | Definir el precio de venta de los artículos en una lista | PRE | 8 | Pendiente |
| 10 | [HU-003](#hu-003) | Administrar los usuarios del sistema | SEG | 5 | Pendiente |
| 11 | [HU-004](#hu-004) | Administrar roles y sus permisos | SEG | 8 | Pendiente |
| 12 | [HU-013](#hu-013) | Administrar proveedores | CMP | 5 | Pendiente |
| 13 | [HU-014](#hu-014) | Registrar los contactos de un proveedor y consultar el listado | CMP | 3 | Pendiente |
| 14 | [HU-015](#hu-015) | Asociar artículos a sus proveedores | ART | 5 | Pendiente |
| 15 | [HU-016](#hu-016) | Consultar las existencias por depósito | STK | 2 | Pendiente |
| 16 | [HU-017](#hu-017) | Registrar un ajuste de stock con motivo documentado | STK | 8 | Pendiente |
| 17 | [HU-018](#hu-018) | Consultar el historial de movimientos de stock | STK | 2 | Pendiente |
| 18 | [HU-019](#hu-019) | Transferir mercadería entre depósitos | STK | 8 | Pendiente |
| 19 | [HU-020](#hu-020) | Definir el stock mínimo y ver los artículos en faltante | STK | 5 | Pendiente |
| 20 | [HU-021](#hu-021) | Administrar clientes | CLI | 5 | Pendiente |
| 21 | [HU-022](#hu-022) | Asignar una lista de precios a un cliente | CLI | 2 | Pendiente |
| 22 | [HU-033](#hu-033) | Registrar la cabecera de una orden de compra | CMP | 3 | Pendiente |
| 23 | [HU-034](#hu-034) | Cargar el detalle de artículos de la orden de compra | CMP | 5 | Pendiente |
| 24 | [HU-035](#hu-035) | Calcular los totales y emitir la orden de compra | CMP | 3 | Pendiente |
| 25 | [HU-024](#hu-024) | Gestionar los estados y consultar las órdenes de compra | CMP | 5 | Pendiente |
| 26 | [HU-036](#hu-036) | Registrar un comprobante de proveedor | CMP | 5 | Pendiente |
| 27 | [HU-037](#hu-037) | Imputar el comprobante a una o varias órdenes de compra | CMP | 5 | Pendiente |
| 28 | [HU-038](#hu-038) | Actualizar el último costo y cerrar la orden cubierta | CMP | 3 | Pendiente |
| 29 | [HU-026](#hu-026) | Ingresar el stock a partir del comprobante recibido | CMP | 8 | Pendiente |
| 30 | [HU-027](#hu-027) | Registrar un pago a proveedor imputado a comprobantes | CMP | 8 | Pendiente |
| 31 | [HU-028](#hu-028) | Consultar el saldo de cuenta corriente de un proveedor | CMP | 3 | Pendiente |
| 32 | [HU-029](#hu-029) | Actualizar precios de forma masiva por porcentaje | PRE | 5 | Pendiente |
| 33 | [HU-030](#hu-030) | Consultar el historial de cambios de precio | PRE | 3 | Pendiente |
| 34 | [EPIC-01](#epic-01) | Resolver el precio de venta según el cliente y el canal | PRE | 8 | Pendiente |
| 35 | [HU-051](#hu-051) | Administrar puntos de venta | ADM | 2 | Pendiente |
| 36 | [HU-039](#hu-039) | Abrir una venta de mostrador | VTA | 3 | Pendiente |
| 37 | [HU-040](#hu-040) | Incorporar artículos a la venta por código de barras o búsqueda | VTA | 5 | Pendiente |
| 38 | [HU-007](#hu-007) | Administrar las alícuotas de IVA | ADM | 2 | Pendiente |
| 39 | [HU-041](#hu-041) | Calcular el precio, el IVA y los totales de la venta | VTA | 5 | Pendiente |
| 40 | [EPIC-03](#epic-03) | Identificar al cliente y determinar el tipo de comprobante | VTA | 5 | Pendiente |
| 41 | [HU-052](#hu-052) | Administrar medios de pago | ADM | 2 | Pendiente |
| 42 | [EPIC-04](#epic-04) | Cobrar la venta con uno o varios medios de pago | VTA | 8 | Pendiente |
| 43 | [HU-042](#hu-042) | Emitir la factura con numeración correlativa por punto de venta | VTA | 8 | Pendiente |
| 44 | [HU-043](#hu-043) | Imprimir y descargar la factura en PDF | VTA | 5 | Pendiente |
| 45 | [EPIC-06](#epic-06) | Descontar el stock automáticamente al confirmar la venta | VTA | 5 | Pendiente |
| 46 | [HU-044](#hu-044) | Anular una venta con nota de crédito y reingreso de stock | VTA | 8 | Pendiente |
| 47 | [HU-045](#hu-045) | Registrar una devolución parcial de cliente | VTA | 5 | Pendiente |
| 48 | [EPIC-08](#epic-08) | Consultar los comprobantes emitidos | VTA | 5 | Pendiente |
| 49 | [EPIC-09](#epic-09) | Consultar la ficha del cliente con su historial | CLI | 5 | Pendiente |
| 50 | [EPIC-10](#epic-10) | Registrar y consultar el log de auditoría | SEG | 8 | Pendiente |
| 51 | [SPIKE-01](#spike-01) | Investigar la integración con ARCA (WSAA y WSFE) | VTA | 3 | Pendiente |
| 52 | [EPIC-11](#epic-11) | Registrarse e iniciar sesión como cliente en la tienda online | CLI | 8 | Pendiente |
| 53 | [HU-046](#hu-046) | Publicar el catálogo en la tienda online | ECO | 5 | Pendiente |
| 54 | [HU-047](#hu-047) | Buscar, filtrar y ordenar artículos en la tienda online | ECO | 5 | Pendiente |
| 55 | [HU-048](#hu-048) | Mostrar la disponibilidad online e impedir la compra sin stock | ECO | 3 | Pendiente |
| 56 | [EPIC-13](#epic-13) | Gestionar el carrito de compras | ECO | 8 | Pendiente |
| 57 | [HU-049](#hu-049) | Elegir la modalidad de entrega y calcular el costo de envío | ECO | 5 | Pendiente |
| 58 | [HU-050](#hu-050) | Pagar el pedido con Mercado Pago en sandbox | ECO | 8 | Pendiente |
| 59 | [EPIC-15](#epic-15) | Procesar el pedido pagado como una venta con factura y egreso de stock | ECO | 8 | Pendiente |
| 60 | [EPIC-16](#epic-16) | Seguir el estado del pedido y recibir notificaciones por correo | ECO | 8 | Pendiente |
| 61 | [EPIC-17](#epic-17) | Administrar los pedidos web desde el panel interno | ECO | 8 | Pendiente |
| 62 | [EPIC-18](#epic-18) | Visualizar los ingresos del periodo | DSH | 8 | Pendiente |
| 63 | [EPIC-19](#epic-19) | Visualizar los egresos del periodo | DSH | 5 | Pendiente |
| 64 | [EPIC-20](#epic-20) | Visualizar la relación entre ingresos y egresos y su evolución | DSH | 8 | Pendiente |
| 65 | [EPIC-21](#epic-21) | Visualizar indicadores operativos complementarios | DSH | 5 | Pendiente |
| 66 | [HU-053](#hu-053) | Exportar a CSV y Excel los listados de stock | STK | 3 | Pendiente |
| 67 | [EPIC-22](#epic-22) | Filtrar y exportar el tablero gerencial | DSH | 5 | Pendiente |
| 68 | [HAB-03](#hab-03) | Estabilización y cierre | - | reserva | Pendiente |

### Historias desglosadas por corrección del PO (2026-08-22)

`HU-005`, `HU-007`, `HU-016` y `HU-018` estaban comprometidas en el Sprint 1 con alcance que
excedía lo que el PO pidió para el arranque -artículos y stock, sin nada que él no hubiera pedido
explícitamente-. Se desglosaron -no se descartaron- para que la parte fuera de alcance actual baje
de prioridad sin perderse. Las historias resultantes se reubicaron justo antes de la primera
historia que realmente las necesita, o al final del backlog cuando ninguna las necesita:

| ID anterior | SP | Se dividió en | SP | Nueva posición |
|---|---|---|---|---|
| HU-005 Administrar sucursales, depósitos y puntos de venta | 5 | HU-005 (sucursales y depósitos) + HU-051 (puntos de venta) | 3 + 2 = 5 | HU-005 se queda; HU-051 antes de HU-039 |
| HU-007 Administrar los parámetros comerciales | 2 | HU-007 (alícuotas de IVA) + HU-052 (medios de pago) | 2 + 2 = 4 | HU-007 antes de HU-041; HU-052 antes de EPIC-04 |
| HU-016 y HU-018, con exportación a CSV y Excel | 3 + 3 | HU-016 y HU-018 (sin exportación) + HU-053 (exportación de los listados de stock) | 2 + 2 + 3 = 7 | HU-016 y HU-018 se quedan; HU-053 antes de EPIC-22 |

El +1 SP de la última fila no es un error de suma: separar la exportación de los dos listados le
agrega el costo de aplicarla dos veces sobre pantallas ya terminadas. Es el precio de posponerla, y
se hace explícito en vez de disimularlo.

## Trazabilidad de la división de ítems de 13 SP (2026-08-11)

Ningún ítem entra a un sprint con 13 SP. Estos siete se dividieron; los IDs viejos ya no existen
y esta tabla sirve para reconciliar contra el Excel que ya vio el PO.

| ID anterior | SP | Se dividió en | SP |
|---|---|---|---|
| HU-023 Emitir una orden de compra | 13 | HU-033 + HU-034 + HU-035 | 3 + 5 + 3 = 11 |
| HU-025 Registrar un comprobante de proveedor | 13 | HU-036 + HU-037 + HU-038 | 5 + 5 + 3 = 13 |
| EPIC-02 Registrar una venta en mostrador | 13 | HU-039 + HU-040 + HU-041 | 3 + 5 + 5 = 13 |
| EPIC-05 Emitir la factura y descargarla en PDF | 13 | HU-042 + HU-043 | 8 + 5 = 13 |
| EPIC-07 Anular una venta y registrar devoluciones | 13 | HU-044 + HU-045 | 8 + 5 = 13 |
| EPIC-12 Navegar el catálogo público | 13 | HU-046 + HU-047 + HU-048 | 5 + 5 + 3 = 13 |
| EPIC-14 Modalidad de entrega y Mercado Pago | 13 | HU-049 + HU-050 | 5 + 8 = 13 |
| | **91** | | **89** |

Cuatro de las siete divisiones siguen las macrofuncionalidades del alcance, que ya venían
separadas: `VTA-01/02/03`, `ECO-01/02/03`, `ECO-05/06` y `CMP-05`.

## HU-005 - Administrar sucursales y depósitos

**Tipo:** Historia · **Módulo:** ADM · **Estimación:** 3 SP · **Estado:** Pendiente · **Alcance:** `ADM-01` · **Depende de:** nada

**Como** Administrador, **necesito** registrar las sucursales con sus depósitos, **para** que el resto del sistema sepa dónde se guarda la mercadería.

**Criterios de aceptación**

- **Datos:**
    - sucursal (nombre, dirección, teléfono, estado)
    - depósito (nombre, sucursal a la que pertenece, estado, indicador de depósito asignado al canal online)
- **Validaciones:**
    - nombre de sucursal único
    - todo depósito pertenece a una sucursal
    - existe a lo sumo un depósito marcado como canal online
    - no se puede dar de baja una sucursal o un depósito con existencias o movimientos registrados

> **Corrección del PO (2026-08-22):** la parte de puntos de venta se sacó de esta historia. No
> hace falta para artículos ni para stock -el punto de venta es de dónde se vende, no de dónde se
> guarda la mercadería- y el PO pidió priorizar exclusivamente eso. Pasó a `HU-051`, reubicada
> más abajo en el backlog, justo antes de `HU-039`, la primera historia que realmente la necesita.

## HU-006 - Administrar categorías, marcas y unidades de medida

**Tipo:** Historia · **Módulo:** ADM · **Estimación:** 5 SP · **Estado:** Pendiente · **Alcance:** `ADM-02` · **Depende de:** nada

**Como** Administrador, **necesito** administrar las categorías con sus subcategorías, las marcas y las unidades de medida, **para** clasificar el catálogo de forma uniforme y evitar que cada persona invente su propia nomenclatura.

**Criterios de aceptación**

- **Datos:**
    - categoría (nombre, categoría padre opcional, estado)
    - marca (nombre, estado)
    - unidad de medida (nombre, abreviatura, estado)
- **Validaciones:**
    - la jerarquía de categorías admite exactamente dos niveles, una subcategoría no puede tener a su vez subcategorías
    - nombre único dentro del mismo nivel
    - abreviatura de unidad de medida única
    - no se puede dar de baja una categoría, marca o unidad con artículos asociados
- **Comportamiento:** el árbol de categorías se visualiza jerárquicamente

## HU-031 - Administrar los parámetros de stock

**Tipo:** Historia · **Módulo:** ADM · **Estimación:** 2 SP · **Estado:** Pendiente · **Alcance:** `ADM-03` · **Depende de:** nada

**Como** Administrador, **necesito** administrar los tipos de movimiento de stock y los motivos de ajuste, **para** que todo movimiento de stock quede tipado y todo ajuste quede justificado con un motivo controlado.

**Criterios de aceptación**

- **Datos:**
    - tipo de movimiento de stock (nombre, signo de afectación suma o resta)
    - motivo de ajuste (nombre, estado)
- **Validaciones:**
    - nombre único dentro de cada entidad
    - no se puede dar de baja un valor ya utilizado en una operación registrada
    - los tipos de movimiento propios del sistema (entrada por compra, salida por venta, devolución, transferencia y ajuste) no pueden eliminarse

## HU-008 - Administrar el catálogo de artículos

**Tipo:** Historia · **Módulo:** ART · **Estimación:** 5 SP · **Estado:** Pendiente · **Alcance:** `ART-01`, `ART-02`, `ART-03` · **Depende de:** HU-006

**Como** Encargado de compras, **necesito** registrar, modificar y dar de baja artículos del catálogo, **para** contar con una única definición de cada artículo compartida por todas las sucursales y canales.

**Criterios de aceptación**

- **Datos:** descripción, código interno, código de barras, categoría, subcategoría, marca, unidad de medida, alícuota de IVA, estado (activo, inactivo, discontinuado), indicador de publicable en el canal online
- **Validaciones:**
    - descripción, código interno, categoría y unidad de medida son obligatorios
    - el código interno es único en todo el catálogo
    - el código de barras es único cuando se informa
    - no se admite crear ni modificar un artículo que genere duplicidad de ninguno de los dos códigos
    - la baja de un artículo con movimientos de stock o ventas asociadas es lógica y lo deja en estado discontinuado
- **Comportamiento:**
    - el artículo NO tiene campo de precio de venta y el formulario no ofrece ninguno
    - el precio se administra exclusivamente desde el módulo de Listas de Precios
    - tampoco tiene proveedor ni depósito como atributos: la relación con proveedores es de muchos a muchos y se administra en HU-015, y la existencia por depósito se consulta en HU-016
    - las imágenes tampoco son un campo del formulario de alta: se administran aparte en HU-032

> **Corrección del PO (2026-08-22):** la alícuota de IVA pasa de obligatoria a opcional. `HU-007`
> (que la administra) bajó de prioridad porque el PO pidió priorizar exclusivamente artículos y
> stock, e IVA es un dato de facturación, no de stock. El artículo puede nacer sin alícuota
> asignada; se completa cuando `HU-007` entre.

## HU-032 - Registrar la imagen de un artículo

**Tipo:** Historia · **Módulo:** ART · **Estimación:** 3 SP · **Estado:** Pendiente · **Alcance:** `ART-05` · **Depende de:** HU-008

**Como** Encargado de compras, **necesito** cargar la imagen de un artículo, **para** que quien lo busque en el sistema o en la tienda online lo reconozca sin depender solo de la descripción.

**Criterios de aceptación**

- **Datos:** artículo, imagen (archivo cargado y su URL)
- **Validaciones:**
    - la imagen admite formatos JPG y PNG de hasta 2 MB
    - un artículo tiene a lo sumo una imagen; cargar una nueva reemplaza a la anterior
- **Comportamiento:**
    - la imagen se administra desde la ficha del artículo, con carga y baja
    - la imagen es la que se muestra en el listado de búsqueda (HU-009) y en el catálogo público (HU-046)
    - un artículo sin imagen se muestra con una imagen genérica, no con un espacio vacío

## HU-009 - Buscar artículos en el catálogo

**Tipo:** Historia · **Módulo:** ART · **Estimación:** 5 SP · **Estado:** Pendiente · **Alcance:** `ART-05` · **Depende de:** HU-008, HU-032

**Como** Encargado de compras, **necesito** buscar y filtrar artículos y ver su imagen, **para** encontrar rápidamente el artículo que necesito entre miles de registros.

**Criterios de aceptación**

- **Datos:** filtros por descripción, código interno, código de barras, categoría, marca, proveedor y estado
- **Validaciones:** la búsqueda por descripción es parcial y no distingue mayúsculas ni acentos
- **Comportamiento:**
    - los filtros se combinan entre sí
    - el resultado se pagina
    - la búsqueda por código de barras exacto devuelve directamente el artículo correspondiente
    - el listado es exportable a CSV y Excel
    - el filtro por proveedor depende de la asociación artículo-proveedor, que llega con HU-015: hasta entonces la búsqueda se entrega sin ese filtro y se completa después

## HU-010 - Importar el catálogo desde un archivo CSV

**Tipo:** Historia · **Módulo:** ART · **Estimación:** 8 SP · **Estado:** Pendiente · **Alcance:** `ART-06` · **Depende de:** HU-008

**Como** Encargado de compras, **necesito** cargar el catálogo completo de forma masiva desde un archivo CSV, **para** no tener que dar de alta miles de artículos uno por uno para poner el sistema en funcionamiento.

**Criterios de aceptación**

- **Datos:** archivo CSV con las columnas descripción, código interno, código de barras, categoría, subcategoría, marca, unidad de medida, alícuota de IVA, estado (activo, inactivo, discontinuado), indicador de publicable en el canal online
- **Validaciones:**
    - se rechaza la fila cuyo código interno ya existe, la que referencia una categoría, marca, unidad o alícuota inexistente, y la que omite un dato obligatorio
    - las filas válidas se importan igualmente aunque otras fallen
- **Comportamiento:**
    - al finalizar se muestra un resumen con la cantidad de filas procesadas, importadas y rechazadas, y el detalle de cada rechazo con su número de fila y su motivo
    - el informe de rechazos se puede descargar
    - la importación se ejecuta dentro de una operación que no deja el catálogo en estado intermedio si se interrumpe

## HU-011 - Administrar listas de precios

**Tipo:** Historia · **Módulo:** PRE · **Estimación:** 5 SP · **Estado:** Pendiente · **Alcance:** `PRE-01` · **Depende de:** nada

**Como** Gerente, **necesito** crear y administrar listas de precios con su canal y su vigencia, **para** aplicar una política de precios distinta según el canal de venta sin tocar el catálogo.

**Criterios de aceptación**

- **Datos:** nombre, canal asociado (mostrador, online o general), fecha de vigencia desde, fecha de vigencia hasta opcional, estado
- **Validaciones:**
    - nombre único
    - la vigencia hasta no puede ser anterior a la vigencia desde
    - no puede haber dos listas activas y vigentes para el mismo canal en el mismo periodo
    - debe existir siempre al menos una lista general vigente
    - no se puede dar de baja una lista utilizada en ventas registradas
- **Comportamiento:** el listado muestra el estado de vigencia calculado a la fecha actual y la cantidad de artículos con precio asignado en cada lista

> **Corrección (2026-08-22):** esta historia dependía de `HU-007`, pero ninguno de sus criterios
> usa medios de pago ni alícuotas de IVA. Se corrige a "Depende de: nada" al revisar `HU-007` por
> el pedido del PO de priorizar artículos y stock.

## HU-012 - Definir el precio de venta de los artículos en una lista

**Tipo:** Historia · **Módulo:** PRE · **Estimación:** 8 SP · **Estado:** Pendiente · **Alcance:** `PRE-02` · **Depende de:** HU-011

**Como** Gerente, **necesito** definir el precio de venta de cada artículo dentro de cada lista de precios, **para** que toda venta tome siempre un precio controlado y no uno cargado a mano por el vendedor.

**Criterios de aceptación**

- **Datos:** lista de precios, artículo, precio de venta
- **Validaciones:**
    - el precio debe ser mayor a cero y admite dos decimales
    - un mismo artículo no puede figurar dos veces en la misma lista
    - solo se pueden asignar precios a artículos en estado activo
- **Comportamiento:**
    - la pantalla permite seleccionar la lista, buscar el artículo y cargar o modificar su precio
    - un mismo artículo puede tener precios distintos en listas distintas
    - se muestra el listado de artículos de la lista con su precio, con filtro por categoría y con filtro de artículos sin precio asignado
- **Verificación:** se carga un mismo artículo en la lista de mostrador y en la lista online con precios distintos y se comprueba que ambos conviven

## HU-003 - Administrar los usuarios del sistema

**Tipo:** Historia · **Módulo:** SEG · **Estimación:** 5 SP · **Estado:** Pendiente · **Alcance:** `SEG-01` · **Depende de:** nada

**Como** Administrador, **necesito** registrar, modificar y dar de baja usuarios, **para** controlar quién puede operar el sistema y que toda operación quede asociada a un responsable.

**Criterios de aceptación**

- **Datos:** nombre, apellido, documento, correo electrónico, nombre de usuario, sucursal de pertenencia, rol, estado activo o inactivo
- **Validaciones:**
    - nombre de usuario, correo y documento únicos
    - correo con formato válido
    - sucursal y rol obligatorios
    - no se permite dejar el sistema sin al menos un usuario administrador activo
- **Comportamiento:**
    - la baja de un usuario con operaciones registradas es siempre lógica y pasa a estado inactivo, nunca se elimina físicamente
    - el listado se filtra por nombre, sucursal, rol y estado
    - al crear el usuario se le asigna una contraseña inicial que deberá cambiar en su primer ingreso
    - la contraseña inicial no se envía por correo: se muestra una única vez en pantalla al Administrador al confirmar el alta, para que se la comunique al empleado por el medio que corresponda

> **Resuelto (2026-08-12, a confirmar con el PO):** se descarta el autoregistro de empleados
> -ese patrón es el de `EPIC-11` para clientes de la tienda online, no para personal interno
> que ya gestiona el Administrador en esta misma historia-. También se descarta el envío de
> mail con la contraseña inicial: `LaLindaAlcanceV1.md` (línea 179) ya deja la "recuperación de
> contraseña por correo electrónico" en **Deseable**, fuera del alcance comprometido de
> `SEG-02`, así que construir envío de mails ahora sería alcance nuevo sin aprobar. La solución
> dentro de alcance es mostrar la contraseña generada en pantalla al Admin (arriba, en
> Comportamiento). Si más adelante se aprueba el ítem deseable, el mecanismo a implementar es
> un link de invitación reutilizando el flujo de recuperación de contraseña del starter kit,
> no reenviar la contraseña en texto plano por mail.

## HU-004 - Administrar roles y sus permisos

**Tipo:** Historia · **Módulo:** SEG · **Estimación:** 8 SP · **Estado:** Pendiente · **Alcance:** `SEG-03`, `SEG-04` · **Depende de:** HU-003

**Como** Administrador, **necesito** definir los roles y los permisos que cada uno tiene sobre cada módulo y operación, **para** que cada usuario acceda únicamente a las funciones que le corresponden según su puesto.

**Criterios de aceptación**

- **Datos:** nombre del rol, descripción, matriz de permisos por módulo y por operación (consulta, alta, modificación, baja, autorización)
- **Validaciones:**
    - nombre de rol único
    - no se puede eliminar un rol con usuarios asignados
    - el rol Administrador conserva siempre la totalidad de los permisos y no puede quedar sin ellos
- **Comportamiento:**
    - el sistema se entrega con los roles predefinidos administrador, gerente, encargado de depósito, encargado de compras y vendedor
    - la restricción se aplica ocultando las opciones no permitidas en la interfaz y además rechazando la petición en el servidor
- **Verificación:** un usuario sin permiso que intenta acceder por URL directa recibe un error de autorización y no la pantalla

## HU-013 - Administrar proveedores

**Tipo:** Historia · **Módulo:** CMP · **Estimación:** 5 SP · **Estado:** Pendiente · **Alcance:** `CMP-01` · **Depende de:** nada

**Como** Encargado de compras, **necesito** registrar, modificar y dar de baja proveedores, **para** tener centralizada la información de quién me abastece y en qué condiciones comerciales.

**Criterios de aceptación**

- **Datos:** razón social, CUIT, condición fiscal, domicilio comercial, rubro, cuenta bancaria para pagos, condiciones comerciales pactadas, estado
- **Validaciones:**
    - razón social y CUIT obligatorios
    - CUIT único y con dígito verificador válido
    - condición fiscal obligatoria seleccionada de una lista cerrada
    - la baja de un proveedor con órdenes, comprobantes o pagos asociados es siempre lógica
- **Comportamiento:** el sistema registra el historial de cambios del proveedor en el log de auditoría

## HU-014 - Registrar los contactos de un proveedor y consultar el listado

**Tipo:** Historia · **Módulo:** CMP · **Estimación:** 3 SP · **Estado:** Pendiente · **Alcance:** `CMP-02` · **Depende de:** HU-013

**Como** Encargado de compras, **necesito** registrar varios contactos por proveedor y buscar proveedores por distintos criterios, **para** saber a quién dirigirme por cada gestión sin depender de la agenda personal de nadie.

**Criterios de aceptación**

- **Datos:**
    - contacto (nombre, cargo, teléfono, correo electrónico, observaciones)
    - filtros del listado por razón social, CUIT, rubro y estado
- **Validaciones:**
    - un proveedor admite más de un contacto
    - nombre y al menos un medio de contacto obligatorios
    - correo con formato válido cuando se informa
- **Comportamiento:** los filtros del listado se combinan entre sí y el resultado es exportable a CSV y Excel

## HU-015 - Asociar artículos a sus proveedores

**Tipo:** Historia · **Módulo:** ART · **Estimación:** 5 SP · **Estado:** Pendiente · **Alcance:** `ART-04` · **Depende de:** HU-013

**Como** Encargado de compras, **necesito** asociar cada artículo a los proveedores que lo abastecen con el código que ellos utilizan, **para** poder emitir órdenes de compra sin tener que traducir códigos manualmente.

**Criterios de aceptación**

- **Datos:** artículo, proveedor, código del artículo en el proveedor, último costo de compra conocido
- **Validaciones:**
    - un artículo puede tener varios proveedores y un proveedor puede abastecer varios artículos, la relación es de muchos a muchos
    - la combinación artículo más proveedor no se repite
    - el código del proveedor es único dentro de ese mismo proveedor
    - el costo debe ser mayor a cero cuando se informa
- **Comportamiento:**
    - la asociación se administra tanto desde la ficha del artículo como desde la ficha del proveedor
    - el último costo se actualizará automáticamente al registrar comprobantes de proveedor (HU-038)

## HU-016 - Consultar las existencias por depósito

**Tipo:** Historia · **Módulo:** STK · **Estimación:** 2 SP · **Estado:** Pendiente · **Alcance:** `STK-01` · **Depende de:** HU-005, HU-008

**Como** Encargado de depósito, **necesito** consultar la existencia de cada artículo en cada depósito, **para** saber con qué mercadería cuento realmente.

**Criterios de aceptación**

- **Datos:** artículo (código, descripción, categoría), depósito, cantidad en existencia
- **Validaciones:** la consulta alcanza a todos los depósitos del sistema; no hay permisos ni alcance operativo por sucursal ni por depósito, únicamente el permiso de consulta del módulo de stock
- **Comportamiento:**
    - se filtra por artículo, categoría, depósito y estado de existencias (con stock, sin stock)
    - se totaliza por sucursal y en general
    - la cantidad se muestra como campo de solo lectura, sin ninguna opción de edición directa

> **Corrección del PO (2026-08-22):** se sacan el stock mínimo y el indicador de faltante de los
> datos y filtros de esta historia. Ambos dependen de un mínimo por artículo que define `HU-020`,
> que quedó fuera del Sprint 1; se reincorporan a `HU-016` cuando `HU-020` entre a un sprint.

> **Segunda corrección del PO (2026-08-22):** se saca la exportación a CSV y Excel, que pasa a
> `HU-053` con prioridad baja. El PO fue explícito en no invertir tiempo en nada que no hubiera
> pedido, y la exportación no estaba entre lo que pidió para el arranque de artículos y stock. La
> estimación baja de 3 a 2 SP.

## HU-017 - Registrar un ajuste de stock con motivo documentado

**Tipo:** Historia · **Módulo:** STK · **Estimación:** 8 SP · **Estado:** Pendiente · **Alcance:** `STK-02`, `STK-03`, `STK-06` · **Depende de:** HU-016, HU-031

**Como** Encargado de depósito, **necesito** registrar un ajuste de existencias indicando el motivo, **para** corregir una diferencia dejando asentado quién la corrigió y por qué.

**Criterios de aceptación**

- **Datos:** depósito, motivo de ajuste, observaciones, y detalle con artículo, cantidad en sistema, cantidad ajustada y diferencia resultante
- **Validaciones:**
    - el motivo es obligatorio y se selecciona de la tabla de motivos, no se escribe en texto libre
    - la cantidad resultante no puede ser negativa
    - el detalle debe contener al menos un artículo
    - un artículo no puede repetirse dentro del mismo ajuste
    - la operación requiere el permiso específico de ajuste de stock
- **Comportamiento:**
    - la existencia nunca se edita de forma directa, se modifica exclusivamente como consecuencia del movimiento registrado
    - el movimiento queda con usuario responsable, fecha y hora
    - una vez confirmado no puede editarse ni eliminarse desde ninguna pantalla
- **Verificación:** se comprueba que no existe ninguna vía en la interfaz para modificar una cantidad sin generar un movimiento, y que el intento de editar un movimiento confirmado es rechazado

## HU-018 - Consultar el historial de movimientos de stock

**Tipo:** Historia · **Módulo:** STK · **Estimación:** 2 SP · **Estado:** Pendiente · **Alcance:** `STK-04` · **Depende de:** HU-017

**Como** Encargado de depósito, **necesito** consultar el historial de movimientos de un artículo o de un depósito, **para** poder rastrear el origen de cualquier diferencia de existencias.

**Criterios de aceptación**

- **Datos:** fecha y hora, tipo de movimiento, depósito de origen, depósito de destino, artículo, cantidad, usuario responsable, documento o motivo asociado
- **Validaciones:** el historial es de solo lectura, no existe opción de editar ni de eliminar en ninguna vista del sistema
- **Comportamiento:**
    - filtros por artículo, depósito, tipo de movimiento, usuario y rango de fechas, combinables entre sí
    - resultado paginado
    - desde cada movimiento se puede navegar al documento que lo originó cuando existe

> **Corrección del PO (2026-08-22):** se saca la exportación a CSV y Excel, que pasa a `HU-053` con
> prioridad baja, por el mismo motivo que en `HU-016`. La estimación baja de 3 a 2 SP.

## HU-019 - Transferir mercadería entre depósitos

**Tipo:** Historia · **Módulo:** STK · **Estimación:** 8 SP · **Estado:** Pendiente · **Alcance:** `STK-05`, `STK-02` · **Depende de:** HU-017

**Como** Encargado de depósito, **necesito** transferir mercadería de un depósito a otro, **para** reponer una sucursal desde el depósito central sin perder el rastro de la mercadería.

**Criterios de aceptación**

- **Datos:** depósito de origen, depósito de destino, fecha, observaciones, detalle con artículo y cantidad
- **Validaciones:**
    - origen y destino obligatorios y distintos entre sí
    - la cantidad a transferir no puede superar la existencia disponible en el depósito de origen
    - el detalle debe contener al menos un artículo, sin repeticiones
    - la cantidad debe ser mayor a cero
- **Comportamiento:**
    - al confirmar se generan dos movimientos vinculados entre sí, uno de egreso en el origen y uno de ingreso en el destino, ambos con el usuario responsable
    - la transferencia confirmada no se edita, se corrige mediante una transferencia inversa

## HU-020 - Definir el stock mínimo y ver los artículos en faltante

**Tipo:** Historia · **Módulo:** STK · **Estimación:** 5 SP · **Estado:** Pendiente · **Alcance:** `STK-07` · **Depende de:** HU-016

**Como** Encargado de depósito, **necesito** definir el stock mínimo de cada artículo en mi depósito y ver cuáles quedaron por debajo, **para** reponer la mercadería antes de quedarme sin existencias.

**Criterios de aceptación**

- **Datos:** artículo, depósito, stock mínimo
- **Validaciones:**
    - el mínimo debe ser un número entero mayor o igual a cero
    - se define por la combinación de artículo y depósito, no de forma global para todo el sistema
- **Comportamiento:**
    - el panel principal muestra un aviso con la cantidad de artículos por debajo del mínimo en todos los depósitos
    - existe un listado de artículos en faltante con su existencia actual y su mínimo, exportable a CSV y Excel
    - el indicador se recalcula automáticamente después de cada movimiento de stock

## HU-021 - Administrar clientes

**Tipo:** Historia · **Módulo:** CLI · **Estimación:** 5 SP · **Estado:** Pendiente · **Alcance:** `CLI-01` · **Depende de:** nada

**Como** Vendedor, **necesito** registrar y modificar los datos de los clientes, **para** poder emitirles el comprobante que corresponde a su condición fiscal.

**Criterios de aceptación**

- **Datos:** tipo de persona (física o jurídica), razón social o nombre y apellido, CUIT o DNI, condición fiscal, domicilio, teléfono, correo electrónico, estado
- **Validaciones:**
    - condición fiscal obligatoria
    - para responsable inscripto el CUIT es obligatorio, único y con dígito verificador válido
    - para consumidor final el documento es opcional
    - correo con formato válido cuando se informa
    - la baja de un cliente con ventas asociadas es siempre lógica
- **Comportamiento:** existe un cliente genérico Consumidor Final que no se puede modificar ni eliminar y que se utiliza por defecto en las ventas de mostrador sin identificación del comprador

## HU-022 - Asignar una lista de precios a un cliente

**Tipo:** Historia · **Módulo:** CLI · **Estimación:** 2 SP · **Estado:** Pendiente · **Alcance:** `CLI-02` · **Depende de:** HU-021, HU-011

**Como** Gerente, **necesito** asignar una lista de precios particular a un cliente, **para** aplicarle condiciones diferenciadas sin necesidad de modificar la lista general.

**Criterios de aceptación**

- **Datos:** cliente, lista de precios asignada (opcional)
- **Validaciones:**
    - solo se pueden asignar listas en estado activo y vigentes
    - un cliente tiene a lo sumo una lista asignada
    - si el cliente no tiene lista asignada se le aplicará la lista del canal de la operación
- **Comportamiento:**
    - la asignación queda visible en la ficha del cliente
    - su efecto sobre el precio se verifica en la historia de resolución de precio (EPIC-01)

---

## HU-033 - Registrar la cabecera de una orden de compra

**Tipo:** Historia · **Módulo:** CMP · **Estimación:** 3 SP · **Estado:** Pendiente · **Alcance:** `CMP-03` · **Depende de:** HU-013, HU-005

**Como** Encargado de compras, **necesito** abrir una orden de compra indicando a quién le compro y dónde quiero recibir la mercadería, **para** empezar a armar el pedido antes de tener definido el detalle de artículos.

**Criterios de aceptación**

- **Datos:** proveedor, depósito de destino, condición de pago, fecha de emisión, fecha esperada de entrega, observaciones, estado
- **Validaciones:**
    - proveedor y depósito de destino obligatorios
    - solo se pueden seleccionar proveedores en estado activo
    - la fecha esperada de entrega no puede ser anterior a la fecha de emisión
- **Comportamiento:** la orden nace en estado borrador y se puede guardar todavía sin detalle, para completarla más tarde

## HU-034 - Cargar el detalle de artículos de la orden de compra

**Tipo:** Historia · **Módulo:** CMP · **Estimación:** 5 SP · **Estado:** Pendiente · **Alcance:** `CMP-03` · **Depende de:** HU-033, HU-015

**Como** Encargado de compras, **necesito** cargar los artículos que le pido al proveedor con su cantidad y su precio acordado, **para** que la orden diga exactamente qué estoy pidiendo y a qué precio.

**Criterios de aceptación**

- **Datos:** detalle con artículo, cantidad y precio unitario acordado
- **Validaciones:**
    - un artículo no puede repetirse dentro de la misma orden
    - cantidad y precio unitario mayores a cero
    - solo se pueden incluir artículos asociados al proveedor de la orden (HU-015)
    - el detalle solo se puede modificar mientras la orden está en estado borrador
- **Comportamiento:**
    - la relación entre la orden y los artículos es de muchos a muchos
    - al seleccionar el artículo se sugiere como precio unitario el último costo de compra conocido para ese proveedor y el usuario puede modificarlo

## HU-035 - Calcular los totales y emitir la orden de compra

**Tipo:** Historia · **Módulo:** CMP · **Estimación:** 3 SP · **Estado:** Pendiente · **Alcance:** `CMP-03` · **Depende de:** HU-034

**Como** Encargado de compras, **necesito** ver el subtotal, el IVA y el total de la orden y confirmarla, **para** saber cuánto voy a gastar antes de enviársela al proveedor.

**Criterios de aceptación**

- **Datos:** subtotal, IVA discriminado por alícuota, total
- **Validaciones:**
    - el IVA se calcula con la alícuota de cada artículo, nunca se carga a mano
    - no se puede emitir una orden cuyo detalle esté vacío
- **Comportamiento:**
    - los totales se recalculan cada vez que se modifica el detalle
    - al emitir, la orden pasa de borrador a emitida y su detalle queda inmutable
    - la emisión de la orden no afecta el stock
- **Verificación:** se emite una orden con artículos de alícuotas distintas y se comprueba que el IVA queda discriminado por alícuota

## HU-024 - Gestionar los estados y consultar las órdenes de compra

**Tipo:** Historia · **Módulo:** CMP · **Estimación:** 5 SP · **Estado:** Pendiente · **Alcance:** `CMP-04` · **Depende de:** HU-035

**Como** Encargado de compras, **necesito** cambiar el estado de una orden y consultar el historial de órdenes, **para** saber en todo momento qué pedí, qué está pendiente y qué ya se cerró.

**Criterios de aceptación**

- **Datos:**
    - estado de la orden (borrador, emitida, cumplida, cancelada)
    - filtros por proveedor, estado, depósito de destino y rango de fechas
- **Validaciones:**
    - solo una orden en estado borrador puede modificarse o eliminarse
    - una orden emitida solo puede pasar a cumplida o cancelada
    - una orden con comprobantes asociados no puede cancelarse
- **Comportamiento:**
    - el listado es exportable a CSV y Excel
    - la orden se puede imprimir o descargar en PDF para enviarla al proveedor

## HU-036 - Registrar un comprobante de proveedor

**Tipo:** Historia · **Módulo:** CMP · **Estimación:** 5 SP · **Estado:** Pendiente · **Alcance:** `CMP-05` · **Depende de:** HU-013

**Como** Encargado de compras, **necesito** registrar la factura o el remito que envía el proveedor, **para** dejar documentado que llegó y cuánto costó.

**Criterios de aceptación**

- **Datos:** proveedor, tipo de comprobante (factura o remito), número, fecha, importe, IVA discriminado
- **Validaciones:**
    - la combinación de proveedor, tipo y número de comprobante es única
    - proveedor, tipo, número y fecha son obligatorios
    - el importe debe ser mayor a cero
    - la fecha del comprobante no puede ser posterior a la fecha actual
- **Comportamiento:** el comprobante queda en estado pendiente de pago hasta que se le imputen pagos (HU-027)

## HU-037 - Imputar el comprobante a una o varias órdenes de compra

**Tipo:** Historia · **Módulo:** CMP · **Estimación:** 5 SP · **Estado:** Pendiente · **Alcance:** `CMP-05` · **Depende de:** HU-036, HU-024

**Como** Encargado de compras, **necesito** vincular el comprobante con las órdenes que cubre y detallar qué cantidades llegaron, **para** saber contra qué pedido corresponde lo que recibí y qué me queda pendiente.

**Criterios de aceptación**

- **Datos:** órdenes de compra imputadas, y detalle con artículo y cantidad recibida
- **Validaciones:**
    - un comprobante puede imputarse a una o varias órdenes y una orden puede recibir uno o varios comprobantes, ambas relaciones son de muchos a muchos
    - solo se pueden imputar órdenes del mismo proveedor y en estado emitida
    - la cantidad recibida no puede superar la cantidad pendiente de la orden
    - solo se pueden recibir artículos que figuren en alguna de las órdenes imputadas
- **Comportamiento:** por cada orden imputada se muestra lo pedido, lo ya recibido en comprobantes anteriores y lo que queda pendiente
- **Verificación:** se imputa un comprobante a dos órdenes del mismo proveedor y se comprueba que el pendiente de cada una queda correctamente descontado

## HU-038 - Actualizar el último costo y cerrar la orden cubierta

**Tipo:** Historia · **Módulo:** CMP · **Estimación:** 3 SP · **Estado:** Pendiente · **Alcance:** `CMP-05` · **Depende de:** HU-037, HU-015

**Como** Encargado de compras, **necesito** que el comprobante actualice por sí solo el costo de los artículos y cierre la orden que quedó completa, **para** no tener que mantener esa información a mano.

**Criterios de aceptación**

- **Datos:** último costo de compra conocido por artículo y proveedor (HU-015), estado de la orden de compra
- **Validaciones:** el último costo se toma del comprobante registrado, nunca se carga manualmente
- **Comportamiento:**
    - al registrar el comprobante se actualiza el último costo de compra de cada artículo para ese proveedor
    - la orden pasa a estado cumplida cuando todas sus líneas quedan totalmente cubiertas
    - una orden cubierta solo parcialmente permanece emitida, con su pendiente actualizado

## HU-026 - Ingresar el stock a partir del comprobante recibido

**Tipo:** Historia · **Módulo:** CMP · **Estimación:** 8 SP · **Estado:** Pendiente · **Alcance:** `CMP-06` · **Depende de:** HU-037, HU-017

**Como** Encargado de depósito, **necesito** que el comprobante de proveedor genere automáticamente la entrada de stock, **para** que las existencias reflejen la mercadería recibida sin tener que cargarla dos veces.

**Criterios de aceptación**

- **Datos:** el movimiento generado toma depósito de destino, artículos, cantidades, usuario y comprobante de origen
- **Validaciones:**
    - el movimiento se genera una sola vez por comprobante y no puede duplicarse
    - si el comprobante se anula se genera el movimiento inverso, nunca se borra el original
- **Comportamiento:**
    - la entrada de stock queda vinculada al comprobante que la originó y desde el historial de movimientos se puede navegar hasta el
    - el movimiento es inmutable como todos los demás

## HU-027 - Registrar un pago a proveedor imputado a comprobantes

**Tipo:** Historia · **Módulo:** CMP · **Estimación:** 8 SP · **Estado:** Pendiente · **Alcance:** `CMP-07` · **Depende de:** HU-036

**Como** Encargado de compras, **necesito** registrar un pago y aplicarlo a uno o varios comprobantes del proveedor, **para** mantener actualizado cuánto le debo realmente a cada proveedor.

**Criterios de aceptación**

- **Datos:** proveedor, fecha, medio de pago, importe total, y detalle de imputación con comprobante e importe aplicado
- **Validaciones:**
    - la relación entre el pago y los comprobantes es de muchos a muchos
    - la suma de los importes imputados debe coincidir con el importe total del pago
    - no se puede imputar a un comprobante más de su saldo pendiente
    - se admiten pagos parciales
    - solo se imputan comprobantes del mismo proveedor
- **Comportamiento:** cada comprobante pasa automáticamente a estado pendiente, pagado parcialmente o pagado según el saldo resultante

## HU-028 - Consultar el saldo de cuenta corriente de un proveedor

**Tipo:** Historia · **Módulo:** CMP · **Estimación:** 3 SP · **Estado:** Pendiente · **Alcance:** `CMP-08` · **Depende de:** HU-027

**Como** Encargado de compras, **necesito** consultar cuánto le debo a cada proveedor con el detalle de lo pendiente, **para** poder priorizar los pagos y detectar comprobantes vencidos.

**Criterios de aceptación**

- **Datos:** proveedor, total de comprobantes recibidos, total pagado, saldo, y detalle de comprobantes pendientes con su fecha, importe, importe pagado, saldo y antigüedad en días
- **Validaciones:** el saldo se calcula siempre como la diferencia entre comprobantes recibidos y pagos imputados, nunca se carga manualmente
- **Comportamiento:** el listado se filtra por proveedor y rango de fechas y es exportable a CSV y Excel

## HU-029 - Actualizar precios de forma masiva por porcentaje

**Tipo:** Historia · **Módulo:** PRE · **Estimación:** 5 SP · **Estado:** Pendiente · **Alcance:** `PRE-04` · **Depende de:** HU-012, HU-015

**Como** Gerente, **necesito** aplicar un aumento o una baja porcentual sobre los precios de una lista filtrando por categoría o proveedor, **para** ajustar los precios ante una variación de costos sin editar artículo por artículo.

**Criterios de aceptación**

- **Datos:** lista de precios, porcentaje a aplicar, filtros por categoría, marca o proveedor, criterio de redondeo
- **Validaciones:**
    - el porcentaje debe ser distinto de cero
    - el precio resultante debe ser mayor a cero
    - la operación requiere permiso de autorización
- **Comportamiento:**
    - antes de confirmar se muestra una previsualización con el precio actual y el precio resultante de cada artículo alcanzado y la cantidad total de artículos afectados
    - la confirmación se aplica en una única operación y queda registrada en el historial de precios y en el log de auditoría

## HU-030 - Consultar el historial de cambios de precio

**Tipo:** Historia · **Módulo:** PRE · **Estimación:** 3 SP · **Estado:** Pendiente · **Alcance:** `PRE-05` · **Depende de:** HU-029

**Como** Gerente, **necesito** consultar cómo evolucionó el precio de un artículo en cada lista, **para** poder justificar ante la dirección cuándo y por qué cambió un precio.

**Criterios de aceptación**

- **Datos:** artículo, lista, precio anterior, precio nuevo, variación porcentual, fecha y hora, usuario responsable
- **Validaciones:** el historial es de solo lectura y se genera automáticamente ante cada cambio, individual o masivo
- **Comportamiento:**
    - filtros por artículo, lista, usuario y rango de fechas
    - exportable a CSV y Excel

---

## EPIC-01 - Resolver el precio de venta según el cliente y el canal

**Tipo:** Epic · **Módulo:** PRE · **Estimación:** 8 SP · **Estado:** Pendiente · **Alcance:** `PRE-03` · **Depende de:** HU-022

**Como** Vendedor, **necesito** que el sistema determine automáticamente qué precio corresponde a cada línea de la venta, **para** vender siempre al precio correcto sin tener que consultar qué lista aplica en cada caso.

**Criterios de aceptación**

- A desglosar: la regla de precedencia es lista asignada al cliente, luego lista vigente del canal, luego lista general vigente. Debe ser idéntica para mostrador y para e-commerce

## HU-051 - Administrar puntos de venta

**Tipo:** Historia · **Módulo:** ADM · **Estimación:** 2 SP · **Estado:** Pendiente · **Alcance:** `ADM-01` · **Depende de:** HU-005

**Como** Administrador, **necesito** registrar los puntos de venta de cada sucursal y el depósito del que descuentan stock, **para** que el circuito de ventas de mostrador sepa desde dónde vender y desde dónde descontar mercadería.

**Criterios de aceptación**

- **Datos:** número, sucursal, depósito desde el cual descuenta stock, estado
- **Validaciones:**
    - todo punto de venta pertenece a una sucursal y tiene un depósito asociado obligatorio
    - el número de punto de venta es único dentro de su sucursal
- **Comportamiento:** la relación punto de venta a depósito es la que determina de qué depósito se descuenta el stock en cada venta de mostrador

> **Historia desglosada de `HU-005` por corrección del PO (2026-08-22):** el PO pidió priorizar
> exclusivamente artículos y stock; los puntos de venta son del circuito de ventas, no del de
> stock, así que se sacaron de `HU-005` y bajaron de prioridad hasta acá, justo antes de la
> primera historia que realmente los necesita.

## HU-039 - Abrir una venta de mostrador

**Tipo:** Historia · **Módulo:** VTA · **Estimación:** 3 SP · **Estado:** Pendiente · **Alcance:** `VTA-01` · **Depende de:** HU-051, HU-021

**Como** Vendedor, **necesito** abrir una operación de venta asociada a mi punto de venta, **para** empezar a cargar los artículos que me pide el cliente.

**Criterios de aceptación**

- Sucursal, punto de venta, canal (mostrador o e-commerce), usuario responsable, cliente y fecha y hora de la operación. La venta arranca con el cliente Consumidor Final y se puede reasignar después (EPIC-03)
- Resto de los criterios a definir en el refinamiento previo al Sprint 4

## HU-040 - Incorporar artículos a la venta por código de barras o búsqueda

**Tipo:** Historia · **Módulo:** VTA · **Estimación:** 5 SP · **Estado:** Pendiente · **Alcance:** `VTA-02` · **Depende de:** HU-039, HU-009

**Como** Vendedor, **necesito** cargar los artículos leyendo su código de barras o buscándolos por código interno y descripción, **para** atender rápido en caja sin demorar al cliente.

**Criterios de aceptación**

- Detalle de artículos y cantidades, con relación de muchos a muchos entre la venta y los artículos. La lectura del código de barras agrega la línea directamente, sin pasar por una pantalla de búsqueda intermedia
- Resto de los criterios a definir en el refinamiento previo al Sprint 4

## HU-007 - Administrar las alícuotas de IVA

**Tipo:** Historia · **Módulo:** ADM · **Estimación:** 2 SP · **Estado:** Pendiente · **Alcance:** `ADM-03` · **Depende de:** nada

**Como** Administrador, **necesito** administrar las alícuotas de IVA, **para** que el catálogo y las operaciones tomen siempre un valor controlado y no cargado a mano en cada pantalla.

**Criterios de aceptación**

- **Datos:** alícuota de IVA (descripción, porcentaje, estado)
- **Validaciones:**
    - nombre único
    - el porcentaje debe estar entre 0 y 100
    - no se puede dar de baja un valor ya utilizado en una operación registrada

> **Corrección del PO (2026-08-22):** medios de pago se sacó de esta historia -tampoco hace
> falta para artículos ni para stock- y pasó a `HU-052`. `HU-008` (catálogo) dejó de depender de
> esta historia: la alícuota de IVA del artículo pasa a opcional hasta que esta historia entre.
> **Reubicación (2026-08-22):** esta historia entera bajó de prioridad y se movió desde el
> bloque de parámetros (ADM, cerca del tope) hasta acá, justo antes de `HU-041`, la primera
> historia que realmente necesita alícuotas para calcular algo. No aporta a artículos ni a
> stock, así que no había motivo para construirla antes que ellos.

## HU-041 - Calcular el precio, el IVA y los totales de la venta

**Tipo:** Historia · **Módulo:** VTA · **Estimación:** 5 SP · **Estado:** Pendiente · **Alcance:** `VTA-03` · **Depende de:** HU-040, EPIC-01, HU-007

**Como** Vendedor, **necesito** que cada línea tome su precio automáticamente y que la venta muestre el IVA y el total, **para** cobrar el importe correcto sin calcular nada a mano.

**Criterios de aceptación**

- El precio de cada línea se obtiene de la lista resuelta según `PRE-03` (EPIC-01), y se calculan el IVA discriminado por alícuota, el subtotal y el total. Ningún precio se puede escribir a mano
- Resto de los criterios a definir en el refinamiento previo al Sprint 4

## EPIC-03 - Identificar al cliente y determinar el tipo de comprobante

**Tipo:** Epic · **Módulo:** VTA · **Estimación:** 5 SP · **Estado:** Pendiente · **Alcance:** `VTA-04` · **Depende de:** HU-039, HU-021

**Como** Vendedor, **necesito** identificar al cliente de la operación o dejarlo como consumidor final, **para** emitir el comprobante que corresponde a su condición fiscal.

**Criterios de aceptación**

- A desglosar en Sprint Planning

## HU-052 - Administrar medios de pago

**Tipo:** Historia · **Módulo:** ADM · **Estimación:** 2 SP · **Estado:** Pendiente · **Alcance:** `ADM-03` · **Depende de:** nada

**Como** Administrador, **necesito** administrar los medios de pago disponibles, **para** que el cobro de una venta se registre siempre con un valor controlado y no cargado a mano.

**Criterios de aceptación**

- **Datos:** medio de pago (nombre, estado, indicador de habilitado en canal online)
- **Validaciones:**
    - nombre único
    - no se puede dar de baja un valor ya utilizado en una operación registrada

> **Historia desglosada de `HU-007` por corrección del PO (2026-08-22):** los medios de pago no
> hacen falta para artículos ni stock, así que se sacaron de `HU-007` y bajaron de prioridad
> hasta acá, justo antes de la primera historia que realmente los necesita.

## EPIC-04 - Cobrar la venta con uno o varios medios de pago

**Tipo:** Epic · **Módulo:** VTA · **Estimación:** 8 SP · **Estado:** Pendiente · **Alcance:** `VTA-05` · **Depende de:** HU-041, HU-052

**Como** Vendedor, **necesito** registrar el cobro repartiéndolo entre varios medios de pago en una misma operación, **para** poder cobrar una parte en efectivo y otra con tarjeta como pide el cliente.

**Criterios de aceptación**

- A desglosar en Sprint Planning

## HU-042 - Emitir la factura con numeración correlativa por punto de venta

**Tipo:** Historia · **Módulo:** VTA · **Estimación:** 8 SP · **Estado:** Pendiente · **Alcance:** `VTA-06` · **Depende de:** EPIC-03, EPIC-04

**Como** Vendedor, **necesito** emitir la factura A, B o C con numeración correlativa por punto de venta e IVA discriminado, **para** entregarle al cliente el comprobante de su compra.

**Criterios de aceptación**

- Tipo de comprobante según la condición fiscal resuelta en EPIC-03, numeración correlativa y sin huecos por punto de venta, IVA discriminado y datos fiscales del emisor y del cliente. La numeración es propia del sistema, no de ARCA (ver SPIKE-01)
- Resto de los criterios a definir en el refinamiento previo al Sprint 4

## HU-043 - Imprimir y descargar la factura en PDF

**Tipo:** Historia · **Módulo:** VTA · **Estimación:** 5 SP · **Estado:** Pendiente · **Alcance:** `VTA-06` · **Depende de:** HU-042

**Como** Vendedor, **necesito** imprimir la factura o descargarla en PDF, **para** entregársela al cliente en papel o por correo.

**Criterios de aceptación**

- Representación impresa de una factura ya emitida, con idéntico contenido en pantalla, en la impresión y en el PDF. La reutiliza el canal online (EPIC-15)
- Resto de los criterios a definir en el refinamiento previo al Sprint 4

## EPIC-06 - Descontar el stock automáticamente al confirmar la venta

**Tipo:** Epic · **Módulo:** VTA · **Estimación:** 5 SP · **Estado:** Pendiente · **Alcance:** `VTA-07` · **Depende de:** HU-042

**Como** Encargado de depósito, **necesito** que la venta genere por sí sola el egreso de stock del depósito del punto de venta, **para** que las existencias reflejen la realidad sin depender de una carga manual.

**Criterios de aceptación**

- A desglosar en Sprint Planning

## HU-044 - Anular una venta con nota de crédito y reingreso de stock

**Tipo:** Historia · **Módulo:** VTA · **Estimación:** 8 SP · **Estado:** Pendiente · **Alcance:** `VTA-08` · **Depende de:** HU-042, EPIC-06

**Como** Vendedor, **necesito** anular una venta completa, **para** corregir un error de facturación dejando el stock y la facturación consistentes.

**Criterios de aceptación**

- La anulación emite la nota de crédito por el total de la venta y genera el movimiento de reingreso de stock. La venta original nunca se borra ni se edita: queda en estado anulada, con su comprobante y su contrapartida
- Resto de los criterios a definir en el refinamiento previo al Sprint 4

## HU-045 - Registrar una devolución parcial de cliente

**Tipo:** Historia · **Módulo:** VTA · **Estimación:** 5 SP · **Estado:** Pendiente · **Alcance:** `VTA-08` · **Depende de:** HU-044

**Como** Vendedor, **necesito** registrar la devolución de algunos artículos de una venta, **para** devolverle al cliente lo que corresponde sin anular toda la operación.

**Criterios de aceptación**

- Selección de artículos y cantidades a devolver sobre una venta facturada, con nota de crédito por el importe devuelto y reingreso de stock por las cantidades devueltas. No se puede devolver más de lo vendido ni devolver dos veces la misma unidad
- Resto de los criterios a definir en el refinamiento previo al Sprint 4

## EPIC-08 - Consultar los comprobantes emitidos

**Tipo:** Epic · **Módulo:** VTA · **Estimación:** 5 SP · **Estado:** Pendiente · **Alcance:** `VTA-09` · **Depende de:** HU-042

**Como** Gerente, **necesito** consultar y filtrar los comprobantes emitidos por distintos criterios, **para** controlar la facturación de cada sucursal y canal.

**Criterios de aceptación**

- A desglosar en Sprint Planning

## EPIC-09 - Consultar la ficha del cliente con su historial

**Tipo:** Epic · **Módulo:** CLI · **Estimación:** 5 SP · **Estado:** Pendiente · **Alcance:** `CLI-04` · **Depende de:** HU-042

**Como** Vendedor, **necesito** ver el historial de compras y comprobantes de un cliente, **para** responder consultas y gestionar devoluciones sin buscar papeles.

**Criterios de aceptación**

- A desglosar en Sprint Planning

## EPIC-10 - Registrar y consultar el log de auditoría

**Tipo:** Epic · **Módulo:** SEG · **Estimación:** 8 SP · **Estado:** Pendiente · **Alcance:** `SEG-05` · **Depende de:** HU-045

**Como** Gerente, **necesito** consultar quién realizó cada operación crítica y qué valores modificó, **para** poder auditar el sistema y deslindar responsabilidades ante una diferencia.

**Criterios de aceptación**

- A desglosar en Sprint Planning. El registro se va incorporando desde las primeras historias del backlog; esta historia entrega la pantalla de consulta y completa la cobertura sobre todos los módulos

## SPIKE-01 - Investigar la integración con ARCA (WSAA y WSFE)

**Tipo:** Spike · **Módulo:** VTA · **Estimación:** 3 SP · **Estado:** Pendiente · **Alcance:** deseable de `VTA`, sin ID propio (integración ARCA) · **Depende de:** nada

**Como** Equipo de Desarrollo, **necesito** conocer el esfuerzo real de integrar la facturación electrónica con ARCA, **para** poder decidir con información si se incorpora al alcance comprometido o queda como deseable.

**Criterios de aceptación**

- Timebox de 3 días. Resultado esperado: obtención de certificado de homologación, prueba de autenticación contra WSAA y de solicitud de CAE contra WSFE para una factura B, y estimación del esfuerzo de integración completa
- La decisión de incorporarlo al alcance la toma el Product Owner al cierre del Sprint 4

---

## EPIC-11 - Registrarse e iniciar sesión como cliente en la tienda online

**Tipo:** Epic · **Módulo:** CLI · **Estimación:** 8 SP · **Estado:** Pendiente · **Alcance:** `CLI-03` · **Depende de:** HU-042

**Como** Cliente, **necesito** crear mi cuenta e iniciar sesión en la tienda, **para** poder comprar y seguir el estado de mis pedidos.

**Criterios de aceptación**

- A desglosar en Sprint Planning

## HU-046 - Publicar el catálogo en la tienda online

**Tipo:** Historia · **Módulo:** ECO · **Estimación:** 5 SP · **Estado:** Pendiente · **Alcance:** `ECO-01` · **Depende de:** EPIC-11

**Como** Cliente, **necesito** ver en la tienda los artículos que el supermercado publica, con su descripción, su imagen y su precio, **para** saber qué puedo comprar y cuánto cuesta.

**Criterios de aceptación**

- Se publican únicamente los artículos activos marcados como publicables (HU-008), con descripción, imagen (HU-032), categoría y el precio de la lista del canal online resuelto por EPIC-01
- Resto de los criterios a definir en el refinamiento previo al Sprint 5

## HU-047 - Buscar, filtrar y ordenar artículos en la tienda online

**Tipo:** Historia · **Módulo:** ECO · **Estimación:** 5 SP · **Estado:** Pendiente · **Alcance:** `ECO-02` · **Depende de:** HU-046

**Como** Cliente, **necesito** filtrar el catálogo y ordenarlo a mi gusto, **para** encontrar lo que busco sin recorrer todo el listado.

**Criterios de aceptación**

- Filtros por categoría, marca, rango de precio y disponibilidad, combinables entre sí, con ordenamiento seleccionable por el cliente
- Resto de los criterios a definir en el refinamiento previo al Sprint 5

## HU-048 - Mostrar la disponibilidad online e impedir la compra sin stock

**Tipo:** Historia · **Módulo:** ECO · **Estimación:** 3 SP · **Estado:** Pendiente · **Alcance:** `ECO-03` · **Depende de:** HU-046, HU-016

**Como** Cliente, **necesito** saber si el artículo está disponible antes de agregarlo, **para** no llevarme la sorpresa de que no hay stock al momento de pagar.

**Criterios de aceptación**

- La disponibilidad se calcula sobre el depósito marcado como canal online (HU-005). Los artículos sin existencias se muestran marcados como no disponibles y no se pueden incorporar al carrito
- Resto de los criterios a definir en el refinamiento previo al Sprint 5

## EPIC-13 - Gestionar el carrito de compras

**Tipo:** Epic · **Módulo:** ECO · **Estimación:** 8 SP · **Estado:** Pendiente · **Alcance:** `ECO-04` · **Depende de:** HU-048

**Como** Cliente, **necesito** agregar artículos a un carrito que se conserve entre visitas y ver el total, **para** armar mi compra con tranquilidad sin perder lo que ya había elegido.

**Criterios de aceptación**

- A desglosar en Sprint Planning

## HU-049 - Elegir la modalidad de entrega y calcular el costo de envío

**Tipo:** Historia · **Módulo:** ECO · **Estimación:** 5 SP · **Estado:** Pendiente · **Alcance:** `ECO-05` · **Depende de:** EPIC-13

**Como** Cliente, **necesito** elegir entre envío a domicilio y retiro en una sucursal, **para** recibir la compra como me quede más cómodo.

**Criterios de aceptación**

- Envío a domicilio con costo de envío, o retiro en una sucursal activa elegida por el cliente (HU-005). El costo de envío se suma al total del pedido
- Resto de los criterios a definir en el refinamiento previo al Sprint 5

> **A revisar con el PO:** falta definir si el costo de envío es un valor fijo único o un
> parámetro configurable. Si es configurable, se administra en `ADM-03`, pero no encaja en
> `HU-007` (alícuotas de IVA) ni en `HU-052` (medios de pago) desde el desglose del 2026-08-22:
> habría que dar de alta un nuevo parámetro cuando se confirme con el PO.

## HU-050 - Pagar el pedido con Mercado Pago en sandbox

**Tipo:** Historia · **Módulo:** ECO · **Estimación:** 8 SP · **Estado:** Pendiente · **Alcance:** `ECO-06` · **Depende de:** HU-049

**Como** Cliente, **necesito** pagar mi pedido en línea, **para** completar la compra sin ir al supermercado.

**Criterios de aceptación**

- Integración con Mercado Pago operando exclusivamente en entorno de prueba (sandbox), registrando el identificador y el estado de la transacción asociada al pedido. El pedido solo pasa a pagado cuando la pasarela confirma la acreditación
- Resto de los criterios a definir en el refinamiento previo al Sprint 5

## EPIC-15 - Procesar el pedido pagado como una venta con factura y egreso de stock

**Tipo:** Epic · **Módulo:** ECO · **Estimación:** 8 SP · **Estado:** Pendiente · **Alcance:** `ECO-08` · **Depende de:** HU-050

**Como** Gerente, **necesito** que el pedido web pagado genere la misma venta, factura y movimiento de stock que una venta de mostrador, **para** no tener dos circuitos de facturación distintos ni diferencias de stock entre canales.

**Criterios de aceptación**

- A desglosar en Sprint Planning. Reutiliza íntegramente VTA-06 y VTA-07

## EPIC-16 - Seguir el estado del pedido y recibir notificaciones por correo

**Tipo:** Epic · **Módulo:** ECO · **Estimación:** 8 SP · **Estado:** Pendiente · **Alcance:** `ECO-07`, `ECO-09` · **Depende de:** EPIC-15

**Como** Cliente, **necesito** ver en qué estado está mi pedido y recibir un aviso cuando se despacha o está listo para retirar, **para** saber cuándo voy a recibir mi compra sin tener que llamar.

**Criterios de aceptación**

- A desglosar en Sprint Planning

## EPIC-17 - Administrar los pedidos web desde el panel interno

**Tipo:** Epic · **Módulo:** ECO · **Estimación:** 8 SP · **Estado:** Pendiente · **Alcance:** `ECO-10` · **Depende de:** EPIC-15

**Como** Encargado de depósito, **necesito** ver y gestionar los pedidos web pendientes de preparación, **para** preparar y despachar los pedidos online sin depender de correos ni planillas.

**Criterios de aceptación**

- A desglosar en Sprint Planning

---

## EPIC-18 - Visualizar los ingresos del periodo

**Tipo:** Epic · **Módulo:** DSH · **Estimación:** 8 SP · **Estado:** Pendiente · **Alcance:** `DSH-01` · **Depende de:** EPIC-15

**Como** Gerente, **necesito** ver cuánto se facturó y cuánto se cobró en el periodo discriminado por medio de pago canal y sucursal, **para** conocer el ingreso real del negocio sin recopilar información a mano.

**Criterios de aceptación**

- A desglosar en Sprint Planning

## EPIC-19 - Visualizar los egresos del periodo

**Tipo:** Epic · **Módulo:** DSH · **Estimación:** 5 SP · **Estado:** Pendiente · **Alcance:** `DSH-02` · **Depende de:** HU-027

**Como** Gerente, **necesito** ver el total de comprobantes de proveedor recibidos y de pagos realizados en el periodo, **para** conocer cuánto se gastó y cuánto efectivamente se pagó.

**Criterios de aceptación**

- A desglosar en Sprint Planning

## EPIC-20 - Visualizar la relación entre ingresos y egresos y su evolución

**Tipo:** Epic · **Módulo:** DSH · **Estimación:** 8 SP · **Estado:** Pendiente · **Alcance:** `DSH-03` · **Depende de:** EPIC-18, EPIC-19

**Como** Gerente, **necesito** ver la relación entre lo que entra y lo que sale y cómo evolucionó mes a mes, **para** tener una lectura rápida de si el negocio está mejorando o empeorando.

**Criterios de aceptación**

- A desglosar en Sprint Planning. Incluye gráficos de barras y de líneas

## EPIC-21 - Visualizar indicadores operativos complementarios

**Tipo:** Epic · **Módulo:** DSH · **Estimación:** 5 SP · **Estado:** Pendiente · **Alcance:** `DSH-04` · **Depende de:** EPIC-20

**Como** Gerente, **necesito** ver los artículos más vendidos los artículos por debajo del mínimo y las compras por proveedor, **para** detectar oportunidades y problemas operativos desde el mismo tablero.

**Criterios de aceptación**

- A desglosar en Sprint Planning

## HU-053 - Exportar a CSV y Excel los listados de stock

**Tipo:** Historia · **Módulo:** STK · **Estimación:** 3 SP · **Estado:** Pendiente · **Alcance:** `STK-01`, `STK-04` · **Depende de:** HU-016, HU-018

**Como** Encargado de depósito, **necesito** exportar a CSV y Excel el listado de existencias y el historial de movimientos, **para** analizar la información fuera del sistema y presentarla sin copiarla a mano.

**Criterios de aceptación**

- **Datos:** las mismas columnas que cada listado muestra en pantalla, sin agregar ninguna que el usuario no pueda ver ahí
- **Validaciones:**
    - la exportación respeta los filtros aplicados en el momento de pedirla, no exporta el listado completo
    - el archivo se genera con el mismo permiso de consulta que habilita el listado, no con uno propio
- **Comportamiento:**
    - alcanza al listado de existencias por depósito (`HU-016`) y al historial de movimientos de stock (`HU-018`)
    - los dos formatos, CSV y Excel, salen del mismo patrón de exportación
- **Verificación:** se exporta un listado con filtros aplicados y se comprueba que el archivo trae exactamente las filas y columnas que se veían en pantalla

> **Desglosada de `HU-016` y `HU-018` el 2026-08-22.** El PO pidió no invertir tiempo en nada que no
> hubiera pedido explícitamente, y la exportación no estaba entre lo que pidió para el arranque de
> artículos y stock. Se baja de prioridad en vez de descartarse. Vale 3 SP y no 5 porque para
> cuando llegue, el patrón de exportación ya lo construyó `HU-009`.

## EPIC-22 - Filtrar y exportar el tablero gerencial

**Tipo:** Epic · **Módulo:** DSH · **Estimación:** 5 SP · **Estado:** Pendiente · **Alcance:** `DSH-05` · **Depende de:** EPIC-20

**Como** Gerente, **necesito** filtrar todo el tablero por fecha sucursal y canal y exportar la información, **para** poder analizar la información por fuera del sistema y presentarla a la dirección.

**Criterios de aceptación**

- A desglosar en Sprint Planning

## HAB-03 - Estabilización y cierre

**Tipo:** Habilitador · **Estimación:** sin estimar, reserva de capacidad · **Estado:** Pendiente · **Depende de:** nada

No se estima en story points a propósito: no es trabajo de tamaño conocido sino una **reserva de
capacidad del Sprint 6**. En el planning de ese sprint se decide cuánta capacidad se le aparta
(orientativo: entre un tercio y la mitad) y el resto se llena con ítems del backlog.

**Criterios de aceptación**

- Corrección de defectos pendientes
- Reducción de deuda técnica acumulada
- Documentación final y DER definitivo
- Carga del juego de datos de demostración completo
- Incorporación de funcionalidades deseables según la capacidad remanente
