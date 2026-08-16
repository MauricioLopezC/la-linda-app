# La Linda App

Sistema de gestión (estilo ERP) para Supermercados La Linda: catálogo de productos, listas de
precios, stock multi-depósito, compras a proveedores, ventas/facturación y canal de e-commerce,
con un dashboard de ingresos y gastos.

Construido con Laravel 13 + Inertia/React.

## Requisitos

- PHP >= 8.3 (el CI corre sobre 8.5)
- Composer 2.x
- Node.js 22.x + npm

## Puesta en marcha

Cloná el repo y corré:

```
composer run setup
```

Este comando hace todo lo necesario para levantar el entorno local:

- `composer install`
- copia `.env.example` a `.env` (si no existe)
- `php artisan key:generate`
- `php artisan migrate --force` (crea `database/database.sqlite` si no existe y corre las
  migraciones)
- `npm install`
- `npm run build`

Después, para levantar el servidor de desarrollo (Laravel + Vite + queue):

```
composer run dev
```

La app queda disponible en `http://localhost:8000`.

## Problemas comunes

- **`Database file at path ... does not exist`**: significa que no se corrió `composer run setup`
  (o al menos `php artisan migrate`) antes de acceder a la app. El comando anterior crea el
  archivo SQLite y corre las migraciones automáticamente.

## Tests y calidad de código

```
composer run test      # tests (Pest) + lint + análisis de tipos
composer run ci:check  # lo mismo que corre el CI: lint, formato, tipos y tests
```

Ver [CONTRIBUTING.md](CONTRIBUTING.md) para el flujo de ramas y Pull Requests.
