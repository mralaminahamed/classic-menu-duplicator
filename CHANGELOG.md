# Changelog

All notable changes to Swift Menu Duplicator are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

> Reconstructed from `readme.txt`, which was the only changelog this repository kept.
> Entries below are those release notes, unchanged; releases from here on are written here
> first and summarised into `readme.txt`.

## [1.1.0]

Everything below shipped as one release: versions 1.0.2 through 1.0.8 were development iterations that never reached the directory, so 1.0.1 is the version you are upgrading from.

**New**

- Snapshot restore. The panel could save and delete revisions but never restore one — the feature was documented from 1.0.0 and missing until now. Restoring replaces the menu's items in place and snapshots the current state first, so a restore can itself be undone.
- Block theme support. Navigation menus stored as `wp_navigation` posts — the ones block themes actually render — can be duplicated, exported, and imported from a dedicated Menu Manager tab, WP-CLI, and REST.
- Deleting a menu can be undone, theme locations included, for an hour afterwards.
- The Menu Manager gained Slug, Description, and Snapshots columns, sortable counts, a working Screen Options panel, and a stylesheet — the screen previously had none.
- REST API gained import and snapshot endpoints, and every route now publishes a schema.
- New WP-CLI commands: `snapshot list|save|restore|delete` and `navigation list|duplicate|export|import`.
- JSON can be pasted into the import screen instead of uploading a file.

**Fixed**

- Duplication and import now write menu items through core's `wp_update_nav_menu_item()` instead of inserting posts and postmeta directly, so WPML, Polylang, caching, and mega-menu plugins finally see cloned items.
- Menu item descriptions and menu descriptions were silently dropped by duplication, export, and import.
- Importing to a different site now re-resolves each item's target by slug, falling back to a custom link, instead of keeping an ID that points at unrelated content on the destination.
- Importing a file back into the site it came from failed outright; the name now falls back to "{name} (2)".
- Bulk "Duplicate" and "Export as JSON" silently did nothing without JavaScript.
- The multisite copy ignored the URL find/replace it documented.
- Duplicated custom links carried the source item's object ID.
- The snapshot panel sat behind the admin bar, and could not be operated by keyboard at all. The duplicate dialog claimed to be modal without trapping focus.
- Notices are announced to screen readers, and accessible names are translatable rather than hardcoded English.
- Uninstall now removes the plugin's term meta; snapshots used to survive deleting the plugin.
- Snapshots are stored one row each instead of rewriting the whole stack on every save.

**Security**

- Imported menu fields are sanitized field-by-field, uploads are validated and size-capped, and the bundled coding-standards dependency was updated for CVE-2026-45293.

**Changed**

- **Breaking:** the REST namespace moved from `cmd/v1` to `swift-menu-duplicator/v1`. Update any REST clients.
- **Breaking:** the WP-CLI command group is `wp swift-menu-duplicator`, not `wp menu-duplicator`.
- New plugin icon, banners, and screenshots.

## [1.0.1]

- Fix: WP-CLI command renamed from `wp menu-duplicator` to `wp swift-menu-duplicator` for consistency with the plugin slug.
- Fix: `composer.lock` was excluded by `.gitignore` glob pattern causing the SVN deploy workflow to fail.
- Fix: Short description trimmed to satisfy the WordPress.org 150-character limit.
- Tested up to WordPress 7.0.

## [1.0.0]

- Initial release.
- One-click menu duplication from the WordPress menu editor with hierarchy preserved.
- Duplicate individual menu items from the editor.
- Snapshot system: auto-snapshot before every save, manual snapshots, restore and delete.
- Appearance → Menu Manager page with sortable table, bulk duplicate, bulk export (ZIP), and bulk delete.
- JSON export and import with URL find & replace and dry-run preview.
- Multisite support: copy menus to any sub-site with optional URL rewriting.
- REST API at `/wp-json/cmd/v1/` — duplicate menu, export menu, duplicate item.
- WP-CLI command group `wp swift-menu-duplicator` — duplicate, export, import, copy-to-site.
- WPML and Polylang compatibility — translation and language meta stripped from duplicates.
- Developer hooks and filters throughout for extensibility.
