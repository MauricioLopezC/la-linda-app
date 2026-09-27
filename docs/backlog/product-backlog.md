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
  entre 180 y 240 SP. El backlog activo estimado suma **382 SP**, entre 1,5 y 2 veces la capacidad.
  El desvío se informa al PO para que decida el recorte; **no se disimula bajando las
  estimaciones**, porque los story points miden tamaño relativo y la capacidad es un hecho aparte.
- **Velocidad observada desde el Sprint 3: 60 a 65 SP por sprint** (Sprint 3 comprometió 59). Los
  sprints se planifican con esa velocidad y no con la capacidad de referencia inicial.
- **Trabajo adelantado fuera del compromiso:** cuando una historia se construyó parcialmente en
  un sprint anterior sin estar comprometida, entra al sprint con la estimación del trabajo
  restante, dejando registrada la original. Así la velocidad no suma dos veces el mismo trabajo.
- **Los ítems se mantienen preferentemente en 8 SP o menos.** Un ítem de 13 es un tercio de la
  capacidad del sprint y merece una revisión especial. Los siete ítems originales de 13 SP se
  dividieron el 2026-08-11; `HU-027` se reestimó excepcionalmente en 13 SP el 2026-09-06 porque
  la orden de pago confirmada debe cerrar en una única operación atómica la compensación de
  comprobantes, los múltiples medios de pago, la numeración, la anulación y el PDF.
- **Criterio de estimación:** el primer ítem que construye un patrón técnico se estima más caro
  que los que después lo reusan. Por eso el primer listado con filtros combinables y exportación
  (`HU-009`) vale 5 y los que vienen después (`HU-028`, `HU-053`) valen 3. `HU-016` y `HU-018`
  valen 2 desde el 2026-08-22, cuando la exportación salió de sus criterios hacia `HU-053`.
- **Alcance** referencia las macrofuncionalidades de `LaLindaAlcanceV1.md` (`ADM-01`, `STK-04`, ...).
- Los criterios de aceptación siguen la estructura *Datos / Validaciones / Comportamiento / Verificación*.
- **Solo las historias ya implementadas tienen criterios de aceptación (decisión del equipo,
  2026-09-27).** Las pendientes dicen "A definir en el Sprint Planning correspondiente": sus
  criterios se desglosan en el planning del sprint en que entren, a partir de lo que el PO explique
  en ese momento. Los criterios escritos por adelantado arrastraban errores conceptuales de
  iteraciones anteriores y de otros documentos (alcance, pliego), y el PO los cambiaba igual al
  llegar el sprint. No se completan criterios de historias pendientes fuera del planning; en el
  planning también se pueden agregar, partir o reformular historias.
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
  usado con las listas de precios: `HU-051` antes de `HU-039`, `HU-007` antes de `HU-041` y,
  desde la corrección del Sprint 2, `HU-052` antes de `HU-027`. `HU-008` deja de depender de
  `HU-007`: la alícuota de IVA pasa a
  opcional en el artículo hasta que esa historia entre; `HU-041` sí depende de `HU-007`, porque
  ahí es donde la alícuota se usa por primera vez para calcular algo.
- Texto con acentos (corregido el 2026-08-11; heredaba del CSV una convención sin acentos).

## Índice

77 registros históricos: 73 ítems activos con **382 story points estimados**, 3 historias
absorbidas, más la reserva de estabilización (`HAB-03`, sin estimar a propósito).

| # | ID | Título | Módulo | SP | Estado |
|---|----|--------|--------|----|--------|
| 1 | [HU-005](#hu-005) | Administrar sucursales y depósitos | ADM | 3 | Pendiente |
| 2 | [HU-006](#hu-006) | Administrar categorías, marcas y unidades de medida | ADM | 5 | Pendiente |
| 3 | [HU-031](#hu-031) | Administrar los tipos de movimiento de stock | ADM | 2 | Pendiente |
| 4 | [HU-008](#hu-008) | Administrar el catálogo de artículos | ART | 5 | Pendiente |
| 5 | [HU-032](#hu-032) | Registrar la imagen de un artículo | ART | 3 | Pendiente |
| 6 | [HU-009](#hu-009) | Buscar artículos en el catálogo | ART | 5 | Pendiente |
| 7 | [HU-010](#hu-010) | Importar el catálogo desde un archivo CSV | ART | 8 | Pendiente |
| 8 | [HU-011](#hu-011) | Administrar listas de precios | PRE | 5 | Pendiente |
| 9 | [HU-012](#hu-012) | Definir el precio de venta de los artículos en una lista | PRE | 8 | Pendiente |
| 10 | [HU-003](#hu-003) | Administrar los usuarios del sistema | SEG | 5 | Pendiente |
| 11 | [HU-004](#hu-004) | Administrar roles y sus permisos | SEG | 8 | Pendiente |
| 12 | [HU-013](#hu-013) | Administrar proveedores | CMP | 5 | Pendiente |
| 13 | [HU-052](#hu-052) | Administrar medios de pago | ADM | 2 | Pendiente |
| 14 | [HU-033](#hu-033) | Emitir y consultar órdenes de compra | CMP | 8 | Pendiente |
| 15 | [HU-036](#hu-036) | Registrar y consultar comprobantes de proveedor con detalle | CMP | 8 | Pendiente |
| 16 | [HU-054](#hu-054) | Gestionar notas de crédito y débito del proveedor | CMP | 3 | Pendiente |
| 17 | [HU-027](#hu-027) | Emitir y anular una orden de pago a proveedor | CMP | 13 | Pendiente |
| 18 | [HU-014](#hu-014) | Registrar los contactos de un proveedor y consultar el listado | CMP | 3 | Pendiente |
| 19 | [HU-015](#hu-015) | Asociar artículos a sus proveedores | ART | 5 | Pendiente |
| 20 | [HU-016](#hu-016) | Consultar las existencias por depósito | STK | 2 | Pendiente |
| 21 | [HU-017](#hu-017) | Registrar un movimiento de stock manual | STK | 8 | Pendiente |
| 22 | [HU-018](#hu-018) | Consultar el historial de movimientos de stock | STK | 2 | Pendiente |
| 23 | [HU-019](#hu-019) | Transferir mercadería entre depósitos | STK | 8 | Pendiente |
| 24 | [HU-020](#hu-020) | Definir el stock mínimo y ver los artículos en faltante | STK | 5 | Pendiente |
| 25 | [HU-021](#hu-021) | Administrar clientes | CLI | 5 | Pendiente |
| 26 | [HU-022](#hu-022) | Asignar una lista de precios a un cliente | CLI | 2 | Pendiente |
| 27 | [HU-037](#hu-037) | Imputar el comprobante a una o varias órdenes de compra | CMP | 5 | Pendiente |
| 28 | [HU-038](#hu-038) | Actualizar el último costo y cerrar la orden cubierta | CMP | 3 | Pendiente |
| 29 | [HU-026](#hu-026) | Ingresar el stock a partir del comprobante recibido | CMP | 8 | Pendiente |
| 30 | [HU-028](#hu-028) | Consultar el saldo de cuenta corriente de un proveedor | CMP | 3 | Pendiente |
| 31 | [HU-055](#hu-055) | Consultar el listado de pagos y egresos del período | CMP | 5 | Pendiente |
| 32 | [HU-029](#hu-029) | Actualizar precios de forma masiva por porcentaje | PRE | 5 | Pendiente |
| 33 | [HU-030](#hu-030) | Consultar el historial de cambios de precio | PRE | 3 | Pendiente |
| 34 | [HU-056](#hu-056) | Resolver el precio de venta según el cliente y el canal | PRE | 8 | Pendiente |
| 35 | [HU-051](#hu-051) | Administrar puntos de venta | ADM | 2 | Pendiente |
| 36 | [HU-057](#hu-057) | Abrir la caja con el fondo inicial desglosado por denominación | VTA | 5 | Pendiente |
| 37 | [HU-039](#hu-039) | Abrir una venta de mostrador dentro del turno de caja | VTA | 3 | Pendiente |
| 38 | [HU-040](#hu-040) | Incorporar artículos a la venta por código de barras o búsqueda | VTA | 2 | Pendiente |
| 39 | [HU-041](#hu-041) | Calcular el precio y los totales de la venta | VTA | 2 | Pendiente |
| 40 | [EPIC-04](#epic-04) | Cobrar la venta con uno o varios medios de pago | VTA | 8 | Pendiente |
| 41 | [EPIC-06](#epic-06) | Descontar el stock automáticamente al confirmar la venta | VTA | 5 | Pendiente |
| 42 | [HU-058](#hu-058) | Registrar ingresos y egresos de dinero en la caja | VTA | 3 | Pendiente |
| 43 | [HU-060](#hu-060) | Cerrar la caja con arqueo por medio de pago | VTA | 8 | Pendiente |
| 44 | [HU-061](#hu-061) | Consultar los turnos de caja y su rendición | VTA | 3 | Pendiente |
| 45 | [HU-059](#hu-059) | Registrar un préstamo de dinero entre cajas | VTA | 3 | Pendiente |
| 46 | [HU-046](#hu-046) | Publicar el catálogo en la tienda online | ECO | 5 | Pendiente |
| 47 | [EPIC-11](#epic-11) | Registrarse e iniciar sesión como cliente en la tienda online | CLI | 8 | Pendiente |
| 48 | [EPIC-13](#epic-13) | Gestionar el carrito de compras | ECO | 8 | Pendiente |
| 49 | [HU-062](#hu-062) | Confirmar el pedido desde el carrito | ECO | 5 | Pendiente |
| 50 | [HU-048](#hu-048) | Mostrar la disponibilidad online e impedir la compra sin stock | ECO | 3 | Pendiente |
| 51 | [HU-047](#hu-047) | Buscar, filtrar y ordenar artículos en la tienda online | ECO | 5 | Pendiente |
| 52 | [HU-007](#hu-007) | Administrar las alícuotas de IVA | ADM | 2 | Pendiente |
| 53 | [HU-063](#hu-063) | Discriminar el IVA por alícuota en la venta | VTA | 3 | Pendiente |
| 54 | [EPIC-03](#epic-03) | Identificar al cliente y determinar el tipo de comprobante | VTA | 5 | Pendiente |
| 55 | [HU-042](#hu-042) | Emitir la factura con numeración correlativa por punto de venta | VTA | 8 | Pendiente |
| 56 | [HU-043](#hu-043) | Imprimir y descargar la factura en PDF | VTA | 5 | Pendiente |
| 57 | [HU-044](#hu-044) | Anular una venta con nota de crédito y reingreso de stock | VTA | 8 | Pendiente |
| 58 | [HU-045](#hu-045) | Registrar una devolución parcial de cliente | VTA | 5 | Pendiente |
| 59 | [EPIC-08](#epic-08) | Consultar los comprobantes emitidos | VTA | 5 | Pendiente |
| 60 | [EPIC-09](#epic-09) | Consultar la ficha del cliente con su historial | CLI | 5 | Pendiente |
| 61 | [EPIC-10](#epic-10) | Registrar y consultar el log de auditoría | SEG | 8 | Pendiente |
| 62 | [SPIKE-01](#spike-01) | Investigar la integración con ARCA (WSAA y WSFE) | VTA | 3 | Pendiente |
| 63 | [HU-049](#hu-049) | Elegir la modalidad de entrega y calcular el costo de envío | ECO | 5 | Pendiente |
| 64 | [HU-050](#hu-050) | Pagar el pedido con Mercado Pago en sandbox | ECO | 8 | Pendiente |
| 65 | [EPIC-15](#epic-15) | Procesar el pedido pagado como una venta con factura y egreso de stock | ECO | 8 | Pendiente |
| 66 | [EPIC-16](#epic-16) | Seguir el estado del pedido y recibir notificaciones por correo | ECO | 8 | Pendiente |
| 67 | [EPIC-17](#epic-17) | Administrar los pedidos web desde el panel interno | ECO | 8 | Pendiente |
| 68 | [EPIC-18](#epic-18) | Visualizar los ingresos del periodo | DSH | 8 | Pendiente |
| 69 | [EPIC-19](#epic-19) | Visualizar los egresos del periodo | DSH | 5 | Pendiente |
| 70 | [EPIC-20](#epic-20) | Visualizar la relación entre ingresos y egresos y su evolución | DSH | 8 | Pendiente |
| 71 | [EPIC-21](#epic-21) | Visualizar indicadores operativos complementarios | DSH | 5 | Pendiente |
| 72 | [HU-053](#hu-053) | Exportar a CSV y Excel los listados de stock | STK | 3 | Pendiente |
| 73 | [EPIC-22](#epic-22) | Filtrar y exportar el tablero gerencial | DSH | 5 | Pendiente |
| 74 | [HU-034](#hu-034) | Cargar el detalle de artículos de la orden de compra | CMP | - | Absorbida por HU-033 |
| 75 | [HU-035](#hu-035) | Calcular los totales y emitir la orden de compra | CMP | - | Absorbida por HU-033 |
| 76 | [HU-024](#hu-024) | Gestionar los estados y consultar las órdenes de compra | CMP | - | Absorbida por HU-033 |
| 77 | [HAB-03](#hab-03) | Estabilización y cierre | - | reserva | Pendiente |

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
| HU-023 Emitir una orden de compra | 13 | HU-033 + HU-034 + HU-035 | 3 + 5 + 3 = 11; reunificada en HU-033 (8 SP) el 2026-09-06 |
| HU-025 Registrar un comprobante de proveedor | 13 | HU-036 + HU-037 + HU-038 | 5 + 5 + 3 = 13 |
| EPIC-02 Registrar una venta en mostrador | 13 | HU-039 + HU-040 + HU-041 | 3 + 5 + 5 = 13 |
| EPIC-05 Emitir la factura y descargarla en PDF | 13 | HU-042 + HU-043 | 8 + 5 = 13 |
| EPIC-07 Anular una venta y registrar devoluciones | 13 | HU-044 + HU-045 | 8 + 5 = 13 |
| EPIC-12 Navegar el catálogo público | 13 | HU-046 + HU-047 + HU-048 | 5 + 5 + 3 = 13 |
| EPIC-14 Modalidad de entrega y Mercado Pago | 13 | HU-049 + HU-050 | 5 + 8 = 13 |
| | **91** | | **89** |

Cuatro de las siete divisiones siguen las macrofuncionalidades del alcance, que ya venían
separadas: `VTA-01/02/03`, `ECO-01/02/03`, `ECO-05/06` y `CMP-05`.

### Absorciones por corrección del modelo de gastos (2026-09-06)

La devolución del profesor acotó las órdenes de compra a un flujo único de emisión y consulta.
Para que el ítem entregue valor de punta a punta y no deje historias de cabecera o cálculo sin una
salida utilizable, `HU-033` absorbe `HU-034`, `HU-035` y la consulta mínima de `HU-024`. Los tres
IDs absorbidos se conservan en el índice por trazabilidad, sin estimación activa ni desarrollo
independiente.

## HU-005 - Administrar sucursales y depósitos

**Tipo:** Historia · **Módulo:** ADM · **Estimación:** 3 SP · **Estado:** Pendiente · **Sprint:** 1 · **Alcance:** `ADM-01` · **Depende de:** nada

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

**Tipo:** Historia · **Módulo:** ADM · **Estimación:** 5 SP · **Estado:** Pendiente · **Sprint:** 1 · **Alcance:** `ADM-02` · **Depende de:** nada

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

## HU-031 - Administrar los tipos de movimiento de stock

**Tipo:** Historia · **Módulo:** ADM · **Estimación:** 2 SP · **Estado:** Pendiente · **Sprint:** 1 · **Alcance:** `ADM-03` · **Depende de:** nada

**Como** Administrador, **necesito** administrar el catálogo de tipos de movimiento de stock, **para** que todo movimiento quede tipado con un tipo descriptivo y con su signo de afectación, y ese nombre sea la justificación que se lee en el historial del artículo.

**Criterios de aceptación**

- **Datos:** tipo de movimiento de stock (nombre descriptivo, signo de afectación **obligatorio**: suma `+1` o resta `-1`, descripción, estado)
- **Validaciones:**
    - nombre único
    - el signo es obligatorio y sólo admite suma o resta; no existe un signo "variable"
    - el signo de un tipo ya utilizado en movimientos registrados no puede cambiarse (haría ilegible el historial)
    - no se puede dar de baja un tipo ya utilizado en un movimiento registrado
    - los tipos propios del sistema no pueden eliminarse
- **Comportamiento:** los tipos que genera automáticamente otro módulo (entrada por compra, salida por venta, devolución de cliente, transferencia de salida y de entrada) no se ofrecen en la pantalla de carga de movimientos manuales

## HU-008 - Administrar el catálogo de artículos

**Tipo:** Historia · **Módulo:** ART · **Estimación:** 5 SP · **Estado:** Pendiente · **Sprint:** 1 · **Alcance:** `ART-01`, `ART-02`, `ART-03` · **Depende de:** HU-006

**Como** Encargado de compras, **necesito** registrar, modificar y dar de baja artículos del catálogo, **para** contar con una única definición de cada artículo compartida por todas las sucursales y canales.

**Criterios de aceptación**

- **Datos:** descripción, código interno, código de barras, categoría, subcategoría, marca, unidad de medida, alícuota de IVA, estado (activo, inactivo), indicador de publicable en el canal online
- **Validaciones:**
    - descripción, código interno, categoría y unidad de medida son obligatorios
    - el código interno es único en todo el catálogo
    - el código de barras es único cuando se informa
    - no se admite crear ni modificar un artículo que genere duplicidad de ninguno de los dos códigos
    - la baja de un artículo con movimientos de stock o ventas asociadas es lógica y lo deja en estado inactivo
- **Comportamiento:**
    - el artículo NO tiene campo de precio de venta y el formulario no ofrece ninguno
    - el precio se administra exclusivamente desde el módulo de Listas de Precios
    - tampoco tiene proveedor ni depósito como atributos: la relación con proveedores es de muchos a muchos y se administra en HU-015, y la existencia por depósito se consulta en HU-016
    - las imágenes tampoco son un campo del formulario de alta: se administran aparte en HU-032

> **Decisión del equipo (Sprint 3):** se unifica el estado `discontinuado` dentro de `inactivo`. La baja lógica deja el artículo en estado `inactivo` para preservar la integridad referencial histórica sin redundancia de conceptos operativos.

> **Corrección del PO (2026-08-22):** la alícuota de IVA pasa de obligatoria a opcional. `HU-007`
> (que la administra) bajó de prioridad porque el PO pidió priorizar exclusivamente artículos y
> stock, e IVA es un dato de facturación, no de stock. El artículo puede nacer sin alícuota
> asignada; se completa cuando `HU-007` entre.

## HU-032 - Registrar la imagen de un artículo

**Tipo:** Historia · **Módulo:** ART · **Estimación:** 3 SP · **Estado:** Pendiente · **Alcance:** `ART-05` · **Depende de:** HU-008

**Como** Encargado de compras, **necesito** cargar la imagen de un artículo, **para** que quien lo busque en el sistema o en la tienda online lo reconozca sin depender solo de la descripción.

**Criterios de aceptación**

- A definir en el Sprint Planning correspondiente

## HU-009 - Buscar artículos en el catálogo

**Tipo:** Historia · **Módulo:** ART · **Estimación:** 5 SP · **Estado:** Pendiente · **Alcance:** `ART-05` · **Depende de:** HU-008, HU-032

**Como** Encargado de compras, **necesito** buscar y filtrar artículos y ver su imagen, **para** encontrar rápidamente el artículo que necesito entre miles de registros.

**Criterios de aceptación**

- A definir en el Sprint Planning correspondiente

## HU-010 - Importar el catálogo desde un archivo CSV

**Tipo:** Historia · **Módulo:** ART · **Estimación:** 8 SP · **Estado:** Pendiente · **Alcance:** `ART-06` · **Depende de:** HU-008

**Como** Encargado de compras, **necesito** cargar el catálogo completo de forma masiva desde un archivo CSV, **para** no tener que dar de alta miles de artículos uno por uno para poner el sistema en funcionamiento.

**Criterios de aceptación**

- A definir en el Sprint Planning correspondiente

## HU-011 - Administrar listas de precios

**Tipo:** Historia · **Módulo:** PRE · **Estimación:** 5 SP · **Estado:** Pendiente · **Sprint:** 3 · **Alcance:** `PRE-01` · **Depende de:** nada

**Como** Gerente, **necesito** crear y administrar listas de precios con su canal y su vigencia, **para** aplicar una política de precios distinta según el canal de venta sin tocar el catálogo.

**Criterios de aceptación**

- **Datos:** nombre, tipo de lista (`de canal` o `particular`), canal asociado (mostrador, online o general; solo para las listas de canal), fecha de vigencia desde, fecha de vigencia hasta opcional, estado
- **Validaciones:**
    - nombre único
    - una lista de canal debe indicar su canal; una lista particular no lleva canal
    - la vigencia hasta no puede ser anterior a la vigencia desde
    - no puede haber dos listas **de canal** activas para el mismo canal con periodos superpuestos; las listas particulares pueden superponerse entre sí y con las de canal
    - el canal general no puede quedar descubierto: siempre tiene que haber una lista general activa que cubra desde hoy en adelante, sin fecha de fin o con una sucesora que arranque el día siguiente
    - no se puede dar de baja una lista utilizada en ventas registradas
- **Comportamiento:**
    - el listado muestra el tipo, el estado de vigencia calculado a la fecha actual y la cantidad de artículos con precio asignado en cada lista
    - para reemplazar la lista vigente de un canal se le pone fecha de fin a la actual y se crea la sucesora a partir del día siguiente

> **Corrección (2026-08-22):** esta historia dependía de `HU-007`, pero ninguno de sus criterios
> usa medios de pago ni alícuotas de IVA. Se corrige a "Depende de: nada" al revisar `HU-007` por
> el pedido del PO de priorizar artículos y stock.

> **Corrección (2026-09-21):** el criterio original ("no puede haber dos listas activas y vigentes
> para el mismo canal en el mismo periodo") hacía inalcanzable el paso 1 de la cascada de `HU-056`
> y el escenario de demo del Sprint 3: una lista preferencial como "Mayorista" no es un canal, pero
> al obligarla a declarar uno chocaba siempre con la lista base de ese canal. Se separa el eje
> **canal** ("¿por dónde se vende?") del eje **cliente** ("¿a quién se le vende?") con el campo
> `tipo de lista`, y la regla de no superposición pasa a aplicar solo a las listas de canal.
> También se refuerza la garantía de lista general, que antes se verificaba solo contra la fecha
> actual y se podía romper sola al vencer.

## HU-012 - Definir el precio de venta de los artículos en una lista

**Tipo:** Historia · **Módulo:** PRE · **Estimación:** 8 SP · **Estado:** Pendiente · **Sprint:** 3 · **Alcance:** `PRE-02` · **Depende de:** HU-011

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

- A definir en el Sprint Planning correspondiente

> **Resuelto (2026-08-12, a confirmar con el PO):** se descarta el autoregistro de empleados
> -ese patrón es el de `EPIC-11` para clientes de la tienda online, no para personal interno
> que ya gestiona el Administrador en esta misma historia-. También se descarta el envío de
> mail con la contraseña inicial: `LaLindaAlcanceV1.md` (línea 179) ya deja la "recuperación de
> contraseña por correo electrónico" en **Deseable**, fuera del alcance comprometido de
> `SEG-02`, así que construir envío de mails ahora sería alcance nuevo sin aprobar. La solución
> dentro de alcance es mostrar la contraseña generada en pantalla al Admin. Si
> más adelante se aprueba el ítem deseable, el mecanismo a implementar es
> un link de invitación reutilizando el flujo de recuperación de contraseña del starter kit,
> no reenviar la contraseña en texto plano por mail.

## HU-004 - Administrar roles y sus permisos

**Tipo:** Historia · **Módulo:** SEG · **Estimación:** 8 SP · **Estado:** Pendiente · **Alcance:** `SEG-03`, `SEG-04` · **Depende de:** HU-003

**Como** Administrador, **necesito** definir los roles y los permisos que cada uno tiene sobre cada módulo y operación, **para** que cada usuario acceda únicamente a las funciones que le corresponden según su puesto.

**Criterios de aceptación**

- A definir en el Sprint Planning correspondiente

## HU-013 - Administrar proveedores

**Tipo:** Historia · **Módulo:** CMP · **Estimación:** 5 SP · **Estado:** Pendiente · **Sprint:** 2 · **Alcance:** `CMP-01` · **Depende de:** nada

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

- A definir en el Sprint Planning correspondiente

## HU-015 - Asociar artículos a sus proveedores

**Tipo:** Historia · **Módulo:** ART · **Estimación:** 5 SP · **Estado:** Pendiente · **Sprint:** 3 · **Alcance:** `ART-04` · **Depende de:** HU-013

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

**Tipo:** Historia · **Módulo:** STK · **Estimación:** 2 SP · **Estado:** Pendiente · **Sprint:** 1 · **Alcance:** `STK-01` · **Depende de:** HU-005, HU-008

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

## HU-017 - Registrar un movimiento de stock manual

**Tipo:** Historia · **Módulo:** STK · **Estimación:** 8 SP · **Estado:** Pendiente · **Sprint:** 1 · **Alcance:** `STK-02`, `STK-03`, `STK-06` · **Depende de:** HU-016, HU-031

**Como** Encargado de depósito, **necesito** registrar un movimiento de existencias eligiendo un tipo de movimiento y cargando sólo la cantidad que entra o sale de cada artículo, **para** corregir una diferencia sin tener que calcular el total resultante y dejando asentado quién lo hizo, con qué tipo y por qué.

**Criterios de aceptación**

- **Datos:** depósito, tipo de movimiento (que trae su signo fijo), observaciones, y detalle con artículo y **cantidad que entra o sale** (siempre positiva). El sistema calcula el nuevo saldo; el usuario nunca ingresa el total ni la diferencia.
- **Validaciones:**
    - el tipo de movimiento es obligatorio y se elige del catálogo de tipos, no se escribe en texto libre
    - no se puede elegir un tipo que genera automáticamente otro módulo (compra, venta, devolución, transferencia)
    - la cantidad ingresada por renglón es mayor que cero
    - la existencia resultante de aplicar el movimiento no puede quedar negativa
    - las observaciones son obligatorias (justificación del movimiento)
    - el detalle debe contener al menos un artículo
    - un artículo no puede repetirse dentro del mismo movimiento
    - la operación requiere el permiso específico de ajuste de stock
- **Comportamiento:**
    - la existencia nunca se edita de forma directa, se modifica exclusivamente como consecuencia del movimiento registrado, sumando o restando según el signo del tipo
    - el movimiento queda con usuario responsable, fecha y hora
    - una vez confirmado no puede editarse ni eliminarse desde ninguna pantalla
- **Verificación:** se comprueba que la pantalla sólo pide la cantidad que entra/sale (no un total ni una diferencia), que no existe ninguna vía en la interfaz para modificar una cantidad sin generar un movimiento, y que el intento de editar un movimiento confirmado es rechazado

## HU-018 - Consultar el historial de movimientos de stock

**Tipo:** Historia · **Módulo:** STK · **Estimación:** 2 SP · **Estado:** Pendiente · **Sprint:** 1 · **Alcance:** `STK-04` · **Depende de:** HU-017

**Como** Encargado de depósito, **necesito** consultar el historial de movimientos de un artículo o de un depósito, **para** poder rastrear el origen de cualquier diferencia de existencias.

**Criterios de aceptación**

- **Datos:** fecha y hora, tipo de movimiento, depósito de origen, depósito de destino, artículo, cantidad, usuario responsable, observaciones y documento asociado cuando exista
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

- A definir en el Sprint Planning correspondiente

## HU-020 - Definir el stock mínimo y ver los artículos en faltante

**Tipo:** Historia · **Módulo:** STK · **Estimación:** 5 SP · **Estado:** Pendiente · **Alcance:** `STK-07` · **Depende de:** HU-016

**Como** Encargado de depósito, **necesito** definir el stock mínimo de cada artículo en mi depósito y ver cuáles quedaron por debajo, **para** reponer la mercadería antes de quedarme sin existencias.

**Criterios de aceptación**

- A definir en el Sprint Planning correspondiente

## HU-021 - Administrar clientes

**Tipo:** Historia · **Módulo:** CLI · **Estimación:** 5 SP · **Estado:** Pendiente · **Sprint:** 3 · **Alcance:** `CLI-01` · **Depende de:** nada

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

**Tipo:** Historia · **Módulo:** CLI · **Estimación:** 2 SP · **Estado:** Pendiente · **Sprint:** 3 · **Alcance:** `CLI-02` · **Depende de:** HU-021, HU-011

**Como** Gerente, **necesito** asignar una lista de precios particular a un cliente, **para** aplicarle condiciones diferenciadas sin necesidad de modificar la lista general.

**Criterios de aceptación**

- **Datos:** cliente, lista de precios asignada (opcional)
- **Validaciones:**
    - solo se pueden asignar listas de tipo `particular` en estado activo y vigentes; las listas de canal son el precio base del canal y no se asignan a un cliente
    - un cliente tiene a lo sumo una lista asignada
    - si el cliente no tiene lista asignada se le aplicará la lista del canal de la operación
- **Comportamiento:**
    - la asignación queda visible en la ficha del cliente
    - su efecto sobre el precio se verifica en la historia de resolución de precio (HU-056)

---

## HU-033 - Emitir y consultar órdenes de compra

**Tipo:** Historia · **Módulo:** CMP · **Estimación:** 8 SP · **Estado:** Pendiente · **Sprint:** 2 · **Alcance:** `CMP-03`, `CMP-04` · **Depende de:** HU-013, HU-005

**Como** Encargado de compras, **necesito** emitir una orden de compra con el proveedor, los artículos y los precios acordados, **para** comunicar formalmente qué necesita comprar La Linda y conservar el documento enviado.

**Criterios de aceptación**

- **Datos:** número de orden, proveedor, depósito de destino, condición de pago, fecha de emisión,
  fecha esperada de entrega, observaciones, estado, y detalle con artículo, cantidad, precio
  unitario pactado y subtotal
- **Validaciones:**
    - proveedor y depósito de destino obligatorios y activos
    - la fecha esperada de entrega no puede ser anterior a la fecha de emisión
    - cantidad y precio unitario mayores a cero
    - un artículo no puede repetirse dentro de la misma orden
    - no se puede emitir una orden sin al menos un artículo
    - el total se calcula como la suma de los subtotales; no se desagregan ni calculan impuestos
- **Comportamiento:**
    - la orden puede guardarse como `borrador` y modificarse mientras permanezca en ese estado
    - al emitir pasa a `emitida`, su cabecera y detalle quedan inmutables y no afecta el stock
    - una orden emitida puede cancelarse, pero no editarse ni eliminarse
    - el listado permite filtrar por proveedor, estado, depósito y rango de fechas, y abrir el detalle
    - la orden emitida se imprime o descarga en PDF con identificación de La Linda, proveedor,
      artículos, cantidades, precios y total
- **Verificación:** se emite una orden con varios artículos, se comprueba el total, se descarga su
  PDF y se verifica que ya no pueda editarse

> **Reformulada por la devolución del profesor (2026-09-06):** absorbe `HU-034`, `HU-035` y la
> consulta mínima de `HU-024`. El alcance termina en la emisión y consulta de la orden; la
> recepción parcial, el cierre por mercadería recibida, la actualización de costos y el ingreso de
> stock permanecen fuera del Sprint 2.

## HU-034 - Cargar el detalle de artículos de la orden de compra

**Tipo:** Historia absorbida · **Módulo:** CMP · **Estimación:** - · **Estado:** Absorbida por HU-033

Su detalle de artículos forma parte de `HU-033` desde la corrección del 2026-09-06. Se conserva el
ID únicamente para mantener la trazabilidad histórica del Product Backlog.

## HU-035 - Calcular los totales y emitir la orden de compra

**Tipo:** Historia absorbida · **Módulo:** CMP · **Estimación:** - · **Estado:** Absorbida por HU-033

El cálculo del total y la emisión forman parte de `HU-033` desde la corrección del 2026-09-06. La
orden de compra ya no calcula IVA discriminado.

## HU-024 - Gestionar los estados y consultar las órdenes de compra

**Tipo:** Historia absorbida · **Módulo:** CMP · **Estimación:** - · **Estado:** Absorbida por HU-033

El listado, los estados mínimos y el PDF forman parte de `HU-033` desde la corrección del
2026-09-06. Los estados relacionados con recepción de mercadería se refinan cuando ingrese el
circuito de recepción, fuera del Sprint 2.

## HU-036 - Registrar y consultar comprobantes de proveedor con detalle

**Tipo:** Historia · **Módulo:** CMP · **Estimación:** 8 SP · **Estado:** Pendiente · **Sprint:** 2 · **Alcance:** `CMP-05` · **Depende de:** HU-013, HU-008

**Como** Encargado de compras, **necesito** registrar y consultar las facturas, notas de crédito y
notas de débito recibidas con todos sus renglones, **para** conservar fielmente el documento del
proveedor y reconocer la deuda o el crédito correspondiente.

**Criterios de aceptación**

- **Datos:**
    - cabecera: proveedor, tipo, letra (`A`, `B`, `C`, `M`), punto de venta, número, fecha de
      emisión, fecha de vencimiento opcional, importe total, observaciones y estado
    - detalle: posición, artículo del catálogo cuando corresponda, descripción original, cantidad,
      unidad de medida, precio unitario e importe del renglón
    - un renglón de concepto sin artículo se admite para cargos, descuentos o ajustes financieros
- **Validaciones:**
    - la combinación proveedor, tipo, letra, punto de venta y número es única
    - proveedor, identificación, fecha de emisión, importe total y al menos un renglón son obligatorios
    - punto de venta y número conservan 4 y 8 dígitos respectivamente
    - importe total, cantidades, precios e importes de renglón son mayores a cero
    - la fecha de emisión no puede ser futura y el vencimiento no puede ser anterior a la emisión
    - el proveedor debe estar activo
    - cada artículo mencionado en el documento debe quedar representado por un renglón
    - no se exige que la suma de renglones coincida con el importe total, porque los renglones pueden
      estar expresados sin los impuestos que el sprint decidió no modelar
- **Comportamiento:**
    - el importe total se transcribe del documento; el sistema no calcula ni desagrega IVA,
      percepciones u otros impuestos
    - una factura y una ND nacen pendientes con saldo igual a su total; una NC nace disponible para
      asociación o compensación según `HU-054`
    - el saldo y el estado se derivan de aplicaciones y órdenes de pago, nunca se cargan a mano
    - el listado muestra identificación, fechas, proveedor, total, saldo, estado y vencimiento
    - la vista de detalle es de solo lectura y muestra la cabecera y todos los renglones
    - un comprobante confirmado, incluidos sus renglones, no se edita ni se elimina; solo se anula
      conservando el historial
    - no se genera PDF propio de facturas, NC o ND porque fueron emitidas por el proveedor
- **Verificación:** se registra cada tipo de comprobante con artículos y un concepto, se consulta su
  detalle completo y se comprueba que no existan acciones de edición, eliminación ni generación de PDF

> **Reformulada por la devolución del profesor (2026-09-06):** se elimina el cálculo y desglose de
> impuestos y cualquier PDF interno del comprobante. Se incorpora el detalle completo de artículos
> solicitado por el equipo, sin asociar todavía el comprobante a órdenes de compra, actualizar
> costos ni generar movimientos de stock.

## HU-037 - Imputar el comprobante a una o varias órdenes de compra

**Tipo:** Historia · **Módulo:** CMP · **Estimación:** 5 SP · **Estado:** Pendiente · **Sprint:** 3 · **Alcance:** `CMP-05` · **Depende de:** HU-036, HU-033

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

> **Regla de negocio / pesables y excedentes:** cuando el proveedor entrega una cantidad superior a la pendiente de la OC (frecuente en carnicería, fiambrería y productos pesables al no poder fraccionar medias reses o piezas exactas), el sistema permite aceptar el excedente. La cantidad imputada salda el renglón de la OC hasta cubrir su saldo pendiente (sin superar el límite de la orden para preservar el presupuesto contractual), y la diferencia se registra como excedente aceptado (`quantity_excess`). Tanto la cantidad imputada como el excedente ingresan al stock físico real y se totalizan en el comprobante a pagar al proveedor.

> **Corrección del PO (2026-09-24):** solo las facturas y los remitos se imputan a órdenes de
> compra; las NC y ND no. Cada renglón de la OC lleva dos pendientes independientes: a recibir
> (lo saldan los remitos) y a facturar (lo saldan las facturas), para que la factura y el remito
> de una misma OC puedan registrarse en cualquier orden sin bloquearse. El excedente de pesables
> se registra por separado en cada circuito.

## HU-038 - Actualizar el último costo y cerrar la orden cubierta

**Tipo:** Historia · **Módulo:** CMP · **Estimación:** 3 SP · **Estado:** Pendiente · **Sprint:** 3 · **Alcance:** `CMP-05` · **Depende de:** HU-037, HU-015

**Como** Encargado de compras, **necesito** que el comprobante actualice por sí solo el costo de los artículos y cierre la orden que quedó completa, **para** no tener que mantener esa información a mano.

**Criterios de aceptación**

- **Datos:** último costo de compra conocido por artículo y proveedor (HU-015), estado de la orden de compra
- **Validaciones:** el último costo se toma del comprobante registrado, nunca se carga manualmente
- **Comportamiento:**
    - al registrar el comprobante se actualiza el último costo de compra de cada artículo para ese proveedor
    - la orden pasa a estado cumplida cuando todas sus líneas quedan totalmente cubiertas
    - una orden cubierta solo parcialmente permanece emitida, con su pendiente actualizado

> **Corrección del PO (2026-09-24):** la orden pasa a `cumplida` solo cuando todas sus líneas
> quedan recibidas por remitos **y** facturadas por facturas (doble condición). Con uno solo de los
> dos circuitos completo permanece emitida, y la consulta de la OC muestra por renglón lo pedido,
> recibido y facturado con sus pendientes.

## HU-026 - Ingresar el stock a partir del comprobante recibido

**Tipo:** Historia · **Módulo:** CMP · **Estimación:** 8 SP · **Estado:** Pendiente · **Sprint:** 3 · **Alcance:** `CMP-06` · **Depende de:** HU-037, HU-017

**Como** Encargado de depósito, **necesito** que el comprobante de proveedor genere automáticamente la entrada de stock, **para** que las existencias reflejen la mercadería recibida sin tener que cargarla dos veces.

**Criterios de aceptación**

- **Datos:** el movimiento generado toma depósito de destino, artículos, cantidades, usuario y comprobante de origen
- **Validaciones:**
    - el movimiento se genera una sola vez por comprobante y no puede duplicarse
    - si el comprobante se anula se genera el movimiento inverso, nunca se borra el original
- **Comportamiento:**
    - la entrada de stock queda vinculada al comprobante que la originó y desde el historial de movimientos se puede navegar hasta el
    - el movimiento es inmutable como todos los demás

## HU-027 - Emitir y anular una orden de pago a proveedor

**Tipo:** Historia · **Módulo:** CMP · **Estimación:** 13 SP · **Estado:** Pendiente · **Sprint:** 2 · **Alcance:** `CMP-07` · **Depende de:** HU-036, HU-054, HU-052

**Como** Encargado de administración, **necesito** emitir una orden de pago aplicando comprobantes
y varios medios de pago, **para** cancelar la deuda con el proveedor y conservar un respaldo
trazable de la operación.

**Criterios de aceptación**

- **Datos:** proveedor, número global, fecha, estado, observaciones, usuario responsable;
  imputaciones con comprobante e importe; y uno o varios medios con importe y sus referencias
- **Validaciones:**
    - el número de OP es correlativo, único globalmente, automático, no editable y nunca se reutiliza
    - todos los comprobantes pertenecen al proveedor seleccionado y conservan importe disponible
    - las facturas y ND suman obligaciones; las NC libres restan como créditos
    - ninguna aplicación supera el saldo o importe disponible del comprobante
    - se admiten cancelaciones totales y parciales y una OP puede afectar varios comprobantes
    - cada medio se elige del catálogo de `HU-052`; sus importes son mayores a cero
    - la suma de los medios de pago coincide con el total neto de la OP
    - el total neto debe ser mayor a cero y se calcula como facturas + ND − NC
- **Comportamiento:**
    - una OP confirmada actualiza los saldos y estados derivados de los comprobantes afectados
    - la OP puede distribuir el total entre efectivo, transferencia, cheque u otros medios activos
    - transferencia y cheque admiten los datos de referencia necesarios para identificar la operación
    - la OP confirmada, sus imputaciones y medios son inmutables y nunca se eliminan físicamente
    - se puede anular indicando motivo; se conservan todas las filas y sus efectos dejan de participar
      en el cálculo de saldos
    - si se quiere anular un comprobante incluido en una OP vigente, primero debe anularse esa OP
    - se genera un PDF de Orden de Pago emitido por La Linda con proveedor, comprobantes, NC
      compensadas, medios, importes, total neto, número y estado
- **Verificación:** se emite una OP con dos facturas, una ND, una NC y varios medios; se comprueban
  el total neto, el PDF y los saldos; después se anula y se verifica que los saldos se restituyen sin
  borrar la orden, sus imputaciones ni sus medios

> **Reformulada por la devolución del profesor (2026-09-06):** amplía la historia a múltiples
> medios de pago, facturas + ND − NC, PDF propio de La Linda, numeración global y anulación por
> estado con conservación íntegra de la trazabilidad. Por este flujo atómico excepcional se
> reestima en 13 SP.

## HU-054 - Gestionar notas de crédito y débito del proveedor

**Tipo:** Historia · **Módulo:** CMP · **Estimación:** 3 SP · **Estado:** Pendiente · **Sprint:** 2 · **Alcance:** `CMP-05` · **Depende de:** HU-036

**Como** Encargado de compras, **necesito** registrar cómo las notas de crédito y débito modifican
la deuda con un proveedor, **para** mantener su saldo correcto sin ajustes manuales ni pasos de
reimputación innecesarios.

**Criterios de aceptación**

- **Datos:** NC o ND con la cabecera y detalle definidos en `HU-036`; para una NC, factura origen
  opcional e importe aplicado
- **Validaciones:**
    - una NC solo puede asociarse a una factura del mismo proveedor y con saldo pendiente
    - el importe aplicado no puede superar ni el saldo de la factura ni el importe disponible de la NC
    - una NC puede quedar total o parcialmente libre para una orden de pago posterior
- **Comportamiento:**
    - si una NC se asocia durante el alta, reduce inmediatamente el saldo de esa factura
    - si no se asocia, queda disponible para compensarla al emitir una OP
    - una ND constituye una obligación independiente, nace pendiente y se cancela mediante una OP
    - no existe una pantalla independiente de reimputación de NC o ND
    - toda asociación confirmada es inmutable y queda trazada con usuario y fecha
- **Verificación:** se registra una NC asociada y otra libre, se comprueba el efecto de la primera
  sobre su factura y la disponibilidad de la segunda; se registra una ND y queda como obligación
  seleccionable para una OP

> **Reformulada por la devolución del profesor (2026-09-06):** reemplaza la imputación N:N
> obligatoria de NC/ND por el modelo mixto. La NC se vincula durante el alta o se compensa en la
> OP; la ND aumenta la deuda como comprobante independiente. Al desaparecer la pantalla propia de
> reimputación, la historia se reestima en 3 SP.

## HU-055 - Consultar el listado de pagos y egresos del período

**Tipo:** Historia · **Módulo:** CMP · **Estimación:** 5 SP · **Estado:** Pendiente · **Sprint:** 3 · **Alcance:** `CMP-08` · **Depende de:** HU-027, HU-036

**Como** Gerente, **necesito** consultar los pagos y egresos realizados en un período con su detalle, **para** controlar cuánto se gastó y a quién se le pagó sin recopilar la información a mano.

**Criterios de aceptación**

- **Datos:** por cada orden de pago y comprobante, fecha, proveedor, tipo de comprobante, medio de pago, importe y estado; y el total de egresos del período
- **Validaciones:**
    - el listado es de solo lectura
    - el total de egresos suma los pagos imputados en el rango de fechas seleccionado, no los importes de comprobantes todavía impagos
- **Comportamiento:**
    - filtros combinables por rango de fechas, proveedor, tipo de comprobante, medio de pago y estado
    - desde cada pago se ve el detalle de los comprobantes afectados y el importe imputado a cada uno
    - el listado es exportable a CSV y Excel
- **Verificación:** se filtra por proveedor y por un rango de fechas y se comprueba que el total de egresos coincide con la suma de los pagos listados

> **Historia nueva del Sprint Planning 2 (2026-08-29).** Cubre el "listado de pagos y egresos" del
> PO. Es la vista operativa por período y por pago; `HU-028` (cuenta corriente por proveedor) es la
> vista complementaria por saldo. Tras la corrección del 2026-09-06, ambas quedan fuera del Sprint
> 2 para priorizar el circuito completo de órdenes de compra, comprobantes y órdenes de pago.
> `HU-055` alimenta más adelante a `EPIC-19` (egresos del período en el tablero gerencial).

## HU-028 - Consultar el saldo de cuenta corriente de un proveedor

**Tipo:** Historia · **Módulo:** CMP · **Estimación:** 3 SP · **Estado:** Pendiente · **Sprint:** 3 · **Alcance:** `CMP-08` · **Depende de:** HU-027

**Como** Encargado de compras, **necesito** consultar cuánto le debo a cada proveedor con el detalle de lo pendiente, **para** poder priorizar los pagos y detectar comprobantes vencidos.

**Criterios de aceptación**

- **Datos:** proveedor, total de comprobantes recibidos, total pagado, saldo, y detalle de comprobantes pendientes con su fecha, importe, importe pagado, saldo y antigüedad en días
- **Validaciones:** el saldo se calcula siempre como la diferencia entre comprobantes recibidos y pagos imputados, nunca se carga manualmente
- **Comportamiento:** el listado se filtra por proveedor y rango de fechas y es exportable a CSV y Excel

## HU-029 - Actualizar precios de forma masiva por porcentaje

**Tipo:** Historia · **Módulo:** PRE · **Estimación:** 5 SP · **Estado:** Pendiente · **Alcance:** `PRE-04` · **Depende de:** HU-012, HU-015

**Como** Gerente, **necesito** aplicar un aumento o una baja porcentual sobre los precios de una lista filtrando por categoría o proveedor, **para** ajustar los precios ante una variación de costos sin editar artículo por artículo.

**Criterios de aceptación**

- A definir en el Sprint Planning correspondiente

## HU-030 - Consultar el historial de cambios de precio

**Tipo:** Historia · **Módulo:** PRE · **Estimación:** 3 SP · **Estado:** Pendiente · **Alcance:** `PRE-05` · **Depende de:** HU-029

**Como** Gerente, **necesito** consultar cómo evolucionó el precio de un artículo en cada lista, **para** poder justificar ante la dirección cuándo y por qué cambió un precio.

**Criterios de aceptación**

- A definir en el Sprint Planning correspondiente

---

## HU-056 - Resolver el precio de venta según el cliente y el canal

**Tipo:** Historia · **Módulo:** PRE · **Estimación:** 8 SP · **Estado:** Pendiente · **Sprint:** 3 · **Alcance:** `PRE-03` · **Depende de:** HU-022

**Como** Vendedor, **necesito** que el sistema determine automáticamente qué precio corresponde a cada línea de la venta, **para** vender siempre al precio correcto sin tener que consultar qué lista aplica en cada caso.

**Criterios de aceptación**

- **Datos:** para cada línea de venta se recibe artículo, cliente (o Consumidor Final) y canal (`mostrador` u `online`); el resultado es el precio unitario a cobrar junto con la lista de precios de origen
- **Validaciones:**
    - la resolución sigue una precedencia estricta: 1) lista `particular` asignada al cliente, si está activa y vigente; 2) lista de canal vigente para el canal de la operación (`mostrador` u `online`); 3) lista de canal `general` vigente, que `HU-011` garantiza que siempre existe
    - solo se consideran listas activas y vigentes a la fecha de la operación; una lista futura o vencida se descarta como si no existiera
    - si el artículo no tiene precio en ninguna lista aplicable según la cascada, la operación se rechaza con un error explícito; nunca se cobra a precio cero o estimado
- **Comportamiento:**
    - la resolución vive en un único Action interno (`ResolveArticlePrice`) invocado tanto desde la venta de mostrador como desde el circuito de e-commerce, de modo que ambos canales aplican siempre la misma regla
    - el resultado deja registrada la lista de origen del precio aplicado, para que la línea de venta quede trazable
    - un cliente sin lista particular asignada usa la lista vigente de su canal, y solo cae a la lista general si el canal no tiene una lista propia vigente
- **Verificación:** se simula una venta con un cliente con lista propia, otra de mostrador con un cliente sin lista propia, y otra por canal online sin lista propia, y se comprueba que cada una toma el precio de la lista que corresponde según la cascada

## HU-051 - Administrar puntos de venta

**Tipo:** Historia · **Módulo:** ADM · **Estimación:** 2 SP · **Estado:** Pendiente · **Sprint:** 3 · **Alcance:** `ADM-01` · **Depende de:** HU-005

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

## HU-052 - Administrar medios de pago

**Tipo:** Historia · **Módulo:** ADM · **Estimación:** 2 SP · **Estado:** Pendiente · **Sprint:** 2 · **Alcance:** `ADM-03` · **Depende de:** nada

**Como** Administrador, **necesito** administrar los medios de pago disponibles, **para** que el cobro de una venta se registre siempre con un valor controlado y no cargado a mano.

**Criterios de aceptación**

- **Datos:** medio de pago (nombre, estado, indicador de habilitado en canal online)
- **Validaciones:**
    - nombre único
    - no se puede dar de baja un valor ya utilizado en una operación registrada
- **Comportamiento:** un mismo pago puede distribuir su importe entre varios medios activos; los
  datos operativos propios de transferencia, cheque u otro medio se registran en la operación que
  los utiliza, no en este catálogo

> **Historia desglosada de `HU-007` por corrección del PO (2026-08-22):** los medios de pago no
> hacen falta para artículos ni stock, así que se sacaron de `HU-007` y bajaron de prioridad
> hasta acá, justo antes de la primera historia que realmente los necesita.

## HU-057 - Abrir la caja con el fondo inicial desglosado por denominación

**Tipo:** Historia · **Módulo:** VTA · **Estimación:** 5 SP · **Estado:** Pendiente · **Sprint:** 4 · **Alcance:** sin ID propio (pedido del PO, 2026-09-26) · **Depende de:** HU-051, HU-052

**Como** Cajero, **necesito** abrir mi turno en una caja declarando cuántos billetes de cada denominación tengo de cambio, **para** que el sistema sepa con qué fondo arranco y asocie a ese turno todo lo que venda y cobre.

**Criterios de aceptación**

- **Datos:** punto de venta (la caja), cajero (usuario logueado), fecha y hora de apertura, conteo por denominación (denominación y cantidad), fondo inicial
- **Validaciones:**
    - el punto de venta debe estar activo
    - una caja tiene a lo sumo un turno abierto, y un cajero también
    - las cantidades son enteras y mayores o iguales a cero
    - el fondo inicial es la suma de denominación × cantidad; nunca se carga a mano
- **Comportamiento:**
    - la apertura crea el turno de caja, cuyo ID identifica todas las ventas y movimientos de dinero del turno
    - el fondo inicial queda registrado como el primer movimiento de caja (tipo apertura, en efectivo)
    - se admite abrir con fondo cero
    - mientras el turno está abierto, el sistema muestra en qué caja trabaja el cajero y desde qué hora
- **Verificación:** se abre una caja con 10 billetes de $10.000 y 5 de $2.000 y se comprueba un fondo de $110.000; un segundo intento de abrir la misma caja se rechaza

> **Historia nueva del Sprint Planning 4 (2026-09-27)**, a partir de la reunión con el PO del
> 26/09: "¿Qué hace un cajero cuando llega? Se sienta, abre la caja y carga cuánto cambio tiene",
> con el conteo de billetes por denominación. La caja se modela como el punto de venta de
> `HU-051`, y el "ID de movimiento de caja" que pidió el PO es el turno de caja.

## HU-039 - Abrir una venta de mostrador dentro del turno de caja

**Tipo:** Historia · **Módulo:** VTA · **Estimación:** 3 SP · **Estado:** Pendiente · **Sprint:** 4 · **Alcance:** `VTA-01` · **Depende de:** HU-057, HU-021

**Como** Cajero, **necesito** abrir una venta en la caja donde tengo el turno abierto, **para** empezar a cargar los artículos del cliente y que la venta quede asociada a mi turno.

**Criterios de aceptación**

- **Datos:** turno de caja (y a través de él, punto de venta y sucursal), canal (siempre mostrador), cajero, cliente, fecha y hora, estado
- **Validaciones:**
    - no se puede abrir una venta sin un turno de caja abierto del usuario
    - la venta toma el punto de venta del turno; el cajero no lo elige
    - solo una venta abierta de un turno abierto acepta cambios
- **Comportamiento:**
    - la venta abre con el cliente Consumidor Final; el cliente se cambia después (`EPIC-03`)
    - una venta abierta se puede descartar y queda registrada como descartada
    - el listado de ventas se filtra por estado, caja y fecha, y muestra el turno de cada venta
    - sin turno abierto, la pantalla ofrece ir a abrir la caja
- **Verificación:** con un turno abierto se abre una venta y queda asociada a ese turno; un usuario sin turno abierto no puede abrir ventas

> **Implementación parcial adelantada (Sprint 3):** el PR #56 construyó la apertura, el listado y
> el descarte de ventas fuera del compromiso (ver `docs/plans/venta-basica-plan.md`).
> **Corrección del PO (2026-09-26):** toda venta se asocia al turno de caja en que se hizo. Por
> ese cambio de comportamiento la historia conserva sus 3 SP.

## HU-040 - Incorporar artículos a la venta por código de barras o búsqueda

**Tipo:** Historia · **Módulo:** VTA · **Estimación:** 2 SP (reestimada, antes 5) · **Estado:** Pendiente · **Sprint:** 4 · **Alcance:** `VTA-02` · **Depende de:** HU-039

**Como** Cajero, **necesito** cargar los artículos leyendo su código de barras o buscándolos por código interno y descripción, **para** atender rápido en caja sin demorar al cliente.

**Criterios de aceptación**

- **Datos:** artículo y cantidad
- **Validaciones:**
    - el código leído se busca exacto por código de barras y, si no aparece, por código interno
    - un código inexistente o un artículo inactivo se rechaza con un mensaje que lo identifica, sin perder la venta
    - la cantidad es mayor a cero, y entera si la unidad de medida del artículo no admite decimales
- **Comportamiento:**
    - leer un código agrega el artículo sin pantalla intermedia y el foco vuelve al lector
    - leer un artículo que ya está en la venta suma 1 a su cantidad
    - se puede modificar la cantidad de una línea y quitarla
- **Verificación:** se escanea dos veces el mismo artículo y queda una sola línea con cantidad 2; un artículo pesable acepta 0,750

> **Reestimada de 5 a 2 SP (Sprint Planning 4):** el PR #56 la construyó fuera del compromiso del
> Sprint 3. Entra con la estimación del trabajo restante —validar contra estos criterios y
> ajustar— para no sumar a la velocidad trabajo ya hecho. Deja de depender de `HU-009`: la venta
> tiene su propia búsqueda de artículos desde el PR #56.

## HU-041 - Calcular el precio y los totales de la venta

**Tipo:** Historia · **Módulo:** VTA · **Estimación:** 2 SP (reestimada, antes 5) · **Estado:** Pendiente · **Sprint:** 4 · **Alcance:** `VTA-03` · **Depende de:** HU-040, HU-056

**Como** Cajero, **necesito** que cada línea tome automáticamente el precio de la lista vigente y que la venta muestre su total, **para** cobrar el importe correcto sin calcular nada a mano.

**Criterios de aceptación**

- **Datos:** por línea, precio unitario, lista de precios de origen y total de la línea; total de la venta
- **Validaciones:**
    - el precio lo resuelve `HU-056` según el cliente y el canal mostrador; nunca se carga a mano
    - un artículo sin precio en ninguna lista aplicable no se agrega a la venta
    - los importes se suman sin errores de redondeo, también con cantidades decimales
- **Comportamiento:**
    - el precio de la línea se fija al agregarla; un cambio posterior en la lista no la modifica
    - cambiar el cliente vuelve a resolver el precio de todas las líneas (`EPIC-03`)
    - cada línea muestra de qué lista salió su precio
    - el precio de lista es final con IVA incluido; la venta no discrimina IVA (`HU-063`)
- **Verificación:** se cargan un artículo con precio en la lista de mostrador y otro que solo tiene precio en la lista general, y se comprueban el precio, la lista de origen y el total

> **División acordada (2026-09-24, `docs/plans/venta-basica-plan.md`):** la historia se partió en
> "precio y totales" (esta) y la discriminación del IVA por alícuota, que pasó a `HU-063`. Por eso
> deja de depender de `HU-007`. **Reestimada de 5 a 2 SP** por el mismo motivo que `HU-040`.
> **Pendiente de confirmar con el PO:** que el precio de lista sea final con IVA incluido.

## EPIC-04 - Cobrar la venta con uno o varios medios de pago

**Tipo:** Historia (antes Epic) · **Módulo:** VTA · **Estimación:** 8 SP · **Estado:** Pendiente · **Sprint:** 4 · **Alcance:** `VTA-05` · **Depende de:** HU-041, HU-052, HU-057

**Como** Cajero, **necesito** registrar el cobro de la venta repartiéndolo entre uno o varios medios de pago, **para** cobrar como pide el cliente —efectivo, tarjeta, billetera virtual— y que cada peso quede registrado en mi caja.

**Criterios de aceptación**

- **Datos:** por cada medio usado, medio de pago, importe y referencia opcional (número de cupón u operación); en efectivo, además, importe entregado por el cliente y vuelto
- **Validaciones:**
    - solo medios de pago activos
    - la suma de los importes coincide con el total de la venta; solo el efectivo puede recibir de más, y la diferencia es el vuelto
    - no se cobra una venta sin líneas, descartada o ya confirmada
- **Comportamiento:**
    - cada medio usado genera un movimiento de caja de tipo venta en el turno, vinculado a la venta
    - en efectivo el movimiento registra el importe de la venta (lo que queda en la caja), no lo entregado
    - al completar el cobro la venta pasa a confirmada y queda inmutable
    - cobro, confirmación y egreso de stock (`EPIC-06`) ocurren en una sola operación: si algo falla, no queda nada a medias
    - cada medio de pago tiene una clase (efectivo, tarjeta, billetera virtual, transferencia u otro) que ordena el arqueo de `HU-060`
- **Verificación:** se cobra una venta de $8.500 con $5.000 de débito y $10.000 en efectivo; el vuelto es $6.500 y quedan dos movimientos de caja, de $5.000 y de $3.500

> **Pasa de Epic a Historia en el Sprint Planning 4 (2026-09-27).** Que las ventas sean
> movimientos de caja es indicación del PO (26/09): "Vayan por esa entidad: las ventas también son
> movimientos de caja".

## EPIC-06 - Descontar el stock automáticamente al confirmar la venta

**Tipo:** Historia (antes Epic) · **Módulo:** VTA · **Estimación:** 5 SP · **Estado:** Pendiente · **Sprint:** 4 · **Alcance:** `VTA-07` · **Depende de:** EPIC-04

**Como** Encargado de depósito, **necesito** que la venta genere por sí sola el egreso de stock del depósito del punto de venta, **para** que las existencias reflejen la realidad sin depender de una carga manual.

**Criterios de aceptación**

- **Datos:** el movimiento generado toma el tipo de sistema "Salida por Venta", el depósito del punto de venta, los artículos y cantidades de la venta, el usuario y la venta de origen
- **Validaciones:**
    - el movimiento se genera una sola vez por venta, al confirmarla
    - si algún artículo quedaría con existencia negativa, la venta no se confirma y el mensaje nombra el artículo (a confirmar con el PO)
- **Comportamiento:**
    - el movimiento es inmutable y queda vinculado a la venta; desde el historial de stock se navega hasta ella
    - las ventas abiertas o descartadas no mueven stock
- **Verificación:** se confirma una venta y se comprueba el egreso en las existencias del depósito de la caja y en el historial de movimientos

> **Dependencia corregida (Sprint Planning 4):** dependía de `HU-042` (factura), pero el egreso
> ocurre al confirmar la venta con el cobro, que existe antes que la factura.

## HU-058 - Registrar ingresos y egresos de dinero en la caja

**Tipo:** Historia · **Módulo:** VTA · **Estimación:** 3 SP · **Estado:** Pendiente · **Sprint:** 4 · **Alcance:** sin ID propio (pedido del PO, 2026-09-26) · **Depende de:** HU-057

**Como** Cajero, **necesito** registrar el dinero que entra o sale de mi caja durante el turno por motivos distintos de una venta —un gasto chico, un retiro, un refuerzo de cambio—, **para** que el arqueo del cierre explique cada diferencia con el fondo.

**Criterios de aceptación**

- **Datos:** turno de caja, tipo (ingreso o egreso), importe, motivo, usuario, fecha y hora
- **Validaciones:**
    - importe mayor a cero y motivo obligatorio
    - solo se registran en un turno abierto
    - un egreso no puede superar el efectivo disponible según el sistema
- **Comportamiento:**
    - se registran en efectivo
    - son inmutables: un error se corrige con el movimiento contrario
    - el turno muestra el listado de sus movimientos (apertura, ventas, ingresos y egresos) y el efectivo esperado
- **Verificación:** se registra un egreso de $3.000 por la compra de artículos de limpieza y el efectivo esperado del turno baja $3.000

> **Historia nueva del Sprint Planning 4 (2026-09-27).** PO (26/09): "A veces pasa que hay gastos
> o se prestan plata entre cajas. Para eso se modela como movimiento de caja". El préstamo entre
> cajas se separó en `HU-059`.

## HU-060 - Cerrar la caja con arqueo por medio de pago

**Tipo:** Historia · **Módulo:** VTA · **Estimación:** 8 SP · **Estado:** Pendiente · **Sprint:** 4 · **Alcance:** sin ID propio (pedido del PO, 2026-09-26) · **Depende de:** EPIC-04, HU-058

**Como** Cajero, **necesito** cerrar mi turno contando el efectivo billete por billete y declarando lo cobrado con cada otro medio, **para** rendir la caja sabiendo si sobra o falta dinero y en qué medio de pago.

**Criterios de aceptación**

- **Datos:** conteo de cierre por denominación; importe declarado por cada medio no efectivo con movimientos en el turno; número de lote del POSNET para las tarjetas; esperado, declarado y diferencia por medio de pago; observaciones; fecha y hora de cierre
- **Validaciones:**
    - el esperado de cada medio se calcula de los movimientos del turno; nunca se carga a mano
    - el efectivo declarado es la suma del conteo por denominación
    - no se puede cerrar con ventas abiertas en el turno
    - si hay alguna diferencia, la observación es obligatoria
- **Comportamiento:**
    - la diferencia es declarado menos esperado: positiva es sobrante y negativa, faltante
    - un turno cerrado es inmutable y no acepta ventas ni movimientos
    - al cerrar se muestra el resumen de rendición del turno —fondo inicial, ventas, ingresos, egresos, y esperado, declarado y diferencia por medio de pago— listo para imprimir
    - cerrado el turno, la caja queda libre para abrir uno nuevo
- **Verificación:** se cierra un turno con un faltante de $500 en efectivo y la tarjeta cuadrada; el resumen muestra ambas líneas y la caja ya no acepta ventas

> **Historia nueva del Sprint Planning 4 (2026-09-27).** PO (26/09): "No solo se vende en
> efectivo; también se cobra con tarjeta, aplicaciones y POSNET. El cajero saca el cierre de lote
> del POSNET y en el sistema tiene que haber un listado con el cierre".

## HU-061 - Consultar los turnos de caja y su rendición

**Tipo:** Historia · **Módulo:** VTA · **Estimación:** 3 SP · **Estado:** Pendiente · **Alcance:** sin ID propio (pedido del PO, 2026-09-26) · **Depende de:** HU-060

**Como** Gerente, **necesito** consultar los turnos de caja de cada sucursal con el resultado de su arqueo, **para** controlar las rendiciones y detectar cajas con diferencias recurrentes.

**Criterios de aceptación**

- A definir en el Sprint Planning correspondiente

> **Historia nueva del Sprint Planning 4 (2026-09-27).** `HU-060` muestra la rendición del turno que
> se cierra; esta es la consulta histórica. Candidata del Sprint 4 si sobra capacidad.

## HU-059 - Registrar un préstamo de dinero entre cajas

**Tipo:** Historia · **Módulo:** VTA · **Estimación:** 3 SP · **Estado:** Pendiente · **Alcance:** sin ID propio (pedido del PO, 2026-09-26) · **Depende de:** HU-058

**Como** Cajero, **necesito** registrar que le presto efectivo a otra caja abierta, **para** que el arqueo de las dos cajas cierre sin diferencias injustificadas.

**Criterios de aceptación**

- A definir en el Sprint Planning correspondiente

> **Historia nueva del Sprint Planning 4 (2026-09-27).** Separada de `HU-058`: genera en una sola
> operación un egreso en una caja y un ingreso en la otra. Mientras no esté, se registra con un
> egreso y un ingreso manuales. Candidata del Sprint 4 si sobra capacidad.

---

## HU-046 - Publicar el catálogo en la tienda online

**Tipo:** Historia · **Módulo:** ECO · **Estimación:** 5 SP · **Estado:** Pendiente · **Sprint:** 4 · **Alcance:** `ECO-01` · **Depende de:** HU-012, HU-056

**Como** Cliente, **necesito** ver en la tienda los artículos que el supermercado publica, con su descripción, su imagen y su precio, **para** saber qué puedo comprar y cuánto cuesta.

**Criterios de aceptación**

- **Datos:** por artículo, imagen (un placeholder hasta que entre `HU-032`), descripción, marca, unidad de medida y precio
- **Validaciones:**
    - solo se muestran artículos activos, marcados como publicables en el canal online y con precio para ese canal
    - el precio lo resuelve `HU-056` con el canal online; si el cliente inició sesión, se aplica su lista particular
- **Comportamiento:**
    - el catálogo se recorre sin iniciar sesión
    - se navega por categoría y se busca por descripción, con resultados paginados
    - no hay calificaciones, estrellas ni reseñas de productos
    - la interfaz está pensada para el cliente final y se valida en desktop
- **Verificación:** un artículo publicable con precio online aparece con ese precio; uno no publicable o sin precio no aparece; un cliente con lista particular ve su precio

> **Dependencia corregida (Sprint Planning 4):** dependía de `EPIC-11`, pero recorrer el catálogo
> no requiere cuenta. **PO (2026-09-26):** interfaz tipo Coto Digital o PedidosYa, sin
> calificaciones ("no quiero eso") y con foco en desktop ("en celular no lo vamos a probar").

## EPIC-11 - Registrarse e iniciar sesión como cliente en la tienda online

**Tipo:** Historia (antes Epic) · **Módulo:** CLI · **Estimación:** 8 SP · **Estado:** Pendiente · **Sprint:** 4 · **Alcance:** `CLI-03` · **Depende de:** HU-021

**Como** Cliente, **necesito** crear mi cuenta e iniciar sesión en la tienda, **para** poder comprar y seguir el estado de mis pedidos.

**Criterios de aceptación**

- **Datos:** nombre y apellido, correo electrónico, contraseña, teléfono, domicilio y DNI opcionales
- **Validaciones:**
    - nombre, correo y contraseña obligatorios
    - correo con formato válido y único entre las cuentas de la tienda
    - contraseña con las mismas reglas que las del personal, confirmada
    - los intentos fallidos de inicio de sesión se limitan como en el panel interno
- **Comportamiento:**
    - registrarse crea el cliente como consumidor final junto con su cuenta, y deja la sesión iniciada
    - la sesión del cliente es independiente de la del personal: un cliente no accede al panel interno
    - el cliente consulta y modifica sus datos de contacto en "Mi cuenta"
    - la recuperación de contraseña por correo queda fuera, como en el resto del sistema
- **Verificación:** un cliente se registra, cierra sesión y vuelve a entrar; al intentar abrir una pantalla del panel interno, se le niega el acceso; el registro con un correo ya usado se rechaza

> **Pasa de Epic a Historia en el Sprint Planning 4 (2026-09-27).** Dependencia corregida: dependía
> de `HU-042` (factura), pero la cuenta solo necesita el cliente de `HU-021`.

## EPIC-13 - Gestionar el carrito de compras

**Tipo:** Historia (antes Epic) · **Módulo:** ECO · **Estimación:** 8 SP · **Estado:** Pendiente · **Sprint:** 4 · **Alcance:** `ECO-04` · **Depende de:** HU-046, EPIC-11

**Como** Cliente, **necesito** agregar artículos a un carrito que se conserve entre visitas y ver el total, **para** armar mi compra con tranquilidad sin perder lo que ya había elegido.

**Criterios de aceptación**

- **Datos:** artículo, cantidad, precio vigente, subtotal por línea y total
- **Validaciones:**
    - solo se agregan artículos publicables con precio online
    - la cantidad es mayor a cero, y entera si la unidad de medida no admite decimales
    - agregar al carrito requiere sesión iniciada; sin sesión se pide iniciarla y se vuelve al artículo
- **Comportamiento:**
    - agregar un artículo que ya está en el carrito suma la cantidad
    - el carrito se conserva entre visitas
    - el precio mostrado es el vigente en el momento; se fija recién al confirmar el pedido (`HU-062`)
    - un artículo que dejó de publicarse o quedó sin precio se marca como no disponible y no suma al total
    - el encabezado de la tienda muestra la cantidad de artículos del carrito
- **Verificación:** se agregan tres artículos, se cierra sesión y al volver el carrito sigue igual; se cambia un precio en la lista online y el carrito muestra el precio nuevo

> **Pasa de Epic a Historia en el Sprint Planning 4 (2026-09-27).** Dependencia corregida: dependía
> de `HU-048`, pero el carrito funciona sin el control de disponibilidad, que queda como candidata.

## HU-062 - Confirmar el pedido desde el carrito

**Tipo:** Historia · **Módulo:** ECO · **Estimación:** 5 SP · **Estado:** Pendiente · **Sprint:** 4 · **Alcance:** `ECO-04` · **Depende de:** EPIC-13

**Como** Cliente, **necesito** confirmar la compra de lo que armé en el carrito eligiendo dónde retirarla, **para** que el supermercado prepare mi pedido.

**Criterios de aceptación**

- **Datos:** número de pedido, cliente, sucursal de retiro, observaciones, fecha y hora, estado; detalle con artículo, cantidad, precio unitario, lista de origen y subtotal; total
- **Validaciones:**
    - el carrito tiene al menos un artículo
    - si algún artículo quedó no disponible, la confirmación se rechaza y el mensaje lo nombra
    - el número de pedido es correlativo, único, automático y no editable
    - la sucursal de retiro está activa
- **Comportamiento:**
    - los precios se resuelven de nuevo al confirmar y quedan fijos en el pedido
    - el pedido nace pendiente; el envío a domicilio (`HU-049`) y el pago online (`HU-050`) se suman después
    - al confirmar, el carrito se vacía
    - el cliente consulta sus pedidos y su detalle en "Mis pedidos", y no ve los de otros clientes
- **Verificación:** se confirma un pedido, se cambia después un precio en la lista online y el pedido conserva el precio original; el carrito queda vacío

> **Historia nueva del Sprint Planning 4 (2026-09-27).** El PO pidió "carrito de compras y
> checkout": "entra y resuelve su compra". El backlog saltaba del carrito a la modalidad de entrega
> y al pago sin un paso de confirmación; esta historia lo agrega.

## HU-048 - Mostrar la disponibilidad online e impedir la compra sin stock

**Tipo:** Historia · **Módulo:** ECO · **Estimación:** 3 SP · **Estado:** Pendiente · **Alcance:** `ECO-03` · **Depende de:** HU-046, HU-016

**Como** Cliente, **necesito** saber si el artículo está disponible antes de agregarlo, **para** no llevarme la sorpresa de que no hay stock al momento de pagar.

**Criterios de aceptación**

- A definir en el Sprint Planning correspondiente

## HU-047 - Buscar, filtrar y ordenar artículos en la tienda online

**Tipo:** Historia · **Módulo:** ECO · **Estimación:** 5 SP · **Estado:** Pendiente · **Alcance:** `ECO-02` · **Depende de:** HU-046

**Como** Cliente, **necesito** filtrar el catálogo y ordenarlo a mi gusto, **para** encontrar lo que busco sin recorrer todo el listado.

**Criterios de aceptación**

- A definir en el Sprint Planning correspondiente

---

## HU-007 - Administrar las alícuotas de IVA

**Tipo:** Historia · **Módulo:** ADM · **Estimación:** 2 SP · **Estado:** Pendiente · **Alcance:** `ADM-03` · **Depende de:** nada

**Como** Administrador, **necesito** administrar las alícuotas de IVA, **para** que el catálogo y las operaciones tomen siempre un valor controlado y no cargado a mano en cada pantalla.

**Criterios de aceptación**

- A definir en el Sprint Planning correspondiente

> **Implementación existente:** el ABM de alícuotas (`pricing/vat-rates`) se construyó en el PR
> #15, antes de que esta historia bajara de prioridad. Después, el commit `183e65a` quitó la
> relación entre el artículo y la alícuota, y con eso quedó sin efecto el control de baja de una
> alícuota en uso (`VatRate::isInUse()` devuelve siempre `false`). Se revisa en el planning.

> **Corrección del PO (2026-08-22):** medios de pago se sacó de esta historia -tampoco hace
> falta para artículos ni para stock- y pasó a `HU-052`. `HU-008` (catálogo) dejó de depender de
> esta historia: la alícuota de IVA del artículo pasa a opcional hasta que esta historia entre.
> **Reubicación (2026-08-22):** esta historia entera bajó de prioridad y se movió desde el
> bloque de parámetros (ADM, cerca del tope) hasta acá, justo antes de `HU-041`, la primera
> historia que realmente necesita alícuotas para calcular algo. No aporta a artículos ni a
> stock, así que no había motivo para construirla antes que ellos.

## HU-063 - Discriminar el IVA por alícuota en la venta

**Tipo:** Historia · **Módulo:** VTA · **Estimación:** 3 SP · **Estado:** Pendiente · **Alcance:** `VTA-03` · **Depende de:** HU-007, HU-041, EPIC-03

**Como** Cajero, **necesito** que la venta desglose el neto y el IVA de cada alícuota, **para** emitir la factura A a un responsable inscripto con el IVA discriminado.

**Criterios de aceptación**

- A definir en el Sprint Planning correspondiente

> **Historia nueva por la división de `HU-041` (acordada el 2026-09-24, registrada en el Sprint
> Planning 4).** Requiere volver a relacionar el artículo con su alícuota, que el commit `183e65a`
> quitó.

## EPIC-03 - Identificar al cliente y determinar el tipo de comprobante

**Tipo:** Epic · **Módulo:** VTA · **Estimación:** 5 SP · **Estado:** Pendiente · **Alcance:** `VTA-04` · **Depende de:** HU-039, HU-021

**Como** Vendedor, **necesito** identificar al cliente de la operación o dejarlo como consumidor final, **para** emitir el comprobante que corresponde a su condición fiscal.

**Criterios de aceptación**

- A definir en el Sprint Planning correspondiente

> **Implementación parcial adelantada (Sprint 3):** para ver funcionando `HU-056` desde una
> pantalla real se construyó una venta básica de mostrador fuera del compromiso del sprint (PR
> #56). Qué se hizo y qué quedó afuera está en `docs/plans/venta-basica-plan.md`. No son criterios
> aprobados: se revisan con el PO en el Sprint Planning en que entre esta historia.
> De esta épica solo se hizo cambiar el cliente de una venta abierta y recalcular sus precios;
> el tipo de comprobante no se construyó.

## HU-042 - Emitir la factura con numeración correlativa por punto de venta

**Tipo:** Historia · **Módulo:** VTA · **Estimación:** 8 SP · **Estado:** Pendiente · **Alcance:** `VTA-06` · **Depende de:** EPIC-03, EPIC-04, HU-063

**Como** Vendedor, **necesito** emitir la factura A, B o C con numeración correlativa por punto de venta e IVA discriminado, **para** entregarle al cliente el comprobante de su compra.

**Criterios de aceptación**

- A definir en el Sprint Planning correspondiente

> **Nota del Sprint Planning 4:** el PO pidió que cada comprobante emitido quede asociado al turno
> de caja (`HU-057`), igual que la venta.

## HU-043 - Imprimir y descargar la factura en PDF

**Tipo:** Historia · **Módulo:** VTA · **Estimación:** 5 SP · **Estado:** Pendiente · **Alcance:** `VTA-06` · **Depende de:** HU-042

**Como** Vendedor, **necesito** imprimir la factura o descargarla en PDF, **para** entregársela al cliente en papel o por correo.

**Criterios de aceptación**

- A definir en el Sprint Planning correspondiente

## HU-044 - Anular una venta con nota de crédito y reingreso de stock

**Tipo:** Historia · **Módulo:** VTA · **Estimación:** 8 SP · **Estado:** Pendiente · **Alcance:** `VTA-08` · **Depende de:** HU-042, EPIC-06

**Como** Vendedor, **necesito** anular una venta completa, **para** corregir un error de facturación dejando el stock y la facturación consistentes.

**Criterios de aceptación**

- A definir en el Sprint Planning correspondiente

## HU-045 - Registrar una devolución parcial de cliente

**Tipo:** Historia · **Módulo:** VTA · **Estimación:** 5 SP · **Estado:** Pendiente · **Alcance:** `VTA-08` · **Depende de:** HU-044

**Como** Vendedor, **necesito** registrar la devolución de algunos artículos de una venta, **para** devolverle al cliente lo que corresponde sin anular toda la operación.

**Criterios de aceptación**

- A definir en el Sprint Planning correspondiente

## EPIC-08 - Consultar los comprobantes emitidos

**Tipo:** Epic · **Módulo:** VTA · **Estimación:** 5 SP · **Estado:** Pendiente · **Alcance:** `VTA-09` · **Depende de:** HU-042

**Como** Gerente, **necesito** consultar y filtrar los comprobantes emitidos por distintos criterios, **para** controlar la facturación de cada sucursal y canal.

**Criterios de aceptación**

- A definir en el Sprint Planning correspondiente

## EPIC-09 - Consultar la ficha del cliente con su historial

**Tipo:** Epic · **Módulo:** CLI · **Estimación:** 5 SP · **Estado:** Pendiente · **Alcance:** `CLI-04` · **Depende de:** HU-042

**Como** Vendedor, **necesito** ver el historial de compras y comprobantes de un cliente, **para** responder consultas y gestionar devoluciones sin buscar papeles.

**Criterios de aceptación**

- A definir en el Sprint Planning correspondiente

## EPIC-10 - Registrar y consultar el log de auditoría

**Tipo:** Epic · **Módulo:** SEG · **Estimación:** 8 SP · **Estado:** Pendiente · **Alcance:** `SEG-05` · **Depende de:** HU-045

**Como** Gerente, **necesito** consultar quién realizó cada operación crítica y qué valores modificó, **para** poder auditar el sistema y deslindar responsabilidades ante una diferencia.

**Criterios de aceptación**

- A definir en el Sprint Planning correspondiente

## SPIKE-01 - Investigar la integración con ARCA (WSAA y WSFE)

**Tipo:** Spike · **Módulo:** VTA · **Estimación:** 3 SP · **Estado:** Pendiente · **Alcance:** deseable de `VTA`, sin ID propio (integración ARCA) · **Depende de:** nada

**Como** Equipo de Desarrollo, **necesito** conocer el esfuerzo real de integrar la facturación electrónica con ARCA, **para** poder decidir con información si se incorpora al alcance comprometido o queda como deseable.

**Criterios de aceptación**

- A definir en el Sprint Planning correspondiente

## HU-049 - Elegir la modalidad de entrega y calcular el costo de envío

**Tipo:** Historia · **Módulo:** ECO · **Estimación:** 5 SP · **Estado:** Pendiente · **Alcance:** `ECO-05` · **Depende de:** HU-062

**Como** Cliente, **necesito** elegir entre envío a domicilio y retiro en una sucursal, **para** recibir la compra como me quede más cómodo.

**Criterios de aceptación**

- A definir en el Sprint Planning correspondiente

> **A revisar con el PO:** falta definir si el costo de envío es un valor fijo único o un
> parámetro configurable. Si es configurable, se administra en `ADM-03`, pero no encaja en
> `HU-007` (alícuotas de IVA) ni en `HU-052` (medios de pago) desde el desglose del 2026-08-22:
> habría que dar de alta un nuevo parámetro cuando se confirme con el PO.

> **Dependencia corregida (Sprint Planning 4):** pasa a depender de `HU-062`, que ya confirma el
> pedido con retiro en sucursal; esta historia le suma el envío a domicilio.

## HU-050 - Pagar el pedido con Mercado Pago en sandbox

**Tipo:** Historia · **Módulo:** ECO · **Estimación:** 8 SP · **Estado:** Pendiente · **Alcance:** `ECO-06` · **Depende de:** HU-049

**Como** Cliente, **necesito** pagar mi pedido en línea, **para** completar la compra sin ir al supermercado.

**Criterios de aceptación**

- A definir en el Sprint Planning correspondiente

## EPIC-15 - Procesar el pedido pagado como una venta con factura y egreso de stock

**Tipo:** Epic · **Módulo:** ECO · **Estimación:** 8 SP · **Estado:** Pendiente · **Alcance:** `ECO-08` · **Depende de:** HU-050

**Como** Gerente, **necesito** que el pedido web pagado genere la misma venta, factura y movimiento de stock que una venta de mostrador, **para** no tener dos circuitos de facturación distintos ni diferencias de stock entre canales.

**Criterios de aceptación**

- A definir en el Sprint Planning correspondiente

## EPIC-16 - Seguir el estado del pedido y recibir notificaciones por correo

**Tipo:** Epic · **Módulo:** ECO · **Estimación:** 8 SP · **Estado:** Pendiente · **Alcance:** `ECO-07`, `ECO-09` · **Depende de:** EPIC-15

**Como** Cliente, **necesito** ver en qué estado está mi pedido y recibir un aviso cuando se despacha o está listo para retirar, **para** saber cuándo voy a recibir mi compra sin tener que llamar.

**Criterios de aceptación**

- A definir en el Sprint Planning correspondiente

## EPIC-17 - Administrar los pedidos web desde el panel interno

**Tipo:** Epic · **Módulo:** ECO · **Estimación:** 8 SP · **Estado:** Pendiente · **Alcance:** `ECO-10` · **Depende de:** EPIC-15

**Como** Encargado de depósito, **necesito** ver y gestionar los pedidos web pendientes de preparación, **para** preparar y despachar los pedidos online sin depender de correos ni planillas.

**Criterios de aceptación**

- A definir en el Sprint Planning correspondiente

---

## EPIC-18 - Visualizar los ingresos del periodo

**Tipo:** Epic · **Módulo:** DSH · **Estimación:** 8 SP · **Estado:** Pendiente · **Alcance:** `DSH-01` · **Depende de:** EPIC-15

**Como** Gerente, **necesito** ver cuánto se facturó y cuánto se cobró en el periodo discriminado por medio de pago canal y sucursal, **para** conocer el ingreso real del negocio sin recopilar información a mano.

**Criterios de aceptación**

- A definir en el Sprint Planning correspondiente

## EPIC-19 - Visualizar los egresos del periodo

**Tipo:** Epic · **Módulo:** DSH · **Estimación:** 5 SP · **Estado:** Pendiente · **Alcance:** `DSH-02` · **Depende de:** HU-027

**Como** Gerente, **necesito** ver el total de comprobantes de proveedor recibidos y de pagos realizados en el periodo, **para** conocer cuánto se gastó y cuánto efectivamente se pagó.

**Criterios de aceptación**

- A definir en el Sprint Planning correspondiente

## EPIC-20 - Visualizar la relación entre ingresos y egresos y su evolución

**Tipo:** Epic · **Módulo:** DSH · **Estimación:** 8 SP · **Estado:** Pendiente · **Alcance:** `DSH-03` · **Depende de:** EPIC-18, EPIC-19

**Como** Gerente, **necesito** ver la relación entre lo que entra y lo que sale y cómo evolucionó mes a mes, **para** tener una lectura rápida de si el negocio está mejorando o empeorando.

**Criterios de aceptación**

- A definir en el Sprint Planning correspondiente

## EPIC-21 - Visualizar indicadores operativos complementarios

**Tipo:** Epic · **Módulo:** DSH · **Estimación:** 5 SP · **Estado:** Pendiente · **Alcance:** `DSH-04` · **Depende de:** EPIC-20

**Como** Gerente, **necesito** ver los artículos más vendidos los artículos por debajo del mínimo y las compras por proveedor, **para** detectar oportunidades y problemas operativos desde el mismo tablero.

**Criterios de aceptación**

- A definir en el Sprint Planning correspondiente

## HU-053 - Exportar a CSV y Excel los listados de stock

**Tipo:** Historia · **Módulo:** STK · **Estimación:** 3 SP · **Estado:** Pendiente · **Alcance:** `STK-01`, `STK-04` · **Depende de:** HU-016, HU-018

**Como** Encargado de depósito, **necesito** exportar a CSV y Excel el listado de existencias y el historial de movimientos, **para** analizar la información fuera del sistema y presentarla sin copiarla a mano.

**Criterios de aceptación**

- A definir en el Sprint Planning correspondiente

> **Desglosada de `HU-016` y `HU-018` el 2026-08-22.** El PO pidió no invertir tiempo en nada que no
> hubiera pedido explícitamente, y la exportación no estaba entre lo que pidió para el arranque de
> artículos y stock. Se baja de prioridad en vez de descartarse. Vale 3 SP y no 5 porque para
> cuando llegue, el patrón de exportación ya lo construyó `HU-009`.

## EPIC-22 - Filtrar y exportar el tablero gerencial

**Tipo:** Epic · **Módulo:** DSH · **Estimación:** 5 SP · **Estado:** Pendiente · **Alcance:** `DSH-05` · **Depende de:** EPIC-20

**Como** Gerente, **necesito** filtrar todo el tablero por fecha sucursal y canal y exportar la información, **para** poder analizar la información por fuera del sistema y presentarla a la dirección.

**Criterios de aceptación**

- A definir en el Sprint Planning correspondiente

## HAB-03 - Estabilización y cierre

**Tipo:** Habilitador · **Estimación:** sin estimar, reserva de capacidad · **Estado:** Pendiente · **Depende de:** nada

No se estima en story points a propósito: no es trabajo de tamaño conocido sino una **reserva de
capacidad para el cierre del proyecto**. En qué sprint entra y cuánta capacidad se le aparta
se decide en el Sprint Planning, como con cualquier otro ítem.

**Criterios de aceptación**

- Corrección de defectos pendientes
- Reducción de deuda técnica acumulada
- Documentación final y DER definitivo
- Carga del juego de datos de demostración completo
- Incorporación de funcionalidades deseables según la capacidad remanente
