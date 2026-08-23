---
paths:
  - 'app/Models/Inventory/**'
---

# Inventory

## Stock: quantities are decimal, the sign lives in the movement line
Settled while building the HU-016/017/018 schema:

- Quantities are `decimal(12, 3)`, cast `decimal:3`, not integers: the catalog seeds Kilogramo and Litro as units of measure, so bulk articles need fractional stock.
- `stock_movement_items.quantity` is a **signed delta** (negative leaves the warehouse, positive enters) and is the only source of truth for the sign. `stock_movement_types.sign` is informative metadata — never read it for a calculation. `system_quantity` is likewise informative (evidence of a physical count), never an input.
- `stock_balances` has no HTTP write route. The quantity only changes as a consequence of a `StockMovement`, inside a transaction. The read-only UI of HU-016 is a consequence of that, not a UI rule.
- `StockMovement` is immutable: no `updated_at` column, `const UPDATED_AT = null`, and no update/delete route. `created_at` is the date of the movement.
- One movement row = one affected warehouse. A transfer is two rows; the column that groups them is deliberately absent until HU-019 brings the `stock_transfers` header. Tell an adjustment from a transfer by `stock_movement_types.code` only — note the seeded code is `inventory_adjustment` (`StockMovementType::CODE_INVENTORY_ADJUSTMENT`), not `adjustment`.
