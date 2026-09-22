# HU-051 — Administrar puntos de venta (Reconectar)

Esta historia reconecta y habilita el módulo de Puntos de Venta (PDV), que había sido construido parcialmente y desconectado por estar fuera del alcance inicial (Sprint 1), para que quede activo en el Sprint 3. El ABM (modelo, migración, controlador, requests de validación y vistas) ya existe y cuenta con tests.

## Criterios de aceptación (de `product-backlog.md`)
1. **Datos:** número, sucursal, depósito desde el cual descuenta stock, estado
2. **Validaciones:**
    - todo punto de venta pertenece a una sucursal y tiene un depósito asociado obligatorio
    - el número de punto de venta es único dentro de su sucursal
3. **Comportamiento:** la relación punto de venta a depósito es la que determina de qué depósito se descuenta el stock en cada venta de mostrador

## Investigación del código existente

| Artefacto | Ubicación | Propósito |
|---|---|---|
| Modelo | `app/Models/Sales/PointOfSale.php` | Entidad PointOfSale existente con relación a Warehouse. |
| Controlador | `app/Http/Controllers/Sales/PointOfSaleController.php` | Controlador que expone el listado (Inertia) y el ABM. |
| Requests | `app/Http/Requests/Sales/StorePointOfSaleRequest.php` | Valida unicidad por sucursal. |
| Frontend | `resources/js/pages/sales/points-of-sale/index.tsx` | Página de Inertia ya implementada. |
| Sidebar | `resources/js/components/app-sidebar.tsx` | Contiene los imports y las entradas del menú de Puntos de Venta comentados. |
| Rutas | `routes/web.php` | Las rutas `sales/points-of-sale` **ya están activas** y no fueron comentadas. |
| Tests | `tests/Feature/Sales/PointOfSaleTest.php` | Tests de creación, validación de unicidad por sucursal, y actualización, los cuales se ejecutan en verde (6 passed). |

## Proposed Changes

#### [MODIFY] resources/js/components/app-sidebar.tsx
- Descomentar el import de `pointsOfSale` de `@/routes/sales/points-of-sale`.
- Agregar el icono `Store` a la importación de `lucide-react`.
- Descomentar el bloque del menú "Puntos de Venta" bajo la sección "Organización".

## Verificación de la Definition of Done

| Criterio | Cómo se verifica |
|---|---|
| Reconectar en el menú | Descomentado en `app-sidebar.tsx`. |
| Validación de unicidad y depósito | Revisión del Request y test automatizado existente. |
| Depósito pertenece a sucursal | Verificado implícitamente por el modelo (el depósito dicta la sucursal). |
| Navegación y Tests | `php artisan test --compact --filter=PointOfSaleTest` debe dar verde (actualmente da verde). |

## Verification Plan
### Automated Tests
- Ejecutar `php artisan test --compact --filter=PointOfSaleTest`.
- Ejecutar verificaciones estáticas de frontend (`npm run lint:check`, `npm run types:check`).

### Manual Verification
- Ingresar al sistema y verificar que "Puntos de Venta" figure en el menú lateral bajo "Organización".
- Acceder, intentar crear un punto de venta seleccionando un depósito.

### CI checks
- `vendor/bin/pint --dirty --format agent`
- `composer run types:check`
- `npm run lint:check`, `npm run format:check`, `npm run types:check`
