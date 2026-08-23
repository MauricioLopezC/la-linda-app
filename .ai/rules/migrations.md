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
