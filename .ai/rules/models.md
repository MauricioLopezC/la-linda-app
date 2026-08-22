---
paths:
  - 'app/Models/**'
---

# Models

## Portable normalized uniqueness
When business uniqueness ignores case or outer whitespace, persist a normalized comparison column populated by NormalizesUniqueAttributes and back it with a UNIQUE index instead of relying on database collation. For nullable hierarchy scopes, use a non-null scope key such as 0 for roots.

## Use native PHP enums for multi-state fields, not booleans
When a field has more than two states (e.g. Article's status: active/inactive/discontinued), define a string-backed enum in `app/Enums/{Module}/...` and cast the column to it via `casts()` — don't extend the `is_active` boolean pattern with extra flags. `App\Enums\Catalog\ArticleStatus` (with a `label()` method for Spanish UI labels) is the first and reference example. Two-state fields still use the existing `is_active` boolean + `scopeActive()` convention.
