# Regla: Reporte de Issues al finalizar una Historia de Usuario

## Cuándo aplica esta regla

Esta regla aplica siempre que el agente reciba el pedido de implementar, resolver o avanzar una Historia de Usuario (HU), tarea del sprint, o cualquier ticket de trabajo del backlog. Se activa automáticamente, sin que el usuario tenga que pedirlo explícitamente.

No aplica para consultas puntuales, dudas rápidas de código, o tareas que no impliquen desarrollo de una HU/ticket.

## Qué debe hacer el agente

### 1. Durante el desarrollo de la HU

Mientras trabaja en la implementación, el agente debe estar atento a cualquiera de estos hallazgos y **registrarlos en el momento en que aparecen** (en memoria de la sesión, no hace falta interrumpir el flujo de trabajo para escribir el archivo todavía):

- **Bug:** un comportamiento incorrecto detectado, esté o no relacionado directamente con la HU actual.
- **Punto abierto:** una duda de diseño, arquitectura, o de negocio que no se puede resolver sin el Product Owner/docente/equipo.
- **Nueva funcionalidad / mejora:** algo que no estaba contemplado en el alcance original de la HU pero que surgió como necesario o conveniente durante la implementación.
- **Deuda técnica:** algo que se resolvió "rápido y sucio" para no bloquear el avance, y que debería revisarse después.

Cada hallazgo debe anotarse con lo mínimo necesario para redactarlo después como issue: tipo, breve descripción, y en qué parte del código/flujo apareció.

### 2. Al finalizar la HU

Cuando el agente termina la implementación de la HU (o la sesión de trabajo sobre ella), **antes de dar el trabajo por cerrado**, debe:

1. Revisar todos los hallazgos registrados durante el desarrollo (paso 1).
2. Si hubo al menos un hallazgo (bug, punto abierto, nueva funcionalidad o deuda técnica): usar la skill `github-issue-writer` para redactar un issue por cada hallazgo, agrupados en un único archivo `.md` correspondiente a esa HU.
3. Si no hubo ningún hallazgo: no es necesario generar ningún archivo ni forzar la creación de issues vacíos.

### 3. Dónde entregar el resultado

Cuando se genera el archivo de issues:

- **Guardarlo en el repositorio**, en la carpeta `.ai/issues/` (crearla si no existe), con el nombre del ID/módulo de la HU (ej: `.ai/issues/us03-login.md`), siguiendo la convención de nombres definida en la skill `github-issue-writer`.
- **Mostrarlo también al usuario** en la respuesta final, para que lo pueda revisar y copiar a GitHub sin tener que ir a buscar el archivo al repo.

### 4. Cierre de la respuesta

Al entregar el trabajo de la HU, el agente debe indicar explícitamente:

- Que la HU fue completada (o el estado en que quedó).
- Si se generaron issues: cuántos y de qué tipo (ej: "se detectaron 2 puntos abiertos y 1 bug, ver `.ai/issues/us03-login.md`").
- Si no se generaron issues: no hace falta aclararlo, simplemente no se menciona.

No se debe pegar el contenido completo de cada issue en la respuesta de chat si ya se generó el archivo; ver referencia al archivo alcanza salvo que el usuario pida ver el detalle ahí mismo.

## Relación con otras reglas y skills

- Esta regla se aplica **junto con** el resto de reglas presentes en `.ai/rules/` (convenciones de código, estilo, testing, etc.), nunca las reemplaza.
- Para el formato y redacción del issue en sí, esta regla delega completamente en la skill `github-issue-writer`: no se debe inventar un formato alternativo de issue.
