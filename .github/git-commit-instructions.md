# Git Commit Instructions for WooCommerce GoCardless Payments

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
| feat | New user-facing feature | feat(gateway): add Instant Bank Pay |
| fix | Bug fix | fix(webhook): correct signature validation |
| perf | Performance improvement | perf(api): cache order queries |
| refactor | Code change w/o feature/bug impact | refactor(gateway): extract refund handler |
| docs | Documentation only | docs(readme): add webhook URL |
| test | Tests added/updated | test(api): add client test |
| chore | Repo maintenance (no src impact) | chore: update .gitignore |
| build | Build system / tooling | build: add phpcs config |
| ci | Continuous integration config | ci: add php 8.3 to matrix |
| security | Security-related fix | security(webhook): validate HMAC signature |

(Use one primary type; secondary concerns go in body.)

## 3. Scopes (Optional)

Common scopes: `gateway`, `webhook`, `refund`, `subscription`, `admin`, `api`, `settings`.
Use lowercase; add new scopes sparingly.

## 4. Breaking Changes

- Start a body line with `BREAKING CHANGE:` followed by explanation & migration steps.
- Optionally append `!` after type/scope (e.g., `feat(gateway)!:`) – still include the body note.

Example:
```
feat(gateway)!: change order amount calculation

BREAKING CHANGE: amounts now include tax by default.
Update existing integrations accordingly.
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
feat(gateway): add Instant Bank Pay support

Adds real-time bank payments via GoCardless Instant Bank Pay.
Closes #10

fix(webhook): validate HMAC signature before processing

Prevents forged webhook payloads.

perf(api): cache GoCardless payment lookups

Adds 5-minute transient cache for payment queries.

refactor(gateway): extract Refund_Handler class

No behavior change; improves testability.

security(webhook): enforce signature verification

Adds hash_equals() HMAC-SHA256 validation.
Closes #25

docs(readme): document webhook URL format

test(api): add regression test for get request
```

## 8. Security / Sensitive Fixes

- Use `security:` type.
- Keep exploit details minimal until release; share full context privately.

## 9. Translation & Escaping Notes

If adding user-facing strings: mention i18n + escaping (e.g., "All new strings wrapped in `__()`; output escaped with `esc_html`").
Text domain: `wc-gocardless-payments`

## 10. Tests Reference

When logic changes: add/adjust tests. If deferred (rare), justify in body.

## 11. Commit Hygiene Checklist

- PHPCS / linters pass.
- No debug output (`var_dump`, `console.log`).
- Inputs validated & output escaped.
- i18n applied (text domain: `wc-gocardless-payments`).
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

Following these conventions keeps WooCommerce GoCardless Payments history clean, searchable, and automatable.

Thank you for contributing!
