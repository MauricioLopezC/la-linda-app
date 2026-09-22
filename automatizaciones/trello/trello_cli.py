#!/usr/bin/env python3
"""
trello_cli.py — Herramienta única para manejar el tablero de Trello del
proyecto "Supermercados La Linda" desde la terminal o desde un agente de IA.

CONFIGURACIÓN (una sola vez):
    1. pip install requests python-dotenv
    2. Copiá .env.example a .env y completá tus valores reales:
         TRELLO_API_KEY=...
         TRELLO_TOKEN=...
         TRELLO_BOARD_ID=...   (opcional, ver "listar-tableros" más abajo)
       El archivo .env NUNCA se sube al repositorio (está en .gitignore).

MODO INTERACTIVO (para uso manual, un humano corriendo el script):
    python trello_cli.py
    → Muestra un menú y va preguntando lo que necesita.

MODO DIRECTO (para uso desde un agente, o para no repetir el menú a mano):
    python trello_cli.py listar-tableros
    python trello_cli.py listar-columnas [BOARD_ID]
    python trello_cli.py crear-columna "Nombre de la columna" [BOARD_ID]
    python trello_cli.py subir ARCHIVO.md LIST_ID [PRODUCT_BACKLOG.md]
    python trello_cli.py mover HU-037 "Nombre o ID de columna destino" [BOARD_ID]
    python trello_cli.py asignar HU-037 NombreIntegrante [BOARD_ID]
    python trello_cli.py sincronizar ARCHIVO.md LIST_ID [PRODUCT_BACKLOG.md]

    Flujo de trabajo (para que lo dispare un agente de desarrollo o de testing):
    python trello_cli.py iniciar HU-037                → mueve a "En progreso"
    python trello_cli.py finalizar HU-037               → mueve a "En Revisión"
    python trello_cli.py revisar HU-037 ok              → mueve a "Finalizado"
    python trello_cli.py revisar HU-037 rechazado "Falta validar el saldo"
                                                         → vuelve a "En progreso" con el motivo como comentario

    Cualquier comando sin argumentos suficientes cae al modo interactivo
    para completar lo que falte, así que también sirven a medias.

MODO AGENTE (flag --agente):
    Agregar --agente a cualquier comando de modo directo activa el protocolo
    de datos faltantes: en vez de llamar a input() o mostrar un menú, el
    script imprime un bloque ##NEEDS_INPUT## con JSON y termina con exit
    code 2, sin haber modificado nada en Trello. Ver README para detalles.
"""

import json
import os
import sys

# Windows usa cp1252 por defecto; forzamos UTF-8 para que los emojis y
# caracteres especiales (✓ ✗ Revisión, etc.) se impriman sin error.
if sys.stdout.encoding and sys.stdout.encoding.lower() != "utf-8":
    sys.stdout = open(sys.stdout.fileno(), mode="w", encoding="utf-8", buffering=1)
if sys.stderr.encoding and sys.stderr.encoding.lower() != "utf-8":
    sys.stderr = open(sys.stderr.fileno(), mode="w", encoding="utf-8", buffering=1)

try:
    from dotenv import load_dotenv
    load_dotenv()
except ImportError:
    pass  # si no está python-dotenv instalado, se sigue con variables de entorno del sistema

import trello_lib as t

# ── Modo agente ───────────────────────────────────────────────────────────────
# Se activa con el flag --agente en cualquier comando de modo directo.
# Se extrae aquí (antes del routing) y se elimina de sys.argv para que
# los comandos no lo vean como un argumento posicional inesperado.

AGENTE_MODE = "--agente" in sys.argv
if AGENTE_MODE:
    sys.argv = [a for a in sys.argv if a != "--agente"]

# El nombre del comando actual, para incluirlo en el bloque NEEDS_INPUT.
_COMANDO_ACTUAL = sys.argv[1] if len(sys.argv) >= 2 else ""


def necesita_input(pregunta, opciones, argumento_faltante):
    """Emite el bloque ##NEEDS_INPUT## en stdout y termina con exit code 2.

    Solo se llama cuando AGENTE_MODE es True. El agente detecta este bloque,
    le muestra la pregunta al usuario, y vuelve a llamar al mismo comando
    con la respuesta como argumento adicional.

    Exit code 2 distingue "dato faltante" de error real (exit 1) y de
    éxito normal (exit 0).
    """
    payload = {
        "pregunta": pregunta,
        "opciones": opciones,
        "comando_pendiente": _COMANDO_ACTUAL,
        "argumento_faltante": argumento_faltante,
    }
    print("##NEEDS_INPUT##")
    print(json.dumps(payload, ensure_ascii=False, indent=2))
    print("##END_NEEDS_INPUT##")
    sys.exit(2)


# ── Utilidades de menú interactivo ─────────────────────────────────────────

def elegir_de_lista(opciones, etiqueta="opción", mostrar=lambda o: o):
    """Muestra una lista numerada y devuelve el elemento elegido.
    En modo agente nunca se llama directamente: cada cmd_* usa
    necesita_input() antes de llegar acá si falta el dato."""
    for i, op in enumerate(opciones, 1):
        print(f"  {i}. {mostrar(op)}")
    while True:
        resp = input(f"Elegí un número de {etiqueta}: ").strip()
        if resp.isdigit() and 1 <= int(resp) <= len(opciones):
            return opciones[int(resp) - 1]
        print("Opción inválida, probá de nuevo.")


def resolver_board_id(board_id_arg=None):
    if board_id_arg:
        return board_id_arg
    if t.BOARD_ID:
        return t.BOARD_ID
    if AGENTE_MODE:
        tableros = t.listar_tableros()
        necesita_input(
            "¿En qué tablero de Trello trabajamos?",
            [f"{b['name']} ({b['id']})" for b in tableros],
            "board_id",
        )
    print("\nNo hay TRELLO_BOARD_ID configurado. Elegí un tablero:")
    tableros = t.listar_tableros()
    elegido = elegir_de_lista(tableros, "tablero", lambda b: b["name"])
    print(f"(Tip: agregá TRELLO_BOARD_ID={elegido['id']} a tu .env para no elegir esto cada vez)")
    return elegido["id"]


def resolver_columna_id(board_id, nombre_o_id):
    """Acepta tanto un ID de lista de Trello como el nombre visible de la columna."""
    columnas = t.listar_columnas(board_id)
    for c in columnas:
        if c["id"] == nombre_o_id or c["name"].strip().lower() == nombre_o_id.strip().lower():
            return c["id"]
    return None


def resolver_owner_interactivo(hu_id, asignaciones):
    """Si ya hay un responsable guardado para esta HU, lo devuelve sin
    preguntar. Si no, pregunta y lo guarda en asignaciones.json para la
    próxima vez que se use cualquier comando con esta historia."""
    if hu_id in asignaciones:
        return asignaciones[hu_id]
    if AGENTE_MODE:
        necesita_input(
            f"¿Quién es el responsable de {hu_id}?",
            list(t.MEMBER_IDS.keys()),
            "integrante",
        )
    print(f"\nTodavía no hay responsable asignado para {hu_id}.")
    nombres = list(t.MEMBER_IDS.keys())
    integrante = elegir_de_lista(nombres, "integrante")
    asignaciones[hu_id] = integrante
    t.guardar_asignaciones(asignaciones)
    return integrante


# ── Comandos ─────────────────────────────────────────────────────────────

def cmd_listar_tableros():
    for b in t.listar_tableros():
        print(f"{b['id']}  {b['name']}")


def cmd_listar_columnas(board_id=None):
    board_id = resolver_board_id(board_id)
    print(f"\nColumnas del tablero {board_id}:\n")
    for c in t.listar_columnas(board_id):
        print(f"{c['id']}  {c['name']}")


def cmd_crear_columna(nombre=None, board_id=None):
    board_id = resolver_board_id(board_id)
    if not nombre:
        if AGENTE_MODE:
            necesita_input(
                "¿Cuál es el nombre de la nueva columna?",
                [],
                "nombre",
            )
        nombre = input("Nombre de la nueva columna: ").strip()
    list_id = t.crear_columna(board_id, nombre)
    print(f"✓ Columna '{nombre}' creada. ID: {list_id}")
    return list_id


def cmd_subir(archivo_md=None, list_id=None, product_backlog=None, board_id=None):
    if not archivo_md:
        if AGENTE_MODE:
            necesita_input(
                "¿Qué archivo .md querés subir a Trello?",
                [],
                "archivo_md",
            )
        archivo_md = input("Ruta del archivo .md a subir: ").strip()
    if not os.path.isfile(archivo_md):
        sys.exit(f"No se encontró el archivo '{archivo_md}'.")

    board_id = resolver_board_id(board_id)
    if not list_id:
        columnas = t.listar_columnas(board_id)
        if AGENTE_MODE:
            necesita_input(
                "¿A qué columna subimos las tarjetas?",
                [c["name"] for c in columnas],
                "list_id",
            )
        print("\n¿A qué columna subimos las tarjetas?")
        elegida = elegir_de_lista(columnas, "columna", lambda c: c["name"])
        list_id = elegida["id"]
    else:
        resolved = resolver_columna_id(board_id, list_id)
        if resolved:
            list_id = resolved

    if product_backlog and not os.path.isfile(product_backlog):
        product_backlog = None
    elif product_backlog is None and os.path.isfile("product-backlog.md"):
        product_backlog = "product-backlog.md"

    asignaciones = t.cargar_asignaciones()
    historias = t.parse_markdown(archivo_md, product_backlog, asignaciones)

    # Si alguna historia no tiene responsable guardado, se pregunta ahora
    # (una sola vez por HU) y queda guardado en asignaciones.json.
    faltantes = [h for h in historias if not h["owner"]]
    if faltantes:
        if AGENTE_MODE:
            # En modo agente solo avisamos, no pedimos — el agente no puede
            # asignar en masa de forma útil sin más contexto del usuario.
            print(f"\n⚠ {len(faltantes)} historia(s) sin responsable asignado: "
                  f"{', '.join(h['id'] for h in faltantes)}. "
                  "Usá 'asignar HU-XXX Nombre --agente' para asignarlas antes o después.")
        else:
            print(f"\n{len(faltantes)} historia(s) sin responsable asignado todavía.")
            if input("¿Querés asignarlas ahora? [s/N]: ").strip().lower() == "s":
                for h in faltantes:
                    h["owner"] = resolver_owner_interactivo(h["id"], asignaciones)

    cards_existentes = t.get_cards_en_lista(list_id)

    a_crear, a_actualizar, ambiguas = [], [], []
    for h in historias:
        try:
            existing_id = t.find_card_by_hu_id(cards_existentes, h["id"])
        except t.TarjetaAmbiguaError as e:
            ambiguas.append(str(e))
            continue
        if existing_id:
            a_actualizar.append((h, existing_id))
        else:
            a_crear.append(h)

    if ambiguas:
        print(f"\n⚠  {len(ambiguas)} historia(s) con tarjeta duplicada en Trello (se saltean):")
        for msg in ambiguas:
            print(f"  ⛔ {msg}")

    print(f"\n{len(a_crear)} tarjetas nuevas a crear, {len(a_actualizar)} ya existentes a actualizar:\n")
    for h in a_crear:
        print(f"  [crear]      {h['id']} ({h['estimacion']} SP, {h['owner'] or 'sin asignar'}) → {h['title']}")
    for h, _ in a_actualizar:
        print(f"  [actualizar] {h['id']} ({h['estimacion']} SP, {h['owner'] or 'sin asignar'}) → {h['title']}")

    if AGENTE_MODE:
        # En modo agente confirmamos automáticamente (el agente ya tiene todos
        # los datos y no hay nadie para contestar un input).
        print("\n[modo agente] Confirmando automáticamente...")
    elif input("\n¿Confirmar en Trello? [s/N]: ").strip().lower() != "s":
        print("Cancelado. No se modificó nada.")
        return

    print()
    for hu in a_crear:
        t.create_trello_card(hu, list_id)
    for hu, card_id in a_actualizar:
        t.update_trello_card(card_id, hu)
    print("\n¡Proceso finalizado!")


def cmd_mover(hu_id=None, destino=None, board_id=None):
    board_id = resolver_board_id(board_id)
    if not hu_id:
        if AGENTE_MODE:
            necesita_input(
                "¿Qué historia querés mover? (ej. HU-037)",
                [],
                "hu_id",
            )
        hu_id = input("ID de la historia a mover (ej. HU-037): ").strip().upper()

    card_id, list_id_actual = t.find_card_by_hu_id_en_tablero(board_id, hu_id)
    if not card_id:
        sys.exit(f"No se encontró ninguna tarjeta '[{hu_id}]' en el tablero.")

    if not destino:
        columnas = t.listar_columnas(board_id)
        if AGENTE_MODE:
            necesita_input(
                f"¿A qué columna movemos {hu_id}?",
                [c["name"] for c in columnas],
                "destino",
            )
        print("\n¿A qué columna la movemos?")
        elegida = elegir_de_lista(columnas, "columna", lambda c: c["name"])
        destino_id = elegida["id"]
    else:
        destino_id = resolver_columna_id(board_id, destino)
        if not destino_id:
            sys.exit(f"No se encontró ninguna columna llamada o con ID '{destino}'.")

    if t.mover_tarjeta(card_id, destino_id):
        print(f"✓ {hu_id} movida correctamente.")
    else:
        print(f"✗ No se pudo mover {hu_id}.")


def cmd_asignar(hu_id=None, integrante=None, board_id=None):
    board_id = resolver_board_id(board_id)
    if not hu_id:
        if AGENTE_MODE:
            necesita_input(
                "¿Qué historia querés asignar? (ej. HU-037)",
                [],
                "hu_id",
            )
        hu_id = input("ID de la historia (ej. HU-037): ").strip().upper()

    card_id, _ = t.find_card_by_hu_id_en_tablero(board_id, hu_id)
    if not card_id:
        sys.exit(f"No se encontró ninguna tarjeta '[{hu_id}]' en el tablero.")

    if not integrante:
        if AGENTE_MODE:
            necesita_input(
                f"¿A quién le asignamos {hu_id}?",
                list(t.MEMBER_IDS.keys()),
                "integrante",
            )
        print("\n¿A quién se lo asignamos?")
        nombres = list(t.MEMBER_IDS.keys())
        integrante = elegir_de_lista(nombres, "integrante")
    elif integrante not in t.MEMBER_IDS:
        sys.exit(f"'{integrante}' no está en la lista de integrantes: {list(t.MEMBER_IDS)}")

    if t.asignar_miembro(card_id, integrante):
        t.set_asignacion(hu_id, integrante)
        print(f"✓ {hu_id} asignada a {integrante}.")
    else:
        print(f"✗ No se pudo asignar {hu_id}.")


def cmd_asignar_lote(archivo_md=None, board_id=None):
    """Pregunta cuántas HU se van a asignar hoy, y para cada una: si ya tiene
    responsable guardado en asignaciones.json lo muestra sin volver a
    preguntar; si no, pregunta y lo guarda. Muestra progreso 'N de TOTAL'.

    En modo agente, cada dato faltante se resuelve con una ida y vuelta
    separada (un bloque NEEDS_INPUT por pregunta), en vez de un loop
    interactivo completo."""
    board_id = resolver_board_id(board_id)

    if not archivo_md:
        if AGENTE_MODE:
            necesita_input(
                "¿Qué archivo .md tiene las historias del sprint a asignar?",
                [],
                "archivo_md",
            )
        archivo_md = input("Archivo .md con las historias disponibles: ").strip()

    if not os.path.isfile(archivo_md):
        sys.exit(f"No se encontró '{archivo_md}'.")

    asignaciones = t.cargar_asignaciones()
    historias = t.parse_markdown(archivo_md, asignaciones=asignaciones)
    ids_disponibles = [h["id"] for h in historias]

    try:
        total_str = sys.argv[sys.argv.index("asignar-lote") + 2] if not AGENTE_MODE else None
    except (ValueError, IndexError):
        total_str = None

    if total_str is None:
        if AGENTE_MODE:
            necesita_input(
                "¿Cuántas historias vas a asignar/revisar en este lote?",
                [str(i) for i in range(1, len(ids_disponibles) + 1)],
                "total",
            )
        try:
            total = int(input("\n¿Cuántas historias vas a asignar/revisar hoy?: ").strip())
        except ValueError:
            sys.exit("Ingresá un número.")
    else:
        try:
            total = int(total_str)
        except ValueError:
            sys.exit("El parámetro 'total' debe ser un número.")

    hechas = []
    for i in range(1, total + 1):
        print(f"\n— Historia {i} de {total} —")
        pendientes = [hu for hu in ids_disponibles if hu not in hechas]

        # En modo agente, si no hay un argumento para esta iteración, pedimos
        # la historia a asignar con un NEEDS_INPUT propio.
        hu_id = None
        arg_index = sys.argv.index("asignar-lote") + 3 + (i - 1) * 2 if AGENTE_MODE else -1
        if AGENTE_MODE:
            try:
                hu_id = sys.argv[arg_index]
            except IndexError:
                necesita_input(
                    f"Historia {i} de {total}: ¿cuál historia asignamos?",
                    pendientes,
                    f"hu_{i}",
                )
        if hu_id is None:
            hu_id = elegir_de_lista(pendientes, "historia")

        ya_asignada = asignaciones.get(hu_id)
        integrante = None

        if AGENTE_MODE:
            try:
                integrante = sys.argv[arg_index + 1]
            except IndexError:
                if ya_asignada:
                    print(f"  Ya está asignada a {ya_asignada}. Pasando al siguiente.")
                    integrante = ya_asignada
                else:
                    necesita_input(
                        f"¿A quién asignamos {hu_id}?",
                        list(t.MEMBER_IDS.keys()),
                        f"integrante_{i}",
                    )
        else:
            if ya_asignada:
                print(f"  Ya está asignada a {ya_asignada}.")
                cambiar = input("  ¿Cambiar el responsable? [s/N]: ").strip().lower()
                integrante = (
                    elegir_de_lista(list(t.MEMBER_IDS.keys()), "integrante")
                    if cambiar == "s"
                    else ya_asignada
                )
            else:
                integrante = elegir_de_lista(list(t.MEMBER_IDS.keys()), "integrante")

        asignaciones[hu_id] = integrante
        t.guardar_asignaciones(asignaciones)

        try:
            card_id, _ = t.find_card_by_hu_id_en_tablero(board_id, hu_id)
        except t.TarjetaAmbiguaError as e:
            print(f"  ⛔ {e}")
            hechas.append(hu_id)
            continue
        if not card_id:
            print(f"  ⚠ {hu_id} no está subida a Trello todavía — se guardó igual la asignación local.")
        elif t.asignar_miembro(card_id, integrante):
            print(f"  ✓ {hu_id} asignada a {integrante}. ({i}/{total})")
        else:
            print(f"  ✗ No se pudo asignar {hu_id} en Trello (se guardó localmente). ({i}/{total})")
        hechas.append(hu_id)

    print(f"\nCompletado: {len(hechas)}/{total} historias procesadas.")


def cmd_sincronizar(archivo_md=None, list_id=None, product_backlog=None, board_id=None):
    """Igual que 'subir', pero pensado para correr repetidas veces según
    cambia el .md: siempre relee el archivo y actualiza todo lo que ya existe,
    sin distinguir mentalmente 'esto es solo para la primera subida'."""
    print("Sincronizando: se van a actualizar todas las tarjetas existentes con el "
          "contenido actual del archivo, y se crearán las que falten.\n")
    cmd_subir(archivo_md, list_id, product_backlog, board_id)


# ── Flujo de trabajo por columnas ────────────────────────────────────────

def cmd_iniciar(hu_id=None, board_id=None):
    """Para que un agente de desarrollo avise que arrancó a trabajar en una
    historia: mueve la tarjeta a 'En progreso'."""
    board_id = resolver_board_id(board_id)
    if not hu_id:
        if AGENTE_MODE:
            necesita_input(
                "¿Qué historia estás por empezar a desarrollar?",
                [],
                "hu_id",
            )
        hu_id = input("ID de la historia que estás por empezar (ej. HU-037): ").strip().upper()
    ok, mensaje = t.iniciar_desarrollo(board_id, hu_id)
    print(("✓ " if ok else "✗ ") + mensaje)


def cmd_finalizar(hu_id=None, board_id=None):
    """Para que un agente de desarrollo avise que terminó una historia:
    mueve la tarjeta a 'En Revisión'."""
    board_id = resolver_board_id(board_id)
    if not hu_id:
        if AGENTE_MODE:
            necesita_input(
                "¿Qué historia terminaste de implementar?",
                [],
                "hu_id",
            )
        hu_id = input("ID de la historia que terminaste (ej. HU-037): ").strip().upper()
    ok, mensaje = t.finalizar_desarrollo(board_id, hu_id)
    print(("✓ " if ok else "✗ ") + mensaje)


def cmd_revisar(hu_id=None, resultado=None, motivo=None, board_id=None):
    """Para la skill de testing: resuelve la revisión de una historia.
    resultado: 'ok' → mueve a Finalizado. 'rechazado' → vuelve a En progreso
    con el motivo como comentario en la tarjeta. Si no se pasa el resultado,
    se pregunta interactivamente (deja a criterio de la persona, como pediste)."""
    board_id = resolver_board_id(board_id)
    if not hu_id:
        if AGENTE_MODE:
            necesita_input(
                "¿Qué historia estás revisando?",
                [],
                "hu_id",
            )
        hu_id = input("ID de la historia revisada (ej. HU-037): ").strip().upper()

    if resultado is None:
        if AGENTE_MODE:
            necesita_input(
                f"¿Cómo quedó la revisión de {hu_id}?",
                ["ok", "rechazado"],
                "resultado",
            )
        print(f"\n¿Cómo quedó la revisión de {hu_id}?")
        opcion = elegir_de_lista(
            ["Está todo bien → mover a Finalizado", "Hay que corregir → volver a En progreso"],
            "resultado",
        )
        aprobado = opcion.startswith("Está todo bien")
        if not aprobado:
            motivo = input("¿Por qué se devuelve? (opcional, Enter para omitir): ").strip() or None
    else:
        aprobado = resultado.strip().lower() in ("ok", "aprobado", "aprobada", "bien")

    ok, mensaje = t.resolver_revision(board_id, hu_id, aprobado, motivo)
    print(("✓ " if ok else "✗ ") + mensaje)
    if ok:
        try:
            impacto = t.analizar_impacto_dependencias(board_id, hu_id, aprobado)
            texto_impacto = t.formatear_impacto_dependencias(impacto)
            if texto_impacto:
                print("\n" + texto_impacto)
        except Exception:
            pass


def cmd_dependencias(hu_id=None, board_id=None):
    """Consulta qué historias dependen de una HU, cuáles quedan desbloqueadas y quién es su responsable."""
    board_id = resolver_board_id(board_id)
    if not hu_id:
        if AGENTE_MODE:
            necesita_input(
                "¿De qué historia querés consultar las dependencias e impacto?",
                [],
                "hu_id",
            )
        hu_id = input("ID de la historia (ej. HU-012): ").strip().upper()

    impacto = t.analizar_impacto_dependencias(board_id, hu_id, aprobado=True)
    print(f"\n=== Dependencias e impacto de [{hu_id}] ===\n")
    print(t.formatear_impacto_dependencias(impacto))


# ── Menú interactivo principal ──────────────────────────────────────────────

MENU = [
    ("Subir historias nuevas desde un .md a una columna", lambda: cmd_subir()),
    ("Asignar/reasignar responsable de historias ya subidas (una por una)", lambda: cmd_asignar_lote()),
    ("Mover una tarjeta de una columna a otra", lambda: cmd_mover()),
    ("Crear una columna nueva", lambda: cmd_crear_columna()),
    ("Sincronizar todo el contenido de un .md contra Trello", lambda: cmd_sincronizar()),
    ("Marcar que empecé a desarrollar una historia → 'En progreso'", lambda: cmd_iniciar()),
    ("Marcar que terminé de desarrollar una historia → 'En Revisión'", lambda: cmd_finalizar()),
    ("Resolver una revisión/testing → 'Finalizado' o de vuelta a 'En progreso'", lambda: cmd_revisar()),
    ("Consultar dependencias e impacto de una historia", lambda: cmd_dependencias()),
    ("Listar tableros disponibles", cmd_listar_tableros),
    ("Listar columnas de un tablero", lambda: cmd_listar_columnas()),
]


def menu_interactivo():
    print("=== Automatización de Trello — Supermercados La Linda ===\n")
    print("¿Qué querés hacer?\n")
    _, accion = elegir_de_lista(MENU, "acción", mostrar=lambda m: m[0])
    accion()


# ── Punto de entrada ─────────────────────────────────────────────────────

COMANDOS = {
    "listar-tableros": lambda args: cmd_listar_tableros(),
    "listar-columnas": lambda args: cmd_listar_columnas(*args),
    "crear-columna": lambda args: cmd_crear_columna(*args),
    "subir": lambda args: cmd_subir(*args),
    "mover": lambda args: cmd_mover(*args),
    "asignar": lambda args: cmd_asignar(*args),
    "asignar-lote": lambda args: cmd_asignar_lote(*args),
    "sincronizar": lambda args: cmd_sincronizar(*args),
    "iniciar": lambda args: cmd_iniciar(*args),
    "finalizar": lambda args: cmd_finalizar(*args),
    "revisar": lambda args: cmd_revisar(*args),
    "dependencias": lambda args: cmd_dependencias(*args),
}


def main():
    t.require_credentials()

    if len(sys.argv) < 2:
        try:
            menu_interactivo()
        except t.TarjetaAmbiguaError as e:
            print(f"⛔ {e}")
            sys.exit(1)
        return

    comando = sys.argv[1]
    args = sys.argv[2:]

    if comando not in COMANDOS:
        print(f"Comando desconocido: '{comando}'")
        print(f"Comandos disponibles: {', '.join(COMANDOS)}")
        sys.exit(1)

    try:
        COMANDOS[comando](args)
    except t.TarjetaAmbiguaError as e:
        print(f"⛔ {e}")
        sys.exit(1)


if __name__ == "__main__":
    main()
