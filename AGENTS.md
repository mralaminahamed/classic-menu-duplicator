# AGENTS.md - WooCommerce GoCardless Payments

Agent-specific documentation for the WooCommerce GoCardless Payments WordPress plugin.

## Overview

WooCommerce payment gateway integrating GoCardless for Direct Debit (ACH/BACS/SEPA), Instant Bank Pay, Variable Recurring Payments (VRP), and Payment Intentions.

- **PHP**: 7.4+ | **WordPress**: 6.2+ | **WooCommerce**: 8.0+
- **Namespace**: `WC_GoCardless` | **Text Domain**: `wc-gocardless-payments`

---

## 1. Build / Lint / Test Commands

### PHP Code Sniffer
```bash
# Full plugin
./vendor/bin/phpcs --standard=WordPress --runtime-set testVersion 7.4- includes/ wc-gocardless-payments.php

# Custom ruleset
./vendor/bin/phpcs --standard=phpcs.xml.dist includes/

# Auto-fix
./vendor/bin/phpcbf includes/
```

### PHPStan
```bash
./vendor/bin/phpstan analyse
```

### PHPUnit
```bash
# All tests
./vendor/bin/phpunit

# Single test file
./vendor/bin/phpunit tests/php/src/Api/API_Client_Test.php

# Specific test method
./vendor/bin/phpunit --filter test_get_request
```

### Other
```bash
npm run lint          # JS linting
composer makepot     # Generate .pot file
composer release     # Create release zip
```

---

## 2. Code Style Guidelines

### General
- Always use `declare( strict_types=1 );` at the top of PHP files
- Follow WordPress Coding Standards (WPCS)
- Use PHP 7.4+ syntax (typed properties, null coalescing, arrow functions)
- Use dependency injection via constructors; avoid globals

### Namespaces & Class Files
```
WC_GoCardless\
├── API\
├── Admin\
├── Gateway\
├── Webhooks\
├── Subscriptions\
├── Utilities\
├── Frontend\
└── Payment_Token\
```

Class file format: `class-wc-gocardless-*.php`

### Naming Conventions
- Classes: `PascalCase` (e.g., `API_Client`)
- Methods/Properties: `snake_case` (e.g., `process_payment`, `$api_client`)
- Constants: `UPPER_SNAKE_CASE`
- Hooks: lowercase with underscores

### Imports
Use explicit class imports. Avoid fully qualified names in code:

```php
// Good
use WC_GoCardless\API\API_Client;
use WC_GoCardless\Gateway\Gateway;

// Bad
$client = \WC_GoCardless\API\API_Client::get_instance();
```

### PHPDoc
Document all public methods with `@param`, `@return`, `@throws`:

```php
/**
 * Process a payment for the given order.
 *
 * @param  \WC_Order $order    Order object.
 * @param  array      $payload Payment payload.
 * @return array{result: string, redirect: string}
 * @throws API_Exception On API error.
 */
public function process_payment( $order, $payload = [] ): array {}
```

### Error Handling
- Throw domain-specific exceptions
- Catch at appropriate levels (controller/gateway)
- Use `\WP_Error` for WordPress-specific errors

---

## 3. Security

- **Escape output**: `esc_html__()`, `esc_attr__()`, `esc_url()`, `esc_js()`
- **Sanitize input**: `sanitize_text_field()`, `absint()`, `wp_kses()`
- **Nonces**: `wp_create_nonce()`, `check_admin_referer()`
- **Capabilities**: `current_user_can( 'manage_woocommerce' )`
- **Database**: `$wpdb->prepare()` with placeholders
- **Webhooks**: Verify HMAC-SHA256 with `hash_equals()`

```php
// Always escape
echo '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Link', 'wc-gocardless-payments' ) . '</a>';

// Always sanitize
$order_id = absint( $_POST['order_id'] );
```

---

## 4. Internationalization (i18n)

- Wrap all user-facing strings: `__( 'Text', 'wc-gocardless-payments' )`
- Use escape variants: `esc_html__()`, `esc_html_e()`, `esc_attr__()`
- Never concatenate translatable strings; use `sprintf()`:

```php
// Bad
$msg = __( 'Order #' . $order_id, 'wc-gocardless-payments' );

// Good
$msg = sprintf( __( 'Order #%d', 'wc-gocardless-payments' ), $order_id );
```

---

## 5. JavaScript

- Plain JS in `assets/js/` — no build step
- Use IIFE pattern with jQuery:

```javascript
( function ( $ ) {
    'use strict';
    $( document ).ready( function () {} );
}( jQuery ) );
```

- Declare globals in `.eslintrc.js`
- Prefer `const` over `let`, avoid `var`

---

## 6. File Organization

```
includes/
├── class-wc-gocardless-payments.php   # Main plugin class
├── api/                               # API clients
├── admin/                             # Admin settings
├── gateway/                           # Payment gateways
├── frontend/                          # Checkout handling
├── webhooks/                          # Webhook processing
├── subscriptions/                     # WooCommerce Subscriptions
├── utilities/                         # Helpers
└── payment-token/                     # Payment tokens
```

---

## 7. Testing

- Tests in `tests/php/src/` mirroring class path
- Use PHPUnit with Brain Monkey for WP mocking
- Test naming: `ClassNameTest.php`

```php
class API_Client_Test extends \PHPUnit\Framework\TestCase {
    public function test_get_returns_array() {
        $client = new API_Client();
        $result = $client->get( 'payments/payment_xxx' );
        $this->assertIsArray( $result );
    }
}
```

---

## 8. Commit Messages

Format: `<type>(<scope>): <short imperative summary>`

Types: `feat`, `fix`, `perf`, `refactor`, `docs`, `test`, `chore`, `build`, `ci`, `security`

---

## 9. Important Hooks

- `woocommerce_payment_gateways` — Register gateway
- `rest_api_init` — Register REST routes
- `woocommerce_update_options_payment_gateways` — Save settings
- `woocommerce_order_refunded` — Process refunds

---

## 10. Key Security Considerations

1. Never expose secrets — API tokens, webhook secret
2. Validate webhook signatures using `hash_equals()` with HMAC-SHA256
3. Use nonces for all AJAX/admin form submissions
4. Check capabilities before privileged operations
5. Sanitize all input — never trust `$_GET`, `$_POST`, `$_REQUEST`
6. Escape all output

---

This file is used by AI agents to understand project conventions.
