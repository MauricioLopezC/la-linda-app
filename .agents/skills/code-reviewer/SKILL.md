---
name: code-reviewer
description: >-
  Activate this skill when the user asks to review a PR, perform a code review, or audit a
  feature/HU branch of the La Linda project against its acceptance criteria. Verifies
  requirements against `product-backlog.md`, checks consistency with project conventions
  (Actions, Data objects, Form Requests, `.ai/rules`), flags efficiency issues, and formats the
  final report in markdown for GitHub.
---

# Code Reviewer Skill — La Linda

Revisa una rama `feature/HU-<n>-...` (o su PR) contra los criterios de aceptación de la HU
correspondiente en `product-backlog.md`, contra las convenciones del proyecto, y produce un
informe en Markdown listo para pegar en la PR. Todo hallazgo debe poder ubicarse en `archivo:línea`.

## Paso 1 — Identificar la HU y sus criterios

1. El nombre de rama sigue `feature/HU-<numero>-slug` (ver `CONTRIBUTING.md`). Extraé el `HU-XXX`
   de ahí, o pedíselo al usuario si no es reconocible.
2. Los criterios de aceptación **viven únicamente en `product-backlog.md`**, buscados por ese ID
   — nunca están (ni deberían estar) copiados en `sprint-backlog-<n>.md`. Si no encontrás el ítem
   ahí, decilo explícitamente en vez de inventar criterios; no avances con criterios supuestos.
3. Los criterios siguen el formato `Datos: … | Validaciones: … | Comportamiento: … |
   Verificación: …` — usalo como checklist literal en el Paso 4, no como prosa libre.
4. Si el ítem referencia un ID de `LaLindaAlcanceV1.md` (columna `Alcance`, ej. `STK-04`), abrí
   también esa sección: el criterio puede depender de una decisión cerrada del alcance (ej. "el
   precio no es atributo del artículo").

## Paso 2 — Identificar los cambios

1. Base branch siempre `master` (única rama larga, ver `CONTRIBUTING.md`).
2. `git fetch origin` antes de diffear, para no comparar contra un `origin/master` viejo.
3. Si hay PR abierto y `gh` disponible: `gh pr diff <numero>` y `gh pr view <numero> --json
   title,body` (el título/body suele traer el HU-ID y contexto).
4. Si no: `git diff origin/master...<rama> --name-only`, y después el diff completo o archivo por
   archivo si es grande, priorizando lo que toca a los criterios de aceptación.
5. Abrí el archivo completo (no solo el hunk) cuando el cambio toca lógica — el contexto
   alrededor del diff suele ser necesario para juzgar correctitud.

## Paso 3 — Revisar reglas del proyecto antes de auditar

Este repo tiene reglas cargadas fuera del código, hay que leerlas antes de opinar sobre
convenciones:

- Si existe `.ai/rules/index.md`, abrilo y leé cada regla cuyo glob cubra los archivos tocados.
  Corré también `grep -rin '<palabra clave del módulo>' .ai/rules` — el mapeo por glob solo no
  alcanza siempre.
- Revisá `docs/context/glosario.md` y `docs/context/roles-permisos.md` si el cambio toca
  entidades o permisos con nombres ambiguos, y `docs/context/design.md` si toca estilos de UI.
- Ubicá 1-2 archivos existentes comparables **no tocados por esta rama** (otra Action del mismo
  módulo, otro Form Request, otro objeto Data) para juzgar coherencia real contra el proyecto, no
  contra una convención genérica de Laravel.

## Paso 4 — Auditar

Aplicá solo las categorías relevantes a los archivos tocados:

- **Criterios de aceptación:** contrastá cada uno (Datos / Validaciones / Comportamiento /
  Verificación) uno por uno contra el diff. Marcá cumplido, parcial o no cumplido con evidencia.
- **Migraciones:** FKs correctas, índices donde corresponda (ver convención de índice único en
  pares como `existencias`), nullable/default sensatos, `down()` reversible.
- **Form Requests (validación):** reglas coinciden exactamente con los límites del criterio
  (únicos, obligatorios, rangos), `authorize()` correcto.
- **Actions (`app/Actions/{Modulo}/`):** ¿la lógica de negocio está ahí y no en el controller? Un
  controller con reglas de negocio, un `where` trivial envuelto innecesariamente en una Action, o
  lógica duplicada que ya existe en otro Action del módulo son todos hallazgos válidos (ver
  criterio Action-needed vs no-Action-needed de `CLAUDE.md`).
- **Data objects (`app/Data/{Modulo}/`):** las respuestas salientes usan `spatie/laravel-data` en
  vez de arrays sueltos; si se agregó/editó uno, ¿se corrió `npm run types:generate`? (buscar el
  tipo correspondiente actualizado en `resources/js/types/generated.d.ts`).
- **Frontend (Inertia/React):** componentes en `resources/js/pages` salvo que el proyecto indique
  otra cosa, uso de props tipadas generadas, sin lógica de negocio en el componente.
- **Seeders:** idempotentes (`firstOrCreate`), registrados en `DatabaseSeeder`.
- **Tests (Pest):** ¿hay test nuevo o actualizado por cada criterio de aceptación tocado? Señalar
  ausencia de test en validaciones o reglas de negocio como hallazgo, no ignorarlo.
- **Eficiencia / Performance:** N+1 (falta `with()`), queries dentro de loops, llamadas a la DB
  redundantes que se puedan batchear, falta de índice en columnas usadas en `WHERE`/`JOIN` de
  consultas nuevas (ej. filtros de `HU-016`/`HU-018`), colecciones sin paginar cargadas enteras a
  memoria. Cada hallazgo con sugerencia concreta de antes/después, no solo "esto se puede
  optimizar".
- **Checks de CI:** si podés correrlos, hacelo y reportá el resultado real en vez de asumir que
  pasan — `vendor/bin/pint --format agent`, `composer run types:check`, `npm run lint:check`,
  `npm run format:check`, `npm run types:check`, `php artisan test --compact` (o `composer run
  ci:check` para todo junto). Si no podés ejecutarlos, decilo explícitamente en el informe en vez
  de omitirlo.

Cada hallazgo (bloqueante o no) lleva `archivo:línea`.

## Paso 5 — Formatear el informe

Markdown listo para pegar en la PR de GitHub:

```markdown
## 📝 Code Review - [HU-XXX] (Título)

### ✅ Criterios de Aceptación
- [x] Datos: ... — cumplido (`archivo:línea`)
- [ ] Validaciones: ... — parcial, falta ... (`archivo:línea`)

### 🏗️ Coherencia Arquitectónica
- Comparado contra `<archivo existente de referencia>`: sigue / se desvía del patrón (Actions,
  Data objects, Form Requests, `.ai/rules`).

### ⚡ Eficiencia / Sugerencias de Optimización
- `archivo:línea` — problema puntual + sugerencia concreta.
- (Si no hay hallazgos: "Sin observaciones de eficiencia.")

### 🧪 CI y Tests
- Resultado real de Pint / Larastan / ESLint / Prettier / tsc / Pest, o aviso explícito de que no
  se pudieron correr.

### 🔎 Observaciones / Comentarios Menores
- No bloqueantes: legibilidad, edge cases, tests faltantes no críticos.

### 🚧 Bloqueantes
- Lo que debe resolverse antes de aprobar (vacío si no hay).

### Conclusión
Resumen breve + estado:
- 🟢 Aprobado
- 🟡 Requiere Cambios
- 🔴 Bloqueado
```

No repitas en prosa lo que ya está en el checklist. Si una sección no tiene nada que decir,
escribí "Sin observaciones" en vez de dejarla vacía o inventar contenido.
