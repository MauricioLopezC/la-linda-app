# Plan de correcciones — PR #97: base del tablero gerencial

Fecha: 2026-10-15. Estado: implementación completada y verificada.

## Base y alcance

Informe de entrada: `/home/mauro/Desktop/lalinda-docs/Revisiones/PR-97-reporting-code-review-2026-10-15.md`.

El HEAD local coincide con el commit revisado: `4ebe332df73459a588ed391eeb1a10b1db4dc9b2`. Se contrastaron los archivos afectados, los tests existentes, las reglas del proyecto y el contrato D2 de `docs/backlog/sprint-backlog-5.md`, sección «Base del tablero + firmas de ingresos/egresos».

Objetivo: resolver los dos bloqueantes P2 de filtros y completar la observación menor sobre bloques anulables. La base sigue siendo preparatoria: los cálculos, gráficos, rankings y exportación pertenecen a EPIC-18 a EPIC-22.

Versiones comprobadas: Laravel 13.25.0, adaptador Inertia Laravel 3.3.1, Pest 5.1.1 y Laravel Data 4.23.0. El frontend declara Inertia React 3, React 19 y Tailwind 4.

## 1. Validar el rango efectivo después de resolver los defaults — P2

**Archivos:** `app/Http/Requests/Reporting/DashboardFilterRequest.php` y `tests/Feature/DashboardTest.php`.

1. Agregar `prepareForValidation(): void` al Form Request y resolver allí las fechas faltantes: primer día del mes actual para `date_from` y hoy para `date_to`.
2. Tratar fechas omitidas, vacías o nulas como ausencia, respetando la normalización de middleware. No usar una comprobación genérica de falsedad que pueda sustituir valores inválidos como `0`; las entradas inválidas deben llegar al validador y rechazarse.
3. Obtener una única referencia de hoy para ambos defaults. Conservar las reglas de fecha y `after_or_equal:date_from`, ahora aplicadas al rango efectivo.
4. Hacer que `filters()` devuelva las fechas ya validadas, eliminando la resolución tardía y duplicada de defaults. Conservar los tipos y la semántica de sucursal/canal.
5. Mantener la validación en el Form Request; el controller solo compone las props y no necesita una Action nueva.

**Cobertura:** fijar hoy en `2026-10-14` para los casos dependientes del calendario y ampliar los datasets existentes.

| Entrada | Resultado esperado |
| --- | --- |
| Sin fechas | `2026-10-01` → `2026-10-14` |
| Solo `date_to=2026-09-30` | Rechazo en `date_to` |
| Solo `date_from=2026-10-20` | Rechazo en `date_to` |
| `date_from` vacío y `date_to=2026-09-30` | Rechazo en `date_to` |
| `date_from=2026-10-20` y `date_to` vacío | Rechazo en `date_to` |
| Solo `date_to=2026-10-10` | `2026-10-01` → `2026-10-10` |
| Solo `date_from=2026-10-10` | `2026-10-10` → `2026-10-14` |
| Ambas fechas vacías o nulas | Defaults del mes actual |
| Fechas iguales, incluso contra un default | Aceptación |
| Fecha mal formada | Rechazo; no sustitución por default |

Conservar los casos de rango explícito invertido, canal desconocido, sucursal inexistente, eco de filtros y sucursales activas. La corrección no agrega una prohibición de fechas futuras cuando el rango explícito sea válido.

## 2. Mostrar errores de filtros y gestionar su ciclo de vida — P2

**Archivo principal:** `resources/js/pages/dashboard/partials/dashboard-filters.tsx`.

1. Conservar la navegación GET mediante `router.get` y `dashboard.url()` de Wayfinder, junto con el estado local de filtros existente.
2. Consumir los errores de validación mediante `onError` y almacenarlos localmente con el tipo de errores de Inertia. Renderizar `InputError` junto a `date_from`, `date_to`, sucursal y canal.
3. Conectar los mensajes con los controles usando identificadores, `aria-invalid` y `aria-describedby`. Dar a los selects identificadores que permitan asociar también sus etiquetas.
4. Conservar las fechas editadas ante un rechazo para permitir corregirlas. El tablero conserva los últimos filtros válidos; el mensaje junto al campo explica por qué el intento no se aplicó.
5. Limpiar los errores al comenzar otro envío, después de una respuesta exitosa y al restablecer filtros. Si se limpia al editar fechas, limpiar también el error de rango de `date_to` cuando cambie `date_from`.
6. Conservar el atributo `min` como ayuda de entrada; la respuesta del servidor determina si el rango es válido.
7. Comprobar la interacción con `key={JSON.stringify(filters)}` en `dashboard.tsx`: un rechazo conserva estado y errores, y un éxito refleja las fechas normalizadas por el servidor.

**Prueba HTTP de integración:** desde una URL válida de dashboard, enviar un rango inválido como visita Inertia, comprobar la redirección de validación y seguirla. Verificar `errors.date_to` en las props y que `filters` sigue representando el último rango válido. Repetir la consulta con un rango correcto y comprobar que el error desaparece y los filtros se actualizan.

**Comprobación visual obligatoria:** partir de un rango válido, invertirlo y pulsar «Filtrar». Deben verse el error junto a la fecha hasta y los valores editados. Corregir y reenviar: desaparece el mensaje y cambian los filtros aplicados. Repetir con cada fecha vacía que produce un rango inválido, con sucursal/canal seleccionados, y con «Limpiar filtros». Revisar legibilidad en móvil y ambos temas.

Actualmente no hay runner de pruebas de componentes ni de navegador configurado en `package.json`/`composer.json`. Los tests HTTP prueban la entrega del error, pero no su renderizado en React. La verificación visual debe registrarse por separado; este plan no requiere instalar dependencias para realizarla.

## 3. Completar los bloques anulables de D2 — observación menor

**Archivos:** `app/Data/Reporting/DashboardData.php`, nuevos Data objects en `app/Data/Reporting/`, `resources/js/pages/dashboard.tsx`, los partials de evolución/operativos y `tests/Feature/DashboardTest.php`.

1. Agregar `evolution: ?DashboardEvolutionData = null` y `operational: ?DashboardOperationalData = null` al contrato de salida. Usar clases Data tipadas y colecciones de Data concretos; evitar `mixed` o arrays sin estructura.
2. Definir la estructura preparatoria según los criterios de EPIC-20/21: evolución con resultado neto, margen, variación y serie temporal; operativos con top de artículos, alertas por artículo/depósito, ranking de proveedores y cantidad de pedidos pendientes. Los elementos de serie y rankings deben tener sus propios tipos.
3. Mantener importes y cantidades decimales como strings según las convenciones existentes; conteos como enteros y porcentajes con el criterio ya utilizado en Reporting. Las métricas sin base de comparación deben admitir `null`.
4. Pasar los bloques desde la página a `DashboardEvolutionSection` y `DashboardOperationalSection`, con props tipadas. Ambos siguen mostrando sus placeholders mientras el bloque sea `null`. La visualización de datos calculados se implementará en sus historias correspondientes.
5. Mantener los nuevos bloques en `null` desde el controller mediante los defaults del constructor. No agregar consultas ni calcular métricas para completar esta observación.
6. Ampliar el test de props por defecto para comprobar la presencia de `evolution` y `operational` con valor `null`. Verificar la serialización de sus estructuras tipadas mediante una prueba enfocada de los Data objects.
7. Regenerar los tipos con `npm run types:generate`; `resources/js/types/generated.d.ts` está ignorado por Git y no se edita a mano.

Esta tarea satisface el requisito explícito de D2 de un bloque anulable por sección. Conviene mantenerla en un commit separado de los dos bloqueantes para facilitar su revisión y la integración de las historias siguientes.

## Orden de ejecución y validación

1. Incorporar los casos de regresión del punto 1 y comprobar que reproducen el rechazo faltante antes de cambiar el Request.
2. Corregir el Request y ejecutar `PAO_DISABLE=1 php artisan test --compact tests/Feature/DashboardTest.php`.
3. Implementar los mensajes del punto 2, agregar el test del flujo Inertia y ejecutar nuevamente ese archivo.
4. Completar el contrato del punto 3, regenerar tipos y ejecutar los tests afectados de dashboard y Data.
5. Ejecutar `vendor/bin/pint --dirty --format agent` tras los cambios PHP.
6. Ejecutar una vez `composer run ci:check` sobre el resultado final. Incluye generación de tipos/rutas, ESLint, Prettier, TypeScript, Pint, PHPStan y la suite PHP. Ejecutar también `npm run build` para verificar el bundle utilizado en la comprobación visual.
7. Realizar la comprobación visual del punto 2, revisar el diff y registrar los resultados reales. El CI verde del informe corresponde al código anterior y no reemplaza esta validación.

Si Pint o alguna herramienta de verificación encuentra una restricción del sandbox, repetir el comando con el mecanismo de permisos correspondiente y conservar su resultado; no darlo por aprobado sin ejecutarlo.

Antes de implementar, consultar `search-docs` de Boost para normalización de Form Requests y errores de visitas Inertia. Ese MCP no está expuesto en esta sesión; para preparar el plan se verificó el orden de `prepareForValidation()` en el framework instalado y el mecanismo de errores compartidos en el adaptador Inertia instalado.

## Criterios de cierre

- [x] Los rangos efectivos invertidos se rechazan tanto con fechas explícitas como con defaults.
- [x] Los defaults y rangos parciales válidos conservan el comportamiento previsto.
- [x] Los errores se muestran junto al campo correspondiente y desaparecen al aplicar filtros válidos o restablecerlos.
- [x] Un rechazo conserva los valores editados y los filtros válidos del tablero.
- [x] Las cuatro secciones tienen bloques anulables y tipos generados coherentes.
- [x] Los tests afectados, el CI completo y el build pasan después de las correcciones.
- [x] El flujo visible fue comprobado en navegador y su resultado quedó registrado.

La entrega inicial contenía solo el plan. La implementación se realizó después de su aprobación.

## Resultado de implementación — 2026-10-15

- Se consultó `search-docs` mediante `php artisan boost:execute-tool`, con resultados versionados de Laravel 13 e Inertia 3. La consulta y el CI necesitaron permisos ampliados por las restricciones de red/puertos del sandbox.
- Antes de corregir el Request, las pruebas reprodujeron cuatro rangos parciales invertidos que se aceptaban. Después de las correcciones, `DashboardTest.php` y `Reporting/DashboardDataTest.php` pasaron: **27 tests, 183 assertions**.
- `composer run ci:check` final pasó: generación de tipos/rutas, ESLint, Prettier, TypeScript, Pint, PHPStan (**0 errores**) y Pest (**919 aprobados, 4 omitidos, 5.193 assertions**). Log local: `/tmp/pr97-corrections-ci.log`.
- `npm run build` pasó; emitió avisos informativos de tamaño del bundle y tiempos de plugins. Log local: `/tmp/pr97-corrections-build.log`.
- Se comprobó el dashboard real con el usuario demo en el navegador de Orca: rango explícito invertido, corrección exitosa, ambos rangos inválidos con una fecha vacía, conservación de sucursal Centro/canal Online y restablecimiento completo de filtros. Las comprobaciones del DOM confirmaron los valores, el error visible, `aria-invalid` y la URL. Consola sin errores.
- Se inspeccionaron capturas del error en escritorio con tema claro y viewport móvil de 390 × 844 con tema oscuro. Capturas locales: `/tmp/pr97-desktop-error.png` y `/tmp/pr97-mobile-error.png`.
- Los bloques nuevos permanecen anulables y sin cálculos conectados; las secciones de evolución e indicadores operativos conservan sus placeholders hasta sus historias correspondientes.
