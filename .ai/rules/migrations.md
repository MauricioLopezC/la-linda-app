---
paths:
  - 'database/migrations/**'
---

# Migrations

## Portable normalized unique indexes
Back case/outer-space-insensitive business uniqueness with normalized columns and UNIQUE indexes so SQLite tests and MySQL production behave consistently. For nullable hierarchy scopes, index a non-null scope key such as 0 for roots.
