# Guía de Configuración: Script de Trello (`trello_cli.py`)

Esta guía detalla los pasos necesarios para que cualquier integrante del equipo pueda configurar y utilizar la automatización de Trello en su entorno local.

## 1. Requisitos Previos

El script está desarrollado en Python, por lo que es necesario tener Python instalado. Además, requiere instalar dos dependencias. Desde la terminal, ejecutá el siguiente comando:

```bash
pip install requests python-dotenv
```

## 2. Generar Credenciales de Trello

Para que el script pueda interactuar con el tablero de Trello del proyecto, cada persona necesita generar sus propias credenciales:

1. Ingresá a la página de administración de Power-Ups de Trello: [https://trello.com/power-ups/admin](https://trello.com/power-ups/admin) (o alternativamente en [https://trello.com/app-key](https://trello.com/app-key)).
2. Buscá o generá tu **API Key**.
3. Desde ahí mismo, generá un **Token** de acceso manual (te pedirá autorizar a la aplicación para leer y escribir en tus tableros).
4. Mantené a mano ambos valores (API Key y Token), los usaremos en el siguiente paso.

## 3. Configurar el archivo `.env`

El script utiliza variables de entorno para mantener seguras las credenciales y evitar que se suban al repositorio.

1. Navegá a la carpeta del script en el proyecto: `automatizaciones/trello/`.
2. Creá un archivo nuevo llamado **exactamente** `.env` (si existe un `.env.example`, podés hacer una copia de ese archivo y renombrarlo a `.env`).
3. Completá el archivo `.env` con tus credenciales:

```env
TRELLO_API_KEY=pega_aqui_tu_api_key
TRELLO_TOKEN=pega_aqui_tu_token
TRELLO_BOARD_ID=6a7674ee5fb1fb95074d5f09
```

> **💡 Nota sobre el Tablero:** El `TRELLO_BOARD_ID` actual del proyecto es `6a7674ee5fb1fb95074d5f09`. Podés dejar este campo vacío; si lo hacés, el script te preguntará qué tablero querés usar la primera vez que lo corras.

> **⚠️ IMPORTANTE:** El archivo `.env` **nunca debe subirse al repositorio**. Es personal e intransferible. Ya se encuentra excluido mediante `.gitignore`, por lo que git no debería detectarlo.

## 4. Archivo de Asignaciones

Al utilizar el script para asignar responsables a las historias de usuario, se generará automáticamente un archivo llamado `asignaciones.json` en la misma carpeta.
Este archivo recuerda a quién se le asignó cada historia (ej. `"HU-037": "Chiara"`). A diferencia del `.env`, el archivo `asignaciones.json` **no contiene información sensible** y el equipo puede decidir subirlo al repositorio para mantener el registro del sprint.

## 5. Prueba de Funcionamiento

Para comprobar que la configuración es correcta, abrí tu terminal en la carpeta `automatizaciones/trello/` y ejecutá:

```bash
python trello_cli.py
```

Si todo está bien configurado, deberías ver un menú interactivo listando todas las acciones disponibles (subir historias, asignar/reasignar, mover tarjetas, etc.). ¡Eso es todo! Ya podés usar la automatización.
