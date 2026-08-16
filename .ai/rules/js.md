---
paths:
  - 'resources/js/**'
---

# Js

## Reuse shadcn/ui components instead of hand-rolling primitives
Before building any UI element, check `resources/js/components/ui` for an existing shadcn primitive first and reuse it — don't hand-write raw HTML/CSS equivalents (buttons, inputs, dialogs, dropdowns, etc.).

If the primitive is missing, add it via `npx shadcn@latest add <component>`. Do not copy-paste or hand-write shadcn component source yourself.
