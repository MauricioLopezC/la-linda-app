---
paths:
  - 'automatizaciones/trello/**'
---

# Trello Automation

## Tablero y sincronización de Historias de Usuario
Toda la gestión del tablero de Trello del proyecto se centraliza en `automatizaciones/trello/trello_cli.py` y `automatizaciones/trello/trello_lib.py`.

Consultá la documentación completa en [automatizaciones/trello/README.md](../../automatizaciones/trello/README.md).

### Convenciones clave:
- **Modo Agente (`--agente`):** Los agentes siempre deben invocar comandos en modo directo con el flag `--agente` (ej. `python automatizaciones/trello/trello_cli.py iniciar HU-XXX --agente`), nunca en modo interactivo.
- **Protocolo `##NEEDS_INPUT##`:** Si al script le falta un dato, devuelve un bloque delimitado con JSON y termina con exit code 2 para que el agente consulte al usuario antes de reintentar.
- **Asignaciones de responsables:** Se persisten de forma incremental en `automatizaciones/trello/asignaciones.json` sin tocar código fuente.
- **Transición de columnas:**
  - `iniciar HU-XXX`: mueve a "En Progreso" al comenzar el desarrollo.
  - `finalizar HU-XXX`: mueve a "En revisión" al concluir la implementación.
  - `revisar HU-XXX ok`: mueve a "Finalizado" tras aprobación de code-review/auditoría.
  - `revisar HU-XXX rechazado "<motivo>"`: devuelve a "En Progreso" con comentario.
