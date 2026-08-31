# HU-013 — Administrar proveedores

Módulo de Compras (`Purchasing` / `CMP`): gestión centralizada del maestro de proveedores (ABM, validación rigurosa de CUIT con dígito verificador, lista cerrada de condiciones fiscales, datos bancarios y comerciales, baja lógica ante registros asociados y listado con filtros combinables). Es la historia base del Sprint 2 y no depende de ninguna otra historia previa.

## Criterios de aceptación (de `product-backlog.md`)

1. **Datos:** razón social, CUIT, condición fiscal, domicilio comercial, rubro, cuenta bancaria para pagos, condiciones comerciales pactadas, estado.
2. **Validaciones:**
   - razón social y CUIT obligatorios.
   - CUIT único y con dígito verificador válido (algoritmo Módulo 11 oficial argentino).
   - condición fiscal obligatoria seleccionada de una lista cerrada.
   - la baja de un proveedor con órdenes, comprobantes o pagos asociados es siempre lógica.
3. **Comportamiento:** el sistema registra el historial de cambios del proveedor en el log de auditoría (con registro de logs e integración lista para el servicio de auditoría de EPIC-10).

---

## Revisión de modificaciones previas en el Alcance y Lógica de Negocio (Sprint 2)

Antes de iniciar la implementación, se consolidaron los cambios acordados en el Sprint Planning 2 y en la auditoría técnica de arquitectura:

1. **Foco del Sprint 2 en Cuentas por Pagar (30 SP):**
   - El circuito comprometido es: **Recepción de Comprobantes de Proveedor (`HU-036`) → Imputación de Notas de Crédito/Débito y Motor de Saldo (`HU-054`) → Órdenes de Pago (`HU-027`) → Listado de Egresos (`HU-055`)**.
   - Se crearon `HU-054` (motor de saldo e imputaciones N:N de NC/ND) y `HU-055` (libro de pagos/egresos).
   - `HU-036` fue ampliada para contemplar Facturas, Notas de Crédito y Notas de Débito con tipos, puntos de venta (4 dígitos), número (8 dígitos), letras (A, B, C, M) e importes discriminados.
   - `HU-027` se reformuló a documento "Orden de pago" con detalle de imputaciones a facturas.
2. **Exclusiones y Desestimaciones explícitas de alcance:**
   - **Órdenes de compra (`HU-033/034/035/024`):** Fuera del Sprint 2. El flujo solicitado por el PO es directo de comprobante a orden de pago.
   - **Transferencias entre depósitos (`HU-019`):** Postergadas a futuros sprints.
   - **Clientes (`HU-021`):** Alcance opcional, sin resolución de listas de precios (`HU-022`).
   - **IVA discriminado en comprobantes:** Se modela simplificado hasta la implementación de `HU-007`.
3. **Mejoras Técnicas y de Dominio incorporadas (`informe-revision-arquitectura.md`):**
   - Inmutabilidad estricta en tablas de ledger/imputaciones (sin `updated_at`).
   - Normalización de strings para búsquedas insensibles a mayúsculas y acentos (`business_name_normalized`).

---

## Investigación del código existente

| Artefacto | Ubicación | Propósito / Patrón a replicar |
| :--- | :--- | :--- |
| **Modelos y Normalización** | `app/Models/Inventory/Warehouse.php`, `app/Concerns/NormalizesUniqueAttributes.php` | Uso de `NormalizesUniqueAttributes`, `scopeActive()`, casts tipados, `uniqueAttributesToNormalize()`. |
| **Enums tipados** | `app/Enums/Catalog/ArticleStatus.php` | Enum nativo de PHP con backing string y método `label()` para visualización en español. |
| **Acciones de Negocio** | `app/Actions/Catalog/CreateCategory.php`, `app/Actions/Catalog/ToggleCategoryStatus.php` | Encapsulación de lógica transaccional, validaciones de dominio mediante `ValidationException`, sin lógica en controladores. |
| **Data Objects (DTO)** | `app/Data/Catalog/ArticleData.php`, `app/Data/Inventory/WarehouseData.php` | Clases que extienden `Spatie\LaravelData\Data` para salida tipada y autogeneración de TypeScript (`types:generate`). |
| **Controladores** | `app/Http/Controllers/Inventory/WarehouseController.php` | Métodos delgados (`index`, `store`, `update`, `toggleStatus`, `destroy`) delegando en Requests y Actions. |
| **Filtros de Búsqueda** | `app/Actions/Inventory/ConsultStockBalances.php` | Filtros combinables con `when()`, ordenamiento e interpolación limpia en Eloquent. |
| **Componentes de UI** | `resources/js/pages/inventory/warehouses/index.tsx`, `resources/js/components/ui/` | Tablas con shadcn/ui (`Table`, `Badge`, `Dialog`, `Input`, `Select`), toasts con Sonner y Wayfinder para URLs tipadas. |
| **Navegación** | `resources/js/components/app-sidebar.tsx` | Incorporación del grupo "Compras" (`Purchasing`) con acceso a "Proveedores". |

> **Nota sobre el DER del Sprint Backlog 2:** El diagrama Mermaid en `sprint-backlog-2.md` lista `business_name`, `tax_id`, `tax_condition`, `address`, `rubro`, `bank_account`, `is_active`. Agregamos la columna `commercial_terms` (`text` nullable) para cumplir textualmente con el criterio de datos de `product-backlog.md` ("condiciones comerciales pactadas").

---

## Proposed Changes

### Backend — Dominio y Base de Datos

#### [NEW] [create_suppliers_table.php](file:///c:/Users/chiar/OneDrive/Documentos/Chiara/Proyectos/la-linda-app/database/migrations/2026_08_30_000001_create_suppliers_table.php)
- Columnas:
  - `id`: bigserial PK
  - `business_name`: string(255) `NOT NULL`
  - `business_name_normalized`: string(255) `NOT NULL` (para orden y búsquedas normalizadas)
  - `tax_id`: string(11) `NOT NULL UNIQUE` (CUIT de 11 dígitos numéricos sin guiones)
  - `tax_condition`: string(50) `NOT NULL`
  - `address`: string(255) `NULL`
  - `rubro`: string(100) `NULL`
  - `bank_account`: string(255) `NULL` (CBU / Alias / Banco / Datos de cuenta)
  - `commercial_terms`: text `NULL` (plazos de pago, descuentos pactados, etc.)
  - `is_active`: boolean `NOT NULL DEFAULT true` con índice
  - `created_at`, `updated_at`: timestamps
- Índices adicionales: sobre `business_name_normalized`, `rubro` y `tax_condition` para acelerar filtros.

#### [NEW] [SupplierTaxCondition.php](file:///c:/Users/chiar/OneDrive/Documentos/Chiara/Proyectos/la-linda-app/app/Enums/Purchasing/SupplierTaxCondition.php)
- Enum respaldado en string con los valores oficiales:
  - `ResponsibleInscripto = 'responsable_inscripto'` → *"IVA Responsable Inscripto"*
  - `Monotributo = 'monotributo'` → *"Responsable Monotributo"*
  - `Exento = 'exento'` → *"IVA Exento"*
  - `NoResponsable = 'no_responsable'` → *"IVA No Responsable"*
  - `ConsumidorFinal = 'consumidor_final'` → *"Consumidor Final"*
  - `ExteriorNoCategorizado = 'exterior_no_categorizado'` → *"Proveedor del Exterior / No Categorizado"*
- Métodos: `label(): string` y `static toOptions(): array` para alimentar selects del frontend.

#### [NEW] [ValidCuit.php](file:///c:/Users/chiar/OneDrive/Documentos/Chiara/Proyectos/la-linda-app/app/Rules/Purchasing/ValidCuit.php)
- Regla de validación de Laravel (`ValidationRule`):
  - Sanitiza guiones y espacios.
  - Verifica longitud exacta de 11 dígitos numéricos.
  - Verifica tipos de CUIT válidos (prefijos 20, 23, 24, 27, 30, 33, 34).
  - Aplica algoritmo Módulo 11 con los multiplicadores `[5, 4, 3, 2, 7, 6, 5, 4, 3, 2]` y compara el dígito verificador obtenido.

#### [NEW] [Supplier.php](file:///c:/Users/chiar/OneDrive/Documentos/Chiara/Proyectos/la-linda-app/app/Models/Purchasing/Supplier.php)
- Atributos rellenables: `business_name`, `tax_id`, `tax_condition`, `address`, `rubro`, `bank_account`, `commercial_terms`, `is_active`.
- Trait: `NormalizesUniqueAttributes` mapeando `business_name` a `business_name_normalized`.
- Casts: `is_active => boolean`, `tax_condition => SupplierTaxCondition::class`.
- Scopes: `scopeActive(Builder $query)`.
- Métodos auxiliares: `hasAssociatedRecords(): bool` para comprobar si tiene comprobantes o pagos antes de baja física o advertencias.

---

### Backend — Validaciones y DTOs

#### [NEW] [StoreSupplierRequest.php](file:///c:/Users/chiar/OneDrive/Documentos/Chiara/Proyectos/la-linda-app/app/Http/Requests/Purchasing/StoreSupplierRequest.php)
- Validación de entrada:
  - `business_name`: `required|string|max:255`
  - `tax_id`: `['required', 'string', new ValidCuit, 'unique:suppliers,tax_id']`
  - `tax_condition`: `['required', Rule::enum(SupplierTaxCondition::class)]`
  - `address`: `nullable|string|max:255`
  - `rubro`: `nullable|string|max:100`
  - `bank_account`: `nullable|string|max:255`
  - `commercial_terms`: `nullable|string|max:1000`
  - `is_active`: `sometimes|boolean`
- Sanitización de `tax_id`: remueve guiones y espacios antes de validar.

#### [NEW] [UpdateSupplierRequest.php](file:///c:/Users/chiar/OneDrive/Documentos/Chiara/Proyectos/la-linda-app/app/Http/Requests/Purchasing/UpdateSupplierRequest.php)
- Validación de actualización:
  - `business_name`: `required|string|max:255`
  - `tax_id`: `['required', 'string', new ValidCuit, Rule::unique('suppliers', 'tax_id')->ignore($this->route('supplier'))]`
  - `tax_condition`: `['required', Rule::enum(SupplierTaxCondition::class)]`
  - `address`: `nullable|string|max:255`
  - `rubro`: `nullable|string|max:100`
  - `bank_account`: `nullable|string|max:255`
  - `commercial_terms`: `nullable|string|max:1000`
  - `is_active`: `sometimes|boolean`

#### [NEW] [SupplierData.php](file:///c:/Users/chiar/OneDrive/Documentos/Chiara/Proyectos/la-linda-app/app/Data/Purchasing/SupplierData.php)
- DTO tipado para Inertia y TypeScript:
  - `id`: int
  - `business_name`: string
  - `tax_id`: string (formateado con guiones `XX-XXXXXXXX-X` para lectura amigable o plano)
  - `tax_id_raw`: string (11 dígitos planos)
  - `tax_condition`: string (value del enum)
  - `tax_condition_label`: string (label descriptivo en español)
  - `address`: ?string
  - `rubro`: ?string
  - `bank_account`: ?string
  - `commercial_terms`: ?string
  - `is_active`: bool
  - `created_at`: ?string

#### [NEW] [SupplierTaxConditionOptionData.php](file:///c:/Users/chiar/OneDrive/Documentos/Chiara/Proyectos/la-linda-app/app/Data/Purchasing/SupplierTaxConditionOptionData.php)
- DTO para el selector de condiciones fiscales en frontend (`value`, `label`).

---

### Backend — Actions y Controlador

#### [NEW] [CreateSupplier.php](file:///c:/Users/chiar/OneDrive/Documentos/Chiara/Proyectos/la-linda-app/app/Actions/Purchasing/CreateSupplier.php)
- Encapsula la creación del proveedor en una transacción `DB::transaction`.
- Sanitiza y normaliza campos.
- Registra el evento en el log de auditoría (`Log::info("Proveedor creado: ...")`).

#### [NEW] [UpdateSupplier.php](file:///c:/Users/chiar/OneDrive/Documentos/Chiara/Proyectos/la-linda-app/app/Actions/Purchasing/UpdateSupplier.php)
- Encapsula la actualización de datos del proveedor.
- Registra en el log de auditoría los campos modificados.

#### [NEW] [ToggleSupplierStatus.php](file:///c:/Users/chiar/OneDrive/Documentos/Chiara/Proyectos/la-linda-app/app/Actions/Purchasing/ToggleSupplierStatus.php)
- Alterna `is_active`.
- Permite la baja lógica segura. Registra en log de auditoría.

#### [NEW] [DeleteSupplier.php](file:///c:/Users/chiar/OneDrive/Documentos/Chiara/Proyectos/la-linda-app/app/Actions/Purchasing/DeleteSupplier.php)
- Verifica si el proveedor tiene registros asociados (`hasAssociatedRecords()`). Si los tiene, lanza `ValidationException` indicando que la baja debe ser lógica. Si no tiene movimientos ni comprobantes, permite la eliminación física.

#### [NEW] [SupplierController.php](file:///c:/Users/chiar/OneDrive/Documentos/Chiara/Proyectos/la-linda-app/app/Http/Controllers/Purchasing/SupplierController.php)
- `index(Request $request)`: lista con filtros (búsqueda por razón social/CUIT/rubro, filtro de condición fiscal, filtro de estado), devuelve Inertia render `purchasing/suppliers/index` con `suppliers` y `taxConditions`.
- `store(StoreSupplierRequest $request, CreateSupplier $action)`
- `update(UpdateSupplierRequest $request, Supplier $supplier, UpdateSupplier $action)`
- `toggleStatus(Supplier $supplier, ToggleSupplierStatus $action)`
- `destroy(Supplier $supplier, DeleteSupplier $action)`

---

### Rutas y Navegación

#### [MODIFY] [routes/web.php](file:///c:/Users/chiar/OneDrive/Documentos/Chiara/Proyectos/la-linda-app/routes/web.php)
- Registrar grupo:
  ```php
  Route::prefix('purchasing/suppliers')->name('purchasing.suppliers.')->group(function () {
      Route::get('/', [SupplierController::class, 'index'])->name('index');
      Route::post('/', [SupplierController::class, 'store'])->name('store');
      Route::put('{supplier}', [SupplierController::class, 'update'])->name('update');
      Route::patch('{supplier}/toggle', [SupplierController::class, 'toggleStatus'])->name('toggle');
      Route::delete('{supplier}', [SupplierController::class, 'destroy'])->name('destroy');
  });
  ```

#### [MODIFY] [app-sidebar.tsx](file:///c:/Users/chiar/OneDrive/Documentos/Chiara/Proyectos/la-linda-app/resources/js/components/app-sidebar.tsx)
- Agregar grupo de navegación `Compras` con ítem `Proveedores` (ícono `Truck` o `Building2`), apuntando a la ruta `purchasing.suppliers.index`.

---

### Frontend — Pantallas y Componentes

#### [NEW] [index.tsx](file:///c:/Users/chiar/OneDrive/Documentos/Chiara/Proyectos/la-linda-app/resources/js/pages/purchasing/suppliers/index.tsx)
- Página principal del maestro de proveedores:
  - Encabezado con título "Proveedores" y descripción.
  - Barra de herramientas con:
    - Input de búsqueda en vivo (por razón social, CUIT o rubro).
    - Select de filtro por Condición Fiscal.
    - Select de filtro por Estado (Todos / Activos / Inactivos).
    - Botón "Nuevo Proveedor" para abrir modal de creación.
  - Tabla de proveedores con columnas: Razón Social, CUIT (formateado), Condición Fiscal, Rubro, Datos Bancarios / Contacto, Estado (Badge), y Menú de Acciones (Editar, Alternar Estado, Eliminar si no tiene registros).
  - Modal / Diálogo de Alta (`Create`) y Edición (`Edit`):
    - Razón Social (Input obligatorio).
    - CUIT (Input con máscara o validación de 11 dígitos).
    - Condición Fiscal (Select de lista cerrada obligatoria).
    - Rubro (Input opcional con sugerencias comunes).
    - Domicilio comercial (Input opcional).
    - Cuenta bancaria / CBU / Alias (Input opcional).
    - Condiciones comerciales pactadas (Textarea opcional).
    - Proveedor activo (Checkbox).
  - Manejo de feedback con `sonner` (`toast.success` / `toast.error`).

---

### Seeders y Factories

#### [NEW] [SupplierFactory.php](file:///c:/Users/chiar/OneDrive/Documentos/Chiara/Proyectos/la-linda-app/database/factories/Purchasing/SupplierFactory.php)
- Factory con generación de CUITs válidos reales (usando algoritmo de cálculo de DV), nombres comerciales de proveedores de consumo masivo, y estados variados.

#### [NEW] [SupplierSeeder.php](file:///c:/Users/chiar/OneDrive/Documentos/Chiara/Proyectos/la-linda-app/database/seeders/Purchasing/SupplierSeeder.php)
- Sembrado de proveedores iniciales representativos del rubro supermercado:
  - Molinos Río de la Plata S.A. (CUIT 30-50085862-8, RI, Alimentos secos)
  - Arcor S.A.I.C. (CUIT 30-50279317-5, RI, Golosinas y conservas)
  - Mastellone Hermanos S.A. (CUIT 30-50051184-9, RI, Lácteos)
  - Cervecería y Maltería Quilmes S.A.I.C.A. y G. (CUIT 30-50094946-1, RI, Bebidas)
  - Distribuidora Mayorista San Cayetano (Monotributo, Limpieza)
- Actualización de [DatabaseSeeder.php](file:///c:/Users/chiar/OneDrive/Documentos/Chiara/Proyectos/la-linda-app/database/seeders/DatabaseSeeder.php) para incluir `SupplierSeeder`.

---

### Tests Automatizados

#### [NEW] [SupplierTest.php](file:///c:/Users/chiar/OneDrive/Documentos/Chiara/Proyectos/la-linda-app/tests/Feature/Purchasing/SupplierTest.php)
- Suite completa en Pest:
  1. `guest cannot access supplier management` (redirige a login).
  2. `user can view suppliers list with filters` (búsqueda, estado, condición fiscal).
  3. `user can create a supplier with valid data and valid CUIT`.
  4. `supplier requires valid business name and tax id`.
  5. `supplier rejects invalid CUIT digit or invalid format`.
  6. `supplier rejects duplicate CUIT`.
  7. `supplier tax condition must belong to the closed enum list`.
  8. `user can update supplier details and change commercial terms`.
  9. `supplier status can be toggled (baja lógica)`.
  10. `supplier cannot be hard-deleted if it has associated records`.
  11. `audit log records creation and updates of suppliers`.

#### [NEW] [ValidCuitRuleTest.php](file:///c:/Users/chiar/OneDrive/Documentos/Chiara/Proyectos/la-linda-app/tests/Unit/Purchasing/ValidCuitRuleTest.php)
- Test unitario de la regla de CUIT:
  - CUITs válidos con y sin guiones (personas físicas 20/27, empresas 30/33).
  - CUITs inválidos: longitud errónea, prefijos no autorizados, dígito verificador erróneo, caracteres alfanuméricos.

---

## Verificación de la Definition of Done

| Criterio | Cómo se verifica |
| :--- | :--- |
| **Criterios de aceptación cumplidos** | Verificados punto a punto contra `product-backlog.md` (datos completos, CUIT Módulo 11, lista cerrada, baja lógica). |
| **Validaciones en backend y frontend** | Form Requests (`StoreSupplierRequest`, `UpdateSupplierRequest`) + validación de CUIT en backend y UI con feedback de errores. |
| **Encapsulación en Actions** | `CreateSupplier`, `UpdateSupplier`, `ToggleSupplierStatus`, `DeleteSupplier` con lógica aislada y transaccional. |
| **Tipado y DTOs** | `SupplierData` en `app/Data/Purchasing/` con generación de types TypeScript sincronizados (`npm run types:generate`). |
| **Rutas con Wayfinder** | Generación de Wayfinder routes (`php artisan wayfinder:generate`) y uso en componentes React. |
| **Pipeline de CI en verde** | `composer run ci:check` pasando al 100% (Pint, PHPStan, Pest, ESLint, Prettier, tsc). |
| **Datos de Demostración** | `SupplierSeeder` con proveedores reales listo para demostración del sprint. |

---

## Verification Plan

### Automated Tests
```powershell
# Ejecutar suite de pruebas de proveedores
php artisan test --compact --filter=SupplierTest
php artisan test --compact --filter=ValidCuitRuleTest

# Ejecutar suite general de tests
php artisan test --compact
```

### CI Checks
```powershell
# Verificación integral de calidad de código
vendor/bin/pint --dirty --format agent
composer run types:check
npm run types:generate
php artisan wayfinder:generate
npm run lint:check
npm run format:check
npm run types:check
```

### Manual Verification
1. Iniciar sesión y navegar al nuevo menú **Compras > Proveedores**.
2. Crear un nuevo proveedor con CUIT válido (ej. `30-50085862-8`), condición fiscal `IVA Responsable Inscripto`, rubro y condiciones comerciales.
3. Intentar crear un proveedor con CUIT inválido (ej. `30-50085862-0`) y comprobar el rechazo con mensaje explicativo.
4. Probar búsqueda y filtros combinados (por nombre, rubro, estado activo/inactivo).
5. Editar un proveedor existente y verificar que los cambios persistan y se reflejen en la tabla.
6. Alternar estado (activo / inactivo) y verificar la baja lógica.
