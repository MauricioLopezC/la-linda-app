# Plantilla de Issue de GitHub

## Plantilla base (Tarea técnica / Historia de Usuario)

```markdown
### [ID-Historia/Módulo] Título claro y conciso de la tarea
**Tipo:** Tarea técnica | Bug | Historia de Usuario | Punto Abierto
**Responsable:** @usuario
**Rama asociada:** feature/nombre-de-rama

#### Descripción
Contexto breve de qué se va a implementar y por qué.

#### Criterios de Aceptación / Tareas a realizar
- [ ] Tarea técnica 1 (ej: migración de base de datos)
- [ ] Tarea técnica 2 (ej: Action / Controller)
- [ ] Tests automáticos (Pest / PHPUnit / Jest / etc.) que verifiquen el caso

#### Dependencias / Bloqueos
- Depende de: PR #XX o Issue #YY
```

## Variante: Bug

```markdown
### [ID-Módulo] Título del bug
**Tipo:** Bug
**Responsable:** @usuario
**Rama asociada:** fix/nombre-de-rama

#### Descripción
- **Comportamiento esperado:** ...
- **Comportamiento actual:** ...
- **Pasos para reproducir:**
  1. ...
  2. ...

#### Criterios de Aceptación / Tareas a realizar
- [ ] Corregir la causa raíz identificada en ...
- [ ] Agregar test de regresión que cubra este caso

#### Dependencias / Bloqueos
- Depende de: Issue #YY
```

## Variante: Punto Abierto

```markdown
### [ID-Módulo] Título de la duda o decisión pendiente
**Tipo:** Punto Abierto
**Responsable:** @usuario
**Rama asociada:** N/A

#### Descripción
Contexto de la duda arquitectónica o de producto que necesita definición.

#### Preguntas a resolver
- [ ] ¿Pregunta concreta 1?
- [ ] ¿Pregunta concreta 2?

#### Dependencias / Bloqueos
- Depende de: definición del PO/docente
```

## Reglas rápidas

1. Título corto, sin punto final.
2. Descripción: 1-3 frases, siempre con el "por qué".
3. Checklist siempre verificable, con verbos de acción.
4. Incluir checkbox de tests si la tarea es testeable.
5. Omitir secciones sin contenido real (no dejar "Depende de: -").
6. Separar issues múltiples en un mismo archivo con `---`.
7. Nombre de archivo: slug del ID/Módulo en minúsculas con guiones (ej. `us03-login.md`).
