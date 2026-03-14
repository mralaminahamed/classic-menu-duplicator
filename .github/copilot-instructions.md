# Copilot Instructions for WP Menu Duplicator

Guidance for using GitHub Copilot (or similar AI assistants) while contributing to this WordPress menu duplicator plugin.

## 1. Scope of Acceptable AI Assistance

Use Copilot for accelerating repetitive or boilerplate tasks:
- WordPress hooks, filters, AJAX handlers.
- PHP class scaffolding under `includes/`.
- PHPDoc blocks, inline comments.
- Refactors: extracting methods, reducing duplication.

Avoid (require human authored or thorough review):
- Licensing, legal, business logic, data privacy decisions.
- Security-critical logic, nonce / capability checks (must be verified).
- Large unreviewed generated files (delete & redo smaller chunks if produced).

## 2. Coding Standards & Tooling

- Run PHPCS with WordPress Coding Standards before committing:
  ```bash
  ./vendor/bin/phpcs --standard=WordPress --runtime-set testVersion 7.4- includes/ wp-menu-duplicator.php
  ```
- Follow WordPress escaping/sanitizing conventions: `esc_html__`, `esc_attr__`, `esc_url`, `sanitize_text_field`, `wp_kses`, `wp_create_nonce`, `check_admin_referer`.
- Keep functions small & single responsibility.
- Always use `declare( strict_types=1 );` at top of PHP files.

## 3. File / Architectural Conventions

- Main plugin file (`wp-menu-duplicator.php`) defines constants and bootstraps.
- `includes/` contains core logic:
  - `class-menu-admin.php` - Admin UI and AJAX handling
  - `class-menu-duplicator.php` - Core duplication logic
- Assets are plain JS in `assets/js/` — no build step required.

## 4. Security Checklist (AI suggestions must be manually validated)

- All DB queries: use `$wpdb->prepare` with placeholders (`%s`, `%d`), never string concatenation.
- Escape on output, sanitize on input, validate business rules.
- Nonces for state-changing actions (AJAX, form submissions) & proper capability checks (`current_user_can`).
- Never expose internal IDs or secrets.

## 5. Performance & Reliability

- Avoid N+1 queries inside loops.
- Use WordPress HTTP API (`wp_remote_get`, `wp_remote_post`) for any external calls.

## 6. Internationalization (i18n)

- All user-facing strings must be wrapped: `__( 'Text', 'wp-menu-duplicator' )` or `esc_html__()`.
- Do not concatenate translatable strings with variables; use placeholders (sprintf).
- Text domain: `wp-menu-duplicator`

## 7. Testing

- PHP: PHPUnit; place tests in `tests/php` mirroring class path.
- Write regression tests for any bug fix.

## 8. Documentation & Comments

- Every public method: concise PHPDoc with `@param` types, `@return`, `@since`.
- Complex queries / algorithms: add rationale comments (why, not just what).
- Update readme.txt if user-facing change.

## 9. Commit Messages

Format: `<type>(<scope>): <short imperative summary>`
Types: `feat`, `fix`, `perf`, `refactor`, `docs`, `test`, `chore`, `build`, `ci`, `security`.
Optional scope: `admin`, `frontend`, `core`.

Example:
```
feat(admin): add duplicate menu button to menu editor

Adds a Duplicate Menu button to the nav-menus.php editor.
Closes #42
```

## 10. Pull Requests

- Ensure: coding standards pass, no debug var_dump / console.log, updated docs.
- AI-generated code must be marked in PR description with verification note.

## 11. Versioning

- Bump version in `wp-menu-duplicator.php` only when preparing a release.
- Document notable changes in readme.txt.

## 12. Handling Sensitive / Proprietary Logic

- Do not paste API keys, user PII, or undisclosed endpoints into prompts.
- Abstract secrets via WordPress options or constants; never hard-code.

## 13. Review Checklist Before Committing AI-Suggested Code

- [ ] Escaping / sanitizing applied where needed.
- [ ] No raw input trust (`$_REQUEST`, `$_GET`, `$_POST`) without validation.
- [ ] Translation functions used for user text with 'wp-menu-duplicator' domain.
- [ ] Memory / query usage reasonable.
- [ ] No dead or commented-out large blocks.
- [ ] Follows commit message spec.

## 14. Example Good Uses

PHP Hook:
```php
add_action( 'wp_ajax_wmd_duplicate_menu', function() {
    // Handle AJAX request.
} );
```

PHPDoc:
```php
/**
 * Duplicate a menu and all its items.
 *
 * @param int $menu_id Term ID of the menu to duplicate.
 * @return int|WP_Error New menu term ID or error.
 */
public function duplicate_menu( int $menu_id ) {
    // ...
}
```

## 15. When to Escalate Instead of Using Copilot

- Ambiguous product requirement – clarify with maintainer first.
- Potential security exploit or vulnerability – open private issue.

---

Thank you for contributing to WP Menu Duplicator! Use Copilot responsibly — human judgment remains essential.
