# Copilot Instructions for WooCommerce GoCardless Payments

Guidance for using GitHub Copilot (or similar AI assistants) while contributing to this WooCommerce GoCardless payment gateway plugin.

## 1. Scope of Acceptable AI Assistance

Use Copilot for accelerating repetitive or boilerplate tasks:
- WordPress hooks, filters, REST endpoint skeletons.
- PHP class scaffolding under `includes/` (respect namespace: `WC_GoCardless`).
- PHPUnit + Brain Monkey test stubs (`tests/php/`).
- PHPDoc blocks, inline comments.
- Refactors: extracting methods, reducing duplication.

Avoid (require human authored or thorough review):
- Licensing, legal, business / pricing logic, data privacy decisions.
- Security-critical SQL, authentication/authorization logic, nonce / capability checks (must be verified).
- Large unreviewed generated files (delete & redo smaller chunks if produced).
- Payment processing logic and API handling.

## 2. Coding Standards & Tooling

- Run PHPCS with WordPress Coding Standards before committing:
  ```bash
  ./vendor/bin/phpcs --standard=WordPress --runtime-set testVersion 7.4- includes/ wc-gocardless-payments.php
  ```
- Follow WordPress escaping/sanitizing conventions: `esc_html__`, `esc_attr__`, `esc_url`, `sanitize_text_field`, `wp_kses`, `wp_create_nonce`, `check_admin_referer`.
- Use dependency injection via constructors; avoid globals where possible.
- Keep functions small & single responsibility.
- Always use `declare( strict_types=1 );` at top of PHP files.

## 3. File / Architectural Conventions

- Main plugin file (`wc-gocardless-payments.php`) defines constants and bootstraps.
- `includes/` contains core logic:
  - `API/` - API_Client, API_Payments
  - `Admin/` - Admin
  - `Gateway/` - Gateway, Gateway_Direct_Debit, Gateway_Instant_Bank, Gateway_VRP
  - `Webhooks/` - Webhook_Handler, Webhook_Processor
  - `Subscriptions/` - Subscriptions
  - `Utilities/` - Logger, Order_Helper, Idempotency
- `class-wc-gocardless.php` - Main plugin class
- Assets are plain CSS/JS in `assets/` — no build step required.

## 4. Security Checklist (AI suggestions must be manually validated)

- All DB queries: use `$wpdb->prepare` with placeholders (`%s`, `%d`), never string concatenation.
- Escape on output, sanitize on input, validate business rules.
- Nonces for state-changing actions (AJAX, form submissions) & proper capability checks (`current_user_can`).
- REST endpoints: explicit `permission_callback`; never return raw user data without filtering.
- Avoid exposing internal IDs or secrets; use hashed tokens.
- Webhook verification using `hash_equals()`.
- Never hardcode API secrets; use WordPress options.

## 5. Performance & Reliability

- Cache expensive queries via transients when needed.
- Avoid N+1 queries inside loops – prefetch where possible.
- Prefer lazy-loading assets only on pages that need them (admin enqueue checks current screen hook).
- Use WordPress HTTP API (`wp_remote_get`, `wp_remote_post`) for API calls.

## 6. Internationalization (i18n)

- All user-facing strings must be wrapped: `__( 'Text', 'wc-gocardless-payments' )` or `esc_html__()`.
- Do not concatenate translatable strings with variables; use placeholders (sprintf).
- Text domain: `wc-gocardless-payments`

## 7. Testing

- PHP: PHPUnit + Brain Monkey for WP function mocking; place tests in `tests/php` mirroring class path.
- Write regression tests for any bug fix.
- Test API client, webhook handling, payment flow.

## 8. Documentation & Comments

- Every public method: concise PHPDoc with `@param` types, `@return`, `@since`.
- Complex queries / algorithms: add rationale comments (why, not just what).
- Update readme.txt if user-facing change (new endpoint, hook, setting).

## 9. Commit Messages

Format: `<type>(<scope>): <short imperative summary>`
Types: `feat`, `fix`, `perf`, `refactor`, `docs`, `test`, `chore`, `build`, `ci`, `security`.
Optional scope: `gateway`, `webhook`, `refund`, `admin`, `api`.

Example:
```
feat(gateway): add Instant Bank Pay support

Adds support for instant bank payments via GoCardless API.
Closes #42
```

## 10. Pull Requests

- Ensure: coding standards pass, tests green, no debug var_dump / console.log, updated docs.
- AI-generated code must be marked in PR description with verification note.

## 11. Versioning

- Bump version in `wc-gocardless-payments.php` only when preparing a release.
- Document notable changes in readme.txt.

## 12. Handling Sensitive / Proprietary Logic

- Do not paste API keys, user PII, or undisclosed partner endpoints into prompts.
- Abstract secrets via WordPress options or constants; never hard-code.

## 13. Review Checklist Before Committing AI-Suggested Code

- [ ] Namespaced correctly (`WC_GoCardless\*`).
- [ ] Escaping / sanitizing applied where needed.
- [ ] No raw input trust (`$_REQUEST`, `$_GET`, `$_POST`) without validation.
- [ ] Translation functions used for user text with 'wc-gocardless-payments' domain.
- [ ] Memory / query usage reasonable.
- [ ] Tests added/updated.
- [ ] No dead or commented-out large blocks.
- [ ] Follows commit message spec.

## 14. Example Good Uses

PHP Hook:
```php
add_action( 'rest_api_init', function() {
    // Register custom REST routes.
} );
```

PHPDoc:
```php
/**
 * Retrieve payment details from GoCardless.
 *
 * @param string $payment_id GoCardless payment ID.
 * @return array Payment data.
 */
public function get_payment( string $payment_id ): array {
    // ...
}
```

Test Stub:
```php
class API_Client_Test extends \PHPUnit\Framework\TestCase {
    public function test_get_request_returns_array() {
        $client = new API_Client();
        $this->assertIsArray( $client->get( 'payments/payment_xxx' ) );
    }
}
```

## 15. When to Escalate Instead of Using Copilot

- Ambiguous product requirement – clarify with maintainer first.
- Potential security exploit or vulnerability – open private issue.
- Payment processing logic.
- Webhook signature verification changes.

---

Thank you for contributing to WooCommerce GoCardless Payments! Use Copilot responsibly — human judgment remains essential.
