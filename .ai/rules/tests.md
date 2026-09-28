---
paths:
  - 'tests/**'
---

# Tests

## Expected constraint violations go inside inSavepoint()
Production runs Postgres: a failed statement there aborts the whole RefreshDatabase transaction, so any query after an expected QueryException fails with SQLSTATE 25P02 (SQLite never shows this). When a test asserts a constraint violation and keeps going, wrap the statement: `expect(inSavepoint(fn () => ...))->toThrow(QueryException::class)`. The helper in tests/Pest.php runs it in DB::transaction, i.e. a savepoint. To check against Postgres: run a `postgres:17` container and export `DB_CONNECTION=pgsql` plus the DB_* variables before `php artisan test` (phpunit.xml does not force sqlite).
