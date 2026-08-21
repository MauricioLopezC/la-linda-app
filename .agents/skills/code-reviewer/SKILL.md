---
name: code-reviewer
description: >-
  Activate this skill when the user asks to review a PR, perform a code review, or audit a branch against user stories or requirements. It provides guidelines on how to verify requirements and format the final code review report in markdown.
---

# Code Reviewer Skill

Use this skill when tasked with performing a code review, auditing a branch, or checking if a feature branch meets the acceptance criteria of a User Story (HU).

## Steps for Code Review

1. **Understand the Requirements:**
   If the user has not provided the acceptance criteria or the branch/PR details in their prompt, you MUST ask for them before proceeding. Do not guess the criteria.

2. **Identify the Changes:**
   Find the branch or the commit(s) associated with the feature.
   - Use `git status` or `git log` to identify the branch name.
   - Use `git diff origin/master...<feature_branch> --name-only` to list the changed files.
   - Read the changes using `git diff` or by opening the specific changed files with your file viewing tools.

3. **Audit against Requirements:**
   - **Migrations:** Verify database schema changes are correct and follow conventions (e.g., indices, normalization).
   - **Validations:** Verify that Form Requests correctly validate the specified limits, unique rules, etc.
   - **Business Logic (Actions):** Verify that actions correctly enforce business rules, state changes, or block invalid operations.
   - **Architecture:** Ensure the code follows project conventions (Controllers, Actions, Spatie Data objects, Inertia pages).
   - **Seeders:** Verify that required seeders were added, follow idempotency (e.g. `firstOrCreate`), and are registered in `DatabaseSeeder`.

4. **Format the Report:**
   Always output the final report in GitHub-flavored Markdown so the user can easily copy-paste it into a PR.
   Structure the report as follows:
   - **Title:** e.g., `## 📝 Code Review - [HU-XXX] (Title)`
   - **Criterios de Aceptación:** A checklist of the requirements and how they were met.
   - **Coherencia Arquitectónica:** Notes on whether the code follows project conventions (e.g., using Actions, Data Objects, etc.).
   - **Observaciones / Comentarios Menores:** Any non-blocking suggestions or edge cases noticed.
   - **Conclusión:** A brief summary and the final status (e.g., `🟢 Aprobado`, `🟡 Requiere Cambios`, `🔴 Bloqueado`).
