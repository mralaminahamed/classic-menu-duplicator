=== WP Menu Duplicator ===
Contributors:      mralaminahamed
Tags:              menus, navigation, duplicate, copy
Requires at least: 6.0
Tested up to:      6.7
Requires PHP:      8.0
Stable tag:        1.0.0
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Adds a Duplicate Menu button to the nav-menus.php screen, enabling one-click duplication of any navigation menu and all of its items.

== Description ==

WP Menu Duplicator places a **Duplicate Menu** button immediately after the **Select** button on the *Appearance → Menus* screen. Clicking it creates an exact copy of the currently selected menu — including all items, nesting hierarchy, and item metadata — and redirects the editor to the newly duplicated menu.

**Features**

* One-click duplication from the standard nav-menus.php toolbar.
* Preserves complete item hierarchy (parent–child relationships).
* Copies all menu item postmeta: type, object, target, CSS classes, XFN, URL.
* New menu is named `{Original Name} (Copy)` and is immediately editable.
* Button is disabled when no menu is selected, preventing accidental clicks.
* Fully internationalised (i18n-ready with a bundled `.pot` file).
* Nonce-protected AJAX endpoint with `edit_theme_options` capability check.

== Installation ==

1. Upload the `wp-menu-duplicator` directory to `/wp-content/plugins/`.
2. Activate the plugin through *Plugins → Installed Plugins*.
3. Navigate to *Appearance → Menus*, select a menu, and click **Duplicate Menu**.

== Frequently Asked Questions ==

= Does it copy theme location assignments? =
No. Theme location assignments are site-specific and are intentionally not copied, so the duplicate does not silently replace an active menu in any location.

= What happens to sub-menu items? =
All parent–child relationships are preserved exactly. The duplication uses a two-pass approach: items are cloned first, then parent references are re-mapped to the new item IDs.

= Is it compatible with HPOS / WooCommerce? =
Yes. The plugin only interacts with `nav_menu_item` posts and `nav_menu` taxonomy terms; it has no dependency on WooCommerce order storage.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
