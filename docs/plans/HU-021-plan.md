# HU-021 — Administrar clientes

Módulo de Clientes (`Customers` / `CLI`): administración centralizada del maestro de clientes de Supermercados La Linda (ABM, validación rigurosa de CUIT con algoritmo Módulo 11 oficial argentino de AFIP/ARCA para Responsables Inscriptos, DNI opcional para Consumidor Final, tipos de persona física y jurídica, condiciones fiscales homologadas, bajas lógicas obligatorias ante operaciones de venta registradas, y protección estricta del cliente genérico por defecto "Consumidor Final" inmutable e ineliminable). Es la historia base de clientes del Sprint 3 y no depende de ninguna otra historia previa.

## Criterios de aceptación (de `product-backlog.md`)

1. **Datos:** tipo de persona (física o jurídica), razón social o nombre y apellido, CUIT o DNI, condición fiscal, domicilio, teléfono, correo electrónico, estado.
2. **Validaciones:**
   - condición fiscal obligatoria.
   - para responsable inscripto el CUIT es obligatorio, único y con dígito verificador válido.
   - para consumidor final el documento es opcional.
   - correo con formato válido cuando se informa.
   - la baja de un cliente con ventas asociadas es siempre lógica.
3. **Comportamiento:**
   - existe un cliente genérico Consumidor Final que no se puede modificar ni eliminar y que se utiliza por defecto en las ventas de mostrador sin identificación del comprador.

---

## Investigación del código existente

| Artefacto | Ubicación | Propósito / Patrón a replicar |
| :--- | :--- | :--- |
| **Modelos y Normalización** | `app/Models/Purchasing/Supplier.php`, `app/Concerns/NormalizesUniqueAttributes.php` | Normalización de nombre en `name_normalized` para orden y búsqueda case/accent-insensitive, casts tipados, `scopeActive()`, `hasAssociatedRecords()`. |
| **Enums tipados** | `app/Enums/Purchasing/SupplierTaxCondition.php` | Enums nativos de PHP respaldados en string con métodos `label(): string` y `toOptions(): array` para alimentar selects del frontend. |
| **Reglas de CUIT** | `app/Rules/Purchasing/ValidCuit.php` | Algoritmo Módulo 11 con multiplicadores `[5,4,3,2,7,6,5,4,3,2]`, validación de prefijos válidos y sanitización de dígitos. |
| **Acciones de Negocio** | `app/Actions/Purchasing/CreateSupplier.php`, `app/Actions/Purchasing/UpdateSupplier.php`, `app/Actions/Purchasing/DeleteSupplier.php` | Encapsulación de lógica en transacciones DB, validaciones de dominio mediante `ValidationException`, sin lógica en controladores. |
| **Data Objects (DTO)** | `app/Data/Purchasing/SupplierData.php` | Clases que extienden `Spatie\LaravelData\Data` para salida tipada y autogeneración de TypeScript (`npm run types:generate`). |
| **Controladores** | `app/Http/Controllers/Purchasing/SupplierController.php` | Métodos delgados (`index`, `store`, `update`, `toggleStatus`, `destroy`) delegando en Requests y Actions. |
| **Componentes de UI** | `resources/js/pages/purchasing/suppliers/index.tsx`, `resources/js/components/ui/` | Tablas con shadcn/ui (`Table`, `Badge`, `Dialog`, `Input`, `Select`, `Button`), feedback con Sonner y rutas tipadas con Wayfinder. |
| **Navegación** | `resources/js/components/app-sidebar.tsx` | Incorporación en la sección "Ventas" con acceso a "Clientes" e ícono `Users`. |

> **Nota sobre el DER del Sprint Backlog 3:** El diagrama Mermaid en `sprint-backlog-3.md` (sección `customers - HU-021 / HU-022`) proyecta la columna `price_list_id` (FK nullable a `price_lists`). Como se especifica en la propia historia `HU-022` del sprint ("Agregar columna price_list_id (nullable, FK a price_lists) en customers"), la tabla `price_lists` aún no existe (se crea en `HU-011`). Por lo tanto, `price_list_id` se incorporará a `customers` en la migración de `HU-022` cuando `price_lists` esté disponible, manteniendo `HU-021` 100% independiente.

---

## Proposed Changes

### Backend — Base de Datos y Dominio

#### [NEW] [create_customers_table.php](file:///d:/Users/elgua/Documents/Programacion/PROYECTOS/la-linda-app/database/migrations/2026_09_19_000001_create_customers_table.php)
- Columnas:
  - `id`: bigserial PK
  - `person_type`: string(20) `NOT NULL` (`fisica`, `juridica`)
  - `name`: string(255) `NOT NULL` (razón social o nombre y apellido)
  - `name_normalized`: string(255) `NOT NULL` indexado (para búsquedas y ordenación case-insensitive)
  - `id_type`: string(20) `NOT NULL` (`cuit`, `dni`, `sin_identificar`)
  - `id_number`: string(20) `NULL` único (dígitos sanitizados; admite múltiples NULLs para clientes sin documento)
  - `tax_condition`: string(50) `NOT NULL` indexado (`responsable_inscripto`, `monotributo`, `consumidor_final`, `exento`)
  - `address`: string(255) `NULL`
  - `phone`: string(50) `NULL`
  - `email`: string(255) `NULL` indexado
  - `is_active`: boolean `NOT NULL DEFAULT true` indexado
  - `is_default`: boolean `NOT NULL DEFAULT false` indexado (para el cliente genérico "Consumidor Final" protegido)
  - `created_at`, `updated_at`: timestamps
- Índices adicionales:
  - `unique('id_number')`
  - `index(['is_active', 'is_default'])`

#### [NEW] [PersonType.php](file:///d:/Users/elgua/Documents/Programacion/PROYECTOS/la-linda-app/app/Enums/Customers/PersonType.php)
- Enum respaldado en string con casos:
  - `Fisica = 'fisica'` → *"Persona Física"*
  - `Juridica = 'juridica'` → *"Persona Jurídica"*
- Métodos: `label(): string` y `static toOptions(): array`.

#### [NEW] [CustomerIdType.php](file:///d:/Users/elgua/Documents/Programacion/PROYECTOS/la-linda-app/app/Enums/Customers/CustomerIdType.php)
- Enum respaldado en string con casos:
  - `Cuit = 'cuit'` → *"CUIT"*
  - `Dni = 'dni'` → *"DNI"*
  - `SinIdentificar = 'sin_identificar'` → *"Sin identificar"*
- Métodos: `label(): string` y `static toOptions(): array`.

#### [NEW] [CustomerTaxCondition.php](file:///d:/Users/elgua/Documents/Programacion/PROYECTOS/la-linda-app/app/Enums/Customers/CustomerTaxCondition.php)
- Enum respaldado en string con casos homologados:
  - `ResponsibleInscripto = 'responsable_inscripto'` → *"IVA Responsable Inscripto"*
  - `Monotributo = 'monotributo'` → *"Responsable Monotributo"*
  - `ConsumidorFinal = 'consumidor_final'` → *"Consumidor Final"*
  - `Exento = 'exento'` → *"IVA Exento"*
- Métodos: `label(): string` y `static toOptions(): array`.

#### [NEW] [ValidCuit.php](file:///d:/Users/elgua/Documents/Programacion/PROYECTOS/la-linda-app/app/Rules/Customers/ValidCuit.php)
- Regla de validación propia del módulo `Customers` (algoritmo Módulo 11 AFIP, longitud de 11 dígitos, prefijos válidos `[20, 23, 24, 27, 30, 33, 34]`, métodos estáticos `sanitize(?string)` y `format(?string)`).

#### [NEW] [Customer.php](file:///d:/Users/elgua/Documents/Programacion/PROYECTOS/la-linda-app/app/Models/Customers/Customer.php)
- Atributos rellenables: `person_type`, `name`, `id_type`, `id_number`, `tax_condition`, `address`, `phone`, `email`, `is_active`, `is_default`.
- Trait: `NormalizesUniqueAttributes` mapeando `name` a `name_normalized`.
- Casts:
  - `person_type => PersonType::class`
  - `id_type => CustomerIdType::class`
  - `tax_condition => CustomerTaxCondition::class`
  - `is_active => 'boolean'`
  - `is_default => 'boolean'`
- Scopes:
  - `scopeActive(Builder $query): Builder`
  - `scopeDefault(Builder $query): Builder` (para obtener rápidamente el Consumidor Final)
- Métodos auxiliares:
  - `hasAssociatedRecords(): bool`: chequea si tiene ventas u operaciones asociadas (cuando se construya el módulo de ventas `sales`, o devuelve `false` si la tabla no existe).
  - `isProtected(): bool`: `$this->is_default`.
  - `formattedIdNumber(): ?string`: formatea con guiones si es CUIT, o devuelve DNI directo.

#### [NEW] [CustomerFactory.php](file:///d:/Users/elgua/Documents/Programacion/PROYECTOS/la-linda-app/database/factories/Customers/CustomerFactory.php)
- Factory con estados:
  - `responsableInscripto()` (Persona Jurídica o Física con CUIT válido y condición `responsable_inscripto`).
  - `consumidorFinal()` (Persona Física, DNI o sin identificar, condición `consumidor_final`).
  - `monotributo()` (Persona Física o Jurídica con CUIT).
  - `defaultCustomer()` (Consumidor Final genérico, `is_default = true`, `id_number = null`).

#### [NEW] [CustomerSeeder.php](file:///d:/Users/elgua/Documents/Programacion/PROYECTOS/la-linda-app/database/seeders/Customers/CustomerSeeder.php)
- Crea el registro inmutable "Consumidor Final" por defecto:
  ```php
  Customer::firstOrCreate(
      ['is_default' => true],
      [
          'person_type' => PersonType::Fisica,
          'name' => 'Consumidor Final',
          'id_type' => CustomerIdType::SinIdentificar,
          'id_number' => null,
          'tax_condition' => CustomerTaxCondition::ConsumidorFinal,
          'is_active' => true,
          'is_default' => true,
      ]
  );
  ```
- Genera además clientes iniciales de prueba (ej. empresa RI con CUIT verificado, monotributista y clientes particulares).

#### [MODIFY] [DatabaseSeeder.php](file:///d:/Users/elgua/Documents/Programacion/PROYECTOS/la-linda-app/database/seeders/DatabaseSeeder.php)
- Agrega `CustomerSeeder::class` al llamado `call([...])`.

---

### Backend — Acciones de Negocio

#### [NEW] [CreateCustomer.php](file:///d:/Users/elgua/Documents/Programacion/PROYECTOS/la-linda-app/app/Actions/Customers/CreateCustomer.php)
- Recibe array de datos validados.
- Transacción de BD.
- Sanitiza `id_number` a solo dígitos si existe.
- Crea el modelo `Customer` (forzando `is_default => false`).
- Loguea auditoría de creación.
- Retorna el `Customer`.

#### [NEW] [UpdateCustomer.php](file:///d:/Users/elgua/Documents/Programacion/PROYECTOS/la-linda-app/app/Actions/Customers/UpdateCustomer.php)
- Valida si `$customer->is_default`:
  - Si es el cliente protegido por defecto, lanza `ValidationException::withMessages(['customer' => 'El cliente por defecto Consumidor Final es inmutable y no puede ser modificado.'])`.
- Si tiene registros asociados (`hasAssociatedRecords()`), valida que no se modifique el número de identificación (`id_number`) ni la condición fiscal (`tax_condition`).
- Transacción de BD.
- Actualiza campos editables.
- Loguea auditoría de modificación.
- Retorna el `Customer`.

#### [NEW] [DeleteCustomer.php](file:///d:/Users/elgua/Documents/Programacion/PROYECTOS/la-linda-app/app/Actions/Customers/DeleteCustomer.php)
- Valida si `$customer->is_default`:
  - Lanza `ValidationException::withMessages(['customer' => 'El cliente por defecto Consumidor Final no puede ser eliminado.'])`.
- Valida si `$customer->hasAssociatedRecords()`:
  - Lanza `ValidationException::withMessages(['customer' => 'No se puede eliminar físicamente un cliente que posee operaciones registradas. Realizá la baja lógica desactivándolo.'])`.
- Elimina físicamente el registro.
- Loguea auditoría de eliminación física.

#### [NEW] [ToggleCustomerStatus.php](file:///d:/Users/elgua/Documents/Programacion/PROYECTOS/la-linda-app/app/Actions/Customers/ToggleCustomerStatus.php)
- Valida si `$customer->is_default`:
  - Lanza `ValidationException::withMessages(['customer' => 'El cliente por defecto Consumidor Final no puede ser desactivado.'])`.
- Invierte `$customer->is_active`.
- Loguea auditoría del cambio de estado.
- Retorna el `Customer`.

---

### Backend — Validaciones (Form Requests) y DTOs

#### [NEW] [StoreCustomerRequest.php](file:///d:/Users/elgua/Documents/Programacion/PROYECTOS/la-linda-app/app/Http/Requests/Customers/StoreCustomerRequest.php)
- `prepareForValidation()`:
  - Si `id_type === 'sin_identificar'`, limpia `id_number` a `null`.
  - Si `id_number` tiene valor, sanitiza con `preg_replace('/\D/', '', ...)`.
- Reglas:
  - `person_type`: `['required', Rule::enum(PersonType::class)]`.
  - `name`: `['required', 'string', 'max:255']`.
  - `tax_condition`: `['required', Rule::enum(CustomerTaxCondition::class)]`.
  - `id_type`: `['required', Rule::enum(CustomerIdType::class)]`.
  - `id_number`:
    - Para Responsable Inscripto: obligatorio, `id_type` debe ser `cuit`, CUIT con dígito verificador válido (`new ValidCuit`), único en `customers,id_number`.
    - Para Persona Jurídica: `id_type` debe ser `cuit`, obligatorio, CUIT válido.
    - Para Consumidor Final:
      - Si `id_type === 'sin_identificar'`: `id_number` opcional / null.
      - Si `id_type === 'dni'`: requerido, numérico, entre 7 y 9 dígitos, único en `customers,id_number`.
      - Si `id_type === 'cuit'`: requerido, CUIT válido, único.
    - Para Monotributo / Exento: `id_type` debe ser `cuit`, obligatorio, CUIT válido, único.
  - `address`: `['nullable', 'string', 'max:255']`.
  - `phone`: `['nullable', 'string', 'max:50']`.
  - `email`: `['nullable', 'string', 'email:rfc', 'max:255']`.
  - `is_active`: `['sometimes', 'boolean']`.

#### [NEW] [UpdateCustomerRequest.php](file:///d:/Users/elgua/Documents/Programacion/PROYECTOS/la-linda-app/app/Http/Requests/Customers/UpdateCustomerRequest.php)
- Mismas validaciones que Store, agregando `Rule::unique('customers', 'id_number')->ignore($customer->id)` para permitir mantener el mismo CUIT/DNI propio.

#### [NEW] [CustomerData.php](file:///d:/Users/elgua/Documents/Programacion/PROYECTOS/la-linda-app/app/Data/Customers/CustomerData.php)
- Propiedades tipadas:
  - `id`: int
  - `person_type`: string
  - `person_type_label`: string
  - `name`: string
  - `id_type`: string
  - `id_type_label`: string
  - `id_number`: ?string (formateado con guiones si es CUIT)
  - `id_number_raw`: ?string (dígitos puros)
  - `tax_condition`: string
  - `tax_condition_label`: string
  - `address`: ?string
  - `phone`: ?string
  - `email`: ?string
  - `is_active`: bool
  - `is_default`: bool
  - `has_associated_records`: bool
  - `created_at`: ?string
- Método estático `fromModel(Customer $customer): self`.

---

### Backend — Controlador y Rutas

#### [NEW] [CustomerController.php](file:///d:/Users/elgua/Documents/Programacion/PROYECTOS/la-linda-app/app/Http/Controllers/Customers/CustomerController.php)
- `index(Request $request): Response`:
  - Filtros: `search` (busca en `name_normalized`, `id_number`, o `email`), `tax_condition`, `person_type`, `status`.
  - Orden: `is_default DESC` (Consumidor Final primero en la cabecera), seguido por `name ASC`.
  - Retorna a Inertia `customers/index` con `customers => CustomerData::collect($customers)`, `taxConditions`, `personTypes`, `idTypes`, y `filters`.
- `store(StoreCustomerRequest $request, CreateCustomer $action): RedirectResponse`
- `update(UpdateCustomerRequest $request, Customer $customer, UpdateCustomer $action): RedirectResponse`
- `toggleStatus(Customer $customer, ToggleCustomerStatus $action): RedirectResponse`
- `destroy(Customer $customer, DeleteCustomer $action): RedirectResponse`

#### [MODIFY] [routes/web.php](file:///d:/Users/elgua/Documents/Programacion/PROYECTOS/la-linda-app/routes/web.php)
- Registra el grupo de rutas bajo el prefijo `customers`:
  ```php
  Route::prefix('customers')->name('customers.')->group(function () {
      Route::get('/', [CustomerController::class, 'index'])->name('index');
      Route::post('/', [CustomerController::class, 'store'])->name('store');
      Route::put('{customer}', [CustomerController::class, 'update'])->name('update');
      Route::patch('{customer}/toggle', [CustomerController::class, 'toggleStatus'])->name('toggle');
      Route::delete('{customer}', [CustomerController::class, 'destroy'])->name('destroy');
  });
  ```

---

### Frontend — Navegación y Vistas

#### [MODIFY] [app-sidebar.tsx](file:///d:/Users/elgua/Documents/Programacion/PROYECTOS/la-linda-app/resources/js/components/app-sidebar.tsx)
- Agrega entrada "Clientes" en el grupo `Ventas` con el ícono `Users` de `lucide-react`:
  ```tsx
  {
    label: 'Ventas',
    items: [
      {
        title: 'Clientes',
        href: customers(),
        icon: Users,
      },
      {
        title: 'Medios de Pago',
        href: paymentMethods(),
        icon: CreditCard,
      },
    ],
  },
  ```

#### [NEW] [index.tsx](file:///d:/Users/elgua/Documents/Programacion/PROYECTOS/la-linda-app/resources/js/pages/customers/index.tsx)
- Página Inertia de gestión de clientes:
  - Header con título "Clientes", descripción y botón "+ Nuevo Cliente".
  - Barra de filtros combinables (Búsqueda reactiva, Condición fiscal, Tipo de persona, Estado activo/inactivo).
  - Tabla completa con componentes shadcn/ui:
    - Columna Cliente: Nombre y badge `Consumidor Final (Por defecto)` si `is_default`.
    - Columna Identificación: CUIT formateado (`XX-XXXXXXXX-X`) o DNI, con badge de tipo o "Sin identificar".
    - Columna Tipo / Condición: Badges semánticos para `Responsable Inscripto`, `Monotributo`, `Consumidor Final`, `Exento`.
    - Columna Contacto: Domicilio, teléfono, correo.
    - Columna Estado: Badge activo/inactivo.
    - Columna Acciones:
      - Si `is_default`: botones deshabilitados con tooltip informativo ("El cliente por defecto no puede ser modificado ni eliminado").
      - Si no es default: botón Editar (abre diálogo modal), botón Cambiar Estado (toggle), botón Eliminar (abre diálogo de confirmación).
  - Modal de Alta / Edición:
    - Selector dinámico de Tipo de persona (`Física` / `Jurídica`).
    - Selector de Condición fiscal (`Responsable Inscripto`, `Monotributo`, `Consumidor Final`, `Exento`).
    - Selector de Tipo de documento (`CUIT`, `DNI`, `Sin identificar`), con ajuste automático reactivo según la condición fiscal (ej: si se elige RI o Jurídica, bloquea y fuerza CUIT; si se elige Consumidor Final, habilita DNI o Sin identificar).
    - Campo de número de documento con validación visual.
    - Campos de Razón Social / Nombre, Domicilio, Teléfono, Correo electrónico.
    - Manejo de errores con `InputError`, toasts de Sonner y estado `processing`.

---

### Tests

#### [NEW] [CustomerManagementTest.php](file:///d:/Users/elgua/Documents/Programacion/PROYECTOS/la-linda-app/tests/Feature/Customers/CustomerManagementTest.php)
- Tests de Feature con Pest:
  1. `index`: lista clientes y renderiza la página con datos y opciones.
  2. `filters`: filtra correctamente por búsqueda (nombre, CUIT, DNI), condición fiscal, tipo de persona y estado.
  3. `store`: crea cliente Responsable Inscripto con CUIT válido y único.
  4. `store validation RI`: rechaza Responsable Inscripto sin CUIT, con CUIT inválido (dígito verificador incorrecto) o CUIT duplicado.
  5. `store consumidor final`: crea Consumidor Final sin documento o con DNI.
  6. `store juridica`: rechaza persona jurídica sin CUIT.
  7. `email validation`: valida formato de correo electrónico cuando se ingresa.
  8. `update`: actualiza datos de un cliente común.
  9. `update default customer`: impide modificar el cliente protegido Consumidor Final.
  10. `toggle status default customer`: impide desactivar el cliente Consumidor Final.
  11. `destroy default customer`: impide eliminar el cliente Consumidor Final.
  12. `destroy customer with sales`: impide eliminación física de cliente con operaciones asociadas exigiendo baja lógica.
  13. `destroy customer without sales`: permite eliminación física limpia si no tiene operaciones asociadas.

#### [NEW] [CustomerModelTest.php](file:///d:/Users/elgua/Documents/Programacion/PROYECTOS/la-linda-app/tests/Unit/Customers/CustomerModelTest.php)
- Tests de Unit con Pest:
  1. Normalización de nombre en `name_normalized` al guardar.
  2. Casts de enums (`PersonType`, `CustomerIdType`, `CustomerTaxCondition`).
  3. Formato de CUIT con guiones.
  4. Seeder genera cliente por defecto con `is_default = true`.

---

## Verificación de la Definition of Done

| Criterio | Cómo se verifica |
| :--- | :--- |
| **Padrón con datos completos** | Creación y visualización de clientes con persona física/jurídica, nombre, CUIT/DNI, condición fiscal, contacto y estado. |
| **Validación rigurosa de CUIT** | Algoritmo Módulo 11 oficial argentino validado en backend para Responsables Inscriptos; rechazo de CUITs mal formados o con dígito incorrecto. |
| **Documento opcional para Consumidor Final** | Registro exitoso de clientes consumidor final sin documento (`sin_identificar`) y con DNI. |
| **Unicidad de CUIT/DNI** | Constraint de base de datos y validación de Form Request que impiden duplicar números de documento existentes. |
| **Baja lógica obligatoria ante ventas** | `DeleteCustomer` rechaza borrado físico si tiene transacciones y orienta a la desactivación lógica. |
| **Cliente Consumidor Final protegido** | Seeder crea el registro inmutable; `UpdateCustomer`, `DeleteCustomer` y `ToggleCustomerStatus` bloquean cualquier alteración; la UI deshabilita las acciones. |
| **Filtros combinables y UI** | Búsqueda por texto y filtros por condición fiscal, tipo de persona y estado en tabla shadcn/ui. |
| **Automatización de Trello** | Integrado con el flujo del equipo en `feature/HU-021-administrar-clientes` (tarjeta ya en "En Progreso" asignada a Azael). |

---

## Verification Plan

### Automated Tests
- Ejecución de los tests unitarios y de feature específicos:
  ```bash
  php artisan test --compact --filter=Customer
  ```
- Ejecución completa de la suite de pruebas del proyecto:
  ```bash
  php artisan test --compact
  ```

### Manual Verification
1. Ingresar al sistema e ir al menú lateral -> sección "Ventas" -> "Clientes".
2. Constatar que aparece el cliente "Consumidor Final" por defecto con su insignia de protegido.
3. Intentar editar o eliminar el Consumidor Final (verificar que los botones están deshabilitados en UI y protegidos en backend).
4. Crear un cliente Responsable Inscripto con un CUIT inválido (ej. `30-50085862-0`) y comprobar mensaje de error.
5. Crear el cliente con CUIT válido (ej. `30-50085862-8`) y verificar guardado exitoso y formato con guiones en la tabla.
6. Crear un cliente Consumidor Final sin documento y verificar guardado.
7. Filtrar por condición fiscal "Responsable Inscripto" y verificar que la tabla se actualiza.
8. Desactivar un cliente común con el botón de toggle y constatar el cambio de badge.

### CI checks
- Formateador Pint:
  ```bash
  vendor/bin/pint --dirty --format agent
  ```
- Análisis estático PHPStan:
  ```bash
  composer run types:check
  ```
- Generación de tipos TypeScript (Laravel Data):
  ```bash
  npm run types:generate
  ```
- Generación de rutas TypeScript (Wayfinder):
  ```bash
  php artisan wayfinder:generate
  ```
- Verificación de tipos TypeScript:
  ```bash
  npm run types:check
  ```
- Linter frontend:
  ```bash
  npm run lint:check
  ```
- Formato frontend:
  ```bash
  npm run format:check
  ```

---

## Open Questions

*No hay preguntas abiertas pendientes. Todos los criterios de negocio, enums, derivación de CUIT, estructura del DER y protección del Consumidor Final están plenamente especificados en el product backlog y en el sprint backlog 3.*
