# Guía de contribución

Convenciones de trabajo en equipo para este repositorio: ramas, Pull Requests y CI.

## Ramas

- `master` es la única rama larga y siempre debe quedar funcional (deployable). Nadie pushea
  directo a `master`: todo entra por Pull Request.
- Cada historia de usuario se desarrolla en su propia rama, creada desde `master`:

  ```
  feature/HU-<numero>-slug-corto
  ```

  Ejemplo: `feature/HU-12-alta-producto`.

- Para fixes que no corresponden a una historia (bugs, ajustes de configuración, etc.) usar
  `fix/slug-corto`, y para cambios de documentación `docs/slug-corto`.
- La rama vive solo mientras dura la historia: se borra al mergear el PR.

## Pull Requests

- Al terminar una historia, abrí un PR de tu rama hacia `master`.
- El PR necesita, antes de poder mergearse:
  - El check `ci` en verde (lint, formato, tipos, tests y PHPStan — ver `composer ci:check`).
  - Al menos 1 aprobación de otro miembro del equipo (el autor no puede autoaprobar su propio PR).
- Si el PR queda desactualizado respecto a `master` (por ejemplo, se mergeó otro PR con un fix que
  el tuyo necesita), traé los cambios con un merge desde `master` antes de pedir review:

  ```
  git fetch origin master
  git merge origin/master
  ```

## Reglas configuradas en GitHub

Estas reglas están aplicadas como protección de la rama `master` (visible en
**Settings → Branches** del repo, o con `gh api repos/<owner>/<repo>/branches/master/protection`):

- Push directo bloqueado (incluso para administradores).
- Requiere 1 aprobación de review; se descarta la aprobación si se suben nuevos commits.
- Requiere que el check `ci` pase.
- Sin force-push ni borrado de la rama.
