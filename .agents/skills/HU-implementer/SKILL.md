---
name: hu-implementer
description: >-
  Activate this skill when the user asks to plan or implement a User Story (HU-XXX) for the
  La Linda project — "implementa la HU-018", "arma el plan para la HU-XX", "seguí con la
  siguiente historia del sprint". Covers researching the existing codebase, writing a versioned
  plan document under docs/plans/, and — after explicit approval — implementing it end to end
  following the project's Laravel/Inertia conventions.
---

# HU Implementer Skill — La Linda

Dos fases, con un punto de aprobación explícito entre medio. **Nunca saltear la Fase 1** aunque
el pedido venga fraseado como "implementá la HU-XX" directamente — el plan siempre se escribe y
se presenta primero.

## Fase 1 — Investigación y Plan

### 1. Ubicar la historia y sus criterios

- Buscar `HU-XXX` en `product-backlog.md` — es la única fuente de los criterios de aceptación
  (formato `Datos / Validaciones / Comportamiento / Verificación`). Si no aparece, decirlo y
  parar; no inventar criterios.
- Revisar `sprint-backlog-<n>.md`: confirmar que la HU está comprometida en el sprint activo, leer
  su lugar en el diagrama de dependencias ("Orden de ataque") y si depende de otra HU que todavía
  no está mergeada a `master` — si depende de algo pendiente, avisar antes de seguir.
- **Nota del repo actual:** no existe `LaLindaAlcanceV1.md` en este checkout. Si una decisión de
  alcance parece necesaria y no está en `product-backlog.md` ni en `sprint-backlog-<n>.md`,
  preguntarle al usuario en vez de asumir — no hay dónde más buscarla.

### 2. Investigar el código existente (obligatorio antes de proponer nada)

El objetivo es que el diseño nuevo reutilice y se parezca al que ya existe, no que introduzca un
patrón propio. Buscar y leer, en este orden:

1. **Migraciones y modelos** relacionados al módulo (`app/Models/{Modulo}/`) — entender el
   esquema real, no el propuesto en el sprint backlog; el código manda si difieren. Contrastar
   contra el diagrama Mermaid de `sprint-backlog-<n>.md` (sección "Diagrama"): es el ERD acordado
   por todo el equipo para las tablas de ese sprint (nombres de tabla, PK/FK, columnas
   `*_normalized` para unicidad case-insensitive, relaciones). Si una migración ya construida
   difiere del diagrama, el código manda — pero avisar la discrepancia en el plan en vez de
   ignorarla, porque puede ser un error de otra HU que conviene que el equipo sepa. Si la HU
   actual necesita una tabla o columna que no está en ese diagrama, tratarlo como Open Question:
   puede ser una tabla de un sprint futuro, no algo para inventar sobre la marcha.
2. **Una Action existente del mismo módulo** que resuelva un caso de uso comparable (ej. si la HU
   es de consulta con filtros, buscar otra consulta con filtros ya construida) — se sigue su
   mismo patrón interno (`buildQuery()`/`applyFilters()` u otro que ya esté establecido), no se
   inventa una estructura nueva.
3. **Un Data object existente** en `app/Data/{Modulo}/` como referencia de shape y de si conviene
   uno liviano para listado vs. uno completo para detalle.
4. **Una página Inertia existente** con estructura similar (listado con filtros, formulario,
   detalle) en `resources/js/pages/` — layout, breadcrumbs, componentes shadcn/ui usados.
5. **Rutas** en `routes/web.php` del mismo módulo, para mantener el prefijo/naming consistente.
6. `docs/context/glosario.md`, `docs/context/roles-permisos.md`, `docs/context/design.md` — solo
   si la HU toca una entidad ambigua, un permiso, o estilos de UI.
7. `.ai/rules/index.md` y las reglas cuyo glob cubra los paths que se van a tocar (ver
   `AGENTS.md`) — son restricciones ya decididas por el equipo, no opcionales.

No proponer un artefacto nuevo (Action, Data object, patrón de filtros, componente) sin haber
mirado antes si ya existe algo comparable para calcarlo.

### 3. Escribir el plan

Crear `docs/plans/HU-XXX-plan.md` con esta estructura (referencia: así quedó documentado el plan
de HU-018 en este mismo repo):

```markdown
# HU-XXX — <título>

<1-2 líneas de contexto: qué construye esta historia y qué ya existe de lo que depende>

## Criterios de aceptación (de `product-backlog.md`)
<lista numerada, tal cual están en el backlog>

## Investigación del código existente
<tabla: Artefacto | Ubicación | Propósito — igual que la de HU-018>

<si el código existente difiere del diagrama Mermaid de `sprint-backlog-<n>.md` en algo relevante
a esta HU, una nota breve indicando la discrepancia — omitir si no hay ninguna>

## Proposed Changes
<una subsección por artefacto nuevo o modificado, agrupado por capa:
Backend — Action(s), Backend — Data object(s), Backend — Controller,
Rutas, Frontend — Página(s), Wayfinder, Tests>

Cada subsección: `#### [NEW]` o `#### [MODIFY]` + ruta del archivo, y el detalle necesario para
implementarlo sin ambigüedad (métodos, filtros, columnas, props que pasa a la vista).

## Verificación de la Definition of Done
<tabla: Criterio | Cómo se verifica>

## Verification Plan
### Automated Tests
### Manual Verification
### CI checks

## Open Questions
<preguntas reales que necesitan una decisión del usuario antes de implementar — no las inventes
para parecer riguroso; si no hay ninguna, omitir la sección>
```

Reglas para esta fase:

- **No hay opción "sin preguntas" forzada.** Si algo depende de una preferencia de UI, una
  decisión de alcance no documentada, o afecta un módulo que no se investigó, va en Open
  Questions — mejor preguntar de más que asumir e implementar mal.
- El plan describe **qué se va a construir**, no lo implementa todavía.

### 4. Presentar el plan y esperar aprobación

Mostrar el plan (resumen en el chat, no hace falta pegar el archivo entero si es largo) y esperar
confirmación explícita del usuario antes de tocar código. Si el plan tiene Open Questions,
resolverlas primero — no implementar con supuestos sobre esas preguntas.

## Fase 2 — Implementación (solo tras aprobación explícita)

1. Seguir el plan en el orden de sus subsecciones (Backend → Rutas → Frontend → Wayfinder →
   Tests), igual que se construyen las dependencias reales: el modelo y la Action tienen que
   existir antes que el Data object los consuma, y este antes que el controller.
2. Cada archivo `[NEW]`/`[MODIFY]` del plan se implementa tal como quedó especificado. Si al
   escribir el código aparece una razón concreta para desviarse del plan, decirlo explícitamente
   en la respuesta (qué cambió y por qué) — no desviarse en silencio.
3. Correr los checks de CI que apliquen a lo tocado: `vendor/bin/pint --dirty --format agent`,
   `composer run types:check`, `npm run lint:check`, `npm run format:check`, `npm run
   types:check`, y los tests nuevos con `php artisan test --compact --filter=<Test>`. Reportar el
   resultado real, no asumir que pasan.
4. Si el plan incluye Data objects nuevos/editados, correr `npm run types:generate` después de
   crearlos. Si incluye rutas nuevas, correr `php artisan wayfinder:generate`.
5. Al terminar, contrastar contra la tabla "Verificación de la Definition of Done" del plan y
   reportar el estado de cada fila — no dar la historia por terminada sin ese chequeo.
6. No crear la rama ni el PR a menos que el usuario lo pida explícitamente — la convención de
   ramas (`feature/HU-<n>-slug`) está en `CONTRIBUTING.md`, pero abrir la rama es una decisión del
   usuario, no algo que la skill asuma por defecto.

## Fase 3 — Informe de cierre para el commit y el PR

Al terminar la implementación (Fase 2) y verificado el chequeo de Definition of Done, generar en
el chat (no hace falta archivo aparte, esto es texto para pegar/usar al pushear):

### 1. Mensaje de commit

Formato convencional, en español, acorde a lo que realmente se hizo — no una descripción genérica
de lo que decía el plan si algo se implementó distinto:

```
<tipo>(<módulo>): <resumen imperativo, HU-XXX>

<cuerpo: 2-4 líneas explicando qué se construyó y por qué, en términos de la historia,
no una lista de archivos>
```

`<tipo>` sigue Conventional Commits (`feat`, `fix`, `refactor`, `test`, etc.) y `<módulo>` es el
nombre corto del módulo tocado (`inventory`, `sales`, etc.), consistente con el prefijo de rutas
del proyecto.

### 2. Cuerpo del Pull Request

Markdown listo para pegar como descripción del PR (rama `feature/HU-XXX-slug` → `master`, según
`CONTRIBUTING.md`):

```markdown
## HU-XXX — <título>

### Qué resuelve
<1-3 líneas, en términos de la historia de usuario, no de implementación>

### Criterios de aceptación
- [x] <criterio 1> — <cómo se verificó>
- [x] <criterio 2> — <cómo se verificó>

### Cambios
<lista breve por capa: Backend (Actions/Data/Controller), Rutas, Frontend, Tests —
apuntando a los archivos [NEW]/[MODIFY] del plan, sin repetir el detalle completo>

### Cómo probarlo
<pasos manuales cortos, basados en el "Manual Verification" del plan>

### CI
<resultado real de los checks corridos en la Fase 2 — Pint, types:check, lint:check,
format:check, tsc, Pest — no asumir que pasaron si no se corrieron>

### Notas
<solo si aplica: desvíos respecto al plan original y por qué, decisiones de Open Questions que
se resolvieron con el usuario, o pendientes para un sprint futuro>
```

Reglas para esta fase:

- El cuerpo del PR no repite el plan completo — lo resume apuntando a `docs/plans/HU-XXX-plan.md`
  para el detalle, ya que ese archivo queda versionado en el repo.
- Si algo del plan no se completó (quedó para después, o se resolvió distinto), decirlo en
  "Notas" — el informe describe lo que efectivamente se hizo, no lo que se planeó hacer.
- No inventar resultados de CI: si no se corrió algún check en la Fase 2, decirlo explícitamente
  acá en vez de omitirlo o asumir que pasa.
