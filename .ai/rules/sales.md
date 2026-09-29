---
paths:
  - 'app/Actions/Sales/**'
---

# Sales

## Cash session contract: openForUser() and PaymentMethod::cash()
The cashier's open session is always `CashSession::query()->openForUser($userId)->first()` (null = no open session; at most one per user, partial unique index). Counter sales (HU-039) take `point_of_sale_id` and `cash_session_id` from it instead of asking the user; without one, reject and link to `route('sales.cash-sessions.create')`. Every cash movement (apertura, ingreso/egreso, cash part of a sale) uses `PaymentMethod::query()->cash()->first()` (first active `kind = efectivo` by id). A zero-amount opening creates no `apertura` movement (`cash_movements.amount > 0`), so expected cash is just the signed sum of movements. The open session is also shared with every Inertia page as the `cashSession` prop (`OpenCashSessionData`).
