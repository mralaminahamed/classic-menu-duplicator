# Classic Menu Duplicator

A simple WordPress plugin that allows users to duplicate navigation menus with a single click.

## Description

Classic Menu Duplicator adds a **Duplicate Menu** button to the WordPress menu editor. With just one click, you can duplicate any navigation menu along with all its items, hierarchy, and settings.

## Features

- **One-click duplication** from the menu editor footer
- **Preserves item hierarchy** - parent-child relationships are maintained
- **Copies all menu item metadata** - type, object, target, CSS classes, XFN, URL
- **Smart naming** - new menu is named "{Original Name} (Copy)"
- **Safe to use** - button is disabled when no menu is selected
- **Internationalization ready** - includes `.pot` translation file
- **Secure** - nonce-protected AJAX with capability checks

## Requirements

- WordPress 6.0+
- PHP 7.4+

## Installation

1. Upload the `classic-menu-duplicator` directory to `/wp-content/plugins/`
2. Activate the plugin through *Plugins → Installed Plugins*
3. Navigate to *Appearance → Menus*, select a menu, and click **Duplicate Menu**

## Frequently Asked Questions

### Does it copy theme location assignments?

No. Theme location assignments are site-specific and are intentionally not copied, so the duplicate does not silently replace an active menu in any location.

### What happens to sub-menu items?

All parent-child relationships are preserved exactly. The duplication uses a two-pass approach: items are cloned first, then parent references are re-mapped to the new item IDs.

### Is it compatible with HPOS / WooCommerce?

Yes. The plugin only interacts with `nav_menu_item` posts and `nav_menu` taxonomy terms; it has no dependency on WooCommerce order storage.

## Changelog

### 1.0.0
- Initial release

## License

GPL-2.0-or-later - see [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html)
