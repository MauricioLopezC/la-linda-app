# Sprint Backlog 4 - Supermercados La Linda

**Sprint 4** · 27/09/2026 al 10/10/2026 · equipo de 6 personas
**Compromiso: 97 story points** (velocidad observada del equipo: 60 a 65 SP) más 12 SP de ítems
candidatos si sobra capacidad.

> Este documento referencia los ítems por ID. Los criterios de aceptación viven en
> `product-backlog.md`; se escribieron en este planning a partir de la reunión con el PO del
> 26/09/2026 (`revisión26sep_limpio.md`).
>
> **Revisión pendiente del Sprint 3:** el PO no llegó a revisarlo el 26/09 y lo revisa el
> **viernes 02/10/2026**. Los ajustes que surjan de esa revisión entran como trabajo no planificado
> de este sprint; por eso el compromiso original quedó en 62 y no en el techo de 65.
>
> **Ampliación del alcance (PO, 28/09/2026):** con las respuestas a las preguntas del planning, el
> PO sumó al sprint la **facturación al cerrar la venta, con PDF** (`HU-042`, `HU-043` y lo que
> necesitan: `EPIC-03`, `HU-063`, `HU-007`) y el **pago con Mercado Pago y el envío a domicilio**
> (`HU-049`, `HU-050`): 35 SP más. **El compromiso de 97 SP supera en unos 32 SP la velocidad
> observada.** El equipo decidió aceptarlo así y dejar el riesgo explícito en lugar de bajar
> estimaciones: si el sprint no llega, lo primero que se corta es lo último del orden de ataque de
> cada dupla (ver "Riesgo del sobrecompromiso").

---

## Objetivo del sprint

1. **Operar una caja de punta a punta:** el cajero abre su turno declarando el fondo inicial
   billete por billete, vende escaneando artículos al precio de la lista vigente, cobra con uno o
   varios medios de pago, registra ingresos y egresos de dinero en el medio del turno y al final
   cierra con un arqueo discriminado por medio de pago (efectivo contado, cierre de lote del
   POSNET, billeteras virtuales) que le sirve para rendir. Toda venta y todo movimiento de dinero
   queda asociado al turno de caja en que ocurrió.
2. **Facturar al cerrar la venta:** al confirmar el cobro se emite la factura A o B según el
   cliente, con numeración correlativa por punto de venta, IVA discriminado y PDF para imprimir o
   descargar.
3. **Abrir la tienda online:** el cliente navega el catálogo publicado con los precios del canal
   online, se registra, arma su carrito, elige retiro en sucursal o envío a domicilio, confirma el
   pedido y lo paga con Mercado Pago (sandbox). Sin calificaciones ni reseñas.

Al cierre del sprint debe existir este recorrido:

```text
Mostrador:  Abrir caja (fondo por denominación)
              → venta escaneando artículos (precio de lista vigente)
              → cliente identificado → tipo de comprobante (A o B)
              → cobro con varios medios de pago → venta confirmada
              → factura emitida (numeración por punto de venta) + PDF → egreso de stock
              → ingresos / egresos de caja durante el turno
              → cierre con arqueo por medio de pago (esperado vs. declarado)

Online:     Catálogo público (precio canal online)
              → registro / inicio de sesión del cliente
              → carrito persistente → retiro o envío (costo de envío)
              → confirmación del pedido → pago con Mercado Pago (sandbox) → pedido pagado
```

La factura es un **comprobante interno sin CAE**: la integración con ARCA (`SPIKE-01`) sigue
afuera. El sprint tampoco convierte el pedido web pagado en venta (`EPIC-15`). Ver "Fuera del
sprint".

---

## Qué pidió el Product Owner y cómo se cubre

| # | Pedido del PO (26/09) | Se cubre con | Decisión de alcance |
|---:|---|---|---|
| 1 | El cajero abre la caja y carga el cambio inicial | `HU-057` (nueva) | Turno de caja por punto de venta con fondo inicial |
| 2 | Registrar la cantidad y denominación de billetes al abrir | `HU-057` (nueva) | Conteo por denominación; el fondo es la suma, no se tipea |
| 3 | La apertura genera un ID de caja y cada venta queda asociada a él | `HU-057`, `HU-039` (modificada) | No se puede abrir una venta sin turno de caja abierto; la venta guarda el turno |
| 4 | Escanear productos con el precio de la lista vigente | `HU-040`, `HU-041`, `HU-056` | Ya adelantado en el PR #56; se cierra con criterios aprobados |
| 5 | Cobrar en efectivo, tarjeta, POSNET y aplicaciones | `EPIC-04` (pasa a historia) | Cobro mixto con vuelto en efectivo; cada cobro es un movimiento de caja |
| 6 | Gastos, retiros y préstamos entre cajas como "movimiento de caja"; las ventas también son movimientos | `HU-058` (nueva), `HU-059` (nueva, candidata) | Entidad única `cash_movements` con tipo; la venta es un tipo más |
| 7 | Cierre y arqueo al final del turno, discriminado por medio de pago, con listado para rendir | `HU-060` (nueva) | Esperado vs. declarado por medio de pago, conteo de billetes al cerrar y número de lote del POSNET |
| 8 | E-commerce: interfaz optimizada para el cliente final, estilo Coto Digital / PedidosYa | `HU-046`, `EPIC-11`, `EPIC-13`, `HU-062` (nueva) | Catálogo público, cuenta de cliente, carrito y confirmación de pedido |
| 9 | Sin calificaciones, estrellas ni reseñas | — | Queda explícitamente fuera del alcance del producto |
| 10 | Foco en desktop; no se prueba en celular | — | Se diseña y se prueba en desktop. Los componentes ya son responsivos, pero no se invierte tiempo en mobile |
| 11 | (28/09) Facturación al cerrar una venta, con PDF | `EPIC-03`, `HU-063`, `HU-007`, `HU-042`, `HU-043` | Factura A o B emitida en la misma operación que confirma el cobro; PDF con dompdf; sin CAE |
| 12 | (28/09) Pago con Mercado Pago y envío | `HU-049`, `HU-050` | Envío con costo fijo o retiro sin costo; Checkout Pro en sandbox con notificación verificada contra la API |

---

## Decisiones del planning

1. **Caja = punto de venta; turno de caja = sesión.** Una caja física es un punto de venta
   (`HU-051`, 1 a 1). Lo que el PO llamó "ID de movimiento de caja" es el **turno de caja**
   (`cash_sessions`): se crea al abrir y es el ID al que se asocian las ventas y los movimientos.
   Un punto de venta tiene a lo sumo un turno abierto, y un usuario también.
2. **Todo movimiento de dinero es un `cash_movement`** (modelo del PO: "vayan por esa entidad"):
   `apertura`, `venta`, `ingreso` y `egreso` en este sprint; `prestamo_salida` y
   `prestamo_entrada` los agrega `HU-059`. Un gasto o un retiro es un `egreso` con motivo
   obligatorio. Los movimientos son inmutables: un error se corrige con el movimiento contrario.
3. **Una venta cobrada con dos medios genera dos movimientos de tipo `venta`**, uno por medio. Así
   el arqueo por medio de pago es una suma de movimientos sin casos especiales.
4. **El efectivo registra lo que queda en la caja, no lo que entregó el cliente:** si paga $10.000
   una venta de $8.500, el movimiento es de $8.500 y el vuelto ($1.500) se guarda solo para
   mostrarlo en el comprobante.
5. **Medios de pago con clase.** `payment_methods` suma `kind` (`efectivo`, `tarjeta`,
   `billetera_virtual`, `transferencia`, `otro`). Solo `efectivo` admite vuelto y se arquea contando
   billetes; `tarjeta` pide el total y el número de lote del POSNET al cerrar. **No se carga el
   cupón de cada cobro** (PO, 28/09): el movimiento de caja no lleva referencia.
6. **Estados de la venta:** `abierta` → `confirmada` (cuando el cobro cubre el total) o
   `descartada`. La venta confirmada es inmutable; su anulación es `HU-044`, fuera del sprint.
7. **El stock se descuenta al confirmar**, del depósito del punto de venta, con el tipo de sistema
   "Salida por Venta" que ya existe. **La falta de stock no bloquea la venta** (PO, 28/09): si el
   artículo está en la caja, se vende; la existencia puede quedar negativa y la línea de la salida
   por venta queda marcada como conflicto para regularizarla después con un movimiento manual.
   Consecuencia técnica: se quita el `CHECK (quantity >= 0)` de `stock_balances`, y la regla de no
   quedar en negativo pasa a vivir solo en `RegisterStockAdjustment` (movimientos manuales) y en
   las transferencias.
8. **Precio de lista = precio final con IVA incluido** (supuesto del PR #56, que el PO todavía no
   confirmó). Con eso el total es correcto sin discriminar IVA. La discriminación pasa a `HU-063`
   (nueva), que entra al sprint junto con la factura.
9. **La cuenta del cliente online es una tabla aparte** (`customer_accounts`, con guard propio),
   no un `User`: los usuarios son personal interno. Registrarse crea el `Customer` como consumidor
   final y su cuenta.
10. **Mirar el catálogo no requiere sesión; agregar al carrito sí.** El carrito se guarda en la
    base asociado al cliente, así que se conserva entre visitas sin fusionar carritos de invitado.
11. **El carrito no guarda precios.** Muestra el precio resuelto en el momento por
    `ResolveArticlePrice` (canal `online`, cliente logueado). El precio se fija al confirmar el
    pedido, igual que la venta de mostrador lo fija al agregar la línea.
12. **El pedido confirmado es un `web_order`, no una `sale`.** Se convierte en venta con factura y
    egreso de stock cuando esté pagado (`EPIC-15`, fuera del sprint). En este sprint el pedido nace
    `pendiente` (de pago) con retiro o envío (`HU-049`) y pasa a `pagado` con Mercado Pago
    (`HU-050`).
13. **Reestimación de lo adelantado en el PR #56.** `HU-040` y `HU-041` se construyeron fuera del
    compromiso del Sprint 3. Entran a este sprint con la estimación del **trabajo restante** (2 SP
    cada una, antes 5) y no con la original, para no inflar la velocidad con trabajo ya hecho.
    `HU-039` mantiene sus 3 SP porque atarla al turno de caja cambia su comportamiento. Con el
    mismo criterio `HU-007` entra con 1 SP (antes 2): el ABM de alícuotas ya existe.
14. **Billetes de $20.000 a $10, sin monedas** (PO, 28/09). `CashDenomination` tiene diez valores
    ($20.000, $10.000, $2.000, $1.000, $500, $200, $100, $50, $20, $10); la apertura y el cierre
    cuentan solo billetes.
15. **La factura se emite al confirmar el cobro, en la misma transacción** que los movimientos de
    caja y el egreso de stock: no existe una venta confirmada sin factura. Se guarda en `invoices`
    con los datos del cliente y los importes congelados, y el PDF se genera a pedido desde esos
    datos (no se almacena el archivo).
16. **Tipo de comprobante por condición del cliente:** La Linda es responsable inscripto, así que
    emite A a responsables inscriptos y B al resto (consumidor final, monotributo, exento). La
    asignación a monotributo y exento se confirma con el PO. No se emite factura C.
17. **Numeración por punto de venta y tipo**, correlativa y sin saltos: el número se toma con
    bloqueo del punto de venta dentro de la transacción, con `UNIQUE(point_of_sale_id, type,
    number)` como red. Sin CAE hasta `SPIKE-01`; el PDF lo aclara.
18. **IVA contenido:** el precio de lista incluye IVA. Cada línea guarda alícuota, neto (total /
    (1 + alícuota), redondeado a centavos) e IVA (total − neto), así la suma nunca se desfasa.
    El artículo vuelve a tener alícuota obligatoria.
19. **Envío con costo fijo único** en `config/ecommerce.php` (variable de entorno) hasta que el PO
    diga si lo quiere administrable. El costo y el domicilio quedan congelados en el pedido.
20. **Mercado Pago Checkout Pro en sandbox.** Al confirmar el pedido se crea la preferencia y se
    redirige; la notificación (webhook) se valida consultando el pago en la API y es idempotente
    por `mp_payment_id`. La redirección de vuelta solo muestra el estado, no acredita. La API se
    llama con el cliente HTTP de Laravel, sin sumar el SDK de Mercado Pago como dependencia.

---

## Ítems comprometidos (97 SP)

| # | ID | Título | SP | Depende de |
|---:|---|---|---:|---|
| 1 | **HU-057** | Abrir la caja con el fondo inicial desglosado por denominación | 5 | HU-051, HU-052 |
| 2 | **HU-039** | Abrir una venta de mostrador dentro del turno de caja | 3 | HU-057, HU-021 |
| 3 | **HU-040** | Incorporar artículos a la venta por código de barras o búsqueda | 2 | HU-039 |
| 4 | **HU-041** | Calcular el precio y los totales de la venta | 2 | HU-040, HU-056 |
| 5 | **EPIC-04** | Cobrar la venta con uno o varios medios de pago | 8 | HU-041, HU-052, HU-057 |
| 6 | **EPIC-06** | Descontar el stock automáticamente al confirmar la venta | 5 | EPIC-04 |
| 7 | **HU-058** | Registrar ingresos y egresos de dinero en la caja | 3 | HU-057 |
| 8 | **HU-060** | Cerrar la caja con arqueo por medio de pago | 8 | EPIC-04, HU-058 |
| 9 | **HU-046** | Publicar el catálogo en la tienda online | 5 | HU-012, HU-056 |
| 10 | **EPIC-11** | Registrarse e iniciar sesión como cliente en la tienda online | 8 | HU-021 |
| 11 | **EPIC-13** | Gestionar el carrito de compras | 8 | HU-046, EPIC-11 |
| 12 | **HU-062** | Confirmar el pedido desde el carrito | 5 | EPIC-13 |
| 13 | **HU-007** | Administrar las alícuotas de IVA (reestimada, antes 2) | 1 | — |
| 14 | **HU-063** | Discriminar el IVA por alícuota en la venta | 3 | HU-007, HU-041 |
| 15 | **EPIC-03** | Identificar al cliente y determinar el tipo de comprobante | 5 | HU-039, HU-021 |
| 16 | **HU-042** | Emitir la factura con numeración correlativa por punto de venta | 8 | EPIC-03, EPIC-04, HU-063 |
| 17 | **HU-043** | Imprimir y descargar la factura en PDF | 5 | HU-042 |
| 18 | **HU-049** | Elegir la modalidad de entrega y calcular el costo de envío | 5 | HU-062 |
| 19 | **HU-050** | Pagar el pedido con Mercado Pago en sandbox | 8 | HU-049 |
| | | **Total comprometido** | **97** | |

Los ítems 13 a 19 los sumó el PO el 28/09 (ver la nota del encabezado).

### Candidatos si sobra capacidad (en este orden)

| # | ID | Título | SP | Por qué queda afuera del compromiso |
|---:|---|---|---:|---|
| 1 | **HU-032** | Registrar la imagen de un artículo | 3 | La tienda funciona con un placeholder; es lo primero que se suma porque mejora mucho la demo |
| 2 | **HU-061** | Consultar los turnos de caja y su rendición | 3 | `HU-060` ya muestra la rendición del turno que se cierra; esto es el histórico para el Gerente |
| 3 | **HU-059** | Registrar un préstamo de dinero entre cajas | 3 | El PO lo mencionó como algo "probable"; un egreso y un ingreso manuales lo cubren mientras tanto |
| 4 | **HU-048** | Mostrar la disponibilidad online e impedir la compra sin stock | 3 | Sin esto se puede pedir algo sin stock; se resuelve al preparar el pedido |

---

## Orden de ataque y paralelización (3 duplas)

```text
┌──────────────────────────────────────────────────────────────────────────┐
│ DUPLA A: Caja e IVA (30 SP)                                              │
│ • 1. HU-057  Turno de caja + conteo + cash_movements   ← bloquea a B     │
│ • 2. HU-046  Catálogo público de la tienda             ← bloquea a C     │
│ • 3. HU-007 + HU-063  Alícuota en el artículo + IVA por línea ← a HU-042 │
│ • 4. HU-058  Ingresos y egresos de caja                                  │
│ • 5. HU-060  Cierre y arqueo por medio de pago                           │
│ • 6. HU-043  PDF de la factura (cuando entra HU-042)                     │
└──────────────────────────────────────────────────────────────────────────┘
┌──────────────────────────────────────────────────────────────────────────┐
│ DUPLA B: Venta de mostrador y factura (33 SP)                            │
│ • 1. HU-040 + HU-041  Cerrar criterios sobre lo del PR #56 (sin esperar) │
│ • 2. HU-039  Atar la venta al turno de caja (cuando entra HU-057)        │
│ • 3. EPIC-03 Cliente de la venta y tipo de comprobante                   │
│ • 4. EPIC-04 Cobro mixto → venta confirmada                              │
│ • 5. EPIC-06 Egreso de stock al confirmar (sin bloquear por faltante)    │
│ • 6. HU-042  Factura emitida al confirmar                                │
└──────────────────────────────────────────────────────────────────────────┘
┌──────────────────────────────────────────────────────────────────────────┐
│ DUPLA C: Tienda online (34 SP)                                           │
│ • 1. EPIC-11 Cuenta de cliente, guard y layout público                   │
│ • 2. EPIC-13 Carrito (cuando entra HU-046)                               │
│ • 3. HU-062  Confirmación del pedido                                     │
│ • 4. HU-049  Retiro o envío a domicilio                                  │
│ • 5. HU-050  Pago con Mercado Pago (sandbox)                             │
└──────────────────────────────────────────────────────────────────────────┘
```

**Contrato del día 1:** la migración de `cash_movements` (tipos, signo, `sale_id`,
`payment_method_id`) la mergea la Dupla A dentro de `HU-057` antes que nada, porque la usan
`EPIC-04` (B) y `HU-058`/`HU-060` (A). Lo mismo con el layout público de la tienda: lo arma C en
`EPIC-11` y A lo reutiliza en `HU-046`. La tabla `invoices` y la firma de `IssueInvoice` las
acuerdan B y A antes de que A empiece `HU-043`, para que el PDF se arme en paralelo con datos de
factory.

### Riesgo del sobrecompromiso

Con 97 SP contra una velocidad de 60 a 65, cada dupla carga entre 30 y 34 SP (antes 20 a 21). Si a
mitad de sprint (daily del lunes 05/10) el avance no alcanza, se recorta desde el final de cada
columna y se avisa al PO apenas se detecte: primero `HU-050`
(el pedido queda pendiente de pago), después `HU-043` (la factura existe pero sin PDF) y
`HU-049` (solo retiro). La caja y la factura sin PDF son lo último que se corta.

---

## Desglose en tareas

Stack: Laravel + Inertia v3 + React. Caja y venta en el módulo `Sales`; tienda en `Ecommerce`.
Lógica en `app/Actions/{Module}`, respuestas en `app/Data/{Module}`, validación en Form Requests.

### HU-057 — Abrir la caja con el fondo inicial (5 SP)
- [ ] Enum `CashDenomination` con los billetes de $20.000 a $10 (diez valores, sin monedas).
- [ ] Migraciones `cash_sessions`, `cash_counts` y `cash_movements` (ver DER).
- [ ] Índices únicos parciales: un turno `abierto` por punto de venta y uno por usuario.
- [ ] Action `OpenCashSession`: valida punto de venta activo y sin turno abierto, guarda el conteo,
      calcula el fondo como suma y registra el movimiento `apertura` en efectivo.
- [ ] Pantalla "Abrir caja": selector de punto de venta y grilla denominación × cantidad con
      subtotal por fila y total en vivo. Se admite fondo cero.
- [ ] Indicador del turno abierto (caja, cajero, hora de apertura) en el layout de ventas.
- [ ] Tests: apertura, doble apertura rechazada (por caja y por usuario), fondo = suma del conteo.

### HU-039 — Abrir una venta dentro del turno de caja (3 SP)
- [ ] Columna `sales.cash_session_id` (obligatoria para `mostrador`, nula para `online`).
- [ ] `OpenSale` toma el punto de venta del turno abierto del usuario; sin turno, rechaza y la
      pantalla ofrece "Abrir caja". Se quita el diálogo de elegir punto de venta.
- [ ] No se puede cerrar el turno con ventas `abiertas` (lo valida `HU-060`).
- [ ] Ajustar `OpenSaleTest` y los tests que crean ventas sin turno.

### HU-040 / HU-041 — Carga de artículos y totales (2 + 2 SP)
- [ ] Revisar lo del PR #56 contra los criterios aprobados y cerrar las diferencias.
- [ ] Solo una venta `abierta` de un turno `abierto` acepta cambios.
- [ ] Tests existentes en verde; agregar los casos nuevos de los criterios.

### EPIC-04 — Cobrar la venta (8 SP)
- [ ] Columna `payment_methods.kind` con migración de datos (el "Efectivo" sembrado pasa a
      `efectivo`) y selector en el ABM de medios de pago.
- [ ] Action `ConfirmSalePayment`: recibe renglones medio + importe (+ recibido en efectivo),
      valida que la suma cubra exactamente el total (el excedente solo en efectivo, como vuelto),
      crea un `cash_movement` `venta` por renglón, pasa la venta a `confirmada` con `confirmed_at`
      y dispara `EPIC-06` y `HU-042`, todo en una transacción.
- [ ] Panel de cobro en `show.tsx`: renglones de medios, saldo restante en vivo, vuelto calculado,
      atajo "todo en efectivo". Sin referencia por renglón (el POSNET se rinde por lote).
- [ ] Venta confirmada en modo lectura con su detalle de cobro.
- [ ] Tests: cobro simple, mixto, con vuelto, suma insuficiente, medio inactivo, doble confirmación.

### EPIC-06 — Descontar el stock al confirmar (5 SP)
- [ ] Action `CreateStockMovementFromSale` (patrón de `CreateStockMovementFromVoucher`): movimiento
      "Salida por Venta" del depósito del punto de venta, vinculado a la venta.
- [ ] Migración que quita el `CHECK (quantity >= 0)` de `stock_balances` (en SQLite implica
      reconstruir la tabla) y actualizar la regla de `.ai/rules/inventory.md`.
- [ ] La venta no se bloquea por faltante: la línea que deja la existencia negativa se marca como
      conflicto (`system_quantity` ya guarda la existencia previa). Badge de conflicto en el
      historial (`HU-018`) y existencias negativas resaltadas en `HU-016`.
- [ ] `RegisterStockAdjustment` y las transferencias siguen rechazando saldos negativos (ahora sin
      la red del CHECK): test que lo cubra.
- [ ] Navegación venta ↔ movimiento desde el historial de stock (`HU-018`).
- [ ] Tests: egreso correcto, generación única, venta con existencia cero confirmada y marcada
      como conflicto, ajuste manual que deja negativo sigue rechazado.

> **Nota — cómo se resuelve el conflicto.** El conflicto no indica una venta mal hecha: indica que
> la existencia previa estaba mal (recepción sin cargar o en otro depósito, transferencia sin
> registrar, otro artículo escaneado en una venta anterior, ajuste mal cargado). La venta no se
> toca; se corrige el origen.
>
> - **Detección sin columnas nuevas:** la línea está en conflicto si
>   `system_quantity + quantity < 0`, calculado sobre datos inmutables.
> - **La resolución la decide una persona, no el sistema.** El encargado revisa el conflicto en
>   `HU-018` y registra el movimiento que corresponda: si faltaba una recepción, carga el
>   comprobante del proveedor (el `purchase_entry` se genera solo); si no encuentra la causa, hace
>   un ajuste manual positivo con `notes`. No se genera un ajuste automático al confirmar la venta,
>   porque taparía el error de origen.
> - **Lo que sí puede automatizarse es la sugerencia:** si una entrada posterior del mismo artículo
>   en ese depósito vuelve a dejar la existencia positiva, mostrar el conflicto como "posiblemente
>   resuelto por el movimiento X", sin cerrarlo.
> - **Cierre explícito (fuera de este sprint):** con la existencia calculada, una compra posterior
>   "arregla" el número sin que nadie revise. Para que no se pierda, registrar `resolved_at`,
>   `resolved_by` y el movimiento de resolución (en `stock_movement_items` o en una tabla
>   `stock_conflicts`) y filtrar los conflictos abiertos en `HU-018`. Candidato a historia del
>   próximo sprint.
> - **Concurrencia:** tomar el saldo con `lockForUpdate()` dentro de la transacción de la venta,
>   para que dos cajas que venden el mismo artículo no guarden un `system_quantity`
>   desactualizado.

### HU-058 — Ingresos y egresos de caja (3 SP)
- [ ] Action `RegisterCashMovement` (`ingreso` / `egreso`, efectivo por defecto, motivo obligatorio).
- [ ] Un egreso no puede dejar el efectivo esperado del turno por debajo de cero.
- [ ] Diálogo desde la pantalla del turno y listado de movimientos del turno con saldo esperado.
- [ ] Tests: registro, motivo obligatorio, egreso mayor al disponible, turno cerrado.

### HU-060 — Cerrar la caja con arqueo (8 SP)
- [ ] Migración `cash_session_closure_lines`.
- [ ] Query `GetCashSessionExpectedTotals`: esperado por medio de pago a partir de los movimientos.
- [ ] Action `CloseCashSession`: conteo de efectivo por denominación, importe declarado por cada
      otro medio con movimientos (total y número de lote del POSNET para tarjetas), diferencias, observación
      obligatoria si hay diferencia, turno `cerrado` e inmutable. Rechaza si hay ventas abiertas.
- [ ] Pantalla de cierre: grilla de billetes, declarados por medio, esperado vs. declarado con
      diferencia resaltada (sobrante/faltante).
- [ ] Resumen de rendición del turno cerrado (vista imprimible): apertura, ventas, ingresos,
      egresos, esperado, declarado y diferencia por medio de pago.
- [ ] Tests: cierre cuadrado, con faltante y con sobrante, ventas abiertas bloquean, turno cerrado
      no acepta movimientos ni ventas.

### HU-007 / HU-063 — Alícuotas e IVA por línea (1 + 3 SP)
- [ ] Migración `articles.vat_rate_id` (FK, obligatoria para activos) con migración de datos que
      asigna el 21% a los artículos existentes; seeders y factories actualizados.
- [ ] Selector de alícuota (solo activas) de vuelta en el ABM de artículos.
- [ ] `VatRate::isInUse()` vuelve a mirar artículos; no se desactiva una alícuota en uso.
- [ ] Columnas `sale_items.vat_rate_id`, `vat_rate`, `net_amount`, `vat_amount`, calculadas al
      agregar la línea (neto = total / (1 + alícuota) redondeado; IVA = total − neto).
- [ ] `AddArticleToSale` rechaza artículos sin alícuota. Resumen de neto e IVA por alícuota en la
      venta.
- [ ] Tests: desglose 21% y 10,5%, neto + IVA = total, alícuota congelada, alícuota en uso.

### EPIC-03 — Cliente y tipo de comprobante (5 SP)
- [ ] Enum `InvoiceType` (`A`, `B`) y método que lo deriva de `CustomerTaxCondition`.
- [ ] Revisar el cambio de cliente del PR #56: búsqueda por nombre o documento, solo activos, solo
      venta abierta.
- [ ] Validar que un responsable inscripto tenga CUIT antes de permitir la factura A.
- [ ] Badge del tipo de comprobante en la venta, recalculado al cambiar el cliente.
- [ ] Tests: tipo por condición fiscal, RI sin CUIT, cliente inactivo, venta confirmada.

### HU-042 — Emitir la factura al confirmar (8 SP)
- [ ] Migraciones `invoices` e `invoice_items` (ver DER).
- [ ] Action `IssueInvoice`, invocada por `ConfirmSalePayment` dentro de la misma transacción:
      bloquea el punto de venta, toma el siguiente número por tipo, congela datos del cliente,
      detalle, neto e IVA por alícuota y total.
- [ ] Datos del emisor (razón social, CUIT, domicilio, condición IVA, inicio de actividades) en
      `config/invoicing.php`.
- [ ] Detalle de la venta confirmada muestra su factura (tipo y número).
- [ ] Tests: numeración correlativa por punto de venta y tipo, numeraciones independientes entre
      A y B y entre cajas, una factura por venta, falla de emisión revierte la confirmación, datos
      del cliente congelados.

### HU-043 — PDF de la factura (5 SP)
- [ ] Vista Blade del comprobante (patrón del PDF de la orden de pago) con letra, código, emisor,
      cliente, detalle, IVA discriminado en la A, total, medios de pago y vuelto, y la leyenda
      "comprobante sin CAE".
- [ ] Ruta de descarga y de impresión (inline) protegida por usuario logueado.
- [ ] Al confirmar la venta, diálogo "Imprimir / Descargar factura"; botón en el detalle de la venta.
- [ ] Tests: el PDF responde para una factura emitida, 404 para una venta sin factura, contenido
      con número y total.

### HU-046 — Catálogo en la tienda online (5 SP)
- [ ] Rutas públicas `/tienda` y layout propio de la tienda (lo arma `EPIC-11`).
- [ ] Query de artículos `activo` + `is_online_publishable` con precio resuelto para el canal
      `online` (cascada de `HU-056`, con la lista particular si el cliente está logueado). Los que
      no tienen precio no se muestran.
- [ ] Grilla de tarjetas (imagen o placeholder, descripción, marca, precio), navegación por
      categoría y búsqueda simple por descripción, paginada.
- [ ] Tests: solo publicables con precio, precio del canal online, precio particular del cliente.

### EPIC-11 — Cuenta de cliente en la tienda (8 SP)
- [ ] Tabla `customer_accounts`, guard y provider `customer` en `config/auth.php`.
- [ ] Registro: crea `Customer` (persona física, consumidor final) + cuenta en una transacción.
- [ ] Login, logout y "Mi cuenta" (ver y editar datos de contacto). Sin recuperación de contraseña.
- [ ] Rate limiting del login como el de Fortify.
- [ ] Tests: registro, email duplicado, login, que el guard de cliente no entre al panel interno y
      que un usuario interno no quede logueado como cliente.

### EPIC-13 — Carrito (8 SP)
- [ ] Tabla `cart_items` (cliente, artículo, cantidad) con `UNIQUE(customer_id, article_id)`.
- [ ] Actions `AddArticleToCart`, `UpdateCartItemQuantity`, `RemoveCartItem`.
- [ ] Agregar desde el catálogo (sin sesión redirige al login y vuelve), contador en el header,
      página del carrito con cantidades editables, precio vigente, subtotales y total.
- [ ] Un artículo que dejó de ser publicable o quedó sin precio se marca como no disponible y no
      suma al total.
- [ ] Tests: agregar, repetir suma cantidad, cantidades decimales solo en pesables, persistencia
      entre sesiones, artículo no disponible.

### HU-062 — Confirmar el pedido (5 SP)
- [ ] Migraciones `web_orders` y `web_order_items` con número correlativo.
- [ ] Action `PlaceWebOrder`: re-resuelve precios, rechaza si algún artículo no está disponible,
      crea el pedido con la foto de precios, vacía el carrito, todo en una transacción.
- [ ] Checkout: resumen, sucursal de retiro, observaciones, confirmar. Pantalla de pedido
      confirmado con su número y "Mis pedidos" (listado y detalle de solo lectura).
- [ ] Tests: confirmación, carrito vacío, artículo sin precio, precio congelado ante un cambio de
      lista posterior, un cliente no ve pedidos de otro.

### HU-049 — Retiro o envío a domicilio (5 SP)
- [ ] Columnas en `web_orders`: `delivery_method`, `shipping_address`, `shipping_notes`,
      `shipping_cost`, `items_amount`; `pickup_branch_id` pasa a nullable (ver DER).
- [ ] Costo fijo en `config/ecommerce.php` (`ECOMMERCE_SHIPPING_COST`).
- [ ] Checkout: selector retiro / envío, domicilio precargado del cliente y editable, resumen con
      subtotal, envío y total.
- [ ] `PlaceWebOrder` valida la modalidad y congela costo y domicilio.
- [ ] Tests: retiro sin costo, envío con costo, envío sin domicilio, sucursal inactiva, costo
      congelado ante un cambio de configuración.

### HU-050 — Pago con Mercado Pago (8 SP)
- [ ] Credenciales sandbox en `.env` / `config/services.php`; nunca en el frontend.
- [ ] Action `CreateMercadoPagoPreference` (cliente HTTP de Laravel): ítems del pedido + envío,
      `external_reference` = id del pedido, URLs de retorno y de notificación.
- [ ] Webhook público (sin CSRF) que consulta el pago en la API, verifica importe y referencia, y
      ejecuta `MarkWebOrderAsPaid` de forma idempotente.
- [ ] Páginas de retorno (aprobado / pendiente / rechazado) que solo muestran estado; reintentar
      desde "Mis pedidos".
- [ ] Tests con `Http::fake()`: pago aprobado, rechazado, notificación duplicada, importe que no
      coincide, pedido de otro cliente, pedido ya pagado.

---

## Diseño de datos (DER proyectado Sprint 4)

```mermaid
erDiagram
    POINTS_OF_SALE ||--o{ CASH_SESSIONS : "abre turnos"
    USERS ||--o{ CASH_SESSIONS : "atiende"
    CASH_SESSIONS ||--o{ CASH_COUNTS : "cuenta billetes"
    CASH_SESSIONS ||--o{ CASH_MOVEMENTS : "registra"
    CASH_SESSIONS ||--o{ CASH_SESSION_CLOSURE_LINES : "arquea"
    CASH_SESSIONS ||--o{ SALES : "agrupa"
    SALES ||--o{ CASH_MOVEMENTS : "se cobra con"
    PAYMENT_METHODS ||--o{ CASH_MOVEMENTS : "medio"
    PAYMENT_METHODS ||--o{ CASH_SESSION_CLOSURE_LINES : "medio"
    SALES ||--o| STOCK_MOVEMENTS : "origina egreso"
    SALES ||--|| INVOICES : "se factura con"
    CASH_SESSIONS ||--o{ INVOICES : "agrupa"
    POINTS_OF_SALE ||--o{ INVOICES : "numera"
    INVOICES ||--|{ INVOICE_ITEMS : "detalla"
    VAT_RATES ||--o{ ARTICLES : "alícuota"
    VAT_RATES ||--o{ SALE_ITEMS : "alícuota congelada"

    CUSTOMERS ||--o| CUSTOMER_ACCOUNTS : "accede a la tienda con"
    CUSTOMERS ||--o{ CART_ITEMS : "arma"
    ARTICLES ||--o{ CART_ITEMS : "en carrito"
    CUSTOMERS ||--o{ WEB_ORDERS : "confirma"
    BRANCHES |o--o{ WEB_ORDERS : "retiro en"
    WEB_ORDERS ||--|{ WEB_ORDER_ITEMS : "contiene"
    PRICE_LISTS ||--o{ WEB_ORDER_ITEMS : "origen del precio"
```

### Tablas nuevas

#### `cash_sessions` — HU-057 / HU-060
| Columna | Tipo | Reglas |
|---|---|---|
| id | bigint PK | el "ID de caja" al que se asocian ventas y movimientos |
| point_of_sale_id | FK → points_of_sale | obligatorio, `restrictOnDelete` |
| user_id | FK → users | cajero que abrió el turno |
| status | varchar(20) | CHECK `in ('abierta', 'cerrada')` |
| opened_at | timestamp | |
| opening_amount | decimal(12,2) | CHECK `>= 0`; suma del conteo de apertura |
| closed_at | timestamp | nullable |
| closing_notes | text | nullable; obligatoria si el cierre tiene diferencias |
| created_at / updated_at | timestamp | |

*Restricciones:* índice único parcial `(point_of_sale_id) WHERE status = 'abierta'` y
`(user_id) WHERE status = 'abierta'`.

#### `cash_counts` — HU-057 / HU-060
| Columna | Tipo | Reglas |
|---|---|---|
| id | bigint PK | |
| cash_session_id | FK → cash_sessions | `cascadeOnDelete` |
| moment | varchar(20) | CHECK `in ('apertura', 'cierre')` |
| denomination | decimal(12,2) | valor de `CashDenomination` |
| quantity | integer | CHECK `>= 0` |

*Restricción:* `UNIQUE(cash_session_id, moment, denomination)`.

#### `cash_movements` — HU-057 / EPIC-04 / HU-058 (HU-059 agrega los tipos de préstamo)
| Columna | Tipo | Reglas |
|---|---|---|
| id | bigint PK | |
| cash_session_id | FK → cash_sessions | obligatorio |
| type | varchar(20) | CHECK `in ('apertura', 'venta', 'ingreso', 'egreso')`; el signo sale del tipo |
| payment_method_id | FK → payment_methods | obligatorio; efectivo para apertura, ingreso y egreso |
| amount | decimal(12,2) | CHECK `> 0` |
| sale_id | FK → sales | nullable; obligatorio si `type = 'venta'` |
| tendered_amount | decimal(12,2) | nullable; lo que entregó el cliente en efectivo (para el vuelto) |
| reason | varchar(255) | nullable; obligatorio para `ingreso` y `egreso` |
| user_id | FK → users | quien lo registró |
| created_at | timestamp | inmutable, sin `updated_at` |

#### `cash_session_closure_lines` — HU-060
| Columna | Tipo | Reglas |
|---|---|---|
| id | bigint PK | |
| cash_session_id | FK → cash_sessions | obligatorio |
| payment_method_id | FK → payment_methods | obligatorio |
| expected_amount | decimal(12,2) | calculado de los movimientos |
| declared_amount | decimal(12,2) | CHECK `>= 0`; en efectivo es la suma del conteo de cierre |
| difference | decimal(12,2) | declarado − esperado (positivo = sobrante) |
| batch_reference | varchar(50) | nullable; nro. de lote del POSNET (obligatorio si el medio es `tarjeta`) |

*Restricción:* `UNIQUE(cash_session_id, payment_method_id)`.

#### `invoices` / `invoice_items` — HU-042 / HU-043
| Columna (`invoices`) | Tipo | Reglas |
|---|---|---|
| id | bigint PK | |
| sale_id | FK → sales | único; `restrictOnDelete` |
| cash_session_id | FK → cash_sessions | turno en que se emitió |
| point_of_sale_id | FK → points_of_sale | |
| type | varchar(1) | CHECK `in ('A', 'B')` |
| number | unsignedInteger | correlativo por punto de venta y tipo |
| issued_at | timestamp | |
| customer_id | FK → customers | |
| customer_name | varchar(255) | congelado al emitir |
| customer_tax_condition | varchar(50) | congelado |
| customer_id_type / customer_id_number | varchar(20) | congelados; nullable para consumidor final |
| customer_address | varchar(255) | nullable; congelado |
| net_amount | decimal(12,2) | CHECK `>= 0` |
| vat_amount | decimal(12,2) | CHECK `>= 0` |
| total_amount | decimal(12,2) | CHECK `> 0`; `= net_amount + vat_amount` |
| user_id | FK → users | quien confirmó la venta |
| created_at | timestamp | inmutable, sin `updated_at` |

*Restricción:* `UNIQUE(point_of_sale_id, type, number)`. El número se toma con `lockForUpdate`
sobre el punto de venta dentro de la transacción de `ConfirmSalePayment`.

| Columna (`invoice_items`) | Tipo | Reglas |
|---|---|---|
| id | bigint PK | |
| invoice_id | FK → invoices | `cascadeOnDelete` |
| article_id | FK → articles | |
| description | varchar(255) | congelada al emitir |
| quantity | decimal(12,3) | CHECK `> 0` |
| unit_price | decimal(12,2) | con IVA incluido |
| vat_rate | decimal(5,2) | porcentaje congelado |
| net_amount / vat_amount / line_total | decimal(12,2) | `line_total = net_amount + vat_amount` |

El desglose por alícuota del PDF es un `GROUP BY vat_rate` sobre `invoice_items`; no se guarda
aparte.

#### `customer_accounts` — EPIC-11
| Columna | Tipo | Reglas |
|---|---|---|
| id | bigint PK | |
| customer_id | FK → customers | único |
| email | varchar | único; es el usuario de la tienda |
| password | varchar | hash |
| remember_token | varchar | nullable |
| created_at / updated_at | timestamp | |

#### `cart_items` — EPIC-13
| Columna | Tipo | Reglas |
|---|---|---|
| id | bigint PK | |
| customer_id | FK → customers | `cascadeOnDelete` |
| article_id | FK → articles | |
| quantity | decimal(12,3) | CHECK `> 0`; entera si la unidad no admite decimales |
| created_at / updated_at | timestamp | |

*Restricción:* `UNIQUE(customer_id, article_id)`. Sin precio: se resuelve al mostrar.

#### `web_orders` / `web_order_items` — HU-062 / HU-049 / HU-050
| Columna (`web_orders`) | Tipo | Reglas |
|---|---|---|
| id | bigint PK | |
| number | unsignedInteger | único, correlativo, no editable |
| customer_id | FK → customers | |
| status | varchar(20) | CHECK `in ('pendiente', 'pagado')` en este sprint; `EPIC-16`/`EPIC-17` agregan el resto |
| delivery_method | varchar(20) | CHECK `in ('retiro', 'envio')` — HU-049 |
| pickup_branch_id | FK → branches | nullable; obligatorio si `delivery_method = 'retiro'` |
| shipping_address | varchar(255) | nullable; obligatorio si `delivery_method = 'envio'` |
| shipping_notes | varchar(255) | nullable |
| items_amount | decimal(12,2) | CHECK `> 0`; suma de las líneas |
| shipping_cost | decimal(12,2) | CHECK `>= 0`; 0 si es retiro; congelado |
| total_amount | decimal(12,2) | CHECK `> 0`; `items_amount + shipping_cost` |
| mp_preference_id | varchar(100) | nullable — HU-050 |
| mp_payment_id | varchar(100) | nullable, único; idempotencia del webhook |
| paid_at | timestamp | nullable; obligatorio si `status = 'pagado'` |
| notes | text | nullable |
| placed_at | timestamp | |

| Columna (`web_order_items`) | Tipo | Reglas |
|---|---|---|
| id | bigint PK | |
| web_order_id | FK → web_orders | `cascadeOnDelete` |
| article_id | FK → articles | |
| quantity | decimal(12,3) | CHECK `> 0` |
| unit_price | decimal(12,2) | CHECK `> 0`; foto del precio al confirmar |
| price_list_id | FK → price_lists | lista de origen |
| line_total | decimal(12,2) | CHECK `> 0` |

*Restricción:* `UNIQUE(web_order_id, article_id)`.

### Tablas modificadas

- **`sales`:** `cash_session_id` (FK nullable; CHECK `channel = 'online' OR cash_session_id IS NOT
  NULL`), `status` suma `confirmada`, `confirmed_at` nullable.
- **`payment_methods`:** `kind` varchar(20) CHECK `in ('efectivo', 'tarjeta', 'billetera_virtual',
  'transferencia', 'otro')`.
- **`stock_movements`:** `sale_id` FK nullable, mismo patrón que `supplier_voucher_id`.
- **`stock_balances`:** se quita `CHECK (quantity >= 0)` (EPIC-06): la venta puede dejar la
  existencia negativa. La regla sigue en las Actions de movimientos manuales y transferencias.
- **`articles`:** `vat_rate_id` FK → vat_rates, obligatoria para artículos activos (HU-063).
- **`sale_items`:** `vat_rate_id`, `vat_rate` decimal(5,2), `net_amount` y `vat_amount`
  decimal(12,2), congelados al agregar la línea (HU-063).

---

## Demostración de cierre del Sprint 4

1. **Apertura:** abrir la Caja 1 declarando 10 billetes de $10.000 y 5 de $2.000 → fondo $110.000.
   Intentar abrirla de nuevo con otro usuario: rechazado.
2. **Venta:** escanear tres artículos (uno repetido, uno pesable, dos alícuotas distintas) y ver el
   precio de la lista vigente con su badge y el IVA discriminado. Cobrar $8.500 con $5.000 de
   débito y $10.000 en efectivo: vuelto $6.500, venta confirmada, factura B 0001-00000001
   emitida y stock descontado del depósito de la caja.
3. **Factura:** descargar el PDF de esa factura. Hacer otra venta a un cliente responsable
   inscripto: sale factura A 0001-00000001 con el IVA discriminado.
4. **Sin stock:** vender un artículo con existencia cero: la venta se confirma y la salida por
   venta queda marcada como conflicto en el historial de stock.
5. **Venta sin turno:** con otro usuario sin caja abierta, intentar vender: el sistema pide abrir
   la caja.
6. **Movimientos:** registrar un egreso de $3.000 ("compra de artículos de limpieza") y un ingreso.
7. **Cierre:** contar billetes con un faltante de $500, declarar el total y el lote del POSNET y cerrar con
   observación. Mostrar el resumen de rendición por medio de pago y que el turno ya no acepta
   ventas.
8. **Tienda:** sin sesión, recorrer el catálogo por categoría y buscar un artículo; ver el precio
   del canal online (distinto al de mostrador).
9. **Cliente:** registrarse, agregar tres artículos al carrito, salir y volver a entrar: el
   carrito sigue ahí.
10. **Pedido:** confirmar el pedido con envío a domicilio (suma el costo de envío) y pagarlo con
    la tarjeta de prueba rechazada de Mercado Pago: queda pendiente. Reintentar con la aprobada:
    en "Mis pedidos" figura pagado, con su número y precios congelados.

---

## Preguntas al PO

### Respondidas (28/09)

| # | Pregunta | Respuesta del PO | Cambio que produjo |
|---:|---|---|---|
| 3 | ¿Qué denominaciones se cuentan? ¿Las monedas se cuentan una por una o como un importe? | Billetes de $20.000 a $10; no se aceptan monedas | `CashDenomination` con diez billetes y sin monedas (`HU-057`, `HU-060`) |
| 5 | ¿Del POSNET alcanza con el total y el número de lote, o hay que cargar cada cupón? | Total y lote al cerrar | Se saca la referencia por cobro de `EPIC-04` y de `cash_movements` |
| 6 | Si el sistema dice que no hay stock pero el artículo está en la caja, ¿se bloquea la venta? | No se bloquea; el conflicto se resuelve en la salida por venta | `EPIC-06` confirma con existencia negativa y marca la línea; se quita el CHECK de `stock_balances` |
| 7 | En el e-commerce, ¿el pago online (Mercado Pago) y el envío a domicilio son del Sprint 5? | No: agregar pago con Mercado Pago y envío a este sprint | Entran `HU-049` y `HU-050` |
| — | (Pedido nuevo) | Agregar facturación al cerrar una venta, con PDF | Entran `EPIC-03`, `HU-063`, `HU-007`, `HU-042` y `HU-043` |

### Abiertas (confirmar el viernes 02/10)

| # | Pregunta | Supuesto con el que se avanza | Afecta a |
|---:|---|---|---|
| 1 | ¿El precio de lista es final con IVA incluido? | Sí | HU-041, HU-063, HU-042 |
| 2 | ¿Una caja es un punto de venta? ¿Un cajero puede atender dos cajas a la vez? | Caja = punto de venta; un turno abierto por caja y por usuario | HU-057 |
| 4 | ¿Se puede cerrar la caja con diferencia? ¿Quién la autoriza? | Sí, con observación obligatoria; sin autorización hasta que haya roles (`HU-004`) | HU-060 |
| 8 | ¿El cliente puede comprar sin registrarse (como invitado)? | No, se registra | EPIC-11 |
| 9 | Sin monedas, ¿cómo se cobra en efectivo un total con centavos o que no es múltiplo de $10? | Se cobra el total exacto y el vuelto se calcula exacto; lo que no se puede dar en billetes aparece como diferencia en el arqueo | EPIC-04, HU-060 |
| 10 | ¿A un monotributista o a un exento se le emite factura B? | Sí: A solo a responsables inscriptos, B al resto | EPIC-03, HU-042 |
| 11 | ¿El costo de envío es un valor fijo único o varía (por zona, por monto)? ¿Hay envío gratis desde cierto monto? | Fijo único, configurable por entorno, sin envío gratis | HU-049 |
| 12 | ¿Todo pedido web se paga online, o el que retira en sucursal puede pagar al retirar? | Todo pedido se paga con Mercado Pago | HU-050 |

---

## Fuera del sprint

| Ítem | Motivo |
|---|---|
| `SPIKE-01` | Integración con ARCA (CAE y QR): la factura del sprint es un comprobante interno |
| `EPIC-08` | Consulta de comprobantes emitidos: la factura se ve desde el detalle de su venta |
| `HU-044`, `HU-045` | Anulación y devolución se construyen sobre la venta confirmada de este sprint |
| `HU-047` | La búsqueda simple y la navegación por categoría entran en `HU-046`; filtros avanzados y orden quedan para después |
| `EPIC-15`, `EPIC-16`, `EPIC-17` | Conversión del pedido pagado en venta con factura y egreso de stock, seguimiento con notificaciones y panel interno de pedidos |
| Calificaciones y reseñas de productos | Excluidas por el PO (26/09): "no quiero eso" |
| Pruebas y ajustes en mobile | El PO corrige en desktop (26/09) |

---

## Definition of Done

- [ ] Criterios de aceptación verificados contra `product-backlog.md`.
- [ ] Validaciones en el servidor mediante Form Requests.
- [ ] Reglas de negocio y mutaciones en `app/Actions/{Module}`.
- [ ] Respuestas tipadas hacia el frontend en `app/Data/{Module}` y `npm run types:generate`.
- [ ] Tests de Pest en verde (`php artisan test --compact`).
- [ ] PHPStan sin errores (`composer run types:check`).
- [ ] Pint, ESLint, Prettier y tsc en verde (`composer run ci:check` cubre todo).
- [ ] Migraciones y DER de este documento sincronizados.
- [ ] Pantallas verificadas en desktop.
