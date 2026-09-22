# Automatización de Trello — Supermercados La Linda

Herramienta única para manejar el tablero de Trello del proyecto: subir historias
desde los `.md` del backlog, moverlas entre columnas, asignar responsables, o
crear columnas nuevas — desde la terminal a mano, o invocada por un agente de IA.

## Instalación (una sola vez, cada persona en su compu)

```
pip install requests python-dotenv
```

Copiá `.env.example` a un archivo nuevo llamado `.env` (en la misma carpeta) y
completá tus credenciales reales:

```
TRELLO_API_KEY=tu_api_key
TRELLO_TOKEN=tu_token
TRELLO_BOARD_ID=          (opcional, se pregunta la primera vez si lo dejás vacío)
```

Conseguí tu API Key y Token en https://trello.com/power-ups/admin (o
https://trello.com/app-key).

**El archivo `.env` nunca se sube al repositorio** — ya está en `.gitignore`.
Cada integrante del equipo crea el suyo con sus propias credenciales; lo único
que se sube al repo es `.env.example`, que no tiene valores reales.

## Archivos de este paquete

- `trello_lib.py` — toda la lógica: parsing de los `.md` del backlog y llamadas
  a la API de Trello. No se corre directamente.
- `trello_cli.py` — la herramienta que se ejecuta. Tiene menú interactivo y
  modo directo por argumentos.
- `.env.example` — plantilla de credenciales, sin valores reales.
- `.gitignore` — evita que `.env` se suba por error.

## Uso interactivo (menú, para correr a mano)

```
python trello_cli.py
```

Te muestra un menú:

```
1. Subir historias nuevas desde un .md a una columna
2. Asignar/reasignar responsable de historias ya subidas (una por una)
3. Mover una tarjeta de una columna a otra
4. Crear una columna nueva
5. Sincronizar todo el contenido de un .md contra Trello
6. Listar tableros disponibles
7. Listar columnas de un tablero
```

La opción 2 es la que pediste originalmente: te pregunta cuántas historias vas
a asignar, y para cada una te muestra la lista de HUs del `.md` y la lista de
integrantes para elegir, mostrando el progreso ("Historia 2 de 10") a medida
que avanza.

## Uso directo por línea de comandos (para un agente, o para no repetir el menú)

```
python trello_cli.py listar-tableros
python trello_cli.py listar-columnas [BOARD_ID]
python trello_cli.py crear-columna "Nombre de la columna" [BOARD_ID]
python trello_cli.py subir sprint-backlog-3.md 6a7676... product-backlog.md
python trello_cli.py mover HU-037 "En progreso"
python trello_cli.py asignar HU-037 Chiara
python trello_cli.py dependencias HU-037
python trello_cli.py sincronizar sprint-backlog-3.md 6a7676...
```

- `mover` y `asignar` reciben el ID de la historia (`HU-037`) y buscan la
  tarjeta sola en todo el tablero — no hace falta indicarle en qué columna
  está ni el ID interno de Trello.
- El destino de `mover` puede ser el nombre visible de la columna
  ("En progreso") o su ID de Trello.
- Si `TRELLO_BOARD_ID` está en el `.env`, ningún comando pregunta por el
  tablero. Si no está, lo pregunta la primera vez y te sugiere agregarlo.

### Para que un agente (por ejemplo Antigravity) lo use solo

Un agente puede invocar directamente los comandos sin pasar por el menú, por
ejemplo después de mergear una PR:

```
python trello_cli.py mover HU-037 "En Revisión / Testing"
```

Esto no pide confirmación ni datos adicionales — es apto para que un agente lo
dispare automáticamente según lo que detecte en el repo (una PR mergeada, un
commit con cierto mensaje, etc.), sin intervención humana en el momento.

## Asignación de responsables (sin tocar código)

El responsable de cada historia ya no vive en un diccionario dentro de
`trello_lib.py` — se guarda en un archivo `asignaciones.json` que se crea
solo la primera vez que hace falta:

- Si corrés `subir`, `sincronizar` o `asignar-lote` y una historia todavía no
  tiene responsable guardado, el script te pregunta quién es y lo guarda para
  siempre (no vuelve a preguntar esa misma HU en el futuro).
- Si ya está guardado, lo usa directamente sin preguntar.
- `asignar HU-037 Chiara` también lo guarda, además de aplicarlo en Trello.

El archivo queda así (podés editarlo a mano si hace falta corregir algo):

```json
{
  "HU-037": "Chiara",
  "HU-021": "Azael"
}
```

**Este archivo tampoco se sube al repo con datos reales de otro sprint** si
no querés — es opcional subirlo (a diferencia del `.env`, no tiene
credenciales, así que no hay riesgo de seguridad en subirlo si al equipo le
sirve tenerlo versionado como registro del reparto de cada sprint).

El módulo de cada historia (CMP, ART, ADM, etc.) tampoco se hardcodea más:
se saca directamente del `product-backlog.md`, así que nunca hay que
mantenerlo a mano en ningún lado.

## Flujo de trabajo por columnas (para agentes de desarrollo y testing)

Se agregaron tres comandos pensados para que los dispare un agente sin
intervención humana, siguiendo la convención de columnas del equipo (`Sin
empezar` → `En progreso` → `En Revisión` → `Finalizado`):

```
python trello_cli.py iniciar HU-037
```
Mueve la tarjeta a **"En progreso"**. Pensado para que el agente de
desarrollo lo corra apenas arranca a trabajar en una historia.

```
python trello_cli.py finalizar HU-037
```
Mueve la tarjeta a **"En Revisión"**. Pensado para que el agente de
desarrollo lo corra al terminar la implementación (por ejemplo, al abrir o
mergear la PR).

```
python trello_cli.py revisar HU-037 ok
python trello_cli.py revisar HU-037 rechazado "Falta validar el saldo negativo"
```
Pensado para la skill de testing del equipo: si el resultado es `ok`, mueve
la tarjeta a **"Finalizado"**. Si es `rechazado`, la vuelve a **"En
progreso"** y agrega el motivo como comentario en la tarjeta de Trello, para
que quien retome la historia sepa qué corregir. Si se corre sin el segundo
argumento (`python trello_cli.py revisar HU-037`), pregunta interactivamente
— queda a criterio de la persona que está revisando, tal como se pidió.

Al finalizar la revisión (sea `ok` o `rechazado`), el comando analiza
automáticamente el grafo de dependencias del sprint backlog actual y muestra
qué historias quedan desbloqueadas (listas para desarrollar) o frenadas, junto
con el integrante responsable asignado a cada una.

```
python trello_cli.py dependencias HU-037
```
Permite consultar en cualquier momento el árbol de dependencias, desbloqueos y
responsables de una historia de usuario sin modificar el estado en Trello.

Los nombres de columna (`En progreso`, `En Revisión`, `Finalizado`) están
definidos como constantes al principio de `trello_lib.py`
(`COLUMNA_EN_PROGRESO`, etc.) — si en algún momento cambian en Trello,
alcanza con editarlos ahí una sola vez.


## Modo `--agente` (protocolo para agentes de IA)

Cualquier comando del modo directo acepta el flag `--agente` al final.
Cuando está presente, el script sabe que quien lo invoca es un agente
(como Antigravity), no una persona en una terminal interactiva.

```
python trello_cli.py asignar HU-037 --agente
python trello_cli.py mover HU-037 --agente
python trello_cli.py revisar HU-037 --agente
```

### Qué hace cuando falta un dato

En vez de llamar a `input()` o mostrar un menú, el script:

1. Imprime en stdout un bloque con marcadores de texto plano:

```
##NEEDS_INPUT##
{
  "pregunta": "¿A quién le asignamos HU-037?",
  "opciones": ["Azael", "Chiara", "Clara", "Mauro", "Facundo", "Pablo"],
  "comando_pendiente": "asignar",
  "argumento_faltante": "integrante"
}
##END_NEEDS_INPUT##
```

2. Termina con **exit code 2** (dato faltante), sin haber modificado nada
   en Trello.

### Códigos de salida

| Código | Significado |
|--------|-------------|
| `0`    | Éxito — el comando se completó sin problemas |
| `1`    | Error real (tarjeta duplicada, credenciales inválidas, etc.) |
| `2`    | Dato faltante — el agente debe preguntar al usuario y reintentar |

### Flujo de ida y vuelta (ejemplo)

**Primera llamada** — falta el integrante:
```
python trello_cli.py asignar HU-037 --agente
# → exit 2 + bloque NEEDS_INPUT con opciones de integrante
```

El agente lee el bloque, le muestra la pregunta al usuario en el chat,
y cuando el usuario elige "Chiara", vuelve a llamar con el dato completo:

**Segunda llamada** — datos completos, se ejecuta sin preguntar:
```
python trello_cli.py asignar HU-037 Chiara --agente
# → exit 0, HU-037 asignada a Chiara en Trello
```

### Qué comandos soportan `--agente`

Todos los comandos del modo directo. Los que hoy no tienen ningún input()
(como `iniciar`, `finalizar`, `listar-tableros`) simplemente ignoran el
flag si ya tienen todos los datos. Los que sí preguntan datos faltantes:

- `subir` — pregunta columna destino y opcionalmente responsables
- `mover` — pregunta `hu_id` y/o columna destino si faltan
- `asignar` — pregunta `hu_id` y/o `integrante` si faltan
- `crear-columna` — pregunta el nombre si falta
- `revisar` — pregunta `hu_id` y/o `resultado` si faltan
- `asignar-lote` — cada dato faltante es un NEEDS_INPUT separado (una
  pregunta por vuelta)
- `iniciar` / `finalizar` — preguntan `hu_id` si falta

### Lo que NO cambia con `--agente`

- El menú interactivo (`python trello_cli.py` sin argumentos) sigue usando
  `input()` tal cual — ese modo es para un humano en su terminal.
- La lógica de negocio y las llamadas a la API no cambian.
- El manejo de tarjetas duplicadas (`TarjetaAmbiguaError`) sigue operando
  igual en ambos modos (sale con exit 1 y mensaje `⛔`).


## Notas

- Los diccionarios de integrantes (`MEMBER_IDS`) y etiquetas de módulo
  (`LABEL_IDS`) viven en `trello_lib.py` porque son datos fijos de Trello que
  no cambian de sprint a sprint. Los responsables por historia y el módulo de
  cada HU ya NO están hardcodeados — ver la sección de arriba.
- `subir` y `sincronizar` hacen lo mismo: si la tarjeta ya existe (matcheada
  por `[HU-XXX]` en el nombre), la actualizan en vez de duplicarla.
- Agregá `TRELLO_ASIGNACIONES_PATH=ruta/asignaciones.json` a tu `.env` si
  querés que el archivo de asignaciones viva en otro lugar (por defecto se
  busca/crea `asignaciones.json` en la carpeta donde corrés el script).
