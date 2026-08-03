# AGENTS.md - Swift Menu Duplicator

Agent-specific documentation for the Swift Menu Duplicator WordPress plugin.

## Overview

A simple WordPress plugin that allows users to duplicate navigation menus with a single click.

- **PHP**: 7.4+ | **WordPress**: 6.0+ | **WooCommerce**: Not required
- **Text Domain**: `swift-menu-duplicator`

---

## 1. Build / Lint / Test Commands

### PHP Code Sniffer
```bash
# Full plugin
./vendor/bin/phpcs --standard=WordPress --runtime-set testVersion 7.4- includes/ swift-menu-duplicator.php

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
yarn install         # Install JS lint tooling
yarn lint            # ESLint + Stylelint
yarn lint:js         # ESLint only (assets/js)
yarn lint:js:fix     # ESLint with --fix
yarn lint:css        # Stylelint only (assets/css)
composer makepot     # Generate .pot file
```

ESLint uses flat config (`eslint.config.mjs`) — ESLint 10 does not read
`.eslintrc.*` at all. Stylelint config lives in `stylelint.config.mjs`.

---

## 2. Code Style Guidelines

### General
- Always use `declare( strict_types=1 );` at the top of PHP files
- Follow WordPress Coding Standards (WPCS)
- Use PHP 7.4+ syntax (typed properties, null coalescing, arrow functions)

### Class Files
```
includes/
├── admin/class-menu-admin.php        # nav-menus.php toolbar + AJAX
├── admin/class-menu-admin-page.php   # Menu Manager page, import, multisite copy
├── admin/class-menu-table.php        # WP_List_Table of nav_menu terms
├── cli/class-menu-cli-command.php    # WP-CLI command group
├── compat/class-menu-compat.php      # WPML / Polylang compatibility
├── core/class-menu-duplicator.php    # Duplication, export, snapshots
├── import/class-menu-importer.php    # JSON parse / preview / import
├── rest/class-menu-rest-controller.php # REST routes
└── utils/class-filesystem.php        # WP_Filesystem wrapper
```

Each subdirectory maps to a namespace under `SwiftMenuDuplicator\` (e.g.
`includes/core/` → `SwiftMenuDuplicator\Core`). Autoloading is classmap-based
over `includes/`.

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
- **Capabilities**: `current_user_can( 'edit_theme_options' )` (menu actions), `manage_network` (multisite copy)
- **Database**: `$wpdb->prepare()` with placeholders

```php
// Always escape
echo '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Link', 'swift-menu-duplicator' ) . '</a>';

// Always sanitize
$menu_id = absint( $_POST['menu_id'] );
```

---

## 4. Internationalization (i18n)

- Wrap all user-facing strings: `__( 'Text', 'swift-menu-duplicator' )`
- Use escape variants: `esc_html__()`, `esc_html_e()`, `esc_attr__()`
- Never concatenate translatable strings; use `sprintf()`:

```php
// Bad
$msg = __( 'Menu #' . $menu_id, 'swift-menu-duplicator' );

// Good
$msg = sprintf( __( 'Menu #%d', 'swift-menu-duplicator' ), $menu_id );
```

---

## 5. JavaScript

- Plain JS in `assets/js/` — no build step
- Use IIFE pattern with jQuery:

```javascript
( function( $ ) {
    'use strict';
    $( document ).ready( function() {} );
}( jQuery ) );
```

- Declare globals in `eslint.config.mjs` (`languageOptions.globals`)
- Prefer `const` over `let`, avoid `var`

---

## 6. File Organization

```
includes/                      # See "Class Files" above for the full tree

assets/js/
├── admin.js                   # nav-menus.php editor integration
└── menu-manager.js            # Menu Manager page

templates/admin/
└── menu-manager.php           # Menu Manager markup

tests/php/src/                 # PHPUnit tests, mirroring includes/

languages/
└── swift-menu-duplicator.pot  # Translation template
```

---

## 7. Testing

- Tests in `tests/php/src/` mirroring class path
- Test naming: `Class_Name_Test.php`
- Run against the real WordPress test environment (`WP_UnitTestCase`); do not
  stub core functions with Brain Monkey — Patchwork cannot redefine functions
  WordPress has already loaded
- AJAX handlers extend `WP_Ajax_UnitTestCase` so `wp_send_json_*()` dies through
  the AJAX handler instead of killing the run
- Shared menu fixtures live in the `MenuFactory` trait

```php
class Menu_Duplicator_Test extends SwiftMenuDuplicatorTestCase {
    public function test_duplicate_returns_new_menu_id(): void {
        $menu_id = $this->create_menu_with_items( 'Source', 2 );

        $this->assertIsInt( ( new Menu_Duplicator() )->duplicate( $menu_id ) );
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
- `wp_ajax_swmd_duplicate_menu` / `swmd_duplicate_item` / `swmd_export_menu` — Menu editor actions
- `wp_ajax_swmd_save_snapshot` / `swmd_get_snapshots` / `swmd_restore_snapshot` / `swmd_delete_snapshot` — Snapshot actions
- `wp_ajax_swmd_bulk_duplicate` / `swmd_bulk_export_zip` / `swmd_copy_to_site` — Menu Manager actions
- `wp_update_nav_menu` — Auto-snapshot before a menu save

---

## 10. Key Security Considerations

1. Validate nonce using `wp_verify_nonce()` or `check_admin_referer()`
2. Use nonces for all AJAX/admin form submissions
3. Check capabilities before privileged operations (`edit_theme_options`; `manage_network` for cross-site copy)
4. Sanitize all input — never trust `$_GET`, `$_POST`, `$_REQUEST`
5. Escape all output

---

This file is used by AI agents to understand project conventions.
