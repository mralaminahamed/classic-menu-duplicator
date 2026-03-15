# AGENTS.md - Classic Menu Duplicator

Agent-specific documentation for the Classic Menu Duplicator WordPress plugin.

## Overview

A simple WordPress plugin that allows users to duplicate navigation menus with a single click.

- **PHP**: 7.4+ | **WordPress**: 6.0+ | **WooCommerce**: Not required
- **Text Domain**: `classic-menu-duplicator`

---

## 1. Build / Lint / Test Commands

### PHP Code Sniffer
```bash
# Full plugin
./vendor/bin/phpcs --standard=WordPress --runtime-set testVersion 7.4- includes/ classic-menu-duplicator.php

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
./vendor/bin/phpunit tests/php/src/Menu_Duplicator_Test.php
```

### Other
```bash
npm run lint          # JS linting
composer makepot     # Generate .pot file
```

---

## 2. Code Style Guidelines

### General
- Always use `declare( strict_types=1 );` at the top of PHP files
- Follow WordPress Coding Standards (WPCS)
- Use PHP 7.4+ syntax (typed properties, null coalescing, arrow functions)

### Class Files
```
includes/
├── class-menu-admin.php       # Admin UI and AJAX
└── class-menu-duplicator.php  # Core duplication logic
```

### Naming Conventions
- Classes: `PascalCase` (e.g., `Menu_Duplicator`)
- Methods/Properties: `snake_case` (e.g., `duplicate_menu`, `$menu_id`)
- Constants: `UPPER_SNAKE_CASE`
- Hooks: lowercase with underscores

### PHPDoc
Document all public methods with `@param`, `@return`:

```php
/**
 * Duplicate a navigation menu.
 *
 * @param int $menu_id Term ID of the menu to duplicate.
 * @return int|WP_Error New menu term ID or WP_Error on failure.
 */
public function duplicate_menu( int $menu_id ) {}
```

---

## 3. Security

- **Escape output**: `esc_html__()`, `esc_attr__()`, `esc_url()`, `esc_js()`
- **Sanitize input**: `sanitize_text_field()`, `absint()`, `wp_kses()`
- **Nonces**: `wp_create_nonce()`, `check_admin_referer()`
- **Capabilities**: `current_user_can( 'manage_options' )`
- **Database**: `$wpdb->prepare()` with placeholders

```php
// Always escape
echo '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Link', 'classic-menu-duplicator' ) . '</a>';

// Always sanitize
$menu_id = absint( $_POST['menu_id'] );
```

---

## 4. Internationalization (i18n)

- Wrap all user-facing strings: `__( 'Text', 'classic-menu-duplicator' )`
- Use escape variants: `esc_html__()`, `esc_html_e()`, `esc_attr__()`
- Never concatenate translatable strings; use `sprintf()`:

```php
// Bad
$msg = __( 'Menu #' . $menu_id, 'classic-menu-duplicator' );

// Good
$msg = sprintf( __( 'Menu #%d', 'classic-menu-duplicator' ), $menu_id );
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
├── class-menu-admin.php       # Admin UI, enqueue scripts, AJAX handler
└── class-menu-duplicator.php  # Core duplication logic

assets/js/
└── admin.js                   # Frontend JavaScript

languages/
└── classic-menu-duplicator.pot     # Translation template
```

---

## 7. Testing

- Tests in `tests/php/src/` mirroring class path
- Use PHPUnit with Brain Monkey for WP mocking
- Test naming: `ClassNameTest.php`

```php
class Menu_Duplicator_Test extends \PHPUnit\Framework\TestCase {
    public function test_duplicate_menu_returns_new_id() {
        $duplicator = new Menu_Duplicator();
        $result = $duplicator->duplicate_menu( 1 );
        $this->assertIsInt( $result );
    }
}
```

---

## 8. Commit Messages

Format: `<type>(<scope>): <short imperative summary>`

Types: `feat`, `fix`, `perf`, `refactor`, `docs`, `test`, `chore`, `build`, `ci`, `security`

---

## 9. Important Hooks

- `admin_enqueue_scripts` — Enqueue admin assets
- `wp_ajax_cmdu_duplicate_menu` — Handle AJAX duplication request

---

## 10. Key Security Considerations

1. Validate nonce using `wp_verify_nonce()` or `check_admin_referer()`
2. Use nonces for all AJAX/admin form submissions
3. Check capabilities before privileged operations (`manage_options`)
4. Sanitize all input — never trust `$_GET`, `$_POST`, `$_REQUEST`
5. Escape all output

---

This file is used by AI agents to understand project conventions.
