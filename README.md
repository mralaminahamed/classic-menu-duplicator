<div align="center">

<img src=".wordpress-org/icon-256x256.png" alt="Swift Menu Duplicator icon" width="96" height="96">

# Swift Menu Duplicator — Developer Guide

**Duplicate navigation menus and their items, snapshot revisions, export and import as JSON, and drive all of it from WP-CLI or REST.**

[![Version](https://img.shields.io/badge/version-1.1.0-21759b.svg)](https://github.com/mralaminahamed/swift-menu-duplicator)
[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759b.svg?logo=wordpress&logoColor=white)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4.svg)](https://php.net/)
[![PHPStan](https://img.shields.io/badge/PHPStan-Level%204-brightgreen.svg)](https://phpstan.org/)
[![License: GPL-2.0-or-later](https://img.shields.io/badge/License-GPL--2.0--or--later-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

</div>

> This is the **contributor / technical** guide. For the public plugin listing — features, screenshots, changelog, upgrade notices — see [`readme.txt`](readme.txt).

| Requirement   | Minimum | Tested up to |
|---------------|---------|--------------|
| **WordPress** | 6.0     | 7.0          |
| **PHP**       | 7.4     | —            |

Current version **1.1.0** · License **GPL-2.0-or-later** · Tooling **Yarn** + Composer · Delivered free on WordPress.org

---

## What it is

WordPress has no way to copy a menu. Rebuilding one by hand is tedious and error-prone, and
the moment you want the same structure on a second site there is no path at all beyond
clicking it out again.

This plugin adds the missing operations — duplicate, snapshot, export, import, bulk-manage,
and copy across a multisite network — and exposes every one of them through **three surfaces
that share one implementation**: the admin screen, WP-CLI, and REST. Automating a menu
migration therefore does not mean reimplementing what the UI does.

---

## Architecture

### PHP — `includes/` (PSR-4 `SwiftMenuDuplicator\`)

| Dir       | Responsibility                                                   |
|-----------|-------------------------------------------------------------------|
| `core/`   | Duplication, snapshots, and the menu-item tree walk                |
| `import/` | JSON import and export                                             |
| `admin/`  | Admin screens, bulk actions, and assets                            |
| `rest/`   | REST routes (`swift-menu-duplicator/v1`)                           |
| `cli/`    | WP-CLI commands                                                    |
| `compat/` | Third-party menu-plugin interoperability                           |
| `utils/`  | Shared helpers                                                     |

The thing worth understanding is that `core/` owns the operations outright. `admin/`, `rest/`
and `cli/` are three ways of asking for the same work, which is why a menu duplicated from the
command line is identical to one duplicated from the screen — there is no second code path to
drift.

Menu items are a **tree**, not a list: children reference parents by ID, so duplication has to
remap every parent reference to the newly created item rather than copying rows verbatim. That
remapping is the part to read first.

### Repo map

```
swift-menu-duplicator.php   Bootstrap
includes/                   PHP (PSR-4 SwiftMenuDuplicator\)
templates/                  Admin markup
assets/                     Admin CSS/JS
resources/                  Brand/source assets
tests/php/                  PHPUnit
tests/e2e/                  Playwright
languages/                  Translations
.wordpress-org/             Directory assets: icon, banners, screenshots
```

---

## Getting started

```bash
composer install     # PHP dependencies + dev tooling
yarn install         # lint tooling and asset scripts
```

---

## Testing

```bash
composer test                        # PHPUnit
composer test-f -- --filter SomeTest
```

Playwright specs live in `tests/e2e/`.

> The PHPUnit config connects as `root` with no password. On a machine where that is not how
> MySQL is set up, the suite fails to bootstrap rather than reporting test failures — set the
> database credentials in `phpunit.xml` before concluding anything is broken.

---

## Code quality

```bash
composer lint          # WordPress Coding Standards
composer lint:fix      # auto-fix
composer analyze       # phpcs + phpstan (level 4)
composer lint:review   # the stricter directory-review ruleset
yarn lint              # JS + CSS
```

---

## Internationalization

```bash
composer makepot
```

Text domain `swift-menu-duplicator`. Translations live in `languages/`.

---

## Release

```bash
composer release
yarn assets:shots      # WordPress.org screenshots
yarn assets:brand      # icon and banners
```

---

## Links

- [WordPress.org listing](https://wordpress.org/plugins/swift-menu-duplicator/)
- [Public readme](readme.txt) — features, screenshots, changelog

---

## Contributing · Security · License

Issues and pull requests are welcome. Please run `composer analyze` and `composer test`
before opening one.

Report security issues privately rather than in a public issue.

GPL-2.0-or-later, as declared in the plugin header.
