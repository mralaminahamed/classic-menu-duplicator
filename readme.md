# Swift Menu Duplicator

> Duplicate WordPress navigation menus in one click. Snapshot revisions, export/import JSON, bulk-manage all menus, copy across Multisite, and automate with WP-CLI or REST API.

[![WordPress Plugin Version](https://img.shields.io/badge/version-1.0.0-blue)](https://wordpress.org/plugins/swift-menu-duplicator/)
[![WordPress Tested Up To](https://img.shields.io/badge/WordPress-7.0-green)](https://wordpress.org/)
[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-blue)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-red)](https://www.gnu.org/licenses/gpl-2.0.html)

## Features

### One-Click Duplication
- **Duplicate button** in the menu editor footer — no page reload required
- Full hierarchy preserved via a two-pass clone that remaps all parent-child item IDs
- All item metadata copied — type, object, URL, target, CSS classes, XFN, description
- Duplicate individual menu items directly from the editor
- Filter `swift_menu_duplicator_new_menu_name` to customise the default "(Copy)" suffix

### Snapshot Revisions
- **Auto-snapshot** before every menu save
- **Manual snapshots** from the menu editor
- **Browse & restore** snapshots from an expandable panel
- **Delete** snapshots you no longer need

### Menu Manager (`Appearance → Menu Manager`)
- Sortable WP_List_Table listing every menu on the site
- Bulk **duplicate**, **export** (ZIP archive), and **delete**
- Row-level duplicate and export actions

### JSON Export / Import
- Export any menu to a portable JSON file
- Import from a file upload or paste JSON directly
- **URL find & replace** for staging → production domain migrations
- **Dry-run preview** before committing changes to the database

### Multisite
- Copy any menu to another sub-site in a WordPress Multisite network
- Automatic URL rewriting in item URLs across sub-sites

### REST API
Full API at `/wp-json/cmd/v1/`:

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/menus/{id}/duplicate` | Duplicate a menu (optional `name` param) |
| `GET`  | `/menus/{id}/export` | Export a menu as JSON |
| `POST` | `/menus/{id}/items/{item_id}/duplicate` | Duplicate a single item |

Permission defaults to `edit_theme_options` and is filterable via `swmd_rest_permission`.

### WP-CLI
```bash
wp menu-duplicator duplicate <menu-id> [--name=<name>]
wp menu-duplicator export <menu-id> [--output=<file>]
wp menu-duplicator import <file> [--name=<name>] [--find=<str>] [--replace=<str>] [--dry-run] [--porcelain]
wp menu-duplicator copy-to-site <menu-id> --target-blog=<id> [--name=<name>] [--find=<str>] [--replace=<str>]
```

### Multilingual Compatibility
- **WPML** — translation meta stripped from duplicated items automatically
- **Polylang** — language meta stripped from duplicated items automatically
- Extend via the `swmd_compat_excluded_meta_keys` filter

## Requirements

- WordPress 6.0+
- PHP 7.4+
- User with `edit_theme_options` capability

## Installation

1. Upload the `swift-menu-duplicator` directory to `/wp-content/plugins/`.
2. Activate through **Plugins → Installed Plugins**.
3. Go to **Appearance → Menus** and click **Duplicate Menu** in the footer.
4. For bulk management and import, visit **Appearance → Menu Manager**.

## Developer Hooks

| Hook | Type | Description |
|------|------|-------------|
| `swift_menu_duplicator_new_menu_name` | filter | Customise the default duplicate name |
| `swmd_rest_permission` | filter | Control REST API access |
| `swmd_item_meta_keys` | filter | Control which meta keys are copied |
| `swmd_compat_excluded_meta_keys` | filter | Extend multilingual meta exclusion list |
| `swmd_before_duplicate_item` | action | Fires before each item is duplicated |
| `swmd_after_duplicate_menu_item` | action | Fires after each item is duplicated |
| `swmd_after_import_menu` | action | Fires after a successful import |

## Architecture

| Tier | Class | Responsibility |
|------|-------|----------------|
| 1 | `Menu_Admin` | Menu editor toolbar — duplicate button, AJAX handlers, snapshot system |
| 2 | `Menu_Admin_Page` | Appearance → Menu Manager — bulk table, importer, multisite copy |
| 3 | `Menu_REST_Controller` | REST API at `/cmd/v1/` |
| — | `Menu_Duplicator` | Core clone logic (two-pass clone + export) |
| — | `Menu_Importer` | ZIP/JSON parser with URL find & replace |
| — | `Menu_CLI_Command` | WP-CLI command group |
| — | `Menu_Compat` | WPML / Polylang compatibility shims |

## Development

```bash
composer run lint        # PHPCS with WordPress Coding Standards
composer run lint:fix    # Auto-fix with PHPCBF
composer run analyze     # PHPStan static analysis (level 4, PHP 7.4)
composer run test        # PHPUnit (all tests)
yarn lint                # ESLint + Stylelint
```

**Release** is handled automatically by pushing a git tag — the [GitHub Actions workflow](.github/workflows/svn-deploy.yml) validates the tag, builds the production artifact, deploys to the WordPress.org SVN repository, and attaches a ZIP to the GitHub release.

## Changelog

### 1.0.0
- Initial release
- One-click menu duplication from the WordPress menu editor
- Snapshot system with auto-snapshot, manual save, restore, and delete
- Menu Manager page with bulk duplicate, export (ZIP), and delete
- JSON export and import with URL find & replace and dry-run preview
- Multisite support — copy menus to any sub-site
- REST API at `/wp-json/cmd/v1/`
- WP-CLI command group `wp menu-duplicator`
- WPML and Polylang compatibility

## License

[GPL-2.0-or-later](https://www.gnu.org/licenses/gpl-2.0.html)
