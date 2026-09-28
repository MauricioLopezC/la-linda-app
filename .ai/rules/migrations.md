---
paths:
  - 'database/migrations/**'
---

# Migrations

## Portable normalized unique indexes
Back case/outer-space-insensitive business uniqueness with normalized columns and UNIQUE indexes so SQLite tests and MySQL production behave consistently. For nullable hierarchy scopes, index a non-null scope key such as 0 for roots.

## CHECK constraints go inline via rawColumn, never ALTER TABLE
Dev and tests run SQLite, production runs Postgres. SQLite has no `ALTER TABLE ... ADD CONSTRAINT`, so `DB::statement('ALTER TABLE x ADD CONSTRAINT ... CHECK (...)')` after `Schema::create()` fails outright locally, and guarding it by driver would leave the rule unenforced in tests.

Declare the CHECK inline on the column instead:

    $table->rawColumn('quantity', 'decimal(12, 3) check (quantity >= 0)')->default(0);

That SQL is valid in both dialects, and the constraint is actually exercised by the test suite. See `create_stock_balances_table` and `create_stock_movement_items_table`.

## Partial unique indexes and money equalities in CHECKs
- "At most one open X" rules are partial unique indexes created with `DB::statement("create unique index ... on t (col) where status = 'abierta'")` after `Schema::create()`. Unlike ALTER TABLE ADD CONSTRAINT, CREATE INDEX ... WHERE works in SQLite and Postgres, so tests exercise it. See `create_cash_sessions_table`.
- A CHECK that compares sums of money (`net + vat = total`) must use a tolerance, `abs(a + b - c) < 0.005`, never `=`: SQLite stores decimal columns as floating point, so an exact equality fails at random. See `create_invoices_table`, `revise_sale_items_for_vat`.
- A column CHECK may reference other columns of the same row (both engines accept it). Use it for state/timestamp pairs such as `(status = 'cerrada' and closed_at is not null) or (status = 'abierta' and closed_at is null)`.
