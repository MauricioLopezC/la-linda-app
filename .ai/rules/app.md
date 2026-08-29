---
paths:
  - 'app/**'
---

# App

## Module-based folder convention for app/
Business logic is grouped by domain module (English, descriptive folder/namespace, not the 3-letter business codes from docs). Apply to Controllers, Models, Requests, Actions, Policies, etc.: `app/Http/Controllers/{Module}/...`, `app/Models/{Module}/...`, `app/Http/Requests/{Module}/...`, `app/Actions/{Module}/...`, with matching namespace `App\Http\Controllers\{Module}\...` etc.

Modules:
- `Security` — auth, users, roles/permissions
- `Catalog` — articles/products, categories, brands, units of measure
- `Pricing` — price lists, tax rates
- `Customers`
- `Purchasing` — suppliers, purchases
- `Inventory` — multi-warehouse stock, warehouses, stock movement types
- `Sales` — sales/invoicing, points of sale, payment methods
- `Ecommerce`
- `Reporting` — management dashboard
- `Organization` — branches and org hierarchy (shared by Inventory and Sales, kept minimal — do not turn this into a generic "admin" catch-all again)

Entities that could plausibly belong to more than one module (e.g. categories, tax rates, warehouses) were assigned to a single primary owner based on where they're most central; other modules reference that model rather than duplicating it. Don't recreate a generic ADM/Admin module — if something feels transversal, either assign it to its most natural owner or, if truly shared with no dominant owner, give it its own small dedicated module (like Organization) rather than a catch-all.
