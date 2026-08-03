<div align="center">

<img src=".wordpress-org/icon-256x256.png" alt="Swift Menu Duplicator logo" width="96" height="96">

# Swift Menu Duplicator

**Duplicate WordPress navigation menus in one click.**
Clone menus and individual items, snapshot revisions, export/import JSON, bulk-manage every menu from one screen, copy across Multisite, and automate it all from WP-CLI or the REST API.

[![WordPress.org version](https://img.shields.io/wordpress/plugin/v/swift-menu-duplicator?label=WordPress.org&logo=wordpress&logoColor=white&color=21759B)](https://wordpress.org/plugins/swift-menu-duplicator/)
[![Downloads](https://img.shields.io/wordpress/plugin/dt/swift-menu-duplicator?label=Downloads&color=21759B)](https://wordpress.org/plugins/swift-menu-duplicator/advanced/)
[![Tested up to](https://img.shields.io/wordpress/plugin/tested/swift-menu-duplicator?label=Tested&logo=wordpress&logoColor=white)](https://wordpress.org/plugins/swift-menu-duplicator/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-GPL--2.0--or--later-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![PRs welcome](https://img.shields.io/badge/PRs-welcome-brightgreen.svg)](https://github.com/mralaminahamed/swift-menu-duplicator/pulls)

</div>

---

## What it does

WordPress ships no way to copy a navigation menu — rebuilding one by hand is slow and error-prone. **Swift Menu Duplicator** adds a **Duplicate** button right in the menu editor and a dedicated **Menu Manager** screen, so you can clone, version, move, export, and import menus without touching a single item twice. Every action is also scriptable through **WP-CLI** and a versioned **REST API**.

## Features

**Duplication**
- **One-click duplicate** from the menu editor footer — no page reload
- Full **hierarchy preserved** via a two-pass clone that re-maps every parent–child relationship
- All item metadata copied — type, object, URL, target, CSS classes, XFN, description
- Duplicate a **single item** (with its children) without cloning the whole menu

**Snapshots (revisions)**
- **Auto-snapshot** taken automatically before every menu save
- **Manual snapshots** on demand — browse and restore from an expandable panel; a restore snapshots the current state first, so it is always reversible
- Capped, filterable history (`swift_menu_duplicator_snapshot_limit`, default 10)

**Menu Manager** — `Appearance → Menu Manager`
- Sortable list of every menu with item counts and theme-location assignments
- Bulk **duplicate**, **export** (ZIP), and **delete**; row-level duplicate/export

**Export / Import**
- Portable **JSON** export of any menu, recording a slug-based reference so imports can re-resolve targets on another site
- Import from a file upload or pasted JSON, with a **dry-run preview**
- **URL find & replace** for staging → production migrations
- Items whose target does not exist on the destination degrade to custom links rather than pointing at unrelated content

**Deletion safety**
- Deleting a menu captures it first; an **Undo** link restores the menu, its items, and its theme locations
- Recoverable for an hour by default (`swift_menu_duplicator_undo_ttl`)

**Block themes**
- Duplicate, export, and import `wp_navigation` posts — the Navigation menus block themes actually render
- Dedicated **Navigation (Block)** tab with row-level duplicate/export, bulk duplicate and trash, link counts, and Site Editor links
- Same operations available from WP-CLI and REST

**Multisite, CLI & REST**
- Copy any menu to another sub-site on a Multisite network, with optional URL find & replace
- Full **WP-CLI** command group and a versioned **REST API**

**Multilingual-safe**
- WPML and Polylang translation/language meta is stripped from clones automatically

## Requirements

| | |
|---|---|
| WordPress | 6.0+ (tested to 7.0) |
| PHP | 7.4+ |
| Capability | `edit_theme_options` |

## Installation

| Method | Steps |
|---|---|
| **WordPress.org** | Search "Swift Menu Duplicator" under **Plugins → Add New**, then install and activate |
| **Upload** | Download the ZIP and upload via **Plugins → Add New → Upload Plugin** |
| **Git** | Clone into `wp-content/plugins/` and run `composer install --no-dev` |

## Quick start

1. Activate the plugin.
2. Go to **Appearance → Menus**, pick a menu, and click **Duplicate Menu** in the footer.
3. For bulk actions, snapshots, and import/export, open **Appearance → Menu Manager**.

## REST API

Base namespace `/wp-json/swift-menu-duplicator/v1/`. All routes require `edit_theme_options` by default.

| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/menus/{id}/duplicate` | Duplicate a menu (optional `name`) |
| `GET` | `/menus/{id}/export` | Export a menu as JSON |
| `POST` | `/menus/{id}/items/{item_id}/duplicate` | Duplicate a single item |
| `POST` | `/menus/import` | Import a menu from an export payload |
| `GET` | `/menus/{id}/snapshots` | List snapshots |
| `POST` | `/menus/{id}/snapshots` | Save a snapshot |
| `POST` | `/menus/{id}/snapshots/{snapshot_id}` | Restore a snapshot |
| `DELETE` | `/menus/{id}/snapshots/{snapshot_id}` | Delete a snapshot |
| `GET` | `/navigations` | List block navigation menus |
| `POST` | `/navigations/{id}/duplicate` | Duplicate a block navigation menu |
| `GET` | `/navigations/{id}/export` | Export a block navigation menu |
| `POST` | `/navigations/import` | Import a block navigation menu |

Permission is filterable via `swift_menu_duplicator_rest_permission`.

## WP-CLI

```bash
wp swift-menu-duplicator duplicate <menu-id> [--name=<name>] [--porcelain]
wp swift-menu-duplicator export <menu-id> [--output=<file>]
wp swift-menu-duplicator import <file> [--name=<name>] [--find=<url>] [--replace=<url>] [--dry-run] [--porcelain]
wp swift-menu-duplicator copy-to-site <menu-id> --target-blog=<id> [--name=<name>] [--find=<url>] [--replace=<url>]
wp swift-menu-duplicator snapshot list|save|restore|delete <menu-id> [--label=<label>] [--id=<uuid>]
wp swift-menu-duplicator navigation list|duplicate|export|import [<id-or-file>] [--title=<title>] [--output=<file>]
```

## Developer hooks

| Hook | Type | Description |
|---|---|---|
| `swift_menu_duplicator_new_menu_name` | filter | Change the default "(Copy)" name |
| `swift_menu_duplicator_rest_permission` | filter | Control REST API access |
| `swift_menu_duplicator_item_meta_keys` | filter | Which item meta keys are copied |
| `swift_menu_duplicator_snapshot_limit` | filter | Max snapshots kept per menu (default 10) |
| `swift_menu_duplicator_max_import_bytes` | filter | Max import upload size (default 2 MB) |
| `swift_menu_duplicator_max_import_items` | filter | Max items per import (default 5000) |
| `swift_menu_duplicator_compat_excluded_meta_keys` | filter | Extend the multilingual meta-exclusion list |
| `swift_menu_duplicator_before_duplicate_item` | action | Before an item (and its children) is duplicated |
| `swift_menu_duplicator_after_duplicate_menu_item` | action | After each item is cloned |
| `swift_menu_duplicator_after_import_menu` | action | After a successful import |
| `swift_menu_duplicator_before_restore_snapshot` | action | Before a menu is rolled back to a snapshot |
| `swift_menu_duplicator_after_restore_snapshot` | action | After a snapshot restore completes |
| `swift_menu_duplicator_after_undo_delete` | action | After a deleted menu is restored |
| `swift_menu_duplicator_undo_ttl` | filter | How long a deleted menu stays recoverable (default 1 hour) |
| `swift_menu_duplicator_undo_limit` | filter | How many deleted menus the buffer keeps (default 20) |
| `swift_menu_duplicator_import_menu_name` | filter | Change the name given to an imported menu |

## How it works

| Layer | Class | Responsibility |
|---|---|---|
| Editor toolbar | `Menu_Admin` | Duplicate button, AJAX handlers, snapshots |
| Menu Manager | `Menu_Admin_Page` | Bulk table, importer, Multisite copy |
| REST | `Menu_REST_Controller` | `/swift-menu-duplicator/v1/` endpoints |
| Core | `Menu_Duplicator` | Two-pass clone + JSON export |
| Core | `Navigation_Duplicator` | Block navigation (`wp_navigation`) clone, export, import |
| Core | `Menu_Undo` | Capture-before-delete buffer and restore |
| Import | `Menu_Importer` | JSON parse, field sanitization, URL find & replace |
| CLI | `Menu_CLI_Command` | `wp swift-menu-duplicator` commands |
| Compat | `Menu_Compat` | WPML / Polylang shims |

Every request that changes data is protected by a nonce and an `edit_theme_options` capability check, and imported data is sanitized field-by-field before it reaches the database.

## Screenshots

| Duplicate button in the menu editor | Menu Manager with bulk actions |
|---|---|
| ![Duplicate button in the menu editor footer](.wordpress-org/screenshot-1.png) | ![Menu Manager — sortable list with bulk actions](.wordpress-org/screenshot-2.png) |
| **Snapshot panel — browse & restore** | **JSON import with find & replace** |
| ![Snapshot panel — browse, restore, and delete revisions](.wordpress-org/screenshot-3.png) | ![JSON import form with URL find & replace and dry-run preview](.wordpress-org/screenshot-4.png) |

## Development

```bash
composer install
composer test        # PHPUnit
composer lint        # PHPCS (WordPress Coding Standards)
composer analyze     # PHPStan (level 4, PHP 7.4)
composer release     # build the distributable ZIP
```

Releases are cut by pushing a git tag — the [SVN deploy workflow](.github/workflows/svn-deploy.yml) validates the tag against the plugin version, builds the production artifact, deploys to the WordPress.org SVN repository, and attaches a ZIP to the GitHub release.

Full version history lives in the plugin's [WordPress.org changelog](https://wordpress.org/plugins/swift-menu-duplicator/#developers) (mirrored in [`readme.txt`](readme.txt)).

## Contributing

Issues and pull requests are welcome:

- [Open an issue](https://github.com/mralaminahamed/swift-menu-duplicator/issues/new) to report a bug or request a feature
- [Submit a pull request](https://github.com/mralaminahamed/swift-menu-duplicator/pulls)

## Support

If this plugin saves you time, **star the repo** and leave a [review on WordPress.org](https://wordpress.org/support/plugin/swift-menu-duplicator/reviews/).

## License

[GPL-2.0-or-later](https://www.gnu.org/licenses/gpl-2.0.html) © Al Amin Ahamed
