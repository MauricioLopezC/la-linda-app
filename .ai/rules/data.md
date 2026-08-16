---
paths:
  - 'app/Data/**'
---

# Data

## Laravel Data: use only for outgoing API/Inertia responses, not for input
`spatie/laravel-data` + `spatie/laravel-typescript-transformer` are installed to type Inertia props / API responses and auto-generate their TS types.

- Scope: Data objects are for the OUTPUT side only (what a controller/Action returns). Form Requests (`app/Http/Requests/{Module}/...`) remain the standard for validating INPUT — don't replace them with Data unless a specific case calls for it.
- Folder convention: `app/Data/{Module}/...` mirroring the module list in `.ai/rules/app.md` (Catalog, Sales, Inventory, etc.).
- TS generation is configured in `app/Providers/TypeScriptTransformerServiceProvider.php`, which registers `LaravelDataTypeScriptTransformerExtension` so any class extending `Spatie\LaravelData\Data` is picked up automatically (no attribute needed).
- Output file is `resources/js/types/generated.d.ts` (ambient global types, e.g. `App.Data.ProductData`) — it's gitignored, regenerate it with `npm run types:generate` (or `npm run types:watch` while developing) after adding/editing a Data class.
