---
name: hu-auditor
description: Auditoría independiente de una Historia de Usuario (HU) ya implementada, con evidencia real y contra los criterios de aceptación del product backlog. Ejecutá esta skill SIEMPRE inmediatamente después de terminar /hu-implementer, y también cuando el usuario diga "auditá la HU", "revisá si la HU quedó bien", "verificá la implementación", "chequeo por módulos", "antes del push", "está lista para push?" o "revisá que no se haya pasado nada por alto", aunque no mencione la palabra "auditoría". No usar para implementar ni planificar una HU; solo para verificarla.
---

# HU Auditor

Verifica que una HU implementada cumple **lo que el product backlog exige**, no lo que el agente dice haber hecho. Se ejecuta después de `hu-implementer` y antes de cualquier push.

## Por qué existe esta skill

Un checklist donde todo está tildado `[x]` no prueba nada: es el mismo agente que implementó describiendo su propio trabajo. Los errores típicos que esto deja pasar:

- El plan interpretó mal un criterio de aceptación y la auditoría lo heredó.
- El plan prometía tests o comandos de CI que nunca se ejecutaron.
- El agente agregó reglas que el backlog no pide (o se olvidó de reglas que sí pide).
- Los números reportados ("17 tests pasados") no coinciden con lo que el plan declaraba.

Por eso esta auditoría se rige por tres principios:

1. **La fuente de verdad es el product backlog**, no el plan. El plan es una hipótesis a contrastar.
2. **Ningún ítem se marca como cumplido sin evidencia**: comando ejecutado con su salida, o archivo y línea leídos.
3. **Se audita como un revisor externo**: asumí que el código puede estar mal hasta demostrar lo contrario. Desconfiá de tus propias afirmaciones previas de la conversación.

## Entradas

Antes de empezar, ubicá estos insumos. Si falta alguno, pedilo o buscalo en el repo; no lo inventes.

| Insumo | Dónde suele estar |
| :--- | :--- |
| Criterios de aceptación de la HU | `product-backlog.md` (sección de la HU) |
| Contexto del sprint, DER y dependencias | `sprint-backlog-N.md` |
| Plan de implementación | `docs/plans/HU-XXX-plan.md` |
| Reglas del equipo | `AGENTS.md`, `.ai/rules/index.md` y las reglas cuyo glob cubra los paths tocados |
| Convenciones de ramas y PR | `CONTRIBUTING.md` |
| Informe de cierre de `hu-implementer` (commit + cuerpo del PR) | la conversación anterior, si sigue disponible |
| Código implementado | el diff de la rama `feature/HU-XXX-*` contra `master` |

## Flujo de trabajo

Seguí las fases en orden. No pases a la siguiente sin cerrar la anterior.

### Fase 0 — Reconstruir la lista de verificación desde los criterios

Antes de mirar el código, leé los criterios de aceptación del backlog y descomponé cada uno en **afirmaciones atómicas y verificables**. Ejemplo: "para responsable inscripto el CUIT es obligatorio, único y con dígito verificador válido" son tres afirmaciones (obligatorio, único, dígito válido), no una.

Numerá cada afirmación (`CA-1.1`, `CA-2.3`, …). Esa numeración es el eje de todo el informe.

### Fase 1 — Cruzar plan contra criterios

Leé el plan y buscá dos tipos de desvío:

- **Criterio sin cobertura:** una afirmación `CA-x` que el plan no implementa.
- **Regla inventada:** algo que el plan agrega y el backlog no pide (ej.: exigir CUIT a Monotributo cuando el criterio solo lo pide a Responsable Inscripto). No es necesariamente un error, pero el usuario debe decidir si la acepta. Registrala como *decisión pendiente de validación*.

### Fase 2 — Auditoría por módulos, con evidencia

Auditá el código real, módulo por módulo. Los módulos estándar están detallados en `references/modulos.md`; leelo antes de empezar esta fase. Cubren: base de datos, dominio/acciones, validaciones, backend de presentación, frontend, tests y CI, e integraciones.

Para cada ítem, la evidencia puede ser:

- **Lectura:** ruta del archivo y líneas relevantes.
- **Ejecución:** el comando y un resumen fiel de la salida.

Un ítem sin evidencia es `SIN VERIFICAR`, nunca `OK`.

Además de los módulos, verificá el cumplimiento de las **reglas del equipo**: leé `.ai/rules/index.md` y las reglas cuyo glob cubra los archivos tocados. Son restricciones ya decididas, no sugerencias; un incumplimiento es un hallazgo aunque el código funcione.

**Qué buscar en cada módulo, con mentalidad adversaria:**

- ¿La regla está en el lugar donde realmente se ejecuta, o solo declarada? (una validación en el frontend sin equivalente en backend no cuenta)
- ¿Hay un test que **falle si la regla se rompe**? Un test que solo pasa no demuestra nada. Comprobá que las aserciones sean sobre el comportamiento y no triviales.
- ¿Se cubren los casos límite: valores nulos, duplicados, bordes de longitud, estados protegidos?
- ¿Qué pasa con las ramas que dependen de módulos futuros (tablas que aún no existen)? Marcalas como riesgo explícito.

### Fase 3 — Ejecutar la verificación técnica

Corré los comandos reales, no confíes en resultados previos. Los comandos concretos del proyecto (tests, linters, tipos, generadores) están en `references/modulos.md`, sección CI.

Reglas:

- Pegá el resultado real. Si un comando falla o no se puede ejecutar, decilo tal cual; no lo reemplaces por una suposición.
- Cotejá los números contra el plan: cantidad de tests declarada vs. ejecutada. Una discrepancia se investiga, no se ignora.
- Corré la suite completa además del filtro por HU, para detectar regresiones en otros módulos.

### Fase 3b — Auditar el informe de cierre del implementer

`hu-implementer` termina generando un mensaje de commit y un cuerpo de PR. Verificalos contra la realidad:

- Cada criterio marcado `[x]` en el PR tiene evidencia real (un test, un archivo). Un `[x]` sin respaldo es un hallazgo.
- La sección "CI" del PR refleja resultados que efectivamente se ejecutaron. Comparala con lo que corriste en la Fase 3.
- La sección "Notas" declara todo desvío respecto al plan. Si detectaste un desvío que no figura ahí, es un hallazgo: el implementer debía avisarlo y no lo hizo.
- El mensaje de commit sigue Conventional Commits, está en español y describe lo que realmente se hizo.
- El commit no incluye archivos internos que no corresponden (checklists locales, borradores).

### Fase 4 — Prueba de mutación manual (solo en reglas críticas)

Para las 2 o 3 reglas más críticas de la HU (las que protegen datos o dinero), verificá que los tests realmente las protegen: rompé la regla temporalmente, corré el test correspondiente y confirmá que **falla**; después revertí. Si el test sigue pasando, la regla no está protegida.

Hacelo sobre una copia o con `git stash` para no dejar el repo modificado. Confirmá que el árbol quedó limpio al terminar.

### Fase 5 — Informe y veredicto

Generá el informe con la estructura de abajo. El veredicto lo determinan los hallazgos, no el tono.

## Formato del informe

Usá exactamente esta estructura:

```markdown
# Auditoría HU-XXX — <título>

**Rama:** <rama> · **Commit auditado:** <hash> · **Fecha:** <fecha>

## Veredicto
<LISTA PARA PUSH | LISTA CON OBSERVACIONES | NO LISTA> — <una frase con la razón principal>

## Trazabilidad de criterios de aceptación
| ID | Afirmación | Estado | Evidencia |
| :-- | :-- | :-- | :-- |
| CA-1.1 | ... | OK / FALLA / PARCIAL / SIN VERIFICAR | archivo:línea o comando |

## Hallazgos
### Bloqueantes
- [B1] ...
### Importantes
- [I1] ...
### Menores
- [M1] ...

## Decisiones pendientes de validación (reglas no pedidas por el backlog)
- ...

## Riesgos por dependencias futuras
- ...

## Resultado de verificación técnica
| Comando | Resultado | ¿Coincide con el plan? |
| :-- | :-- | :-- |

## Discrepancias plan vs. realidad
- ...

## Para el push
- [ ] Working tree limpio
- [ ] Commit coincide con el auditado
- [ ] Sin hallazgos bloqueantes abiertos
```

### Criterios del veredicto

- **NO LISTA:** hay al menos un hallazgo bloqueante (un criterio de aceptación con estado `FALLA`, o un comando de CI que falla).
- **LISTA CON OBSERVACIONES:** sin bloqueantes, pero con hallazgos importantes o decisiones pendientes que el usuario debe resolver.
- **LISTA PARA PUSH:** todos los criterios en `OK` con evidencia, CI limpio y sin discrepancias sin explicar.

### Severidad

- **Bloqueante:** incumple un criterio de aceptación, rompe datos, o falla CI.
- **Importante:** regla sin test que la proteja, discrepancia entre plan y realidad, riesgo real en producción.
- **Menor:** estilo, nombres, mejoras no funcionales.

## Qué NO hacer

- **No arreglar código durante la auditoría.** Auditar y corregir son roles distintos; si mezclás ambos, el informe pierde valor. Reportá el hallazgo y proponé la corrección aparte, esperando confirmación.
- **No hacer commit ni push.** El push es decisión del usuario.
- **No editar `docs/plans/HU-XXX-plan.md`.** Ese archivo queda versionado en el repo como registro de lo que se planeó; si la implementación se desvió, el desvío se reporta en el informe, no se maquilla reescribiendo el plan.
- **No copiar el checklist del plan** ni marcar `[x]` por lo que dice el plan. Cada estado sale de tu evidencia.
- **No suavizar hallazgos.** Un `NO LISTA` bien fundado es el resultado más útil de esta skill.

## Cierre con el usuario

Terminá el mensaje con un resumen corto: veredicto, cantidad de hallazgos por severidad, y las decisiones que necesitás del usuario. Si hay bloqueantes, ofrecé corregirlos y **esperá su confirmación** antes de tocar el código. Después de corregir, volvé a ejecutar las fases 2, 3 y 5 sobre lo modificado.