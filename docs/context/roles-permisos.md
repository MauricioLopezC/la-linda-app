# Roles y permisos

Intención de negocio detrás de quién puede hacer qué. Este fichero explica el *por qué*;
la implementación exacta (enum `UserRole`, Gates por módulo, middleware `can:`) vive en el código y
es la fuente de verdad sobre el estado actual — si este documento y el código no coinciden, el
código gana y hay que actualizar este fichero.

## Cómo usar este fichero

- Un rol por sección, con su valor en `App\Enums\Security\UserRole`, su pantalla principal al
  iniciar sesión y qué puede/no puede hacer a alto nivel.
- Anotar reglas de negocio no obvias (ej. excepciones, casos límite, jerarquías entre roles).
- No listar aquí cada ruta técnica una por una; eso se consulta en los Gates y en `routes/web.php`.

## Roles (`App\Enums\Security\UserRole`)

Definidos en el Sprint Planning 5 (`HU-003` / `HU-004`) sobre la columna `users.role` (migrando el
valor inicial `personal_interno` a `administrador`):

### Administrador (`administrador`)
- **Pantalla principal:** `/dashboard`.
- Acceso total al sistema sin restricciones mediante `Gate::before`. Es el único rol habilitado para gestionar usuarios internos (`HU-003`, Gate `manage-users`). No se puede desactivar a sí mismo, y el sistema garantiza que siempre exista al menos un `administrador` activo.

### Gerente (`gerente`)
- **Pantalla principal:** `/dashboard` (Tablero Gerencial).
- Supervisa el negocio y la operatoria general. Tiene acceso al Tablero Gerencial (`EPIC-18` a `EPIC-22`, Gate `view-dashboard`), listas de precios (`manage-pricing`), organización/parámetros (`manage-organization`) y consulta de operaciones (catálogo, compras, clientes, stock y pedidos web). Como los Gates son por módulo, esa "consulta" le da el módulo completo (`manage-catalog`, `manage-purchases`, `manage-customers`, `manage-stock`, `manage-web-orders`), no un acceso de solo lectura. No opera la caja ni las ventas de mostrador (`operate-cash`) y no administra usuarios internos.

### Encargado de compras (`compras`)
- **Pantalla principal:** órdenes de compra (`/purchasing/orders`).
- Gestiona el circuito de abastecimiento y cuentas por pagar (`manage-purchases`, `manage-catalog`): catálogo de artículos (`HU-008`, `HU-015`), proveedores (`HU-013`), órdenes de compra (`HU-033`), comprobantes de proveedor (`HU-036`, `HU-037`, `HU-038`, `HU-054`), cuenta corriente (`HU-028`), órdenes de pago (`HU-027`) y listado de pagos y egresos (`HU-055`), además de consultar las existencias de stock (`HU-016`). Esa consulta le otorga el Gate `manage-stock` completo, que incluye movimientos manuales. No opera cajas ni el POS de ventas.

### Encargado de depósito (`deposito`)
- **Pantalla principal:** pedidos web a preparar (`/ecommerce/orders`; mientras esa pantalla no exista, existencias de stock).
- Gestiona el inventario físico y la preparación/entrega de pedidos del canal online (`manage-stock`, `manage-web-orders`): consulta de existencias (`HU-016`), registro de movimientos manuales (`HU-017`), historial de movimientos (`HU-018`) y preparación y entrega/despacho de pedidos de la tienda online con baja de stock (`EPIC-17`). No accede a precios, compras financieras, caja ni tablero gerencial.

### Cajero (`cajero`)
- **Pantalla principal:** la caja (`/sales/cash-sessions/current`: su turno abierto, o la apertura si no tiene uno).
- Opera la atención al público y cobros en sucursal (`operate-cash`, `manage-customers`): apertura de turno de caja (`HU-057`), ingresos/egresos de caja (`HU-058`), cierre y arqueo de su propio turno (`HU-060`), ventas en mostrador (`HU-039` a `HU-041`, `EPIC-03`, `EPIC-04`), emisión y descarga de facturas (`HU-042`, `HU-043`) y gestión de clientes (`HU-021`). No accede a compras, listas de precios, administración de usuarios ni tablero gerencial.

### Cliente (`cliente`)
- **Pantalla principal:** tienda online (`/tienda`, `route('tienda.home')`).
- Cuenta vinculada a un registro de `customers` (`users.customer_id IS NOT NULL`). Opera exclusivamente la tienda pública, su carrito y "Mis pedidos" (`EPIC-11`, `EPIC-13`, `HU-062`); no accede a ninguna ruta del panel interno.
