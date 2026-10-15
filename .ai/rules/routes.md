---
paths:
  - 'routes/**'
---

# Routes

## Every internal route group needs a can:<gate> middleware
Each Route::prefix group inside the `internal.staff` block declares `->middleware('can:<gate>')` with a Permission value (HU-004). ADM screens (branches, warehouses, stock parameters, points of sale, payment methods, VAT rates) go under `manage-organization`; only personal settings and `panel` stay ungated. tests/Feature/Security/ModuleAuthorizationTest.php fails when a new internal route has no `can:`, so add the route's screen to its `module screens` dataset too. Frontend links to "home" use the `panel` route (redirects to UserRole::homeRoute()), never `dashboard()`, and links to another module are hidden with `useCan()`.
