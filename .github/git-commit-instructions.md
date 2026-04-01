# Git Commit Instructions for Swift Menu Duplicator

Consistent commit messages improve readability, changelog generation, and release automation.

## 1. Format (Conventional Style)

```
<type>(<optional-scope>): <short imperative summary>

<optional body>

<optional footer>
```

- Summary: ≤ 72 chars, imperative, no trailing period.
- Wrap body lines at ~100 chars.
- Separate sections with blank lines.

## 2. Allowed Types

| Type | Purpose | Examples |
|------|---------|----------|
| feat | New user-facing feature | feat(admin): add duplicate button |
| fix | Bug fix | fix(ajax): correct menu ID retrieval |
| perf | Performance improvement | perf(core): cache menu items |
| refactor | Code change w/o feature/bug impact | refactor: extract duplication logic |
| docs | Documentation only | docs(readme): add installation steps |
| test | Tests added/updated | test(core): add duplication test |
| chore | Repo maintenance (no src impact) | chore: update .gitignore |
| build | Build system / tooling | build: add phpcs config |
| ci | Continuous integration config | ci: add php 8.2 to matrix |
| security | Security-related fix | security: validate nonce |

(Use one primary type; secondary concerns go in body.)

## 3. Scopes (Optional)

Common scopes: `admin`, `frontend`, `core`, `i18n`.
Use lowercase; add new scopes sparingly.

## 4. Breaking Changes

- Start a body line with `BREAKING CHANGE:` followed by explanation & migration steps.
- Optionally append `!` after type/scope (e.g., `feat(core)!:`) – still include the body note.

Example:
```
feat(core)!: change menu item duplication

BREAKING CHANGE: custom menu item meta is no longer copied.
Update existing workflows accordingly.
```

## 5. Referencing Issues & PRs

Footer lines:
- `Closes #123`
- `Refs #456`
One reference per line.

## 6. Body Content Guidelines

Explain:
- Motivation (why)
- Approach (how) if non-trivial
- Side effects / trade-offs
- Performance or security considerations
- Testing notes ("Adds regression test", "Covered by existing tests")

## 7. Examples

```
feat(admin): add duplicate menu button to menu editor

Adds a Duplicate Menu button to the nav-menus.php editor.
Closes #10

fix(ajax): validate nonce before processing request

Prevents unauthorized duplication requests.

perf(core): cache menu items query

Adds transient cache for menu items lookups.

refactor(core): extract Menu_Duplicator class

No behavior change; improves testability.

security: enforce capability check

Adds manage_options capability verification.
Closes #25

docs(readme): document new filter hook

test(core): add regression test for menu duplication
```

## 8. Security / Sensitive Fixes

- Use `security:` type.
- Keep exploit details minimal until release; share full context privately.

## 9. Translation & Escaping Notes

If adding user-facing strings: mention i18n + escaping (e.g., "All new strings wrapped in `__()`; output escaped with `esc_html`").
Text domain: `swift-menu-duplicator`

## 10. Tests Reference

When logic changes: add/adjust tests. If deferred (rare), justify in body.

## 11. Commit Hygiene Checklist

- PHPCS / linters pass.
- No debug output (`var_dump`, `console.log`).
- Inputs validated & output escaped.
- i18n applied (text domain: `swift-menu-duplicator`).
- No obvious performance regressions (N+1 queries, etc.).
- Tests updated/added.

## 12. Squashing & History

- Squash trivial fixup commits before merge.
- Do not squash security fix commits with unrelated changes.

## 13. Changelog Compatibility

Accurate types enable automated categorization (feat/fix/perf/security). Choose carefully.

## 14. Anti-Patterns

Avoid: `fix stuff`, `update code`, past tense (Added/Fixes), ticket-only messages, multi-unrelated changes.

## 15. When Unsure

Default: feat (new behavior), fix (defect), refactor (internal), chore (maintenance). Ask in PR if edge.

---

Following these conventions keeps Swift Menu Duplicator history clean, searchable, and automatable.

Thank you for contributing!
