=== Classic Menu Duplicator ===
Contributors:      mralaminahamed
Tags:              menus, navigation, duplicate, copy, menu duplicator
Requires at least: 6.0
Tested up to:      6.9
Requires PHP:      7.4
Stable tag:        1.0.0
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

A simple yet powerful WordPress plugin that allows users to duplicate navigation menus with a single click.

== Description ==

Classic Menu Duplicator is a simple yet powerful WordPress plugin that allows users to duplicate navigation menus with a single click. Perfect for creating menu backups or quickly creating variations of existing menus.

**Features**

* One-Click Duplication: Instantly clone any navigation menu
* Preserves Hierarchy: Maintains all parent-child relationships
* Menu Items: Duplicates all menu items including custom links, pages, posts, and categories
* User-Friendly: Simple admin interface with clear feedback
* Secure: Uses WordPress nonces and capability checks
* Internationalized: Ready for translation with included `.pot` file

**Requirements**

* WordPress 6.0 or higher
* PHP 7.4 or higher
* User with `edit_theme_options` capability

== Installation ==

1. Upload the `swift-menu-duplicator` directory to `/wp-content/plugins/`.
2. Activate the plugin through **Plugins → Installed Plugins**.
3. Navigate to **Appearance → Menus**, select a menu, and click **Duplicate Menu**.

== Frequently Asked Questions ==

= Does it copy theme location assignments? =

No. Theme location assignments are site-specific and are intentionally not copied, so the duplicate does not silently replace an active menu in any location.

= What happens to sub-menu items? =

All parent-child relationships are preserved exactly. The duplication uses a two-pass approach: items are cloned first, then parent references are re-mapped to the new item IDs.

= Is it compatible with HPOS / WooCommerce? =

Yes. The plugin only interacts with `nav_menu_item` posts and `nav_menu` taxonomy terms; it has no dependency on WooCommerce order storage.

= Can I duplicate a menu with draft items? =

Yes, the plugin duplicates all menu items regardless of their status.

== Screenshots ==

1. The Duplicate Menu button in the menu editor footer.
2. Duplicated menu with "(Copy)" suffix in the menu list.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice =

= 1.0.0 =
Initial release.
