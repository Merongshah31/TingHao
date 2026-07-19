# Ting Hao Agent Instructions

This Laravel project is the Ting Hao Inventory Management System. AI coding agents must keep the application and documentation aligned on every task.

## Documentation Rule

Every time you make any system change, you must update documentation before completing the task.

A system change includes:

- New feature
- UI adjustment
- Bug fix
- Route change
- Database migration
- Controller change
- Model change
- Permission change
- Language/localization update
- Email workflow update
- Report/PDF update
- Demo/prototype feature update
- Dashboard shortcut update

After making changes, update the relevant documentation files.

Required documentation files:

- `docs/CHANGELOG.md`: Record every update in chronological format.
- `docs/current-function-inventory.md`: Track implemented functions, limitations, pages/routes, and role permissions.
- `docs/backend-api.md`: Document backend routes, controllers, request/response behavior, and future API behavior.
- `docs/prd.md`: Update product requirements when feature scope, role permissions, roadmap, or demo features change.
- `docs/database.md`: Document database tables, important fields, relationships, status values, and notes.
- `docs/ui-guide.md`: Document UI pages, routes, actions, visibility, wording, and design decisions.
- `docs/TODO.md`: Track pending work and future improvements.

## Required Changelog Entry Format

Each `docs/CHANGELOG.md` entry must include:

- Date
- Feature/module updated
- Summary of change
- Files changed
- Routes added/changed
- Database changes
- Permission changes
- Known limitations
- Next steps

Use dates in `YYYY-MM-DD` format.

## Definition Of Done

A task is only complete when:

1. Code is updated, if code changes were needed.
2. Routes are checked.
3. UI is checked, if UI changed.
4. Role permissions are checked, if permissions or protected pages changed.
5. Documentation is updated.
6. `docs/CHANGELOG.md` has a new entry.
7. `docs/TODO.md` is updated if there are remaining future improvements.

## Final Summary Format

At the end of every task, provide:

```markdown
## Implementation Summary

- What was changed
- What files were updated
- What routes were added
- What database changes were made
- What documentation files were updated
- What should be tested next
```

## Maintenance Notes

- Do not delete existing documentation.
- Do not overwrite large documentation sections unless necessary.
- Append new updates clearly.
- Keep documentation simple and readable.
- Use Markdown format.
- Keep route, permission, database, and UI notes consistent across docs.
