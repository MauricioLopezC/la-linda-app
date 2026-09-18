"""
trello_lib.py — Funciones reusables para hablar con la API de Trello y para
parsear los archivos product-backlog.md / sprint-backlog-*.md del proyecto.

No se ejecuta directamente: lo importan trello_cli.py y cualquier otro
script que necesite estas funciones (por ejemplo, un agente de IA que
quiera manejar Trello mediante llamadas Python en vez de por la CLI).
"""

import json
import os
import re
import sys
import requests

# ── Credenciales ──────────────────────────────────────────────────────────
# Se leen del entorno (vía .env con python-dotenv, cargado en trello_cli.py,
# o vía variables de entorno del sistema). Nunca se escriben acá.
API_KEY = os.environ.get("TRELLO_API_KEY")
TOKEN = os.environ.get("TRELLO_TOKEN")
BOARD_ID = os.environ.get("TRELLO_BOARD_ID")  # opcional, usado por listar-columnas / crear-columna

AUTH = {}  # se completa con require_credentials()


def require_credentials():
    """Valida que las credenciales estén cargadas y arma el dict de auth para requests.
    Se llama explícitamente al arrancar la CLI (no al importar el módulo), para que
    importar trello_lib.py no falle si todavía no se cargó el .env."""
    global AUTH
    if not API_KEY or not TOKEN:
        sys.exit(
            "Faltan credenciales. Creá un archivo .env (copiá .env.example) con "
            "TRELLO_API_KEY y TRELLO_TOKEN, o definilas como variables de entorno."
        )
    AUTH = {"key": API_KEY, "token": TOKEN}


# ── Diccionarios de sistema del proyecto ────────────────────────────────────
# Estos son datos fijos de Trello (IDs de miembros y de etiquetas), no cambian
# de sprint a sprint, así que sí tiene sentido que vivan en código.
MEMBER_IDS = {
    "Azael": "672d1ba046a5bdfcf0a3f1e0",
    "Chiara": "6a767484ae875f6c97914002",
    "Clara": "6a764c3643438a9c8836e8c5",
    "Mauro": "5e4866dc18c26d8dac160ee7",
    "Facundo": "6a7675f46c8e0d77814d7a75",
    "Pablo": "6a8073c3d3522934b6976f23",
}

LABEL_IDS = {
    "HAB/HIST": "6a7674ef6f0791f3af5abb43",
    "STK": "6a7674f0cc30bb4163c78c8c",
    "CMP": "6a94a12b509ca0246b42a406",
    "ADM": "6a7674f0f34eb30ef432d8e4",
    "ART": "6a7674ef1af21483f5701178",
    "CLI": "6a94a272750f2ce63a740c2f",
    "BUG": "6a7674f06abd7384a52172ee",
    "SEG": "6a78ba1ce137e7342a188486",
}

# Nombres de columna estándar del flujo de trabajo del equipo. Si en algún
# momento cambian los nombres reales en Trello, alcanza con editar acá.
COLUMNA_EN_PROGRESO = "En Progreso"
COLUMNA_EN_REVISION = "En revisión"
COLUMNA_FINALIZADO = "Finalizado"

# Archivo donde se guardan las asignaciones responsable-por-HU entre
# ejecuciones del script (en vez de un diccionario hardcodeado en código).
ASIGNACIONES_PATH = os.environ.get("TRELLO_ASIGNACIONES_PATH", "asignaciones.json")


def cargar_asignaciones(path=None):
    """Lee el archivo de asignaciones {hu_id: nombre_integrante}. Si no existe,
    devuelve {} sin error — se irá completando y guardando a medida que se usa."""
    path = path or ASIGNACIONES_PATH
    if not os.path.isfile(path):
        return {}
    with open(path, "r", encoding="utf-8") as f:
        return json.load(f)


def guardar_asignaciones(asignaciones, path=None):
    path = path or ASIGNACIONES_PATH
    with open(path, "w", encoding="utf-8") as f:
        json.dump(asignaciones, f, ensure_ascii=False, indent=2, sort_keys=True)


def set_asignacion(hu_id, integrante, path=None):
    """Guarda (o actualiza) el responsable de una historia en el archivo de
    asignaciones, de forma incremental — no pisa las demás asignaciones."""
    asignaciones = cargar_asignaciones(path)
    asignaciones[hu_id] = integrante
    guardar_asignaciones(asignaciones, path)



# ── Parsing de markdown ──────────────────────────────────────────────────

def parse_dependencias_de_tabla(filepath):
    """Extrae el 'Depende de' desde la tabla '## Ítems comprometidos' de un
    sprint-backlog-N.md. Devuelve {hu_id: "HU-036, HU-033"}."""
    with open(filepath, "r", encoding="utf-8") as f:
        content = f.read()

    deps = {}
    for m in re.finditer(
        r"^\|\s*\d+\s*\|\s*\*{0,2}((?:HU|EPIC)-\d+)\*{0,2}\s*\|"
        r"[^|]*\|\s*\d+\s*\|[^|]*\|\s*([^|]+?)\s*\|\s*$",
        content,
        re.MULTILINE,
    ):
        deps[m.group(1).strip()] = m.group(2).strip()
    return deps


def parse_narrativa_de_product_backlog(filepath):
    """Extrae Como/necesito/para, 'Depende de' y Módulo desde el formato
    narrativo de product-backlog.md, por HU. Devuelve {} si el archivo no
    existe (no es fatal)."""
    if not filepath or not os.path.isfile(filepath):
        return {}

    with open(filepath, "r", encoding="utf-8") as f:
        content = f.read()

    pattern = r"^##\s*((?:HU|EPIC)-\d+)\s*-\s*.+?$"
    headers = list(re.finditer(pattern, content, re.MULTILINE))

    narrativa = {}
    for i, h in enumerate(headers):
        hu_id = h.group(1).strip()
        body_start = h.end()
        body_end = headers[i + 1].start() if i + 1 < len(headers) else len(content)
        body = content[body_start:body_end].strip()

        como = re.search(r"\*\*Como\*\*\s+(.*?),\s+\*\*necesito\*\*", body, re.IGNORECASE)
        necesito = re.search(r"\*\*necesito\*\*\s+(.*?),\s+\*\*para\*\*", body, re.IGNORECASE)
        para = re.search(r"\*\*para\*\*\s+(.*?)\.", body, re.IGNORECASE)
        depende = re.search(r"\*\*Depende de:\*\*\s*([^\n·]+)", body, re.IGNORECASE)
        modulo = re.search(r"\*\*M[oó]dulo:\*\*\s*([A-Z]{3})", body, re.IGNORECASE)

        narrativa[hu_id] = {
            "como": como.group(1).strip() if como else None,
            "necesito": necesito.group(1).strip() if necesito else None,
            "para": para.group(1).strip() if para else None,
            "depende_de": depende.group(1).strip() if depende else None,
            "modulo": modulo.group(1).upper() if modulo else None,
        }
    return narrativa


def parse_markdown(filepath, product_backlog_path=None, asignaciones=None):
    """Extrae cada historia de un archivo .md del proyecto (sprint-backlog-N.md
    o product-backlog.md), cruzando narrativa, módulo y dependencias cuando
    hace falta. `asignaciones` es un dict {hu_id: nombre_integrante} — si no
    se pasa, se usa cargar_asignaciones() para leer asignaciones.json."""
    if asignaciones is None:
        asignaciones = cargar_asignaciones()

    with open(filepath, "r", encoding="utf-8") as f:
        content = f.read()

    deps_de_tabla = parse_dependencias_de_tabla(filepath)
    narrativa_externa = parse_narrativa_de_product_backlog(product_backlog_path)

    pattern = (
        r"^#{2,5}\s*((?:HU|EPIC)-\d+)\s*[-—]\s*(.+?)\s*"
        r"(?:\((\d+)\s*SP\))?\s*$"
    )
    headers = list(re.finditer(pattern, content, re.MULTILINE))

    if not headers:
        sys.exit(
            f"No se encontró ninguna historia en {filepath}. "
            "Revisá que los encabezados tengan el formato '### HU-037 — Título (5 SP)' "
            "o '## HU-037 - Título'."
        )

    stories = []
    for i, h in enumerate(headers):
        hu_id = h.group(1).strip()
        title = h.group(2).strip()
        sp_from_header = h.group(3)

        body_start = h.end()
        body_end = headers[i + 1].start() if i + 1 < len(headers) else len(content)
        body = content[body_start:body_end].strip()

        if sp_from_header:
            estimacion = sp_from_header
        else:
            m = re.search(r"\*\*Estimaci[oó]n:\*\*\s*(\d+)\s*SP", body, re.IGNORECASE)
            estimacion = m.group(1) if m else "?"

        modulo_match = re.search(r"\*\*M[oó]dulo:\*\*\s*([A-Z]{3})", body, re.IGNORECASE)
        modulo = modulo_match.group(1).upper() if modulo_match else narrativa_externa.get(hu_id, {}).get("modulo") or "N/A"

        como = re.search(r"\*\*Como\*\*\s+(.*?),\s+\*\*necesito\*\*", body, re.IGNORECASE)
        necesito = re.search(r"\*\*necesito\*\*\s+(.*?),\s+\*\*para\*\*", body, re.IGNORECASE)
        para = re.search(r"\*\*para\*\*\s+(.*?)\.", body, re.IGNORECASE)
        depende_inline = re.search(r"\*\*Depende de:\*\*\s*([^\n·]+)", body, re.IGNORECASE)

        externa = narrativa_externa.get(hu_id, {})
        como_txt = como.group(1) if como else externa.get("como")
        necesito_txt = necesito.group(1) if necesito else externa.get("necesito")
        para_txt = para.group(1) if para else externa.get("para")

        depende_txt = (
            deps_de_tabla.get(hu_id)
            or (depende_inline.group(1).strip() if depende_inline else None)
            or externa.get("depende_de")
        )

        criterios = re.findall(r"^\s*-\s*\[ \]\s+(.+)$", body, re.MULTILINE)
        if not criterios:
            criterios_section = re.search(
                r"\*\*Criterios de aceptaci[oó]n\*\*([\s\S]*)", body, re.IGNORECASE
            )
            if criterios_section:
                criterios = re.findall(
                    r"^\s*[\*\-]\s+(.+)$", criterios_section.group(1), re.MULTILINE
                )

        stories.append({
            "id": hu_id,
            "title": title,
            "modulo": modulo,
            "estimacion": estimacion,
            "como": como_txt,
            "necesito": necesito_txt,
            "para": para_txt,
            "depende_de": depende_txt,
            "criterios": criterios,
            "owner": asignaciones.get(hu_id),
        })
    return stories


def build_description(story):
    rama_git = f"feature/{story['id']}-{re.sub(r'[^a-z0-9]+', '-', story['title'].lower()).strip('-')[:30]}"

    narrativa = ""
    if story.get("como") or story.get("necesito") or story.get("para"):
        narrativa = (
            "### 👤 Historia de Usuario\n\n"
            f"- **Como:** {story.get('como') or '...'}\n"
            f"- **Necesito:** {story.get('necesito') or '...'}\n"
            f"- **Para:** {story.get('para') or '...'}\n\n---\n\n"
        )

    dependencias = ""
    if story.get("depende_de"):
        dependencias = f"\n\n**Depende de:** {story['depende_de']}"

    return f"""{narrativa}### 📌 Módulo y Prioridad

- **Módulo:** {story['modulo']}
- **Puntos de Función:** {story['estimacion']} SP

---

### 📝 Criterios de Aceptación (ver Checklist)

> _Toda validación de esta tarjeta debe cumplirse antes de pasar a "En Revisión / Testing"._{dependencias}

---

### 🔗 Recursos & Repositorio

- **Rama de Git:** `{rama_git}`
- **Pull Request:** [Link a la PR en GitHub]
"""


# ── Llamadas a la API de Trello ─────────────────────────────────────────────

def _get(url, **params):
    res = requests.get(url, params={**AUTH, **params}, timeout=15)
    res.raise_for_status()
    return res.json()


def _post(url, **params):
    res = requests.post(url, params={**AUTH, **params}, timeout=15)
    return res


def _put(url, **params):
    res = requests.put(url, params={**AUTH, **params}, timeout=15)
    return res


def _delete(url, **params):
    return requests.delete(url, params={**AUTH, **params}, timeout=15)


def listar_tableros():
    """Devuelve los tableros del usuario autenticado: [{id, name}, ...]."""
    return _get("https://api.trello.com/1/members/me/boards", fields="id,name")


def listar_columnas(board_id):
    """Devuelve las listas (columnas) de un tablero: [{id, name}, ...]."""
    return _get(f"https://api.trello.com/1/boards/{board_id}/lists", fields="id,name")


def crear_columna(board_id, nombre):
    """Crea una columna nueva al final del tablero. Devuelve su id."""
    res = _post("https://api.trello.com/1/lists", name=nombre, idBoard=board_id, pos="bottom")
    res.raise_for_status()
    return res.json()["id"]


def get_cards_en_lista(list_id):
    """Tarjetas de una lista, con id y nombre."""
    return _get(f"https://api.trello.com/1/lists/{list_id}/cards", fields="id,name")


def get_cards_en_tablero(board_id):
    """Todas las tarjetas de un tablero (todas las columnas), con id, nombre e idList."""
    return _get(f"https://api.trello.com/1/boards/{board_id}/cards", fields="id,name,idList")


class TarjetaAmbiguaError(Exception):
    """Se lanza cuando hay más de una tarjeta con el mismo prefijo [HU-XXX]
    en el tablero — hay que resolverlo a mano en Trello antes de que
    cualquier comando de mover/asignar/revisar pueda actuar con seguridad."""

    def __init__(self, hu_id, cards):
        self.hu_id = hu_id
        self.cards = cards
        nombres = "; ".join(f"{c['name']} (id: {c['id']})" for c in cards)
        super().__init__(
            f"Hay {len(cards)} tarjetas con el prefijo [{hu_id}] en el tablero: {nombres}. "
            "Resolvé el duplicado en Trello (fusioná, archivá o renombrá la que sobra) "
            "antes de repetir esta operación."
        )


def find_card_by_hu_id(cards, hu_id):
    """Busca, en una lista de tarjetas ya traídas, la que corresponde a un
    HU-ID (matchea por el prefijo '[HU-037]' del nombre, tal como este
    sistema nombra sus tarjetas). Devuelve el id de tarjeta o None.
    Lanza TarjetaAmbiguaError si hay más de una coincidencia — no elige
    silenciosamente la primera."""
    prefijo = f"[{hu_id}]"
    encontradas = [c for c in cards if c["name"].startswith(prefijo)]
    if len(encontradas) > 1:
        raise TarjetaAmbiguaError(hu_id, encontradas)
    return encontradas[0]["id"] if encontradas else None


def borrar_checklists_de_tarjeta(card_id):
    res = _get(f"https://api.trello.com/1/cards/{card_id}/checklists")
    for chk in res:
        _delete(f"https://api.trello.com/1/checklists/{chk['id']}")


def agregar_checklist(card_id, criterios):
    if not criterios:
        return
    chk_res = _post(
        f"https://api.trello.com/1/cards/{card_id}/checklists",
        name="Criterios de Aceptación",
    )
    if chk_res.status_code != 200:
        print(f"  ! No se pudo crear el checklist: {chk_res.text}")
        return
    checklist_id = chk_res.json()["id"]
    for crit in criterios:
        _post(
            f"https://api.trello.com/1/checklists/{checklist_id}/checkItems",
            name=crit[:16384],
        )
    print(f"  + Checklist con {len(criterios)} criterios.")


def create_trello_card(story, list_id):
    """Crea una tarjeta nueva a partir de una historia parseada. Devuelve el card_id o None."""
    params = {
        "idList": list_id,
        "name": f"[{story['id']}] {story['title']} ({story['estimacion']} Pts)",
        "desc": build_description(story),
    }
    member_id = MEMBER_IDS.get(story.get("owner"))
    if member_id:
        params["idMembers"] = member_id
    label_id = LABEL_IDS.get(story.get("modulo"))
    if label_id:
        params["idLabels"] = label_id

    res = _post("https://api.trello.com/1/cards", **params)
    if res.status_code == 200:
        card_id = res.json()["id"]
        print(f"✓ Creada tarjeta: {story['id']} — {story['title']}")
        agregar_checklist(card_id, story.get("criterios"))
        return card_id
    print(f"✗ Error al crear {story['id']}: {res.status_code} {res.text}")
    return None


def update_trello_card(card_id, story):
    """Reescribe descripción/miembro/etiqueta de una tarjeta y recrea su checklist."""
    params = {
        "name": f"[{story['id']}] {story['title']} ({story['estimacion']} Pts)",
        "desc": build_description(story),
    }
    member_id = MEMBER_IDS.get(story.get("owner"))
    if member_id:
        params["idMembers"] = member_id
    label_id = LABEL_IDS.get(story.get("modulo"))
    if label_id:
        params["idLabels"] = label_id

    res = _put(f"https://api.trello.com/1/cards/{card_id}", **params)
    if res.status_code == 200:
        print(f"✓ Actualizada tarjeta: {story['id']} — {story['title']}")
        borrar_checklists_de_tarjeta(card_id)
        agregar_checklist(card_id, story.get("criterios"))
        return True
    print(f"✗ Error al actualizar {story['id']}: {res.status_code} {res.text}")
    return False


def mover_tarjeta(card_id, list_id):
    res = _put(f"https://api.trello.com/1/cards/{card_id}", idList=list_id)
    return res.status_code == 200


def asignar_miembro(card_id, member_name):
    """Reemplaza los miembros de la tarjeta por uno solo (el responsable actual)."""
    member_id = MEMBER_IDS.get(member_name)
    if not member_id:
        return False
    res = _put(f"https://api.trello.com/1/cards/{card_id}", idMembers=member_id)
    return res.status_code == 200


def find_card_by_hu_id_en_tablero(board_id, hu_id):
    """Busca una tarjeta por su HU-ID en TODO el tablero (todas las columnas),
    útil para mover/editar sin tener que saber de antemano en qué columna está.
    Devuelve (card_id, list_id) o (None, None) si no existe ninguna.
    Lanza TarjetaAmbiguaError si hay más de una coincidencia — no elige
    silenciosamente la primera."""
    cards = get_cards_en_tablero(board_id)
    prefijo = f"[{hu_id}]"
    encontradas = [c for c in cards if c["name"].startswith(prefijo)]
    if len(encontradas) > 1:
        raise TarjetaAmbiguaError(hu_id, encontradas)
    if not encontradas:
        return None, None
    return encontradas[0]["id"], encontradas[0]["idList"]


# ── Flujo de trabajo por columnas (En progreso / En Revisión / Finalizado) ──
# Estas funciones encapsulan las convenciones del equipo para que un agente de
# desarrollo o de testing las invoque por su nombre de historia, sin tener que
# saber IDs de columna ni de tarjeta.

def _find_columna_por_nombre(board_id, nombre):
    columnas = listar_columnas(board_id)
    nombre_norm = nombre.strip().lower()
    for c in columnas:
        if c["name"].strip().lower() == nombre_norm:
            return c["id"]
    return None


def iniciar_desarrollo(board_id, hu_id):
    """Mueve la tarjeta de una historia a la columna 'En progreso'.
    Pensado para que lo dispare un agente de desarrollo al arrancar a
    trabajar en una historia. Devuelve (ok: bool, mensaje: str)."""
    card_id, _ = find_card_by_hu_id_en_tablero(board_id, hu_id)
    if not card_id:
        return False, f"No se encontró ninguna tarjeta '[{hu_id}]' en el tablero."

    list_id = _find_columna_por_nombre(board_id, COLUMNA_EN_PROGRESO)
    if not list_id:
        return False, f"No existe la columna '{COLUMNA_EN_PROGRESO}' en este tablero."

    if mover_tarjeta(card_id, list_id):
        return True, f"{hu_id} movida a '{COLUMNA_EN_PROGRESO}'."
    return False, f"No se pudo mover {hu_id}."


def finalizar_desarrollo(board_id, hu_id):
    """Mueve la tarjeta de una historia a la columna 'En Revisión'.
    Pensado para que lo dispare un agente de desarrollo al terminar de
    implementar una historia (por ejemplo, al abrir o mergear la PR).
    Devuelve (ok: bool, mensaje: str)."""
    card_id, _ = find_card_by_hu_id_en_tablero(board_id, hu_id)
    if not card_id:
        return False, f"No se encontró ninguna tarjeta '[{hu_id}]' en el tablero."

    list_id = _find_columna_por_nombre(board_id, COLUMNA_EN_REVISION)
    if not list_id:
        return False, f"No existe la columna '{COLUMNA_EN_REVISION}' en este tablero."

    if mover_tarjeta(card_id, list_id):
        return True, f"{hu_id} movida a '{COLUMNA_EN_REVISION}'."
    return False, f"No se pudo mover {hu_id}."


def resolver_revision(board_id, hu_id, aprobado, motivo=None):
    """Resuelve el resultado de la revisión/testing de una historia:
    - Si aprobado=True, la mueve a 'Finalizado'.
    - Si aprobado=False, la mueve de vuelta a 'En progreso' y agrega un
      comentario en la tarjeta con el motivo del rechazo (si se indicó).
    Pensado para que lo dispare la skill de testing del equipo.
    Devuelve (ok: bool, mensaje: str)."""
    card_id, _ = find_card_by_hu_id_en_tablero(board_id, hu_id)
    if not card_id:
        return False, f"No se encontró ninguna tarjeta '[{hu_id}]' en el tablero."

    columna_destino = COLUMNA_FINALIZADO if aprobado else COLUMNA_EN_PROGRESO
    list_id = _find_columna_por_nombre(board_id, columna_destino)
    if not list_id:
        return False, f"No existe la columna '{columna_destino}' en este tablero."

    if not mover_tarjeta(card_id, list_id):
        return False, f"No se pudo mover {hu_id}."

    if not aprobado and motivo:
        agregar_comentario(card_id, f"🔁 Devuelta a revisión: {motivo}")

    if aprobado:
        return True, f"{hu_id} aprobada y movida a '{COLUMNA_FINALIZADO}'."
    detalle = f" Motivo: {motivo}" if motivo else ""
    return True, f"{hu_id} devuelta a '{COLUMNA_EN_PROGRESO}'.{detalle}"


def agregar_comentario(card_id, texto):
    res = _post(f"https://api.trello.com/1/cards/{card_id}/actions/comments", text=texto)
    return res.status_code == 200

