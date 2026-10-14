# HU-003 — Administrar los usuarios del sistema

Módulo de Seguridad (`Security` / `SEG`): gestión integral del personal interno del sistema (ABM de usuarios, asignación de roles operativos de La Linda, sucursal predeterminada opcional, activación/desactivación lógica con salvaguardas de negocio, blanqueo o definición de contraseñas seguras, cierre del autoregistro público en el panel interno y expulsión de usuarios inactivos).

---

## Criterios de aceptación (de `product-backlog.md`)

1. **Datos:** nombre y apellido, correo electrónico, contraseña inicial o temporal, rol asignado (`users.role`, enum `UserRole`: `administrador`, `gerente`, `compras`, `deposito`, `cajero`), sucursal predeterminada opcional (`branch_id`), estado (`is_active`: `activo` o `inactivo`) y fecha de alta.
2. **Validaciones:**
   - nombre, correo electrónico y rol de personal interno son obligatorios.
   - el correo electrónico tiene formato válido y es único en la tabla de usuarios (normalizado en minúsculas).
   - no se puede desactivar al propio usuario con sesión iniciada ni dejar al sistema sin ningún usuario con rol `administrador` activo.
   - la baja de un usuario es siempre lógica (`is_active = false`) para preservar la integridad referencial de movimientos de stock, órdenes, comprobantes, ventas, facturas, entregas de pedidos web y turnos de caja históricos.
   - no se puede dar de baja a un usuario que tenga un turno de caja abierto en ese momento.
3. **Comportamiento:**
   - al dar de alta un usuario o restablecer su contraseña, el Administrador puede definir una contraseña o generar una aleatoria segura que se muestra por única vez en pantalla para entregársela al empleado.
   - un usuario en estado `inactivo` no puede iniciar sesión y, si tenía sesión activa al ser desactivado, el middleware de autenticación la cierra en su próxima petición.
   - el listado de usuarios del panel interno excluye las cuentas con rol `cliente` de la tienda online (`customer_id IS NOT NULL` / `role = 'cliente'`) y permite buscar por nombre o correo y filtrar por rol y estado.
   - se deshabilita la ruta pública `/register` de Fortify para el panel interno: el personal solo es dado de alta por un Administrador.
4. **Verificación:** el Administrador crea un usuario con rol `cajero`, inicia sesión con él en otra ventana, luego lo desactiva desde la cuenta de Administrador y comprueba que el cajero ya no puede operar ni volver a iniciar sesión, y que no es posible desactivar al último `administrador` activo.

---

## Revisión y Evaluación de los Archivos Externos (`C:\Users\Usuario\Downloads\HU-003-Archivos`)

Se analizaron minuciosamente los 11 archivos provistos por el usuario. Diagnóstico y adaptaciones requeridas:

| Archivo analizado | ¿Sirve para la HU? | Diagnóstico y mejoras necesarias |
| :--- | :---: | :--- |
| `2024_05_20_000000_add_branch_and_active_to_users_table.php` | **Parcialmente** | En el repo ya existe `database/migrations/2026_10_12_100001_add_branch_and_active_to_users_table.php` (portón D1) que agregó `branch_id` e `is_active`. La parte valiosa es la migración de datos de `personal_interno` a `administrador`, la cual se implementará en una migración separada respetando la fecha del Sprint 5 (`2026_10_14_000001_migrate_personal_interno_to_administrador.php`). |
| `app.php` | **Referencia** | Es solo un esqueleto que muestra cómo agregar el middleware. Se registrará `EnsureUserIsActive::class` en el `bootstrap/app.php` real del proyecto en el grupo `web`. |
| `EnsureUserIsActive.php` | **Sí (con mejoras)** | 1. Mensaje en español (`'Su cuenta ha sido desactivada. Comuníquese con el administrador.'`).<br>2. Se debe ejecutar tanto antes como después de `$next($request)` para interceptar tanto sesiones ya abiertas que fueron desactivadas como el login directo de un usuario inactivo mediante Fortify. |
| `fortify.php` | **Sí** | Deshabilita `Features::registration()` en `config/fortify.php` para cerrar `/register` en el panel interno. |
| `CreateUser.php` | **Sí (con mejoras)** | 1. Viola `.ai/rules/data.md`: usaba `UserData` como entrada. Se adaptará para recibir datos validados tipados (`array` o parámetros explícitos) originados en un Form Request.<br>2. Permitir definir una contraseña manual o generarla aleatoriamente con `Str::password(12, true)` si viene vacía.<br>3. Normalizar email a minúsculas y trim. |
| `ResetUserPassword.php` | **Sí (con mejoras)** | Permitir recibir una contraseña definida por el Administrador opcionalmente, o generar una segura aleatoria si no se especificó. |
| `ToggleUserActive.php` | **Sí (con correcciones críticas)** | 1. **Corrección de Bug:** Usaba `$user->cashRegisterShifts()->whereNull('closed_at')->exists()`. En el repo no existe `CashRegisterShift`, la entidad real es `App\Models\Sales\CashSession`. Debe consultar `CashSession::query()->openForUser($user->id)->exists()`.<br>2. Comparar rol con el enum `UserRole::Administrador`.<br>3. Mensajes en español consistentes con el diseño de La Linda. |
| `UserController.php` | **Sí (con mejoras)** | 1. `index`: No filtraba cuentas de `cliente`, no permitía búsqueda combinada por nombre/email ni filtros por rol y estado. Debe incluir `UserRole` y `Branch` para los selectores.<br>2. **Faltaba `update`:** La HU exige modificar usuarios (nombre, rol, sucursal). Se añade la Action `UpdateUser` y el método `update()`.<br>3. Usar Form Requests para validación de entrada (`StoreUserRequest`, `UpdateUserRequest`).<br>4. Retornar DTO `UserData` paginado para Inertia y autorizar mediante Gate `manage-users`. |
| `UserData.php` | **Sí (reestructurado)** | Usaba atributos de validación para entrada (contrario a `.ai/rules/data.md`). Se reescribe como DTO de salida puro con `fromModel(User $user)` incluyendo `id`, `name`, `email`, `role`, `branch_id`, `branch_name`, `is_active`, `created_at`. |
| `UserManagementTest.php` | **Sí (con correcciones)** | 1. Corregir relación con `CashSession` en lugar de `CashRegisterShift`.<br>2. Ampliar tests para cubrir actualización de usuario, listado con filtros, exclusión de clientes, contraseñas manuales vs aleatorias. |
| `web.php` | **Sí (con ampliaciones)** | Agregar ruta `PUT settings/users/{user}` para actualización, integrarlas dentro de `routes/settings.php` bajo `can:manage-users`. |

---

## Investigación del código existente

| Artefacto | Ubicación | Propósito / Patrón a replicar |
| :--- | :--- | :--- |
| **Modelo User** | `app/Models/User.php` | Modelo con `role` (cast a `UserRole`), `branch_id`, `is_active`, relaciones `branch()`, `customer()`, scope `active()`. Actualizar `isInternal()` para validar que no sea cliente. |
| **Enum UserRole** | `app/Enums/Security/UserRole.php` | Ampliar casos a `Administrador = 'administrador'`, `Gerente = 'gerente'`, `Compras = 'compras'`, `Deposito = 'deposito'`, `Cajero = 'cajero'`, `Cliente = 'cliente'` con etiquetas legibles en español. |
| **Modelo CashSession** | `app/Models/Sales/CashSession.php` | Contiene `scopeOpenForUser(int $userId)` para validar turnos de caja abiertos al intentar desactivar. |
| **Configuración Fortify** | `config/fortify.php` | Deshabilitar `Features::registration()`. |
| **Middleware de Seguridad** | `app/Http/Middleware/EnsureUserIsActive.php` | Expulsión automática de usuarios inactivos e invalidación de sesión. |
| **Gate `manage-users`** | `app/Providers/AppServiceProvider.php` | Definir Gate `manage-users` para permitir acceso únicamente al rol `administrador`. |
| **Rutas Settings** | `routes/settings.php` | Grupo de rutas `settings.users.*` protegido por `['internal.staff', 'can:manage-users']`. |
| **Componentes de UI** | `resources/js/pages/organization/branches/index.tsx` | Referencia para el listado con tabla, filtros de búsqueda, badges de estado, modales con shadcn/ui (`Dialog`, `Input`, `Select`, `Button`) y Sonner toasts. |
| **Barra lateral** | `resources/js/components/app-sidebar.tsx` | Incorporar acceso a "Usuarios" en la sección de Configuración para usuarios con permiso `manage-users`. |

---

## Proposed Changes

### Backend — Dominio y Base de Datos

#### [NEW] `database/migrations/2026_10_14_000001_migrate_personal_interno_to_administrador.php`
- Migración de datos: actualiza todos los registros existentes con `role = 'personal_interno'` a `role = 'administrador'`.

#### [MODIFY] `app/Enums/Security/UserRole.php`
- Incorporar los cinco roles operativos del personal interno más el rol cliente:
  ```php
  case Administrador = 'administrador';
  case Gerente = 'gerente';
  case Compras = 'compras';
  case Deposito = 'deposito';
  case Cajero = 'cajero';
  case Cliente = 'cliente';
  ```
- Mantener temporalmente `PersonalInterno = 'personal_interno'` si fuera necesario para transiciones, o sustituirlo completamente tras la migración.
- Método `label(): string` para etiquetas amigables en español.
- Método `static internalRoles(): array` para alimentar selects del ABM excluyendo a `cliente`.

#### [MODIFY] `app/Models/User.php`
- Actualizar `isInternal(): bool` para comprobar `$this->role !== UserRole::Cliente` (cualquier rol operativo es personal interno).
- Asegurar relación `cashSessions(): HasMany` hacia `CashSession::class`.

#### [MODIFY] `config/fortify.php`
- Comentar `Features::registration()` en el array `features`.

#### [MODIFY] `app/Providers/AppServiceProvider.php`
- Registrar Gate `manage-users`:
  ```php
  Gate::define('manage-users', fn (User $user) => $user->role === UserRole::Administrador);
  ```

---

### Backend — Middleware y Actions

#### [NEW] `app/Http/Middleware/EnsureUserIsActive.php`
- Inspecciona la autenticación del usuario. Si `Auth::check() && ! Auth::user()->is_active`, cierra la sesión con `Auth::logout()`, invalida la sesión y redirige a `login` con error en español:
  `'Su cuenta ha sido desactivada. Comuníquese con el administrador.'`
- Evalúa tanto peticiones entrantes como peticiones de login entrantes para asegurar que un usuario inactivo nunca acceda.

#### [MODIFY] `bootstrap/app.php`
- Registrar `EnsureUserIsActive::class` en el array de middlewares web (`$middleware->web(append: [...])`).

#### [NEW] `app/Actions/Security/CreateUser.php`
- Crea un usuario interno en una transacción `DB::transaction`.
- Normaliza el email (`trim` + `mb_strtolower`).
- Acepta contraseña manual opcional; si no se provee, genera una con `Str::password(12, true)`.
- Retorna `['user' => $user, 'plain_password' => $plainPassword]`.

#### [NEW] `app/Actions/Security/UpdateUser.php`
- Actualiza nombre, email (normalizado), rol operativo y `branch_id`.
- Valida unicidad de email ignorando el usuario actual.

#### [NEW] `app/Actions/Security/ToggleUserActive.php`
- Aplica las tres reglas de negocio obligatorias:
  1. Impedir autodesactivación (`Auth::id() === $user->id`).
  2. Impedir desactivar al último administrador activo (`$user->role === UserRole::Administrador && User::where('role', UserRole::Administrador)->where('is_active', true)->count() <= 1`).
  3. Impedir desactivar si el usuario tiene un turno de caja abierto (`CashSession::query()->openForUser($user->id)->exists()`).
- Alterna el booleano `is_active` y guarda.

#### [NEW] `app/Actions/Security/ResetUserPassword.php`
- Restablece la contraseña del usuario con un valor provisto o generado con `Str::password(12, true)`.
- Retorna la contraseña en texto plano para mostrarla por única vez.

---

### Backend — Form Requests y DTOs

#### [NEW] `app/Http/Requests/Security/StoreUserRequest.php`
- Valida `name` (required, string, max:255), `email` (required, email, unique:users,email), `role` (required, enum:UserRole interno), `branch_id` (nullable, exists:branches,id), `password` (nullable, string, min:8).

#### [NEW] `app/Http/Requests/Security/UpdateUserRequest.php`
- Valida `name` (required, string, max:255), `email` (required, email, unique:users,email,{id}), `role` (required, enum:UserRole interno), `branch_id` (nullable, exists:branches,id).

#### [NEW] `app/Data/Security/UserData.php`
- DTO de salida para Inertia:
  ```php
  public int $id,
  public string $name,
  public string $email,
  public string $role,
  public string $role_label,
  public ?int $branch_id,
  public ?string $branch_name,
  public bool $is_active,
  public string $created_at,
  ```

---

### Backend — Controlador y Rutas

#### [NEW] `app/Http/Controllers/Security/UserController.php`
- `index(Request $request)`: Lista usuarios excluyendo cuentas cliente (`whereNull('customer_id')->where('role', '!=', UserRole::Cliente)`). Aplica búsqueda por nombre o email, filtros por rol y estado (`is_active`). Retorna datos paginados tipados con `UserData`, además del listado de sucursales activas y roles disponibles para los formularios.
- `store(StoreUserRequest $request, CreateUser $action)`: Ejecuta alta y redirige con flash `generated_password` y `success`.
- `update(UpdateUserRequest $request, User $user, UpdateUser $action)`: Modifica datos y redirige con `success`.
- `toggleActive(User $user, ToggleUserActive $action)`: Alterna estado activo/inactivo validando las salvaguardas.
- `resetPassword(Request $request, User $user, ResetUserPassword $action)`: Restablece la contraseña y retorna flash `generated_password`.

#### [MODIFY] `routes/settings.php`
- Registrar rutas dentro del grupo `internal.staff` protegidas por `can:manage-users`:
  ```php
  Route::prefix('settings/users')->name('settings.users.')->group(function () {
      Route::get('/', [UserController::class, 'index'])->name('index');
      Route::post('/', [UserController::class, 'store'])->name('store');
      Route::put('{user}', [UserController::class, 'update'])->name('update');
      Route::patch('{user}/toggle-active', [UserController::class, 'toggleActive'])->name('toggle-active');
      Route::post('{user}/reset-password', [UserController::class, 'resetPassword'])->name('reset-password');
  });
  ```

---

### Frontend — Interfaz de Usuario y Navegación

#### [NEW] `resources/js/pages/settings/users/index.tsx`
- Pantalla principal de administración de usuarios construida con componentes `shadcn/ui`:
  - Barra superior con buscador en tiempo real (nombre o correo), selector de filtro por rol y selector de filtro por estado (Todos, Activos, Inactivos).
  - Botón "Nuevo usuario" que abre modal de alta.
  - Tabla de usuarios: Nombre, Email, Rol (badge), Sucursal predeterminada, Estado (badge activo/inactivo), Fecha de alta y columna de Acciones.
  - Acciones por fila:
    - Editar usuario (abre modal de edición de nombre, rol y sucursal).
    - Desactivar / Activar usuario (con diálogo de confirmación y advertencias de negocio).
    - Restablecer contraseña (con opción de generar automática o ingresar una manual).
  - Modal de "Contraseña generada": al crear usuario o resetear contraseña, si la sesión flash contiene `generated_password`, se despliega un diálogo emergente destacando la contraseña con botón de "Copiar al portapapeles" y advertencia de que no volverá a mostrarse.
  - Paginación integrada con `TablePagination`.

#### [MODIFY] `resources/js/components/app-sidebar.tsx`
- Agregar enlace a "Usuarios" (`href: '/settings/users'`) en el menú lateral para usuarios autorizados.

---

### Tests Automatizados

#### [NEW] `tests/Feature/Security/UserManagementTest.php`
- Tests Pest exhaustivos cubriendo:
  1. Administrador lista usuarios del personal interno (comprobando que se excluyen cuentas con rol `cliente`).
  2. Búsqueda por término (nombre y email) y filtros por rol y estado.
  3. Creación de usuario con contraseña aleatoria generada y entrega en flash.
  4. Creación de usuario con contraseña definida manualmente.
  5. Edición de usuario (nombre, rol, sucursal).
  6. Restablecimiento de contraseña (aleatoria y definida) con entrega en flash.
  7. Bloqueo de autodesactivación con error de validación.
  8. Bloqueo de desactivación del último administrador activo.
  9. Bloqueo de desactivación de un usuario con turno de caja abierto (`CashSession` abierta).
  10. Desactivación exitosa de un usuario válido y activación de uno inactivo.
  11. Middleware `EnsureUserIsActive`: usuario inactivo no puede iniciar sesión.
  12. Middleware `EnsureUserIsActive`: usuario activo que es desactivado es desautenticado en su siguiente petición web.
  13. Usuario no administrador (o invitado) recibe `403 Forbidden` al intentar acceder a las rutas de gestión de usuarios.

---

## Verificación de la Definition of Done

| Criterio | Cómo se verifica |
| :--- | :--- |
| **Campos y datos obligatorios** | Form Requests `StoreUserRequest` y `UpdateUserRequest` validan nombre, email, rol y sucursal opcional. |
| **Unicidad y normalización de email** | Test de intento de duplicación de email y almacenamiento en minúsculas. |
| **Regla: no autodesactivarse** | Test de PATCH `settings.users.toggle-active` con el usuario en sesión recibiendo error de validación. |
| **Regla: último administrador activo** | Test intentando desactivar al único admin activo recibiendo error de validación. |
| **Regla: caja abierta** | Test intentando desactivar a un cajero con `CashSession` en estado `abierta` recibiendo error de validación. |
| **Visualización única de clave generada** | Test verificando que la contraseña en texto plano viaja en la variable flash `generated_password`. |
| **Invalidación y bloqueo de inactivos** | Tests verificando que un usuario inactivo no puede iniciar sesión y que es expulsado si su cuenta se desactiva. |
| **Exclusión de clientes en el listado** | Test verificando que usuarios con `customer_id` / rol `cliente` nunca aparecen en `settings/users`. |
| **Deshabilitación de `/register` interno** | Test verificando que `route('register')` no está habilitada en Fortify interno. |
| **Estilos, tipos y linters** | Ejecución de `pint`, `types:check` (Larastan), `tsc`, `lint:check` y `format:check`. |

---

## Verification Plan

### Automated Tests
- Correr el conjunto específico de la HU:
  ```bash
  php artisan test --compact tests/Feature/Security/UserManagementTest.php
  ```
- Correr tests de autenticación existentes para verificar no regresión:
  ```bash
  php artisan test --compact --filter=Auth
  ```

### Manual Verification
1. Iniciar sesión como `Administrador`.
2. Navegar a `/settings/users` desde el menú lateral.
3. Crear un usuario con rol `Cajero` y sucursal predeterminada, dejando la contraseña automática: verificar modal con la clave y botón de copiar.
4. Abrir una ventana incógnito, iniciar sesión con el cajero creado y abrir un turno de caja.
5. Volver a la sesión de Administrador e intentar desactivar al cajero: verificar mensaje de bloqueo por turno de caja abierto.
6. Cerrar el turno del cajero en la ventana incógnito.
7. Desde el admin, desactivar al cajero: verificar que en su siguiente petición en la ventana de incógnito es deslogueado hacia `/login` con mensaje de cuenta desactivada.
8. Intentar desactivar al propio usuario Administrador: verificar rechazo.

### CI checks
```bash
vendor/bin/pint --dirty --format agent
composer run types:check
npm run types:generate
npm run lint:check
npm run format:check
npm run types:check
php artisan test --compact
```

---

## Open Questions

1. **Ruta y naming del módulo:**
   Proponemos `/settings/users` (nombre de ruta `settings.users.*`), consistente con las pantallas de perfil, seguridad y apariencia que ya residen en `settings/` y en `routes/settings.php`. ¿Estás de acuerdo con este prefijo de ruta o preferís `/users`? (Recomendado: `/settings/users`).
2. **Impacto de deshabilitar `Features::registration()` en Fortify:**
   En el Sprint 1/4 (EPIC-11), el registro de clientes de la tienda online redirigía a `/register`. Al deshabilitar `Features::registration()` en Fortify para cerrar el registro interno, la ruta `/register` de Fortify deja de existir. Si se requiere mantener el registro de clientes en la tienda pública (`/tienda/registro`), crearemos una ruta y controlador dedicados para clientes (`StoreRegistrationController` o similar) para que la tienda online siga registrando clientes sin exponer el registro en el panel interno. ¿Confirmamos esta estrategia?
