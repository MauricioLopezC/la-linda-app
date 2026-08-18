---
paths:
  - 'app/Models/**'
---

# Models

## Portable normalized uniqueness
When business uniqueness ignores case or outer whitespace, persist a normalized comparison column populated by NormalizesUniqueAttributes and back it with a UNIQUE index instead of relying on database collation. For nullable hierarchy scopes, use a non-null scope key such as 0 for roots.
