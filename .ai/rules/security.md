---
paths:
  - 'app/Enums/Security/**'
---

# Security

## Role → Gate matrix lives in UserRole::permissions()
The 10 module Gates are the cases of `App\Enums\Security\Permission`. AppServiceProvider defines one Gate per case, and `Gate::before` lets the Administrador through everything. Which role holds which Gate is decided only in `UserRole::permissions()`, and the landing screen only in `UserRole::homeRoute()` (shared by LoginResponse and redirectUsersTo). Gates cover whole modules: a role that only needs to "consult" a module (e.g. gerente on purchases/stock) gets the full Gate, with no read/write split (team decision, Sprint 5). Gerente has no `operate-cash`. Changing the matrix means updating the dataset in tests/Feature/Security/RoleGatesTest.php.
