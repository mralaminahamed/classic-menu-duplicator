=== Swift Menu Duplicator ===
Contributors:      mralaminahamed
Tags:              menus, navigation, duplicate, copy, menu manager
Requires at least: 6.0
Tested up to:      7.0
Requires PHP:      7.4
Stable tag:        1.1.0
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Duplicate WordPress menus in one click. Snapshot revisions, export/import JSON, bulk-manage all menus, copy across Multisite, automate with WP-CLI.

== Description ==

**Swift Menu Duplicator** gives you full control over your WordPress navigation menus. Clone any menu in one click, manage all menus from a dedicated admin page, version them with snapshots, move them across Multisite sub-sites, and automate everything from the command line or REST API.

=== One-Click Duplication ===

* Duplicate button right in the menu editor footer — no page reload required
* Full hierarchy preserved via a two-pass clone that remaps all parent-child item IDs
* All item metadata copied — type, object, URL, target, CSS classes, XFN, description
* Duplicate individual menu items directly from the menu editor
* Custom name support — filter `swift_menu_duplicator_new_menu_name` to override the default "(Copy)" suffix

=== Snapshot Revisions ===

* **Auto-snapshot** — a snapshot is saved automatically before every menu save
* **Manual snapshots** — save named snapshots from the menu editor at any time
* **Browse & restore** — view all snapshots in an expandable panel and restore with one click; the pre-restore state is snapshotted first
* **Housekeeping** — delete individual snapshots you no longer need

=== Menu Manager (Appearance → Menu Manager) ===

* Dedicated page listing every menu on the site in a sortable WP_List_Table
* **Bulk duplicate** — clone multiple menus at once
* **Bulk export** — download selected menus as a single ZIP archive
* **Bulk delete** — remove multiple menus in one action
* Row actions — duplicate or export individual menus directly from the list

=== JSON Export / Import ===

* Export any menu to a portable JSON file (via admin or REST API)
* Import from a JSON file upload or paste JSON directly into the text area
* **URL find & replace** — swap domain names during import for staging → production migrations
* **Dry-run preview** — review what will be imported before making any changes to the database

=== Deletion Safety ===

* Deleting a menu — from the Menu Manager, the row action, or the Delete Menu button in the editor — captures it first
* An **Undo** link appears while the deletion is still recoverable (one hour by default, filterable)
* Restoring recreates the menu and its items and puts it back in the theme locations it occupied

=== Block Themes ===

* Duplicate, export, and import the **Navigation menus block themes actually render** (`wp_navigation` posts), not just classic menus
* A dedicated **Navigation (Block)** tab in the Menu Manager, with per-row Duplicate and Export JSON plus bulk duplicate and trash
* Link counts per menu, and one-click access to the Site Editor
* Available from WP-CLI and the REST API alongside the classic-menu commands

=== Multisite Support ===

* Copy any menu to another site in your WordPress Multisite network
* Optional URL find & replace applied to item URLs on the destination site

=== REST API ===

Full REST API at `/wp-json/swift-menu-duplicator/v1/` for headless and block-editor integrations:

* `POST /menus/{id}/duplicate` — duplicate a menu (optional `name` parameter)
* `GET  /menus/{id}/export` — export a menu as a JSON payload
* `POST /menus/{id}/items/{item_id}/duplicate` — duplicate a single menu item
* `POST /menus/import` — import a menu from an export payload
* `GET  /menus/{id}/snapshots` — list snapshots
* `POST /menus/{id}/snapshots` — save a snapshot
* `POST /menus/{id}/snapshots/{snapshot_id}` — restore a snapshot
* `DELETE /menus/{id}/snapshots/{snapshot_id}` — delete a snapshot
* `GET  /navigations` — list block navigation menus
* `POST /navigations/{id}/duplicate` — duplicate a block navigation menu
* `GET  /navigations/{id}/export` — export a block navigation menu
* `POST /navigations/import` — import a block navigation menu

Permission is controlled by the `swift_menu_duplicator_rest_permission` filter (defaults to `edit_theme_options`).

=== WP-CLI ===

Full command-line support under the `wp swift-menu-duplicator` command group:

* `wp swift-menu-duplicator duplicate <menu-id> [--name=<name>]` — duplicate a menu
* `wp swift-menu-duplicator export <menu-id> [--output=<file>]` — export to JSON
* `wp swift-menu-duplicator import <file> [--name=<name>] [--find=<str>] [--replace=<str>] [--dry-run] [--porcelain]` — import from JSON
* `wp swift-menu-duplicator copy-to-site <menu-id> --target-blog=<id> [--name=<name>] [--find=<str>] [--replace=<str>]` — copy to a sub-site
* `wp swift-menu-duplicator snapshot list|save|restore|delete <menu-id> [--label=<label>] [--id=<uuid>]` — manage snapshots
* `wp swift-menu-duplicator navigation list|duplicate|export|import [<id-or-file>] [--title=<title>] [--output=<file>]` — block navigation menus

=== Multilingual Compatibility ===

* **WPML** — translation meta keys (`_icl_lang_duplicate_of`, `wpml_language`, etc.) are stripped from duplicated items automatically
* **Polylang** — language meta keys (`_pll_synced_taxonomies`, `_pll_menu_language`, etc.) are stripped from duplicated items automatically
* Additional keys can be excluded via the `swift_menu_duplicator_compat_excluded_meta_keys` filter

=== Developer Hooks ===

* `swift_menu_duplicator_new_menu_name` — customise the default duplicate name
* `swift_menu_duplicator_rest_permission` — control REST API access
* `swift_menu_duplicator_before_duplicate_item` / `swift_menu_duplicator_after_duplicate_menu_item` — fired around item duplication
* `swift_menu_duplicator_after_import_menu` — fired after a successful import
* `swift_menu_duplicator_before_restore_snapshot` / `swift_menu_duplicator_after_restore_snapshot` — fired around a snapshot restore
* `swift_menu_duplicator_after_undo_delete` — fired after a deleted menu is restored
* `swift_menu_duplicator_undo_ttl` / `swift_menu_duplicator_undo_limit` — how long deletions stay recoverable, and how many are kept
* `swift_menu_duplicator_item_meta_keys` — control which meta keys are copied
* `swift_menu_duplicator_compat_excluded_meta_keys` — extend the multilingual meta exclusion list
* `wp_update_nav_menu` — triggers auto-snapshot before every menu save

=== Security ===

* All AJAX actions verified with nonces and `edit_theme_options` capability checks
* All output escaped; all input sanitized
* Database queries use `$wpdb->prepare()` — no string concatenation
* REST API permission is filterable but defaults to `edit_theme_options`
* WordPress Filesystem API used for all file read/write/delete operations

**Requirements**

* WordPress 6.0 or higher
* PHP 7.4 or higher
* User with `edit_theme_options` capability

== Installation ==

1. Upload the `swift-menu-duplicator` directory to `/wp-content/plugins/`.
2. Activate the plugin through **Plugins → Installed Plugins**.
3. Go to **Appearance → Menus**, select a menu, and click **Duplicate Menu** in the footer.
4. For bulk management, snapshots, and import, visit **Appearance → Menu Manager**.

== Frequently Asked Questions ==

= Does it copy theme location assignments? =

No. Theme location assignments are site-specific and intentionally not copied, so the duplicate does not silently replace an active menu in any location.

= What happens to sub-menu items? =

All parent-child relationships are preserved exactly. The plugin uses a two-pass approach: items are cloned first, then parent ID references are re-mapped to the newly created item IDs.

= Can I rename the duplicate before it is created? =

Yes. A name field is shown in the duplicate modal. You can also change the default suffix globally by filtering `swift_menu_duplicator_new_menu_name`.

= Is it compatible with WPML or Polylang? =

Yes. Translation and language meta keys are automatically stripped from duplicated items so the copy starts as a clean, language-neutral menu.

= Does it work with block themes? =

Yes. Block themes render Navigation blocks (`wp_navigation` posts) rather than classic menus, and the Menu Manager has a **Navigation (Block)** tab for exactly those: duplicate, export, import, bulk duplicate, and move to trash, with an Edit in Site Editor link on every row. Classic menus keep their own tab, so a site part-way through a theme migration can manage both.

The tab appears when the active theme is a block theme, or whenever the site already has block navigation menus — a classic theme can still have them left over from a previous theme.

= Is it compatible with WooCommerce / HPOS? =

Yes. The plugin only interacts with `nav_menu_item` posts and the `nav_menu` taxonomy. It has no dependency on WooCommerce or its High-Performance Order Storage.

= Can I duplicate a menu that contains draft items? =

Yes. All items are duplicated regardless of their post status.

= How do I use the REST API? =

Authenticate with a cookie session or an Application Password, then send:

`POST /wp-json/swift-menu-duplicator/v1/menus/{menu_id}/duplicate`

The response includes the new menu's `id`, `name`, and `edit_url`.

= How do I migrate menus between environments? =

Export the source menu to JSON (admin UI or `wp swift-menu-duplicator export`), transfer the file, then import it on the target site. Use the find/replace fields to rewrite domain-specific URLs during import.

= What capability is required? =

`edit_theme_options` for all duplication, snapshot, export, and import actions. Multisite copy-to-site additionally requires the network `manage_network` capability.

== Screenshots ==

1. Duplicating a menu from the menu editor — name the copy before it is created.
2. Menu Manager — every menu on the site, with item and snapshot counts, sortable columns, and bulk actions.
3. Snapshot panel in the menu editor — save, browse, restore, and delete revisions.
4. JSON import with URL find & replace, for moving menus between environments.
5. Deleting a menu can be undone, theme locations included.

== Changelog ==

= 1.1.0 =

Everything below shipped as one release: versions 1.0.2 through 1.0.8 were development iterations that never reached the directory, so 1.0.1 is the version you are upgrading from.

**New**

* Snapshot restore. The panel could save and delete revisions but never restore one — the feature was documented from 1.0.0 and missing until now. Restoring replaces the menu's items in place and snapshots the current state first, so a restore can itself be undone.
* Block theme support. Navigation menus stored as `wp_navigation` posts — the ones block themes actually render — can be duplicated, exported, and imported from a dedicated Menu Manager tab, WP-CLI, and REST.
* Deleting a menu can be undone, theme locations included, for an hour afterwards.
* The Menu Manager gained Slug, Description, and Snapshots columns, sortable counts, a working Screen Options panel, and a stylesheet — the screen previously had none.
* REST API gained import and snapshot endpoints, and every route now publishes a schema.
* New WP-CLI commands: `snapshot list|save|restore|delete` and `navigation list|duplicate|export|import`.
* JSON can be pasted into the import screen instead of uploading a file.

**Fixed**

* Duplication and import now write menu items through core's `wp_update_nav_menu_item()` instead of inserting posts and postmeta directly, so WPML, Polylang, caching, and mega-menu plugins finally see cloned items.
* Menu item descriptions and menu descriptions were silently dropped by duplication, export, and import.
* Importing to a different site now re-resolves each item's target by slug, falling back to a custom link, instead of keeping an ID that points at unrelated content on the destination.
* Importing a file back into the site it came from failed outright; the name now falls back to "{name} (2)".
* Bulk "Duplicate" and "Export as JSON" silently did nothing without JavaScript.
* The multisite copy ignored the URL find/replace it documented.
* Duplicated custom links carried the source item's object ID.
* The snapshot panel sat behind the admin bar, and could not be operated by keyboard at all. The duplicate dialog claimed to be modal without trapping focus.
* Notices are announced to screen readers, and accessible names are translatable rather than hardcoded English.
* Uninstall now removes the plugin's term meta; snapshots used to survive deleting the plugin.
* Snapshots are stored one row each instead of rewriting the whole stack on every save.

**Security**

* Imported menu fields are sanitized field-by-field, uploads are validated and size-capped, and the bundled coding-standards dependency was updated for CVE-2026-45293.

**Changed**

* **Breaking:** the REST namespace moved from `cmd/v1` to `swift-menu-duplicator/v1`. Update any REST clients.
* **Breaking:** the WP-CLI command group is `wp swift-menu-duplicator`, not `wp menu-duplicator`.
* New plugin icon, banners, and screenshots.

= 1.0.1 =
* Fix: WP-CLI command renamed from `wp menu-duplicator` to `wp swift-menu-duplicator` for consistency with the plugin slug.
* Fix: `composer.lock` was excluded by `.gitignore` glob pattern causing the SVN deploy workflow to fail.
* Fix: Short description trimmed to satisfy the WordPress.org 150-character limit.
* Tested up to WordPress 7.0.

= 1.0.0 =
* Initial release.
* One-click menu duplication from the WordPress menu editor with hierarchy preserved.
* Duplicate individual menu items from the editor.
* Snapshot system: auto-snapshot before every save, manual snapshots, restore and delete.
* Appearance → Menu Manager page with sortable table, bulk duplicate, bulk export (ZIP), and bulk delete.
* JSON export and import with URL find & replace and dry-run preview.
* Multisite support: copy menus to any sub-site with optional URL rewriting.
* REST API at `/wp-json/cmd/v1/` — duplicate menu, export menu, duplicate item.
* WP-CLI command group `wp swift-menu-duplicator` — duplicate, export, import, copy-to-site.
* WPML and Polylang compatibility — translation and language meta stripped from duplicates.
* Developer hooks and filters throughout for extensibility.

== Upgrade Notice ==

= 1.1.0 =
Large release. Snapshot restore now exists, block themes are supported, deleting a menu can be undone, and duplication goes through WordPress's own menu-item API so translation and caching plugins see cloned items. Breaking: the REST namespace is now `swift-menu-duplicator/v1` and the WP-CLI group is `wp swift-menu-duplicator`.

= 1.0.1 =
WP-CLI users: the command has been renamed from `wp menu-duplicator` to `wp swift-menu-duplicator`. Update any scripts or aliases accordingly.

= 1.0.0 =
Initial release — no upgrade steps required.
