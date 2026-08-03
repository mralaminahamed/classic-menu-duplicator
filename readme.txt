=== Swift Menu Duplicator ===
Contributors:      mralaminahamed
Tags:              menus, navigation, duplicate, copy, menu manager
Requires at least: 6.0
Tested up to:      7.0
Requires PHP:      7.4
Stable tag:        1.0.6
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

1. Duplicate Menu button in the menu editor footer.
2. Menu Manager page — sortable list of all menus with bulk actions.
3. Snapshot panel in the menu editor — browse, restore, and delete revisions.
4. JSON import form with URL find & replace and dry-run preview.
5. WP-CLI `duplicate` and `export` commands in a terminal.

== Changelog ==

= 1.0.6 =
* Accessibility: The Duplicate Menu dialog now traps Tab focus, moves focus to the name field when it opens, returns focus to the button that opened it when it closes, and marks the page behind it inert where the browser supports it.
* Accessibility: The snapshot panel is reachable and operable by keyboard — Escape closes it, Tab stays inside it, focus moves into it on open and back out on close, and the toggle button reports its state with `aria-expanded`.
* Accessibility: Success and error messages are announced to screen readers via `wp.a11y.speak()` and carry `role="status"` / `role="alert"`.
* Accessibility: The snapshot delete and panel close buttons had hardcoded English accessible names ("Delete", "Close"); both are now translatable and more descriptive.
* Accessibility: Added visible focus styles for the controls the plugin injects, and honoured `prefers-reduced-motion`.
* Fix: Notice text is inserted as text rather than markup, so a menu name containing HTML can no longer break the notice.

= 1.0.5 =
* Feature: **Block theme support.** Navigation menus stored as `wp_navigation` posts — the ones block themes actually render — can now be duplicated, exported, and imported. A new "Navigation (Block)" tab in the Menu Manager lists them with per-row Duplicate and Export JSON, bulk duplicate and trash, link counts, and a link into the Site Editor. Previously the plugin only told block-theme users that it did not apply to them.
* Feature: WP-CLI `wp swift-menu-duplicator navigation list|duplicate|export|import`.
* Feature: REST endpoints `GET /navigations`, `POST /navigations/{id}/duplicate`, `GET /navigations/{id}/export`, and `POST /navigations/import`, each with a schema.
* Changed: The Menu Manager tabs are ordered All Menus, Navigation (Block), Copy to Site, Import JSON.
* Fix: The Menu Manager's "+ New Menu" button opened the last-edited menu instead of the create-a-menu screen.

= 1.0.4 =
* Fix: Duplication and import now write menu items through core's `wp_update_nav_menu_item()` instead of inserting posts and postmeta directly. Core normalises the item meta and fires `wp_add_nav_menu_item` / `wp_update_nav_menu_item`, so WPML, Polylang, caching, and mega-menu plugins finally see cloned items.
* Fix: Duplicated custom links no longer carry the source item's `_menu_item_object_id`; core's own invariant (a custom link points at itself) is respected.
* Fix: Importing to a different site re-resolves each item's target by the slug recorded at export time, and falls back to a custom link using the original URL when the object does not exist. Previously the raw ID was kept, so items pointed at whatever content happened to hold that ID.
* Fix: The menu description is copied on duplicate and restored on import — the export already carried it, but nothing applied it.
* Fix: Bulk "Duplicate" and "Export as JSON" now have server-side handlers. Without JavaScript they silently did nothing.
* Fix: Snapshots are stored one term meta row each, instead of rewriting the entire stack into a single row on every save. Existing snapshots migrate automatically.
* Feature: The Menu Manager table gains Slug, Description, and Snapshots columns (Slug and Description hidden by default), sortable item and snapshot counts, and a working Screen Options panel for per-page and column visibility.
* Feature: The Menu Manager finally has a stylesheet — the import tab, preview table, and multisite copy form were previously unstyled.
* Feature: REST API gains import and snapshot endpoints (list, save, restore, delete), and every route now publishes a schema.
* Feature: New `wp swift-menu-duplicator snapshot list|save|restore|delete` command.
* Fix: Block themes now get an explanatory notice on the Menu Manager screen — they render Navigation blocks, not classic menus.
* Fix: Uninstall removes the new snapshot rows as well as the legacy ones.
* Changed: Admin scripts load with `defer`; downloads send `nocache_headers()` and `X-Content-Type-Options: nosniff`; the multisite site list uses `get_site()` data rather than `get_blog_details()`; the row Delete confirm moved out of an inline `onclick`.

= 1.0.3 =
* Feature: Snapshot **restore** now exists. The snapshot panel documented since 1.0.0 could only save and delete — restoring a menu from a snapshot was never implemented. Restoring replaces the menu's items in place (the menu ID and theme locations survive) and snapshots the current state first, so a restore can itself be undone.
* Fix: Menu item **descriptions** were silently dropped by duplication, export, and import. They are stored in `post_content`, which the clone never copied; exports now carry a `content` field per item.
* Fix: Importing a file exported from the same site failed outright, because WordPress rejects a duplicate menu name. The importer now falls back to "{name} (2)".
* Fix: An export of an empty menu was rejected as malformed on import; a valid but empty `items` array is now accepted.
* Fix: The Menu Manager's multisite copy ignored the URL find/replace documented for it — the fields are now present in the UI and applied on the destination site.
* Fix: The import screen now accepts pasted JSON as well as a file upload, as documented.
* Fix: Auto-snapshots are no longer written for empty menus, so duplicating or importing a menu no longer pushes a snapshot of nothing onto the stack.
* Fix: Uninstall now removes the plugin's term meta (`_swmd_snapshots`, `_swmd_created`); previously snapshots survived deletion of the plugin.
* Fix: The test suite could not load at all (the base test case's file name did not match its class) and targeted the pre-1.0.2 hook names and REST namespace. The suite runs green again and covers restore, descriptions, and the AJAX handlers.
* Changed: `ext-zip` is now a Composer suggestion rather than a hard requirement — bulk ZIP export already degrades gracefully without it.

= 1.0.2 =
* Security: Import now sanitizes every menu-item field (URLs, CSS classes, types, targets) instead of trusting the JSON file, preventing stored cross-site scripting from a malicious import.
* Security: JSON uploads are validated as genuine uploads and capped in size and item count before they are processed.
* Fix: The Delete action (row and bulk) relied on a capability that does not exist and never worked; it now uses `edit_theme_options` like every other action.
* Fix: Multisite copy now confirms the destination site exists before switching to it (admin UI and WP-CLI).
* Changed: **Breaking:** the REST API namespace moved from the generic `cmd/v1` to `swift-menu-duplicator/v1`. Update any REST clients to `/wp-json/swift-menu-duplicator/v1/`.
* Fix: Corrected developer-hook names throughout the documentation (they use the `swift_menu_duplicator_` prefix).
* Fix: Repaired the release workflow (correct plugin slug and asset-staging path) and the static-analysis configuration.

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

= 1.0.6 =
Accessibility release: keyboard focus management for the duplicate dialog and snapshot panel, screen-reader announcements for notices, translatable accessible names, and visible focus styles.

= 1.0.5 =
Adds block theme support: the navigation menus block themes render (`wp_navigation`) can now be duplicated, exported, and imported from the admin, WP-CLI, and REST.

= 1.0.4 =
Duplication and import now go through WordPress's own menu-item API, so translation and caching plugins see cloned items, and cross-site imports re-resolve their targets by slug instead of trusting raw IDs. Adds snapshot REST/CLI endpoints, new Menu Manager columns, and styles for the import screen.

= 1.0.3 =
Snapshot restore now works (it was documented but missing), menu item descriptions survive duplication and export/import, and importing a menu back into its own site no longer fails. Uninstall now clears leftover snapshot data.

= 1.0.2 =
Security and reliability fixes: imported menu fields are now sanitized, uploads are validated, and the Delete action works. Breaking: the REST namespace changed from `cmd/v1` to `swift-menu-duplicator/v1` — update any REST API clients.

= 1.0.1 =
WP-CLI users: the command has been renamed from `wp menu-duplicator` to `wp swift-menu-duplicator`. Update any scripts or aliases accordingly.

= 1.0.0 =
Initial release — no upgrade steps required.
