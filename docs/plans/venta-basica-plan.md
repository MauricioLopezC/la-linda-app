# Venta básica de mostrador — HU-039 + HU-040 + HU-041 (sin IVA) + recorte de EPIC-03

Este plan arma el mínimo del circuito de ventas necesario para **ver funcionando HU-056**
(`ResolveArticlePrice`, PR #53) desde una pantalla real: abrir una venta, cargarle artículos y
ver para cada línea el precio resuelto, la lista de la que salió y el total de la venta.

Queda deliberadamente fuera todo lo que ocurre *después* de armar la venta: cobro (EPIC-04),
factura y numeración (HU-042, HU-043), descuento de stock (EPIC-06), anulación y devolución
(HU-044, HU-045) y consulta de comprobantes (EPIC-08).

## Alcance por historia

| Historia | Qué entra | Qué queda afuera |
|---|---|---|
| **HU-039** Abrir una venta de mostrador | Abrir la venta con punto de venta, canal, usuario, cliente (Consumidor Final por defecto) y fecha y hora. Listado de ventas y descarte de una venta abierta | — |
| **HU-040** Incorporar artículos | Agregar líneas leyendo el código de barras (sin pantalla intermedia) o buscando por código interno y descripción. Editar la cantidad y quitar líneas | Control de stock disponible (EPIC-06) |
| **HU-041** Precio, IVA y totales | Precio unitario de cada línea resuelto por HU-056 (nunca a mano), total de la línea y total de la venta | **IVA discriminado por alícuota** (ver la sección siguiente) |
| **EPIC-03** (recorte) | Cambiar el cliente de una venta abierta y recalcular el precio de sus líneas | Tipo de comprobante según la condición fiscal (va con HU-042) |

Los criterios de aceptación de HU-039, HU-040 y HU-041 dicen "a definir en el refinamiento previo
al Sprint 4". Los que usa este plan son una **propuesta** que el PO tiene que validar.

## IVA: ¿se puede calcular el total sin discriminarlo?

**Sí, siempre que el precio de lista sea precio final con IVA incluido.** En ese caso:

```
total de la línea = cantidad × precio unitario
total de la venta = Σ totales de las líneas
```

El IVA ya está adentro del precio y discriminarlo solo *parte* el total en neto + IVA por
alícuota: no lo cambia. Así, dejar la discriminación para después no afecta ningún importe.

Motivos para hacerlo así:

- Es como vende un supermercado a consumidor final: el precio de la góndola es final y la
  Factura B no discrimina IVA. Discriminarlo solo hace falta para la Factura A a un responsable
  inscripto, que depende del tipo de comprobante (EPIC-03 completo y HU-042).
- Hoy no se podría discriminar aunque se quisiera: el commit `183e65a` quitó la relación entre
  el artículo y la alícuota de IVA, así que una línea no sabe qué alícuota tiene.

**Supuesto acordado (2026-09-24):** mientras el PO no diga lo contrario, `price_list_items.price`
es **precio final con IVA incluido**. `sprint-backlog-3.md` (HU-012) había dejado esto pendiente.
Si el PO después define que es neto, el total sin IVA quedaría **mal** (faltaría el impuesto) y
habría que revisar este atajo.

**Qué cambia en el backlog (aceptado):** HU-041 se parte en dos. HU-041 queda como "precio y totales" y se
crea una historia nueva, "discriminar el IVA por alícuota en la venta". La nueva depende de
HU-007, de volver a relacionar el artículo con su alícuota y del tipo de comprobante de EPIC-03,
y va antes de HU-042. El esquema de abajo se puede ampliar para el IVA sin cambiar lo existente:
basta con agregar columnas a `sale_items`.

## Decisiones de diseño

1. **La venta abierta se guarda en el servidor desde que se abre** (no es un carrito en el
   navegador que se manda al final). Cada escaneo es un request que resuelve el precio en el
   servidor con `ResolveArticlePrice`. Si el artículo no tiene precio, el vendedor lo ve en el
   momento y no al confirmar.
2. **El precio de la línea es una foto** tomada al agregarla: `unit_price` y `price_list_id` se
   guardan en `sale_items`. Si el Gerente cambia la lista con la venta abierta, la línea no se
   mueve. Solo se re-resuelve al cambiar el cliente (decisión 5).
3. **Escanear un artículo que ya está en la venta suma 1 a su cantidad (acordado)** en lugar de
   agregar otra línea o dar error, que es lo que se espera en una caja. Se respalda con
   `UNIQUE(sale_id, article_id)`. La orden de compra hace lo contrario (rechaza el repetido)
   porque allí la carga es manual.
4. **La sucursal no se guarda en la venta:** sale de `point_of_sale → warehouse → branch`, igual
   que en `PointOfSale`, que tampoco tiene `branch_id`.
5. **Cambiar el cliente vuelve a resolver el precio de todas las líneas** en una transacción. Si
   un artículo queda sin precio para el cliente nuevo, se rechaza el cambio completo y el mensaje
   nombra el artículo. Nunca queda una venta con precios mezclados de dos clientes.
6. **Canal fijo en Mostrador (acordado):** la pantalla y `OpenSale` siempre crean la venta con
   canal `mostrador`, y el vendedor no lo elige. El canal `online` es exclusivo del e-commerce:
   esas ventas las va a crear el pedido pagado (EPIC-15), no este panel. Igual se guarda
   `sales.channel`, porque HU-039 lo pide como dato de la venta y porque EPIC-15 va a escribir en
   la misma tabla. El escenario online de HU-056 queda cubierto por `ResolveArticlePriceTest`.
7. **Estados:** `abierta` y `descartada`. `confirmada` se agrega recién cuando exista cobro o
   factura (EPIC-04 o HU-042). Solo una venta `abierta` acepta cambios.
8. **Montos:** se usa `ConvertsMoneyToCents` para sumar, igual que en `CreatePurchaseOrder`, para
   evitar errores de redondeo de float en el total.

## Diseño de datos (DER propuesto)

### `sales` — HU-039

| Columna | Tipo | Reglas |
|---|---|---|
| id | bigint PK | |
| point_of_sale_id | FK → points_of_sale | obligatorio, punto de venta activo al abrir, `restrictOnDelete` |
| channel | varchar(20) | CHECK `in ('mostrador', 'online')`. Este panel siempre graba `mostrador`; `online` lo va a usar EPIC-15 |
| customer_id | FK → customers | obligatorio, por defecto Consumidor Final (`Customer::default()`) |
| user_id | FK → users | vendedor responsable |
| opened_at | timestamp | fecha y hora de la operación, indexado |
| status | varchar(20) | CHECK `in ('abierta', 'descartada')`, default `abierta`, indexado |
| total_amount | decimal(12,2) | CHECK `>= 0`, default 0 |
| created_at / updated_at | timestamp | |

`Customer::hasAssociatedRecords()` ya consulta `sales.customer_id`: con esta tabla, la baja de un
cliente con ventas pasa a ser lógica sin tocar nada más.

### `sale_items` — HU-040 / HU-041

| Columna | Tipo | Reglas |
|---|---|---|
| id | bigint PK | |
| sale_id | FK → sales | `cascadeOnDelete` |
| article_id | FK → articles | artículo activo al agregarlo |
| quantity | decimal(12,3) | CHECK `> 0` (admite artículos pesables) |
| unit_price | decimal(12,2) | CHECK `> 0`, foto del precio resuelto |
| price_list_id | FK → price_lists | lista de origen (trazabilidad de HU-056), `restrictOnDelete` |
| line_total | decimal(12,2) | CHECK `> 0` |
| created_at / updated_at | timestamp | |

*Restricción:* `UNIQUE(sale_id, article_id)`. Los CHECK van inline con `rawColumn`
(`.ai/rules/migrations.md`). Hoy las listas de precios no tienen ruta para borrarlas, así que
`restrictOnDelete` no bloquea ningún flujo existente.

Este DER va al sprint backlog del Sprint 4 cuando se cree, en su sección "Tablas nuevas".

## Cambios propuestos

### Backend — Enums (`app/Enums/Sales/`)

- `SaleChannel` (`Mostrador`, `Online`) con `label()` y `toPriceListChannel(): PriceListChannel`.
  No se reutiliza `PriceListChannel` porque incluye `General`, que no es un canal de venta.
- `SaleStatus` (`Abierta`, `Descartada`) con `label()`.

### Backend — Modelos y factories

- `app/Models/Sales/Sale.php`: relaciones `pointOfSale`, `customer`, `user` e `items`, casts de
  los dos enums, `scopeOpen()` e `isOpen()`.
- `app/Models/Sales/SaleItem.php`: relaciones `sale`, `article` y `priceList`.
- `SaleFactory` con el estado `descartada()`, y `SaleItemFactory`.

### Backend — Actions (`app/Actions/Sales/`)

| Action | Qué hace |
|---|---|
| `OpenSale` | Valida que el punto de venta esté activo, asigna Consumidor Final si no llega cliente y crea la venta `abierta` con `opened_at = now()` |
| `AddArticleToSale` | Recibe `article_id` o `code`: el código escaneado se busca exacto contra `barcode_normalized` y, si no aparece, contra `internal_code_normalized`. Si la unidad de medida del artículo no admite decimales, la cantidad tiene que ser entera. Valida que la venta esté abierta y el artículo activo. Si el artículo ya está, suma la cantidad. Si no, llama a `ResolveArticlePrice` y crea la línea con la foto del precio. Recalcula el total. Convierte `ArticleNotPricedException` en `ValidationException` |
| `UpdateSaleItemQuantity` | Cantidad mayor a cero y venta abierta. Recalcula el total de la línea y el de la venta |
| `RemoveSaleItem` | Quita la línea y recalcula el total |
| `ChangeSaleCustomer` | Cambia el cliente y re-resuelve todas las líneas en una transacción. Si alguna queda sin precio, se rechaza todo (decisión 5) |
| `DiscardSale` | Pasa la venta abierta a `descartada` |

El recálculo del total va en un método del modelo (`Sale::recalculateTotal()`), porque cuatro
Actions lo usan y no tiene reglas propias.

### Backend — HTTP

- **Form Requests** (`app/Http/Requests/Sales/`): `StoreSaleRequest`, `StoreSaleItemRequest`
  (`article_id` o `code`, uno de los dos obligatorio; `quantity` opcional, 1 por defecto),
  `UpdateSaleItemRequest` y `UpdateSaleCustomerRequest`.
- **Controller:** `app/Http/Controllers/Sales/SaleController.php` (solo orquesta los Actions).
- **Rutas** (`routes/web.php`, bloque `sales/sales`, nombre `sales.sales.`):

| Método | Ruta | Acción |
|---|---|---|
| GET | `/` | `index`: listado paginado, filtros por estado, punto de venta y fecha |
| POST | `/` | `store`: abre la venta y redirige a `show` |
| GET | `search-articles` | búsqueda por código interno, descripción o barras (mismo patrón que `PurchaseOrderController::searchArticles`) |
| GET | `{sale}` | `show`: la pantalla de venta |
| POST | `{sale}/items` | agrega un artículo |
| PATCH | `{sale}/items/{item}` | cambia la cantidad |
| DELETE | `{sale}/items/{item}` | quita la línea |
| PATCH | `{sale}/customer` | cambia el cliente |
| POST | `{sale}/discard` | descarta la venta |

  La ruta con el comodín `{sale}` va al final del bloque, igual que en `pricing/price-lists`.

### Backend — Data (`app/Data/Sales/`)

`SaleListData`, `SaleData` (cabecera, sucursal derivada, cliente, líneas y total),
`SaleItemData` (artículo, cantidad, precio, total de la línea y nombre y alcance de la lista de
origen), `SaleArticleOptionData` y `SaleCustomerOptionData` (con la lista particular asignada, si
tiene). Después de agregarlos: `npm run types:generate`.

### Frontend (`resources/js/pages/sales/sales/`)

- `index.tsx`: listado de ventas con badge de estado y un botón "Abrir venta". La venta arranca
  siempre con Consumidor Final (el cliente se cambia desde `show.tsx`); si hay un solo punto de
  venta activo se abre directo, y si hay varios, un diálogo pide solo el punto de venta. El canal
  no se muestra como opción: es siempre Mostrador.
- `show.tsx`: la pantalla de venta.
  - **Cabecera:** sucursal, punto de venta, canal (solo lectura), vendedor, fecha y hora, y selector de cliente
    que llama a `PATCH customer`.
  - **Input de código de barras** con foco automático: con Enter hace `POST items` y se limpia.
    Al lado, un combobox de búsqueda que usa `search-articles`.
  - **Tabla de líneas:** artículo, cantidad editable, precio unitario, **badge con la lista de
    origen** (por ejemplo "Mostrador", o "Particular: Mayorista") que muestra la cascada de
    HU-056 a simple vista, total de la línea y botón para quitarla.
  - **Pie:** total de la venta y botón "Descartar venta".
  - Los errores (artículo sin precio, código inexistente) se muestran como error del input y
    como toast, sin perder la venta.
  - Una venta `descartada` se muestra en modo lectura.
- Entrada "Ventas" en `app-sidebar.tsx`, en el grupo de Clientes y Medios de pago.
- Se reutilizan primitivos de `components/ui` (Command/Combobox, Table, Badge, Dialog) y se
  consulta `docs/context/design.md`. Si falta un primitivo: `npx shadcn@latest add`.

### Datos de demostración (`database/seeders/`)

Hoy `PriceListSeeder` crea solo la "Lista General", **sin precios**, así que la pantalla daría
"sin precio" para todo. Hace falta sembrar:

- precios en la Lista General para todos los artículos del seeder;
- una lista **Mostrador** vigente, con precios distintos a los de la General en un subconjunto de
  artículos;
- una lista **particular** "Mayorista" y un cliente con esa lista asignada (en `CustomerSeeder`);
- al menos un artículo con precio solo en la Lista General, para ver la caída al paso 3.

En producción el `deployCommand` corre `migrate` pero no `db:seed`: después del deploy hay que
correr `cloud command:run production --cmd='php artisan db:seed --force'`.

## Tests (`tests/Feature/Sales/`)

| Archivo | Qué cubre |
|---|---|
| `OpenSaleTest` | Abre con Consumidor Final por defecto, rechaza un punto de venta inactivo, guarda usuario y `opened_at`; el canal es siempre `mostrador` aunque el request mande `online` |
| `AddArticleToSaleTest` | Agrega por `article_id` y por código de barras, un código inexistente da error, el mismo artículo suma cantidad, un artículo inactivo se rechaza, un artículo sin precio se rechaza y no crea la línea, la línea guarda `price_list_id`, una venta descartada no acepta líneas |
| `SaleItemTest` | Editar la cantidad y quitar una línea recalculan el total; una cantidad de 0 o negativa se rechaza |
| `ChangeSaleCustomerTest` | Pasar a un cliente con lista particular re-precia las líneas; si un artículo queda sin precio se rechaza todo y la venta no cambia |
| `SalePriceScenariosTest` | Los escenarios de mostrador de la Verificación de HU-056, de punta a punta por HTTP: cliente con lista propia, mostrador sin lista propia y caída a la General cuando la lista Mostrador no tiene el artículo, cada uno con su precio y su lista de origen. El escenario online ya está en `ResolveArticlePriceTest` |
| `SaleTotalsTest` | Total con cantidades decimales (pesables) sin error de redondeo |
| `DatabaseSeederTest` (existente) | Ajustarlo si el seeder nuevo cambia lo que verifica |

## Orden de implementación (PRs)

1. **HU-039:** enums, migración de `sales`, modelo, factory, `OpenSale`, `DiscardSale`, `index`,
   diálogo "Abrir venta" y `show` con la cabecera sola.
2. **HU-040 + HU-041 sin IVA:** migración de `sale_items`, `AddArticleToSale`,
   `UpdateSaleItemQuantity`, `RemoveSaleItem`, búsqueda, tabla de líneas, badge de lista y total.
   El precio y el total van en este mismo PR porque una línea no puede existir sin precio
   (nunca a cero).
3. **EPIC-03 (recorte):** `ChangeSaleCustomer` y el selector de cliente.
4. **Seeder de demostración** (o dentro del PR 2, para poder probarlo a mano en ese momento).

## Verificación

**Automática** (antes de cada PR): `php artisan test --compact tests/Feature/Sales`,
`vendor/bin/pint --dirty --format agent`, `composer run types:check`, `npm run types:generate` y
después `npm run lint:check`, `npm run format:check` y `npm run types:check`. O todo junto con
`composer run ci:check`.

**Manual** (el guion de la demo de HU-056):

1. Abrir una venta de mostrador con Consumidor Final y escanear un artículo con precio en la lista
   Mostrador: sale el precio de Mostrador con el badge "Mostrador".
2. Escanear un artículo que solo tiene precio en la Lista General: badge "General" (paso 3).
3. Cambiar el cliente al que tiene la lista "Mayorista": las líneas cambian de precio y el badge
   pasa a "Particular: Mayorista".
4. Escanear dos veces el mismo artículo: queda una sola línea con cantidad 2.
5. Escanear un artículo sin precio en ninguna lista: error explícito y la línea no se agrega.

## Decisiones acordadas (2026-09-24)

1. **Precio de lista:** se asume final con IVA incluido hasta que el PO diga lo contrario.
2. **HU-041 se parte:** "precio y totales" entra en este plan y el IVA discriminado pasa a una
   historia nueva.
3. **Canal:** fijo en Mostrador en este panel; `online` es exclusivo del e-commerce (EPIC-15).
4. **Artículo repetido:** escanearlo de nuevo suma cantidad.
