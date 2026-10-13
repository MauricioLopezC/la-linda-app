# Sprint Backlog 5 - Supermercados La Linda

**Sprint 5** · 11/10/2026 al 25/10/2026 · equipo de 6 personas (6 carriles individuales)
**Compromiso: 53 story points** en escala de Fibonacci (velocidad observada del equipo: 59 SP en Sprint 3 y 97 SP en Sprint 4).

> Este documento referencia los ítems por ID. Los criterios de aceptación viven en
> `product-backlog.md`; se escribieron en este planning a partir de la revisión del Sprint 4 con el
> Product Owner / Profesor del 12/10/2026.

---

## Objetivo del sprint

1. **Cerrar el circuito de entrega de pedidos web desde Depósito con baja de stock y tipo de movimiento propio:** cuando se acredita el pago de un pedido web con Mercado Pago se genera automáticamente la venta online confirmada y su factura A o B (`EPIC-15`), sin descontar todavía la mercadería. El encargado de depósito entra a su pantalla principal de pedidos a preparar, inicia la preparación (`en_preparacion`), junta los artículos y confirma la salida del depósito (`entregado`, mostrado como `"Despachado"` si es envío a domicilio) (`EPIC-17`): **en ese momento se registra el movimiento de sistema `"Salida por Entrega de Pedido Web"` y baja el stock del depósito**.
2. **Garantizar la disponibilidad online y completar filtros y ordenamiento en la tienda:** se impide que dos clientes compren el mismo stock calculando la disponibilidad online real descontando los pedidos ya pagados o en preparación pendientes de salir del depósito (`HU-048`), y el cliente filtra el catálogo por marca y rango de precios y lo ordena por precio y descripción (`HU-047`).
3. **Habilitar la administración de usuarios y roles fijos con Gates por módulo y pantalla principal por rol:** el Administrador gestiona al personal interno (`HU-003`) asignándole su rol operativo en `UserRole` (`administrador`, `gerente`, `compras`, `deposito`, `cajero`) protegido por Gates por módulo en código (`HU-004`), y cada rol aterriza al iniciar sesión en su pantalla principal de trabajo (`Gerente` en `/dashboard`, `Depósito` en los pedidos a preparar, `Cajero` en la caja).
4. **Construir el Tablero Gerencial (Dashboard) completo:** el Gerente consulta en `/dashboard` los ingresos facturados y cobrados por canal, sucursal y medio de pago (`EPIC-18`), los egresos devengados en comprobantes de proveedor frente a los pagos efectivos y egresos de caja (`EPIC-19`), el resultado neto y su evolución temporal (`EPIC-20`), y los indicadores operativos de artículos más vendidos, alertas de stock, compras por proveedor y pedidos pendientes de preparar (`EPIC-21`), filtrando todo el tablero por fecha, sucursal y canal y exportando la información (`EPIC-22`).

Al cierre del sprint debe existir este recorrido:

```text
Tienda online:   Catálogo público con disponibilidad real (HU-048)
                   → filtrar por categoría, marca y rango de precio y ordenar (HU-047)
                   → confirmar pedido y pagar con Mercado Pago (sandbox)
                   → pago acreditado (pagado) → venta online confirmada + factura A/B (EPIC-15)
                   → el stock físico aún no baja, pero queda comprometido para otro cliente (HU-048)

Depósito:        Iniciar sesión como Depósito → aterriza directo en Pedidos a preparar (HU-004)
                   → tomar el pedido pagado de Juan Pérez → pasar a en_preparacion (prepared_by)
                   → juntar artículos del depósito (picking) y confirmar entrega/despacho
                   → pasa a entregado (etiqueta "Despachado" si es envío, delivered_by)
                   → se genera "Salida por Entrega de Pedido Web" y baja el stock (EPIC-17)

Cajero:          Iniciar sesión como Cajero → aterriza directo en la Caja (HU-004)
                   → opera su turno y ventas; recibe 403 si intenta abrir /dashboard o /purchases

Gerencia:        Iniciar sesión como Gerente → aterriza directo en /dashboard (HU-004)
                   → ver ingresos por canal, sucursal y medio de pago (EPIC-18)
                   → ver egresos devengados de proveedores vs. órdenes de pago y egresos de caja (EPIC-19)
                   → ver resultado neto y gráfico evolutivo ingresos vs. egresos (EPIC-20)
                   → ver top vendidos, compras por proveedor y alertas operativas (EPIC-21)
                   → filtrar por fechas, sucursal y canal y exportar a CSV / Excel (EPIC-22)
```

---

## Qué pidió el Product Owner y cómo se cubre

| # | Pedido del PO (12/10) | Se cubre con | Decisión de alcance |
|---:|---|---|---|
| 1 | *"¿Hacemos un ajuste? No, cuando se entrega la mercadería... El stock baja cuando entregás, cuando vos lo sacás del depósito, no cuando lo vendés"* | `EPIC-15`, `EPIC-17` (pasan a historias) | `EPIC-15` crea la venta online y su factura al acreditarse el pago; `EPIC-17` descuenta el stock al salir del depósito con el tipo propio `"Salida por Entrega de Pedido Web"` |
| 2 | Que dos clientes no se vean afectados comprando mercadería sin stock mientras un pedido aguarda despacho | `HU-048` | Se incorpora `HU-048`: la disponibilidad online resta del saldo del depósito online las unidades comprometidas en pedidos `pagado` y `en_preparacion` |
| 3 | Que la preparación y entrega la realice el usuario de Depósito y que cada puesto entre a su módulo | `HU-003`, `HU-004`, `EPIC-17` | Se amplía el enum `UserRole` (`administrador`, `gerente`, `cajero`, `deposito`, `compras`, `cliente`), se aplican Gates por módulo y cada rol entra a su pantalla principal |
| 4 | Filtrar y ordenar el catálogo de la tienda online | `HU-047` (reestimada a 3 SP) | Filtros combinables por marca y rango de precios y ordenamiento por precio y descripción sobre el precio resuelto |
| 5 | Cerrar el Dashboard gerencial completo (ingresos, egresos, evolución, KPIs y filtros) | `EPIC-18` a `EPIC-22` (pasan a historias) | Tablero gerencial unificado en `/dashboard` con gráficos (`shadcn chart` / `recharts`), filtros globales y exportación |

---

## Decisiones del planning

1. **Roles fijos en `UserRole` + Gates en código en lugar de las tablas `roles`/`permissions`/`permission_role` (`HU-004`).**
   - Se amplía el enum `App\Enums\Security\UserRole` existente (`users.role` ya es `varchar(50)`): `administrador`, `gerente`, `cajero`, `deposito`, `compras`, `cliente`.
   - La migración del Día 1 convierte los registros actuales con `role = 'personal_interno'` a `'administrador'`.
   - Se definen Gates por módulo en código (`view-dashboard`, `manage-web-orders`, `manage-users`, `operate-cash`, `manage-catalog`, `manage-pricing`, `manage-purchases`, `manage-stock`, `manage-customers`, `manage-organization`) y se aplican con middleware `can:<gate>` por grupo de rutas en `routes/web.php`.
   - Se mantiene `Gate::before` para `UserRole::Administrador`.
   - `UserFactory` incorpora un state por rol (`User::factory()->gerente()`, `->cajero()`, `->deposito()`, `->compras()`, `->administrador()`, `->cliente()`).
   - Se eliminan `role_id` y las tablas `roles`, `permissions` y `permission_role` del DER (`branch_id` e `is_active` en `users` se mantienen).
   - La matriz editable de permisos por rol pasa a **"Fuera del sprint / deseable"** y se agrega como pregunta al PO.
2. **Pantalla principal por rol al iniciar sesión (`LoginResponse` y redirección raíz).**
   - `Gerente` (y `Administrador`) → `/dashboard`
   - `Depósito` → bandeja de pedidos web a preparar (`/ecommerce/orders`)
   - `Cajero` → la caja (`/sales/cash-sessions`)
   - `Compras` → órdenes de compra (`/purchases/orders`)
   - `Cliente` → tienda online (`route('tienda.home')`)
3. **Incorporación de `HU-048` para proteger a dos clientes concurrentes.** Como el stock físico del depósito online recién baja cuando el depósito entrega el pedido (`EPIC-17`), la disponibilidad online (`available_quantity`) se calcula como la existencia física del depósito `is_ecommerce = true` **menos** las cantidades comprometidas en pedidos con estado `pagado` o `en_preparacion`. Se valida al agregar al carrito y dentro de la transacción de `PlaceWebOrder` (`HU-062`).
4. **Tipo de movimiento de stock propio `"Salida por Entrega de Pedido Web"` (`EPIC-17`).** Se agrega a `StockMovementType` el código `CODE_WEB_ORDER_DELIVERY = 'web_order_delivery'` (`name = 'Salida por Entrega de Pedido Web'`, `sign = -1`, `is_system = true`), incluido en `AUTOMATIC_CODES` para que **no sea elegible en ajustes manuales** (`HU-017`). Responde al *"¿hacemos un ajuste? No"* del PO, deja distinguible en el historial (`HU-018`) la `"Salida por Venta"` de mostrador (`sale_exit`) de la entrega online (`web_order_delivery`) y reutiliza `stock_movements.sale_id`.
5. **Máquina de estados simplificada en `web_orders` (`EPIC-17`).**
   - Cuatro estados: `pendiente` → `pagado` → `en_preparacion` → `entregado`, mostrando en la interfaz la etiqueta **"Despachado"** cuando `delivery_method = 'envio'` y **"Entregado"** cuando `delivery_method = 'retiro'`. El pedido se cierra al salir del depósito.
   - **Quedan fuera:** `listo_para_retiro`, `en_camino`, `cancelado`, `ready_or_dispatched_at` y `cancelled_at`.
   - **Motivo:** con `en_camino` nadie registra en el sistema la recepción final en el domicilio del comprador, y `cancelado` no tiene flujo de devolución del pago en Mercado Pago.
6. **Auditoría separada de preparación y entrega en `web_orders` (`EPIC-17`).** En lugar de un único `dispatched_by_user_id`, se registran `preparation_started_at` + `prepared_by` (`FK → users` nullable) al pasar a `en_preparacion`, y `delivered_at` + `delivered_by` (`FK → users` nullable) al cerrar el pedido en `entregado`.
7. **Integridad en el esquema y states de factory desde el Día 1.**
   - En `sales`: `CHECK ((channel = 'online' AND web_order_id IS NOT NULL) OR (channel = 'mostrador' AND web_order_id IS NULL))`.
   - En `web_orders`: `CHECK ((status = 'entregado' AND delivered_at IS NOT NULL AND delivered_by IS NOT NULL) OR (status <> 'entregado' AND delivered_at IS NULL AND delivered_by IS NULL))` e índice compuesto `INDEX (status, paid_at)`.
   - En `WebOrderFactory`: states `paid()`, `inPreparation()` y `delivered()` incluidos en el PR de esquema del Día 1.
8. **Dependencia nueva de gráficos (`shadcn chart` / `recharts`) pendiente de aprobación.** Para las visualizaciones de `EPIC-18`, `EPIC-19` y `EPIC-20` se solicita incorporar el componente `chart` de shadcn (`recharts`), sujeto a aprobación del equipo/líder técnico antes de modificar `package.json`.
9. **Estimaciones estrictamente en escala de Fibonacci (`1, 2, 3, 5, 8, 13`).** Se eliminan los valores intermedios (`4` y `6` SP) y se reestiman los ítems del sprint en números de Fibonacci puros, sumando **53 SP**.

---

## Ítems comprometidos (53 SP — escala de Fibonacci)

| # | ID | Título | SP | Carril / Responsable | Depende de |
|---:|---|---|---:|---|---|
| 1 | **EPIC-15** | Procesar el pedido pagado como una venta online con su factura (reestimada, antes 8) | 5 | Carril 1 · **Chiari** | HU-050, HU-042 |
| 2 | **HU-048** | Mostrar la disponibilidad online e impedir la compra sin stock | 3 | Carril 1 · **Chiari** | HU-046, HU-016 |
| 3 | **EPIC-17** | Despachar y entregar pedidos web desde depósito con baja de stock | 8 | Carril 2 · **Facu** | EPIC-15, EPIC-06 |
| 4 | **HU-003** | Administrar los usuarios del sistema | 5 | Carril 3 · **Aza** | — |
| 5 | **HU-047** | Buscar, filtrar y ordenar artículos en la tienda online (reestimada, antes 5) | 3 | Carril 3 · **Aza** | HU-046 |
| 6 | **HU-004** | Administrar roles y sus permisos (enum `UserRole` + Gates + inicio por rol) | 8 | Carril 4 · **Mauri** | HU-003 |
| 7 | **EPIC-18** | Visualizar los ingresos del período (reestimada, antes 8) | 5 | Carril 5 · **Clarita** | EPIC-15, HU-042 |
| 8 | **EPIC-20** | Visualizar la relación entre ingresos y egresos y su evolución (reestimada, antes 8) | 5 | Carril 5 · **Clarita** | EPIC-18, EPIC-19 |
| 9 | **EPIC-19** | Visualizar los egresos del período | 5 | Carril 6 · **Pablo** | HU-027, HU-058 |
| 10 | **EPIC-21** | Visualizar indicadores operativos complementarios (reestimada, antes 5) | 3 | Carril 6 · **Pablo** | EPIC-20, EPIC-17 |
| 11 | **EPIC-22** | Filtrar y exportar el tablero gerencial (reestimada, antes 5) | 3 | Carril 6 · **Pablo** *(propuesta: **Clarita**)* | EPIC-20 |
| | | **Total comprometido** | **53** | | |

---

## Orden de ataque y paralelización (6 carriles individuales · 12/10 → 23/10)

Cada developer es dueño de sus ítems de punta a punta (backend, frontend y tests). Promedio parejo: **8,8 SP por persona** (~1,6 SP por día hábil).

```text
┌──────────────────────────────────────────────────────────────────────────────┐
│ CARRIL 1 — Chiari (8 SP): Esquema D1 + Venta Online y Disponibilidad         │
│ • Portón D1 (0 SP): PR de Esquema del Sprint 5 (hoy, Lu 12/10)               │
│ • 1. EPIC-15 (5 SP, Ma 13/10 → Ju 15/10) Pedido pagado → venta + factura A/B │
│ • 2. HU-048  (3 SP, Vi 16/10 → Mi 21/10) Disponibilidad online y bloqueo     │
├──────────────────────────────────────────────────────────────────────────────┤
│ CARRIL 2 — Facu (8 SP): Preparación y Entrega en Depósito                    │
│ • 1. EPIC-17 (8 SP, Lu 12/10 → Lu 19/10) Bandeja de depósito, en_preparacion │
│                     → entregado ("Despachado" si envío) + movimiento         │
│                     web_order_delivery (D1–D4 con factories; D5 integra Sale)│
├──────────────────────────────────────────────────────────────────────────────┤
│ CARRIL 3 — Aza (8 SP): Usuarios Internos y Catálogo Tienda                   │
│ • 1. HU-003  (5 SP, Lu 12/10 → Ju 15/10) ABM de usuarios, baja lógica,       │
│                     EnsureUserIsActive y cierre de /register                 │
│ • 2. HU-047  (3 SP, Vi 16/10 → Mi 21/10) Filtros por marca/precio + orden en │
│                     pages/ecommerce/index.tsx y ConsultOnlineCatalog         │
├──────────────────────────────────────────────────────────────────────────────┤
│ CARRIL 4 — Mauri (8 SP): Contrato de Roles, Gates y Barrido de Tests         │
│ • Portón D2 (0 SP): Contrato de Roles (Lu 12/10 → Ma 13/10)                  │
│ • 1. HU-004  (8 SP, Mi 14/10 → Lu 19/10) Gates por módulo, sidebar y barrido │
│                     de tests 200/403 por rol (mergeado a más tardar el D6)   │
├──────────────────────────────────────────────────────────────────────────────┤
│ CARRIL 5 — Clarita (10 SP, o 13 SP con propuesta): Base, Ingresos, Evolución │
│ • Portón D2 (0 SP): Base del Tablero (Lu 12/10 → Ma 13/10)                   │
│ • 1. EPIC-18 (5 SP, Ma 13/10 → Ju 15/10) Ingresos por canal, sucursal y pago │
│ • 2. EPIC-20 (5 SP, Vi 16/10 → Ma 20/10) Resultado neto y evolución          │
├──────────────────────────────────────────────────────────────────────────────┤
│ CARRIL 6 — Pablo (11 SP, o 8 SP con propuesta): Egresos, KPIs y Exportación  │
│ • 1. EPIC-19 (5 SP, Lu 12/10 → Ju 15/10) Egresos devengados vs. pagados      │
│ • 2. EPIC-21 (3 SP, Vi 16/10 → Ma 20/10) Indicadores operativos              │
│ • 3. EPIC-22 (3 SP, Mi 21/10 → Ju 22/10) Barra de filtros y exportación      │
└──────────────────────────────────────────────────────────────────────────────┘
```

> **Propuesta de balanceo (`EPIC-22` a Clarita):** la barra de filtros y el export viven sobre `DashboardFilterRequest` y el shell de `dashboard.tsx` que arma Clarita en la base del D2, y `EPIC-20` también es de ella. Si `EPIC-22` pasa a Clarita, Pablo baja de 11 a 8 SP y le quedan libres D8–D9 para las pruebas cruzadas del tablero.

### Contratos del día 1 y 2

1. **Portón 0 — Mergear el PR de esquema del Sprint 5 (Chiari, D1 · Lu 12/10):**
   - Migraciones de `users`, `web_orders`, `sales`, `invoices` e `invoice_items`, restricciones `CHECK`, índice `(status, paid_at)`, el tipo `web_order_delivery` en `StockMovementType` + `StockMovementTypeSeeder` y los states `paid()`, `inPreparation()` y `delivered()` de `database/factories/Ecommerce/WebOrderFactory.php`. Holgura cero: una vez en `master`, nadie espera la Action de otro y todos arrancan con factories.
2. **`ConvertPaidWebOrderToSale(WebOrder): Sale` (Chiari → Facu):**
   - La implementa Chiari en `EPIC-15`, la llama `MarkWebOrderAsPaid`, y Facu lee la `Sale` por `web_order_id` en `DeliverWebOrder`. Mientras tanto (D1–D4), Facu usa `WebOrder::factory()->paid()` con `Sale::factory()` online vinculada al pedido.
3. **Contrato de roles (Mauri, D2 · Ma 13/10):**
   - Nombres de los casos de `UserRole`, `Gate::before` y los 10 Gates, un state por rol en `UserFactory` (con `administrador` por defecto para que no se rompa ningún test) y `LoginResponse` por rol. Destraba a Aza y a Facu. **Una vez mergeado, nadie más toca `UserFactory.php`.**
4. **Base del tablero + firmas de ingresos/egresos (Clarita y Pablo, D2 · Ma 13/10):**
   - `DashboardController@index`, `DashboardFilterRequest`, `DashboardData` con un bloque anulable por sección y `dashboard.tsx` partido en `pages/dashboard/partials/*.tsx` (no necesita el esquema: puede salir desde el D1). Clarita y Pablo acuerdan las firmas de `GetDashboardRevenues` y `GetDashboardExpenses` y qué total de egresos usa el resultado neto de `EPIC-20` (el devengado, aclarando el pagado).
5. **Grupos de rutas por módulo en `routes/web.php` (D1/D2):**
   - Cada uno declara su grupo de rutas el D1/D2. Mauri agrega los `->middleware('can:...')` sobre esos grupos ya existentes recién en su PR de `HU-004`.

### Cronograma día por día (Lu 12/10 a Vi 23/10)

| Día | Chiari (Esquema + `EPIC-15` + `HU-048`) | Facu (`EPIC-17`) | Aza (`HU-003` + `HU-047`) | Mauri (Roles + `HU-004`) | Clarita (Base + `EPIC-18` + `EPIC-20`) | Pablo (`EPIC-19` + `EPIC-21` + `EPIC-22`) |
|---|---|---|---|---|---|---|
| **D1 · Lu 12/10** *(Planning · Esquema)* | **PR Esquema D1** mergeado (`users`, `web_orders`, `sales`, `invoices`, `web_order_delivery`, `WebOrderFactory`) | `EPIC-17` con factories: diseño de `StartWebOrderPreparation` y `DeliverWebOrder` | `HU-003` sin depender de roles: cerrar `/register` en `config/fortify.php` y middleware `EnsureUserIsActive` | Armar contrato de roles (`UserRole`, 10 Gates, states en `UserFactory`, `LoginResponse`) | Armar `DashboardController`, `DashboardFilterRequest`, `DashboardData` y `pages/dashboard/partials/*.tsx` | `EPIC-19` con factories: query `GetDashboardExpenses` sobre comprobantes, OP y `cash_movements` |
| **D2 · Ma 13/10** *(Contratos D2)* | `EPIC-15`: `ConvertPaidWebOrderToSale` + renglón `"Envío a domicilio"` en `IssueInvoice` | `EPIC-17`: Actions de preparación y entrega con `"Salida por Entrega de Pedido Web"` | `HU-003`: suma Actions de alta, rol, sucursal y blanqueo sobre el contrato de roles | **PR Contrato de Roles D2** mergeado (`UserRole`, Gates, `UserFactory`, inicio por rol) | **PR Base del Tablero D2** mergeado + arranque de `GetDashboardRevenues` (`EPIC-18`) con `Sale::factory()` | Enchufar `GetDashboardExpenses` (`EPIC-19`) en `DashboardExpensesSection.tsx` |
| **D3 · Mi 14/10** | `EPIC-15`: integración con `MarkWebOrderAsPaid` (idempotente ante doble webhook) y PDF en `orders/show.tsx` | `EPIC-17`: controlador y bandeja en `/ecommerce/orders` con índice `(status, paid_at)` | `HU-003`: validaciones de último admin y caja abierta + pantalla y modal de clave por única vez | `HU-004`: `auth.can` en `HandleInertiaRequests` y filtro de `app-sidebar.tsx` | `EPIC-18`: desglose por canal, sucursal y medio de pago en `DashboardRevenuesSection.tsx` | `EPIC-19`: tarjetas devengado vs. pagado, gráfico por medio de pago y tests |
| **D4 · Ju 15/10** *(Control D4)* | **PR `EPIC-15` mergeado** · **Control D4** | `EPIC-17`: vista de detalle (*picking*), rebase de `orders/show.tsx` y etiqueta `"Despachado"`/`"Entregado"` | **PR `HU-003` mergeado** · **Control D4** | `HU-004`: aplicar `can:` por grupo de rutas en `routes/web.php` | **PR `EPIC-18` mergeado** · **Control D4** | **PR `EPIC-19` mergeado** · **Control D4** |
| **D5 · Vi 16/10** | `HU-048`: cálculo de disponibilidad online (saldo `is_ecommerce` − `pagado`/`en_preparacion`) | `EPIC-17`: integración con la `Sale` real de `EPIC-15` y tests de punta a punta | `HU-047`: ampliar `ConsultOnlineCatalogRequest` y `ConsultOnlineCatalog` (`brand_id`, `min_price`, `max_price`, `sort`) | `HU-004`: barrido de tests de todos los módulos y suite `200`/`403` por rol | `EPIC-20`: `GetDashboardEvolution` reutilizando totales de ingresos y egresos (serie con `0.00`) | `EPIC-21`: queries de top vendidos, compras por proveedor y alertas de stock (`<= 0`) |
| **D6 · Lu 19/10** *(Control D6 · tope `HU-004`)* | `HU-048`: validación en `AddArticleToCart`, `UpdateCartItemQuantity` y con lock en `PlaceWebOrder` | **PR `EPIC-17` mergeado** · **Control D6** | `HU-047`: UI de filtros en componente aparte para `pages/ecommerce/index.tsx` | **PR `HU-004` + Barrido de tests mergeado (Tope D6)** · **Control D6** | `EPIC-20`: gráfico comparativo ingresos vs. egresos y tarjeta de superávit/déficit | `EPIC-21`: KPI de pedidos `pagado`/`en_preparacion` y paneles en `DashboardOperationalSection.tsx` |
| **D7 · Ma 20/10** | `HU-048`: rebase sobre `HU-047`, badges en `pages/ecommerce/index.tsx` y tests de concurrencia | Pruebas cruzadas del circuito online → depósito → historial de movimientos (`HU-018`) | `HU-047`: tests sobre precio resuelto (`HU-056`) y combinación de filtros | Verificación de redirección inicial por rol y ajustes de `DatabaseSeeder` por rol | **PR `EPIC-20` mergeado** | **PR `EPIC-21` mergeado** |
| **D8 · Mi 21/10** | **PR `HU-048` mergeado** | Ajuste de seeders de demostración (pedidos web pagados y entregados) | **PR `HU-047` mergeado** | Pruebas de permisos finos en botones de acción por rol | Apoyo en `EPIC-22` (o toma `EPIC-22` si se aplica la propuesta) | `EPIC-22`: barra de filtros sincronizada con URL y `/dashboard/export` |
| **D9 · Ju 22/10** *(Pruebas integrales)* | Pruebas integrales de los 4 ejes en `master` y `composer run ci:check` | Pruebas integrales de los 4 ejes en `master` | Pruebas integrales de los 4 ejes en `master` | Pruebas integrales de todos los roles de punta a punta | Pruebas integrales del tablero con datos reales de ambos canales | **PR `EPIC-22` mergeado** y pruebas integrales |
| **D10 · Vi 23/10** *(Ensayo de la Review)* | Ensayo del recorrido completo de la Review | Ensayo de la demo de Depósito | Ensayo de la demo de Tienda y Usuarios | Ensayo de la demo de Roles | Ensayo de la demo de Gerencia | Ensayo de la demo de Gerencia |

### Las tres cadenas críticas que definen el sprint

| Cadena | Secuencia y responsables | Diagnóstico y holgura |
|---|---|---|
| **1. Permisos** | `Esquema` (Chiari, D1) → `Contrato de roles` (Mauri, D2) → `HU-004` (Mauri, D6) | Pasa por 2 personas con **holgura cero hasta el D2**. Corta todos los tests del Sprint 1 al 4: por eso `UserFactory` usa `administrador` por defecto y el barrido tiene **tope obligatorio el D6 (Lu 19/10)**. |
| **2. Tablero** | `Base tablero` (Clarita, D2) → `EPIC-18` (Clarita, D4) + `EPIC-19` (Pablo, D4) → `EPIC-20` (Clarita, D7) → `EPIC-22` (Pablo/Clarita, D9) | **13 SP en serie**, más `EPIC-19` de Pablo que tiene que cerrar el D4 para que entre `EPIC-20`. Es la cadena más larga: termina el D9 (Ju 22/10). |
| **3. Circuito online** | `Esquema` (Chiari, D1) → `EPIC-15` (Chiari, D4) → `EPIC-17` (Facu, D6) | **13 SP entre Chiari y Facu**, pero avanzan en paralelo del D1 al D4 gracias a `WebOrder::factory()->paid()`. La dependencia es dura recién el D5 (Vi 16/10), cuando se integra la `Sale` real. **No se recorta bajo ningún concepto.** |

### Archivos donde van a chocar y protocolo de coordinación

| Archivo | Quiénes lo tocan | Qué hacer |
|---|---|---|
| `routes/web.php` | Facu, Aza, Mauri, Clarita, Pablo | Cada uno declara su grupo de rutas el D1/D2. Mauri suma los `->middleware('can:...')` sobre los grupos ya existentes en su PR de `HU-004`. |
| `resources/js/components/app-sidebar.tsx` | Facu, Aza, Mauri | Facu y Aza agregan su ítem en su sección. Mauri aplica el filtro por `auth.can` en un único PR. |
| `resources/js/pages/ecommerce/index.tsx` + `ConsultOnlineCatalog` | Aza (`HU-047`), Chiari (`HU-048`) | Aza pone los filtros en un componente aparte y mergea primero. Chiari rebasea y agrega el badge y la columna de disponibilidad. |
| `resources/js/pages/ecommerce/orders/show.tsx` | Chiari (`EPIC-15`), Facu (`EPIC-17`) | Chiari mergea el PDF de la factura el D4. Facu rebasea y suma la etiqueta `"Despachado"`/`"Entregado"`. |
| `resources/js/pages/dashboard.tsx` | Clarita, Pablo | Queda como contenedor en la base del D2. Cada uno edita solo su archivo en `resources/js/pages/dashboard/partials/`. |
| `FortifyServiceProvider` / `AppServiceProvider` | Mauri, Aza | Todo va en el PR de roles del D2 (Mauri). Aza solo toca `config/fortify.php` y `EnsureUserIsActive`. |
| `database/factories/UserFactory.php` | Mauri (D2), usado por todos | Se edita una sola vez en el PR del contrato de roles del D2. Nadie más lo toca después. |
| `resources/js/types/generated.d.ts` | Todos | No resolver conflictos a mano: correr `npm run types:generate` después de cada rebase. |

### Mayor riesgo técnico: Barrido de tests de `HU-004` (tope D6 · Lu 19/10)

Activar `can:<gate>` en todas las rutas del panel interno es **el mayor riesgo del sprint**, porque atraviesa los tests de todos los módulos construidos entre el Sprint 1 y el Sprint 4.
- **Mitigación en D2:** `UserFactory::definition()` pasa a crear por defecto usuarios con `role = UserRole::Administrador` (que pasa por `Gate::before`), de modo que los tests existentes de dominio sigan en verde cuando se monten los Gates.
- **Cierre obligatorio en D6 (Lu 19/10):** el barrido de tests de autorización de `HU-004` (que prueba explícitamente `200`/`403` por cada rol con `User::factory()->cajero()`, `->deposito()`, `->compras()`, `->gerente()`) debe mergearse **a más tardar el D6**, dejando D7–D9 libres de roturas transversales.

### Puntos de control D4 / D6 y orden de recorte

- **Punto de control D4 (Ju 15/10):** deben estar mergeados el Esquema (D1), el Contrato de Roles (D2), la Base del Tablero (D2), `EPIC-15` (Chiari), `HU-003` (Aza), `EPIC-18` (Clarita) y `EPIC-19` (Pablo).
- **Punto de control D6 (Lu 19/10):** deben estar mergeados `EPIC-17` (Facu) y `HU-004` con el barrido completo de tests (Mauri).
- **Orden estricto de recorte (`EPIC-22` → `EPIC-21` → `HU-047` → `HU-048`):**
  1. **`EPIC-22`** (3 SP — barra de filtros y exportación; sin `EPIC-22` quedan los filtros básicos de la base del D2)
  2. **`EPIC-21`** (3 SP — indicadores operativos complementarios del tablero)
  3. **`HU-047` / `HU-065`** (3 SP — filtros por marca/rango de precio y ordenamiento en la tienda; sin `HU-047` quedan la búsqueda y las categorías de `HU-046`)
  4. **`HU-048`** (3 SP — disponibilidad online y bloqueo sin stock)
- **No se recortan bajo ningún concepto:**
  - El **circuito de entrega online con baja de stock** (`EPIC-15` + `EPIC-17`).
  - Los **usuarios y roles** (`HU-003` + `HU-004`).
  - Los **ingresos, egresos y su evolución en el Tablero Gerencial** (`EPIC-18` + `EPIC-19` + `EPIC-20`).

---

## Desglose en tareas

Stack: Laravel + Inertia v3 + React. Tienda y despachos en `Ecommerce` / `Stock`; seguridad en
`Security` / `Settings`; tablero en `Dashboard`. Lógica en `app/Actions/{Module}`, respuestas en
`app/Data/{Module}`, validación en Form Requests.

### EPIC-15 — Procesar el pedido pagado como una venta online con su factura (5 SP)
- [ ] En el PR de esquema (D1): migración en `sales` (`web_order_id` único nullable, `shipping_amount`, `CHECK (channel = 'online' ⇔ web_order_id IS NOT NULL)`), ajuste de nulabilidad en `invoices` (`cash_session_id` y `user_id` nullable) e `invoice_items` (`article_id` nullable).
- [ ] Action `ConvertPaidWebOrderToSale`, invocada por `MarkWebOrderAsPaid` dentro de la misma transacción: devuelve la `Sale` existente si ya fue procesado (idempotencia), resuelve el depósito activo con `is_ecommerce = true` y su punto de venta activo, y crea la `Sale` `confirmada` (`channel = 'online'`, `cash_session_id = null`, `user_id = null`, `shipping_amount = $webOrder->shipping_cost`) con sus `sale_items` y desglose de neto e IVA (`HU-063`), sin descontar stock.
- [ ] Emitir la `Invoice` (`A` o `B` según la condición fiscal del cliente) reutilizando `IssueInvoice`; cuando `shipping_cost > 0`, agregar la línea `"Envío a domicilio"` (`article_id = null`, IVA 21%) para que el total facturado coincida exactamente con el importe pagado en Mercado Pago.
- [ ] Exponer la factura emitida y el botón de descarga de su PDF (`HU-043`) en `resources/js/pages/ecommerce/orders/show.tsx` ("Mis pedidos" del cliente) y en la vista interna.
- [ ] Tests: pedido pagado con retiro y con envío genera venta + factura con totales exactos, idempotencia ante doble webhook y existencia de stock intacta tras el pago.

### HU-048 — Mostrar la disponibilidad online e impedir la compra sin stock (3 SP)
- [ ] Query/servicio de disponibilidad online que calcula `available_quantity` por artículo como el saldo en `stock_balances` del depósito `is_ecommerce = true` menos la suma de cantidades en `web_order_items` de pedidos en estado `'pagado'` o `'en_preparacion'`.
- [ ] Exponer el estado de disponibilidad (`disponible`, `ultimas_unidades`, `sin_stock`) y `available_quantity` en el catálogo público (`ConsultOnlineCatalog`) y en el carrito.
- [ ] Validar en `AddArticleToCart`, `UpdateCartItemQuantity` y dentro de la transacción de `PlaceWebOrder` (`HU-062`, con lock para evitar condiciones de carrera entre dos clientes) que la cantidad solicitada no supere `available_quantity`.
- [ ] En `resources/js/pages/ecommerce/index.tsx` y `cart.tsx`, mostrar el badge de disponibilidad y deshabilitar `"Agregar al carrito"` / `"Confirmar pedido"` cuando no alcance el stock.
- [ ] Tests: dos clientes intentando comprar las últimas unidades (usando `WebOrder::factory()->paid()`); el pago/pedido del primero reduce la disponibilidad online del segundo aunque el depósito todavía no haya entregado la mercadería.

### EPIC-17 — Despachar y entregar pedidos web desde depósito con baja de stock (8 SP)
- [ ] En el PR de esquema (D1): agregar en `StockMovementType` y `StockMovementTypeSeeder` el tipo de sistema `"Salida por Entrega de Pedido Web"` (`CODE_WEB_ORDER_DELIVERY = 'web_order_delivery'`, `sign = -1`, `is_system = true`, incluido en `AUTOMATIC_CODES` para no ofrecerlo en ajustes manuales), migración en `web_orders` (`status` con `CHECK IN ('pendiente', 'pagado', 'en_preparacion', 'entregado')`, `preparation_started_at`, `prepared_by`, `delivered_at`, `delivered_by`, `CHECK (delivered_* ⇔ status = 'entregado')`, índice `(status, paid_at)`) y states `paid()`, `inPreparation()` y `delivered()` en `database/factories/Ecommerce/WebOrderFactory.php`.
- [ ] Action `StartWebOrderPreparation`: transiciona `pagado` → `en_preparacion` registrando `preparation_started_at = now()` y `prepared_by = auth()->id()`.
- [ ] Action `DeliverWebOrder`: transiciona `en_preparacion` → `entregado` registrando `delivered_at = now()` y `delivered_by = auth()->id()`, y ejecuta en la misma transacción el movimiento `"Salida por Entrega de Pedido Web"` (`web_order_delivery`) sobre el depósito online reutilizando `stock_movements.sale_id = $sale->id` (una única vez por venta, marcando conflicto de `EPIC-06` si alguna línea queda negativa).
- [ ] Pantalla operativa de pedidos web a preparar en `/ecommerce/orders` (pantalla principal del rol `deposito`) con contadores por estado, filtros por estado, modalidad de entrega, sucursal y fechas, hoja de preparación (*picking list*) y botones de iniciar preparación y confirmar entrega/despacho.
- [ ] En la vista de depósito y en `resources/js/pages/ecommerce/orders/show.tsx` ("Mis pedidos" del cliente), mostrar la etiqueta **"Despachado"** cuando `status = 'entregado'` y `delivery_method = 'envio'` (y **"Entregado"** cuando es `retiro`).
- [ ] Tests: flujo `pagado` → `en_preparacion` → `entregado`, auditoría de `prepared_by` y `delivered_by`, movimiento `"Salida por Entrega de Pedido Web"` no elegible en ajustes manuales, descuento único en el depósito online y rechazo de transiciones inválidas.

### HU-003 — Administrar los usuarios del sistema (5 SP)
- [ ] En el PR de esquema (D1): columnas `branch_id` (`FK → branches`, nullable) e `is_active` (`boolean` default `true`) en `users`, y migración de datos `personal_interno` → `administrador`.
- [ ] Deshabilitar `Features::registration()` en `config/fortify.php` para cerrar `/register` en el panel interno (manteniendo el registro de clientes de `EPIC-11` en `/tienda/registro`).
- [ ] Middleware `EnsureUserIsActive`: impide iniciar sesión a usuarios inactivos y cierra la sesión en curso si un usuario pasa a `is_active = false`.
- [ ] Actions y controlador de usuarios (`/settings/users` o `/users`, protegido por `can:manage-users`): alta, edición de rol (`UserRole`) y sucursal, activación/desactivación lógica y blanqueo de contraseña (mostrando la clave generada o definida por única vez en pantalla).
- [ ] Validaciones: no desactivar al propio usuario en sesión, no dejar al sistema sin al menos un `administrador` activo y no desactivar a un usuario con turno de caja abierto.
- [ ] Tests: alta con clave inicial, login bloqueado para inactivo, cierre de sesión al desactivar, protección del último Administrador y de usuario con caja abierta.

### HU-047 — Buscar, filtrar y ordenar artículos en la tienda online (3 SP)
- [ ] Ampliar `ConsultOnlineCatalogRequest` y `ConsultOnlineCatalog` para recibir y validar `brand_id`, `min_price`, `max_price` (`max_price >= min_price`) y `sort` (`relevance`, `price_asc`, `price_desc`, `name_asc`, `name_desc`), combinables con `search` y `category_id` de `HU-046`.
- [ ] Aplicar el filtro de rango de precios y el ordenamiento `price_asc` / `price_desc` sobre el precio efectivo resuelto para el visitante actual (`HU-056`: lista particular del cliente si está logueado, o lista del canal `online` / `general`).
- [ ] Enviar al frontend las marcas activas con artículos publicables en el canal online.
- [ ] En `resources/js/pages/ecommerce/index.tsx` (en un subcomponente de filtros para no chocar con `HU-048`), agregar selectores de marca, rango de precio mínimo y máximo, selector de ordenamiento, chips de filtros activos removibles, contador de resultados y botón "Limpiar filtros", preservando los parámetros al paginar.
- [ ] Tests: filtro por marca, rango de precios (visitante anónimo y cliente con lista particular), ordenamiento por precio y por descripción, combinación de filtros.

### HU-004 — Administrar roles y sus permisos (8 SP)
- [ ] En el PR Contrato de Roles (D2): ampliar `App\Enums\Security\UserRole` (`administrador`, `gerente`, `cajero`, `deposito`, `compras`, `cliente`), definir `Gate::before` para `Administrador` y los Gates por módulo (`view-dashboard`, `manage-web-orders`, `manage-users`, `operate-cash`, `manage-catalog`, `manage-pricing`, `manage-purchases`, `manage-stock`, `manage-customers`, `manage-organization`), agregar states por rol en `UserFactory` y actualizar `LoginResponse` para que cada rol entre a su pantalla principal (`Gerente` → `/dashboard`, `Depósito` → pedidos a preparar, `Cajero` → la caja, `Compras` → compras, `Cliente` → `/tienda`).
- [ ] Compartir `auth.user.role` y `auth.can` en `HandleInertiaRequests` y filtrar los módulos de `resources/js/components/app-sidebar.tsx` según los Gates activos.
- [ ] Aplicar `middleware('can:<gate>')` por grupo de rutas en `routes/web.php` y sembrar un usuario de demostración por cada rol en `DatabaseSeeder`.
- [ ] **Barrido de tests (mergeado a más tardar el D6):** actualizar los tests de módulos que requieran un rol específico y agregar la suite de autorización que verifica `200` en módulos permitidos, `403` en módulos no permitidos y redirección inicial para cada uno de los 5 roles.

### EPIC-18 — Visualizar los ingresos del período (5 SP)
- [ ] En el PR Base del Tablero (D2): `DashboardController@index`, `DashboardFilterRequest`, `DashboardData` y layout modular en `resources/js/pages/dashboard.tsx`.
- [ ] Action/query `GetDashboardRevenues`: sobre las ventas `confirmada` del período (`mostrador` y `online` de `EPIC-15`) calcula Total Facturado con IVA, Ingreso Neto sin IVA, IVA total, Cantidad de ventas confirmadas, Ticket promedio y variación porcentual respecto al período anterior.
- [ ] Desglose de ingresos por canal (`mostrador` vs. `online`), por sucursal y por medio de pago (movimientos `venta` de caja más cobros acreditados de Mercado Pago).
- [ ] Tarjetas de KPIs y gráficos de participación en `DashboardRevenuesSection.tsx` (usando `shadcn chart` / `recharts`, pendiente de aprobación).
- [ ] Tests: suma exacta de ventas confirmadas de mostrador y online, exclusión de ventas abiertas o descartadas y de pedidos `pendiente`, filtrado por canal y sucursal.

### EPIC-20 — Visualizar la relación entre ingresos y egresos y su evolución (5 SP)
- [ ] Action/query `GetDashboardEvolution`: calcula el Resultado Neto del período (`ingresos − egresos`), el margen porcentual y la serie temporal comparativa `Ingresos vs. Egresos` (día a día en rangos cortos o mes a mes en rangos de varios meses, completando con `0.00` los períodos sin movimientos).
- [ ] Indicador de Resultado Neto (superávit en verde / déficit en rojo) y gráfico evolutivo de barras/líneas en `DashboardEvolutionSection.tsx`.
- [ ] Tests: resultado neto positivo y negativo, serie temporal completa sin huecos.

### EPIC-19 — Visualizar los egresos del período (5 SP)
- [ ] Action/query `GetDashboardExpenses`: calcula el gasto devengado en proveedores (facturas + notas de débito − notas de crédito confirmadas, excluyendo remitos y anulados) y el desembolso real de dinero (órdenes de pago confirmadas + egresos operativos de caja `cash_movements` de tipo `egreso`, desglosado por medio de pago).
- [ ] Tarjetas comparativas (devengado vs. pagado), gráfico por medio de pago y enlaces a Pagos y Egresos (`HU-055`) y Cuentas Corrientes (`HU-028`) en `DashboardExpensesSection.tsx`.
- [ ] Tests: cálculo de comprobantes recibidos vs. órdenes de pago y egresos de caja, exclusión de remitos y anulados.

### EPIC-21 — Visualizar indicadores operativos complementarios (3 SP)
- [ ] Action/query `GetDashboardOperationalIndicators`: top artículos más vendidos del período (por unidades e importe facturado), ranking de compras por proveedor y saldo pendiente en cuenta corriente, artículos sin stock o en conflicto (`quantity <= 0` en `stock_balances`) y cantidad de pedidos web en estado `pagado` o `en_preparacion` pendientes de entrega en depósito (`EPIC-17`).
- [ ] Paneles de rankings y tarjetas de alerta con enlaces directos a Existencias, Pedidos Web por preparar y Cuentas Corrientes en `DashboardOperationalSection.tsx`.
- [ ] Tests: ranking de artículos más vendidos, ranking de proveedores y conteo de alertas de stock y de pedidos web pendientes.

### EPIC-22 — Filtrar y exportar el tablero gerencial (3 SP)
- [ ] Barra superior de filtros sincronizada con la URL (`date_from`, `date_to`, atajos *Hoy*, *Este mes*, *Mes anterior*, *Últimos 6 meses*, *Este año*, `branch_id` y `channel`) y endpoint `DashboardController@export` (`/dashboard/export`).
- [ ] Descarga del resumen del tablero en CSV / Excel respetando los filtros activos (siguiendo el patrón de `HU-028` y `HU-055`).
- [ ] Tests: filtros por defecto, validación de fechas, filtrado por sucursal y canal y respuesta de exportación.

---

## Diseño de datos (DER proyectado Sprint 5)

> **Esquema entregado por adelantado en el D1.** Todas las migraciones de este DER (tablas
> modificadas, restricciones `CHECK`, índice `(status, paid_at)`, el tipo de movimiento
> `"Salida por Entrega de Pedido Web"` y los states de `WebOrderFactory`) se mergean el día 1 en un
> único PR de esquema. No se crean las tablas `roles`, `permissions` ni `permission_role`: los roles
> viven en `users.role` (`UserRole`) y los permisos en Gates por módulo.

```mermaid
erDiagram
    BRANCHES |o--o{ USERS : "branch_id (sucursal por defecto)"
    CUSTOMERS |o--o| USERS : "customer_id (solo rol cliente)"

    WEB_ORDERS ||--o| SALES : "origina al pagarse (EPIC-15)"
    USERS |o--o{ WEB_ORDERS : "prepared_by (inicia preparación)"
    USERS |o--o{ WEB_ORDERS : "delivered_by (entrega / despacha)"
    SALES ||--|| INVOICES : "se factura con (EPIC-15)"
    INVOICES ||--|{ INVOICE_ITEMS : "detalla (incluye envío)"
    SALES ||--o| STOCK_MOVEMENTS : "baja stock con web_order_delivery (EPIC-17)"
    STOCK_MOVEMENT_TYPES ||--o{ STOCK_MOVEMENTS : "tipifica (incl. web_order_delivery)"
```

### Tablas nuevas

Ninguna tabla nueva en el Sprint 5 (se reutiliza `users.role` con el enum `UserRole` y `stock_movements.sale_id` con el nuevo tipo de sistema `web_order_delivery`).

### Tablas modificadas

#### `users` — HU-003, HU-004
| Columna | Tipo | Reglas |
|---|---|---|
| role | varchar(50) | ya existe; el enum `UserRole` pasa a `'administrador'`, `'gerente'`, `'cajero'`, `'deposito'`, `'compras'`, `'cliente'`. Migración de datos: `personal_interno` → `administrador` |
| branch_id | FK → branches | nullable (`nullOnDelete`); sucursal predeterminada opcional del empleado |
| is_active | boolean | default `true`; baja lógica e invalidación de sesión |

#### `web_orders` — EPIC-17
| Columna | Tipo | Reglas |
|---|---|---|
| status | varchar(30) | `CHECK (status IN ('pendiente', 'pagado', 'en_preparacion', 'entregado'))` |
| paid_at | timestamp | nullable; `CHECK ((status = 'pendiente' AND paid_at IS NULL) OR (status <> 'pendiente' AND paid_at IS NOT NULL))` |
| preparation_started_at | timestamp | nullable; fecha/hora en que depósito inició el armado |
| prepared_by | FK → users | nullable; usuario de depósito que inició la preparación |
| delivered_at | timestamp | nullable; fecha/hora en que salió del depósito |
| delivered_by | FK → users | nullable; usuario de depósito que confirmó la entrega/despacho |

*Restricciones e índices en `web_orders`:*
- `CHECK ((status = 'entregado' AND delivered_at IS NOT NULL AND delivered_by IS NOT NULL) OR (status <> 'entregado' AND delivered_at IS NULL AND delivered_by IS NULL))`
- `CHECK ((status IN ('en_preparacion', 'entregado') AND preparation_started_at IS NOT NULL AND prepared_by IS NOT NULL) OR (status IN ('pendiente', 'pagado') AND preparation_started_at IS NULL AND prepared_by IS NULL))`
- `INDEX web_orders_status_paid_at_index (status, paid_at)`

#### `sales` — EPIC-15
| Columna | Tipo | Reglas |
|---|---|---|
| web_order_id | FK → web_orders | nullable, `UNIQUE`; pedido web que originó la venta online |
| shipping_amount | decimal(12,2) | default `0`, `CHECK (shipping_amount >= 0)` |

*Restricción en `sales`:*
- `CHECK ((channel = 'online' AND web_order_id IS NOT NULL) OR (channel = 'mostrador' AND web_order_id IS NULL))`

#### `invoices` / `invoice_items` — EPIC-15
- **`invoices`:** `cash_session_id` y `user_id` pasan a `nullable` (obligatorios en mostrador; nulos cuando la factura la emite automáticamente el pago de Mercado Pago en una venta online).
- **`invoice_items`:** `article_id` pasa a `nullable` para admitir el renglón de concepto `"Envío a domicilio"` cuando `shipping_cost > 0`.

#### `stock_movement_types` — EPIC-17
- Se siembra/inserta en la migración del D1 el tipo de sistema `code = 'web_order_delivery'`, `name = 'Salida por Entrega de Pedido Web'`, `sign = -1`, `is_system = true`, `is_active = true`, incluido en `StockMovementType::AUTOMATIC_CODES`.

---

## Preguntas al PO

| # | Pregunta | Supuesto con el que se avanza | Afecta a |
|---:|---|---|---|
| 1 | ¿Alcanza con los 5 roles operativos fijos para el personal interno (`Administrador`, `Gerente`, `Encargado de compras`, `Encargado de depósito`, `Cajero`) o se necesita además una pantalla para crear roles nuevos y editar una matriz de permisos? | Alcanza con los 5 roles fijos en `UserRole` y permisos por módulo en código; la matriz editable queda fuera como deseable | `HU-004` |
| 2 | En un pedido web con envío a domicilio, ¿el pedido se da por cerrado (`entregado` / etiqueta `"Despachado"`) apenas sale del depósito, o hace falta que alguien registre después que llegó al domicilio? | Se cierra al salir del depósito con la etiqueta `"Despachado"` (nadie registra la recepción en la puerta del cliente) | `EPIC-17` |
| 3 | En el bloque de egresos del Tablero Gerencial, ¿el Resultado Neto (`EPIC-20`) se compara contra los comprobantes devengados de proveedores o contra los pagos efectivos (órdenes de pago + egresos de caja)? | Se muestran ambas métricas en `EPIC-19` y el Resultado Neto se calcula sobre lo devengado aclarando el flujo de caja pagado | `EPIC-19`, `EPIC-20` |

---

## Demostración de cierre del Sprint 5

1. **Roles, pantalla principal por rol y usuarios (`HU-003`, `HU-004`):**
   - Iniciar sesión como `Administrador`, abrir el ABM de usuarios y dar de alta o blanquear la clave de un empleado viendo su contraseña temporal por única vez.
   - Iniciar sesión como `Cajero`: comprobar que aterriza directo en la **Caja**, que solo ve Caja, Ventas y Clientes en el sidebar y que al intentar abrir `/dashboard` o `/purchases` recibe `403`.
   - Iniciar sesión como `Depósito`: comprobar que aterriza directo en los **Pedidos web a preparar**.
   - Iniciar sesión como `Gerente`: comprobar que aterriza directo en `/dashboard`.
2. **Tienda online — disponibilidad, filtros y ordenamiento (`HU-048`, `HU-047`):**
   - En `/tienda`, filtrar artículos por categoría, marca y rango de precios y ordenarlos por "Menor precio".
   - Mostrar un artículo con 2 unidades disponibles: cuando un primer cliente compra y paga esas 2 unidades, un segundo cliente lo ve automáticamente `"Sin stock"` aunque el depósito todavía no lo haya despachado.
3. **Compra online → Venta y Factura sin descontar stock aún (`EPIC-15`):**
   - Confirmar un pedido con envío a domicilio (p. ej. 5 unidades de leche) y pagarlo en Mercado Pago sandbox. Mostrar que quedó `pagado`, que se crearon la venta online confirmada y su factura B con el renglón de envío (descargando el PDF), y comprobar en Existencias que **el stock físico del depósito todavía no bajó**.
4. **Preparación y entrega en Depósito con baja de stock (`EPIC-17`):**
   - En la sesión del usuario de `Depósito`, tomar el pedido de Juan Pérez, pasarlo a `En preparación` (quedando `prepared_by` y `preparation_started_at`), revisar los artículos a juntar (*5 leches*) y confirmar la **Entrega / Despacho**: comprobar que el pedido pasa a `Despachado` (`entregado` con `delivered_by` y `delivered_at`), que **en ese instante se genera el movimiento `"Salida por Entrega de Pedido Web"`** en el historial de stock y que la existencia del depósito disminuye en 5 unidades.
5. **Tablero Gerencial completo (`EPIC-18` a `EPIC-22`):**
   - En la sesión del `Gerente` (`/dashboard`), mostrar los ingresos por canal (`mostrador` y `online`), sucursal y medio de pago, los egresos devengados de proveedores frente a las órdenes de pago y egresos de caja, el Resultado Neto y el gráfico evolutivo, y los indicadores de artículos más vendidos, compras por proveedor y alertas operativas. Filtrar el tablero por sucursal y canal y exportar el resumen a Excel / CSV.

---

## Fuera del sprint

| Ítem | Motivo |
|---|---|
| Matriz editable de roles y permisos en base de datos | Se resuelve `HU-004` con los 5 roles fijos del negocio en `UserRole` y Gates por módulo; la edición dinámica de permisos pasa a deseable y se consulta con el PO |
| Estados `listo_para_retiro`, `en_camino` y `cancelado` en pedidos web | Con `en_camino` nadie registra la recepción final en el domicilio, y `cancelado` no tiene flujo de devolución del pago en Mercado Pago; el pedido se cierra al salir del depósito (`entregado` / `"Despachado"`) |
| `EPIC-16` | Notificaciones por correo de seguimiento del pedido |
| `HU-044`, `HU-045`, `EPIC-08`, `EPIC-09`, `EPIC-10` | Anulación y devolución con nota de crédito, explorador de comprobantes, ficha histórica del cliente y log de auditoría quedan fuera de los 4 ejes acordados para esta entrega |
| `HU-019`, `HU-020`, `HU-053` | Transferencias entre depósitos, stock mínimo y exportación de stock |
| `HU-061`, `HU-059` | Historial gerencial de turnos de caja y préstamos entre cajas |
| `SPIKE-01` | Integración con ARCA (CAE): las facturas siguen siendo comprobantes internos |

---

## Definition of Done

- [ ] Criterios de aceptación verificados contra `product-backlog.md`.
- [ ] Validaciones en el servidor mediante Form Requests.
- [ ] Reglas de negocio y mutaciones en `app/Actions/{Module}`.
- [ ] Respuestas tipadas hacia el frontend en `app/Data/{Module}` y `npm run types:generate`.
- [ ] Dependencia `shadcn chart` (`recharts`) aprobada antes de modificar `package.json`.
- [ ] Tests de Pest en verde (`php artisan test --compact`), incluyendo el barrido de permisos por rol del D6.
- [ ] PHPStan sin errores (`composer run types:check`).
- [ ] Pint, ESLint, Prettier y tsc en verde (`composer run ci:check` cubre todo).
- [ ] Migraciones y DER de este documento sincronizados.
- [ ] Pantallas verificadas en desktop.
