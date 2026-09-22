---
paths:
  - 'app/Models/**'
---

# Models

## Portable normalized uniqueness
When business uniqueness ignores case or outer whitespace, persist a normalized comparison column populated by NormalizesUniqueAttributes and back it with a UNIQUE index instead of relying on database collation. For nullable hierarchy scopes, use a non-null scope key such as 0 for roots.

## Use native PHP enums for multi-state fields, not booleans
When a domain field has explicit enumerated states (e.g. Article's status: `active`/`inactive`), define a string-backed enum in `app/Enums/{Module}/...` and cast the column to it via `casts()` — don't extend the `is_active` boolean pattern with extra flags. `App\Enums\Catalog\ArticleStatus` (with a `label()` method for Spanish UI labels) is a reference example. Binary fields without domain-specific state transitions continue using `is_active` boolean + `scopeActive()`.

## Comparar columnas `date` en SQL con whereDate, no con where
El cast `date` de Eloquent persiste `Y-m-d H:i:s` en SQLite (dev/tests), mientras que Postgres (producción) guarda una fecha pura. Un `where('valid_from', '<=', $hoy)` comparando strings falla en SQLite para el mismo día: `'2026-09-21 00:00:00' <= '2026-09-21'` es false, así que un registro que arranca hoy queda fuera del resultado.

Usar siempre `whereDate()` / `orWhereDate()` al comparar una columna con cast `date` contra un string de fecha. Es portable (SQLite y Postgres) y hace que el test suite realmente ejerza la regla.

Encontrado en `PriceList::scopeCurrentlyValid()` y `scopeOverlapping()`, donde además hacía que dos periodos que se tocan en un mismo día no se detectaran como superpuestos.
