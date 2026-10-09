---
paths:
  - 'app/Actions/**'
---

# Actions

## No lockForUpdate() on aggregates (max/count/sum)
Postgres (production) rejects `SELECT max(...) ... FOR UPDATE` (SQLSTATE 0A000); SQLite (dev/tests) compiles lockForUpdate() to nothing, so the suite stays green and it only breaks in prod. To serialize a "next number" sequence, lock the parent row (e.g. the PointOfSale in IssueInvoice) and run the aggregate unlocked; keep a UNIQUE index as the safety net. Same family: `distinct()->pluck()` on a relation with an orderBy fails in Postgres. Run the suite against Postgres (see tests.md) when touching locking or raw queries.
