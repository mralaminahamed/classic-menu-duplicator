# CLAUDE.md

Guidance for Claude Code when working in this repository.

`AGENTS.md` holds the full conventions (code style, security, i18n, file
layout). This file covers what is not obvious from reading the code.

## What this plugin is

Swift Menu Duplicator duplicates WordPress nav menus and their items, keeps
snapshot revisions, exports/imports JSON, bulk-manages menus from
`Appearance → Menu Manager`, copies menus across Multisite, and exposes the
same operations over WP-CLI and REST.

No build step. Plain PHP + plain JS; Composer classmap autoload over
`includes/`.

## Commands

```bash
composer install
composer lint          # PHPCS (WordPress standard)
composer lint:fix      # PHPCBF
composer lint:review   # Stricter WP.org plugin-review ruleset (warnings only)
composer analyze       # PHPStan level 4, PHP 7.4 target
composer makepot       # Regenerate languages/swift-menu-duplicator.pot

yarn install
yarn lint              # ESLint + Stylelint
yarn lint:js:fix       # ESLint --fix
```

### Running the PHP tests

The suite is a real WordPress integration suite, so it needs a throwaway
MySQL database — it **drops all tables** with the `unit_` prefix in it.

```bash
mysql -uroot -p -e "CREATE DATABASE IF NOT EXISTS wp_phpunit_tests;"
WP_DB_PASS=<password> vendor/bin/phpunit
```

`tests/php/phpunit-wp-config.php` reads `WP_DB_NAME`, `WP_DB_USER`,
`WP_DB_PASS`, `WP_DB_HOST` from the environment and falls back to
`wp_phpunit_tests` / `root` / empty / `localhost`. ABSPATH resolves to the
WordPress install five directories up, i.e. the site this plugin lives in.

## Conventions that are easy to get wrong

- **Hook prefix is `swift_menu_duplicator_`**, not `swmd_`. `swmd_` survives
  only in `wp_ajax_swmd_*` action names, the `swmd_menu_actions` nonce, script
  handles, and the `_swmd_*` meta keys. Tests once drifted from this and
  silently stopped asserting anything.
- **REST namespace is `swift-menu-duplicator/v1`.** The old `cmd/v1` namespace
  was removed in 1.0.2.
- **The capability is `edit_theme_options`**, plus `manage_network` for the
  Multisite copy. Not `manage_options`.
- **A menu item's description lives in `post_content`**, not in postmeta. Any
  code that copies items must carry it.
- **Deleting a menu is captured, not prevented.** `Menu_Undo` exports the menu
  into a per-user transient before `wp_delete_nav_menu()` runs — core deletes
  the items before the term, so no hook inside that function is early enough.
  A restored menu gets a new term ID; theme locations are re-applied.
- **Plugin data in the database**: term meta `_swmd_snapshot` (one row per
  snapshot, capped by `swift_menu_duplicator_snapshot_limit`), the legacy
  `_swmd_snapshots` stack from 1.0.3 and earlier, and `_swmd_created`. New
  storage must be added to `uninstall.php`.
- **Write menu items through `wp_update_nav_menu_item()`.** Never insert the
  `nav_menu_item` post and its `_menu_item_*` meta by hand: core normalises the
  meta and fires `wp_add_nav_menu_item` / `wp_update_nav_menu_item`, which is
  how translation, caching, and mega-menu plugins learn the item exists.
- **Object IDs are site-local.** Exports also record `object_slug` /
  `object_url` so a cross-site import can re-resolve the target, falling back
  to a custom link.
- **Do not stub core functions with Brain Monkey.** The suite boots real
  WordPress, so Patchwork cannot redefine functions core already loaded — it
  throws `DefinedTooEarly`. Use the real environment; for AJAX handlers extend
  `WP_Ajax_UnitTestCase` so `wp_send_json_*()` dies through the AJAX handler
  rather than killing the run.
- **`wp_update_nav_menu` also fires on menu creation** (`wp_create_nav_menu()`
  routes through `wp_update_nav_menu_object()`), so anything hooked there runs
  during this plugin's own duplicate and import paths.
- **Two menu systems.** Classic menus are `nav_menu` terms plus `nav_menu_item`
  posts (`Menu_Duplicator`). Block themes render `wp_navigation` posts whose
  post_content is block markup (`Navigation_Duplicator`). Every capability on
  `wp_navigation` maps to `edit_theme_options`, the same gate used elsewhere.
- **Do not kses navigation block markup by hand.** Block delimiters are HTML
  comments, which `wp_kses_post()` strips. Pass content to `wp_insert_post()`
  and let core apply the same filtering the Site Editor gets.
- **ESLint uses flat config only.** ESLint 10 ignores `.eslintrc.*` entirely.

## Releasing

A version bump must land in all six places or the WP.org deploy workflow
rejects the tag:

1. `swift-menu-duplicator.php` — `Version:` header
2. `swift-menu-duplicator.php` — `SWIFT_MENU_DUPLICATOR_VERSION`
3. `composer.json` — `version`
4. `package.json` — `version`
5. `readme.txt` — `Stable tag`, plus `== Changelog ==` and
   `== Upgrade Notice ==` entries
6. `languages/swift-menu-duplicator.pot` — regenerate with `composer makepot`
   (it derives the version from the plugin header, so bump that first)

There is no `CHANGELOG.md`: `readme.txt` is the single changelog. Do not add a
second one — two changelogs drift.

Do **not** bump historical changelog entries. Release ZIP contents are governed
by `.distignore`; add new dev-only files there.

## Git

Conventional Commits (`<type>(<scope>): <imperative summary>`). Branch from a
freshly fetched `origin/trunk` — `trunk` is the default branch, not `main`.
Merge PRs with a merge commit; never squash.
