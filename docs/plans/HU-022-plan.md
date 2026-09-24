# HU-022 — Asignar una lista de precios a un cliente

Permite asignar una lista de precios de tipo particular a un cliente para aplicarle condiciones comerciales diferenciadas sin modificar la lista base general o del canal.

## Criterios de aceptación (de `product-backlog.md`)

1. **Datos:** cliente, lista de precios asignada (opcional)
2. **Validaciones:**
    - solo se pueden asignar listas de tipo `particular` en estado activo y vigentes; las listas de canal son el precio base del canal y no se asignan a un cliente
    - un cliente tiene a lo sumo una lista asignada
    - si el cliente no tiene lista asignada se le aplicará la lista del canal de la operación
3. **Comportamiento:**
    - la asignación queda visible en la ficha del cliente
    - su efecto sobre el precio se verifica en la historia de resolución de precio (HU-056)

## Investigación del código existente

| Artefacto | Ubicación | Propósito |
|---|---|---|
| Modelo Customer | `app/Models/Customers/Customer.php` | Entidad cliente con relación `belongsTo(PriceList::class)`. |
| Modelo PriceList | `app/Models/Pricing/PriceList.php` | Listas de precios con scopes `active()`, `currentlyValid()` y tipos en `PriceListScope`. |
| Actions de Cliente | `app/Actions/Customers/CreateCustomer.php`, `UpdateCustomer.php` | Manejan la creación y edición validando que la lista sea de tipo `particular`, activa y vigente. |
| Requests | `app/Http/Requests/Customers/StoreCustomerRequest.php`, `UpdateCustomerRequest.php` | Validan `price_list_id` opcional (`nullable`, `integer`, `exists:price_lists,id`). |
| Data Object | `app/Data/Customers/CustomerData.php` | Expone `price_list_id` y `price_list_name` hacia Inertia. |
| Controlador | `app/Http/Controllers/Customers/CustomerController.php` | Pasa clientes con su lista relacionada y `availablePriceLists` (solo listas particulares activas y vigentes). |
| Vista Frontend | `resources/js/pages/customers/index.tsx` | Selector en modales de alta/edición y badge en la tabla de clientes. |
| Tests | `tests/Feature/Customers/CustomerManagementTest.php` | Pruebas de asignación, nulabilidad, rechazo de listas de canal, inactivas y vencidas. |

## Proposed Changes

#### [NEW] database/migrations/2026_09_22_164034_add_price_list_id_to_customers_table.php
- Agrega clave foránea nullable `price_list_id` referenciando a `price_lists` con `nullOnDelete()`.

#### [MODIFY] app/Models/Customers/Customer.php
- Añade `price_list_id` a `$fillable` y define la relación `priceList(): BelongsTo`.

#### [MODIFY] app/Actions/Customers/CreateCustomer.php y UpdateCustomer.php
- Implementa `validatePriceList(int $priceListId)` asegurando que la lista exista, sea de `scope = particular`, esté activa y no esté vencida.

#### [MODIFY] app/Http/Requests/Customers/StoreCustomerRequest.php y UpdateCustomerRequest.php
- Agrega regla `'price_list_id' => ['nullable', 'integer', Rule::exists('price_lists', 'id')]`.

#### [MODIFY] app/Data/Customers/CustomerData.php
- Mapea `price_list_id` y `price_list_name`.

#### [MODIFY] app/Http/Controllers/Customers/CustomerController.php
- Incluye `with('priceList')` en la consulta y envía `availablePriceLists` (solo particulares activas y vigentes).

#### [MODIFY] resources/js/pages/customers/index.tsx
- Agrega selector en modal de alta y edición, y badge de lista asignada junto al nombre del cliente en el listado.

#### [MODIFY] tests/Feature/Customers/CustomerManagementTest.php
- Incorpora suite de tests para HU-022 cubriendo asignación, desasignación, rechazo de listas de canal, inactivas y vencidas, e inmutabilidad del cliente por defecto.

## Verificación de la Definition of Done

| Criterio | Cómo se verifica |
|---|---|
| Campo opcional `price_list_id` | Migración con columna `nullable()` y tests de asignación nula. |
| Validación de lista particular | Action rechaza `scope !== particular` con `ValidationException`. |
| Validación de estado activo | Action rechaza listas con `is_active = false`. |
| Validación de vigencia | Action rechaza listas con status `vencida`. |
| Un cliente a lo sumo una lista | Clave foránea única directa en la tabla `customers`. |
| Asignación visible en cliente | Badge visual en la tabla de clientes mostrando el nombre de la lista. |
| Tests automatizados | `php artisan test --compact tests/Feature/Customers/CustomerManagementTest.php` en verde (29 tests). |

## Verification Plan

### Automated Tests
- `php artisan test --compact tests/Feature/Customers/CustomerManagementTest.php`
- `php artisan test --compact`

### CI Checks
- `vendor/bin/pint --dirty --format agent`
- `composer run types:check`
- `npm run lint:check`
- `npm run format:check`
- `npm run types:check`
