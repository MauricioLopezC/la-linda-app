---
name: plan-de-sprint
description: Arma el "orden de ataque" visual de un sprint a partir de la distribución de tareas del equipo (quién hace qué historia) y el sprint backlog. Publica un Artifact con carga de SP por persona, cronograma día por día con dependencias interactivas, caminos críticos, contratos del día 1, archivos donde van a chocar y orden de recorte. Usar cuando el usuario pase un reparto de historias por persona y pida ordenar, coordinar o paralelizar el trabajo del sprint, o pida "el diagrama del sprint" / "orden de ataque".
---

# Orden de ataque del sprint

Convierte un reparto de historias por persona en una página interactiva para coordinar el sprint.
La página es `template.html` (en esta carpeta): todo el render sale de un único objeto `PLAN` en su
`<script>`. **Solo se reemplaza el bloque entre `DATOS DEL SPRINT` y `FIN DE LOS DATOS`** (y el
`<title>`); el CSS y las funciones de render no se tocan.

## Entrada

- El reparto que pega el usuario, por ejemplo `57 MAURI`, `07+63 CHIARA`, `E03 PABLO`. Los números
  sueltos son `HU-0NN` y `E0N` es `EPIC-0N`. `migraciones` (o similar) es el PR de esquema: un
  portón con `sp: 0` en el día 0.
- `docs/backlog/sprint-backlog-N.md` (el sprint vigente, el de número más alto): fechas, SP,
  columna "Depende de", decisiones, desglose en tareas, hitos (revisión del PO, checkpoint de
  recorte), orden de recorte y DER.
- `docs/backlog/product-backlog.md` solo si hace falta el título o los criterios de algún ítem.
- Estado real: `git log`, `gh pr list` (¿el PR de esquema ya está mergeado?). Si hay una decisión
  que cambia el plan y no está en los docs, preguntarla antes de publicar.

## Análisis (antes de llenar los datos)

1. **Carga por persona:** sumar SP. Comparar con el promedio parejo (`totalSp / personas`) y
   marcar a quien supere por mucho (`overloadAbove`, típicamente promedio × 1,5).
2. **Dependencias:** tomar las de la columna "Depende de", pero solo las que están dentro del
   sprint (las de sprints anteriores ya están hechas). Todo lo que usa tablas nuevas depende del
   portón del esquema.
3. **Caminos críticos:** las cadenas más largas de dependencias, en general una por área. Anotar
   cuántas personas cruza cada una y cuántos SP suma en serie.
4. **Cómo no esperar:** para cada ítem con dependencia, decidir si puede arrancar con factories o
   stubs contra el esquema ya mergeado (`fac: true`) y escribir en su `note` cómo hacerlo. Esto
   es lo que más valor aporta: el orden real suele ser mucho más paralelo que la cadena formal.
5. **Cronograma:** días hábiles del sprint (día 0 = planning). Duración de cada bloque ≈
   `SP / spPerDay` (2,5 por defecto; ajustar con la velocidad observada). Ubicar cada bloque después
   de sus dependencias duras y sin superponer bloques de la misma persona. Lo que no entra va a la
   columna `after`: que se vea, no esconderlo.
6. **Propuesta de rebalanceo (opcional, una sola):** si alguien queda sobrecargado o dos personas
   tocan la misma Action o pantalla, proponer mover un ítem con `alt: { who, s, e }` en ese ítem y
   `proposal` con el texto. El interruptor de la página la aplica en vivo.
7. **Contratos del día 1:** firmas de Actions o piezas compartidas que varias personas consumen
   (quién la implementa, quién la llama, stub mientras tanto).
8. **Archivos donde van a chocar:** Actions, páginas (`resources/js/pages/...`) o tests que tocan
   dos o más ítems de personas distintas, con qué hacer (quién mergea primero, partir en
   componentes, etc.). Verificar en el código que los archivos existen o se van a crear.
9. **Orden de recorte:** el del sprint backlog si lo tiene; si no, proponer uno desde el final de
   cada camino crítico.

## Llenado y publicación

1. Copiar `template.html` al scratchpad de la sesión con un nombre como `sprint-N-plan.html`.
2. Reemplazar el bloque `PLAN` respetando el esquema documentado en su comentario. Máximo 3
   `tracks`, con las clases `caja` (verde), `fact` (ámbar) y `tienda` (azul); el portón usa
   `track: 'gate'`. Cambiar el `<title>` a `Sprint N · Orden de ataque`.
3. Chequear la sintaxis del script:
   `node -e "const s=require('fs').readFileSync('<archivo>','utf8');new Function(s.split('<script>')[1].split('</script>')[0]);console.log('ok')"`.
4. Publicar con el tool `Artifact` (`icon: "calendar"`, descripción de una oración). Si ya existe
   el artifact del mismo sprint, actualizarlo por su `url` en lugar de crear uno nuevo.
5. Responder corto: el link, qué muestra (una línea por sección), los supuestos del cronograma
   (SP por día), la propuesta de rebalanceo si la hay, y que la página es privada hasta que se
   comparta desde el menú Share.

## Estilo del contenido

- Español rioplatense ("tocá", "arrancá"), frases cortas y concretas.
- Nombres de las personas como los escribe el usuario, en formato título (MAURI → Mauri).
- IDs como en el backlog (`HU-057`, `EPIC-04`); pares como `HU-007+063`.
- Las notas de cada bloque dicen cómo arrancar sin esperar y qué desbloquea, con nombres reales de
  Actions, tablas y factories del proyecto.
