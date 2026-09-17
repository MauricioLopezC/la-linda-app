---
name: github-issue-writer
description: Convierte tareas del backlog, historias de usuario, bugs o puntos abiertos en Issues de GitHub bien estructurados, entregados como archivo(s) .md listos para copiar/pegar en la pestaña "Issues" del repositorio. Usar esta skill cuando el usuario pida redactar, generar o formatear uno o varios issues de GitHub a partir de una tarea, contrato técnico, bug encontrado, feedback de sprint review o cualquier ítem de trabajo que deba quedar registrado en el tablero del proyecto.
license: Complete terms in LICENSE.txt
---

## Cuándo usar esta skill

Usar esta skill cuando el usuario pida cualquiera de estas cosas:

- Redactar un issue de GitHub a partir de una tarea técnica, historia de usuario, bug o punto abierto.
- Desglosar una Historia de Usuario de Sprint Planning en tareas técnicas de issue.
- Documentar un bug detectado durante el desarrollo.
- Registrar un punto abierto o duda arquitectónica para el Product Owner/docente.
- Convertir feedback de una Sprint Review en deuda técnica para el backlog.
- Generar un archivo `.md` con uno o varios issues para subir/copiar a GitHub.

No usar esta skill para: crear el issue directamente en GitHub vía API/CLI (esta skill solo redacta el contenido en Markdown), ni para gestionar Pull Requests.

## Cómo usar esta skill

### 1. Reunir la información del issue

Antes de redactar, identificar (preguntar si falta algo crítico, pero no bloquear el trabajo por detalles menores — usar valores razonables por defecto):

- **Tipo:** Tarea técnica | Bug | Historia de Usuario | Punto Abierto
- **ID/Módulo:** identificador corto de la historia o módulo (ej: `US03`, `AUTH`, `SPRINT2-04`). Si el usuario no da uno, proponer uno breve basado en el título/módulo.
- **Título:** claro, conciso, en imperativo o descriptivo (ej: "Implementar endpoint de login con JWT").
- **Responsable:** usuario de GitHub (`@usuario`) si se conoce; si no, dejar el placeholder `@usuario` o omitir la línea si el usuario lo pide explícitamente.
- **Rama asociada:** nombre de rama si existe o se puede inferir (`feature/...`, `fix/...`).
- **Descripción:** contexto breve — qué se implementa y por qué.
- **Criterios de aceptación / tareas:** lista de checkboxes `- [ ]` con las tareas concretas o condiciones de "hecho".
- **Dependencias/bloqueos:** referencias a otros Issues (`#YY`) o PRs (`#XX`) si aplica. Si no hay, omitir la sección completa (no dejarla vacía con guiones sin contenido).

Si el usuario pasa una conversación de trabajo, un fragmento de código, notas de reunión o una lista suelta de pendientes, extraer de ahí los issues implícitos en lugar de pedirle que los formatee él mismo.

### 2. Redactar cada issue con la plantilla

Usar exactamente esta estructura por cada issue (ver `reference/template.md`):

```markdown
### [ID-Historia/Módulo] Título claro y conciso de la tarea
**Tipo:** Tarea técnica | Bug | Historia de Usuario | Punto Abierto
**Responsable:** @usuario
**Rama asociada:** feature/nombre-de-rama

#### Descripción
Contexto breve de qué se va a implementar y por qué.

#### Criterios de Aceptación / Tareas a realizar
- [ ] Tarea técnica 1
- [ ] Tarea técnica 2
- [ ] Tests automáticos que verifiquen el caso

#### Dependencias / Bloqueos
- Depende de: PR #XX o Issue #YY
```

Reglas de redacción:

- Título en una sola línea, sin punto final, capitalizado como título de tarea (no como oración larga).
- La descripción va en 1-3 frases: contexto suficiente para que cualquiera del equipo entienda el "por qué" sin tener que preguntar.
- Los criterios de aceptación deben ser verificables (algo que se pueda marcar como hecho/no hecho), no vagos. Preferir verbos de acción: "Crear migración de...", "Implementar endpoint...", "Agregar test que verifique...".
- Siempre incluir al menos un checkbox de testing cuando la tarea implique lógica de backend/frontend testeable.
- Si es un **Bug**, adaptar la sección de Descripción para incluir: comportamiento esperado vs. comportamiento actual, y pasos para reproducir, dentro del mismo bloque de descripción.
- Si es un **Punto Abierto**, la sección de "Criterios de Aceptación" puede reemplazarse por "Preguntas a resolver" con el mismo formato de checklist.
- No inventar números de PR/Issue para dependencias; si no se conocen, omitir la sección.

### 3. Entregar como archivo .md

- Siempre generar el resultado como archivo `.md`, nunca solo como texto plano en el chat (el objetivo es que se pueda copiar/pegar directo o adjuntar).
- **Un archivo puede contener varios issues**: si el usuario trae varias tareas de una misma sesión de trabajo o del mismo módulo, agruparlas en un solo `.md`, separando cada issue con `---` (línea horizontal) entre uno y otro.
- **Nombrar el archivo según el ID/Módulo** del issue principal o del módulo que agrupa a todos, en minúsculas y con guiones, por ejemplo:
  - Un solo issue: `us03-login.md`
  - Varios issues del mismo módulo: `sprint2-auth.md`
  - Si no hay ID claro, usar un slug corto del título principal.
- Antes de crear el archivo, revisar `/mnt/skills/public/md/SKILL.md` si está disponible para las convenciones de formato Markdown de archivos.
- Guardar el archivo en el directorio de salida y presentarlo con `present_files` para que el usuario pueda descargarlo y compartirlo con el equipo.

### 4. Confirmar antes de cerrar

Al entregar el archivo, resumir en 1-2 líneas cuántos issues se generaron y de qué tipo, sin repetir el contenido completo en el chat (el archivo ya lo tiene).

## Ejemplo de uso

**Usuario:** "Necesito un issue para el endpoint de recuperación de contraseña, todavía no está asignado, va en la rama feature/password-reset"

**Resultado esperado:** un archivo `.md` (ej: `auth-password-reset.md`) con un solo bloque de issue, tipo "Tarea técnica", con descripción del endpoint, checklist de migración/controller/tests, y sin sección de dependencias (porque no se mencionaron).

## Keywords

github issue, issue de github, historia de usuario, tarea técnica, bug report, sprint planning, criterios de aceptación, backlog, punto abierto, plantilla de issue, antigravity
