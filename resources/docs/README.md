# Swift Menu Duplicator - Admin Documentation

## Overview

Swift Menu Duplicator is a simple yet powerful WordPress plugin that allows users to duplicate navigation menus with a single click. This documentation covers the admin-facing features and usage instructions.

## Features

- **One-Click Duplication**: Instantly clone any navigation menu
- **Preserves Hierarchy**: Maintains all parent-child relationships
- **Copies All Menu Items**: Including custom links, pages, posts, categories, and custom post types
- **User-Friendly**: Simple admin interface with clear feedback
- **Secure**: Uses WordPress nonces and capability checks
- **Internationalized**: Ready for translation with included `.pot` file

## Requirements

- WordPress 6.0 or higher
- PHP 7.4 or higher
- User with `edit_theme_options` capability

## Installation

1. Upload the `swift-menu-duplicator` directory to `/wp-content/plugins/`
2. Activate the plugin through **Plugins → Installed Plugins**
3. The plugin requires no additional configuration

## Usage

### Duplicating a Menu

1. Navigate to **Appearance → Menus**
2. Select an existing menu from the menu selector dropdown
3. Locate the **Duplicate Menu** button in the menu editor footer (next to Save Menu)
4. Click the button
5. The plugin will create a copy and redirect you to the new menu for editing

### What Gets Duplicated

- Menu name (with " (Copy)" suffix added)
- All menu items
- Parent-child hierarchy relationships
- Menu item settings:
  - Navigation label
  - Title attribute
  - CSS classes
  - Link relationship (XFN)
  - Link target
  - Description
  - Menu item type (custom, post, page, category, taxonomy)
  - Custom link URL

### What Does NOT Get Duplicated

- Theme location assignments (intentional, to prevent accidentally replacing live menus)
- Menu items that are not published

## Security

- All AJAX requests are protected with WordPress nonces
- Capability check (`edit_theme_options`) required
- Input sanitization on all user inputs
- Output escaping on all displayed data

## Frequently Asked Questions

### Does it copy theme location assignments?

No. Theme location assignments are site-specific and are intentionally not copied, so the duplicate does not silently replace an active menu in any location.

### What happens to sub-menu items?

All parent-child relationships are preserved exactly. The duplication uses a two-pass approach: items are cloned first, then parent references are re-mapped to the new item IDs.

### Is it compatible with HPOS / WooCommerce?

Yes. The plugin only interacts with `nav_menu_item` posts and `nav_menu` taxonomy terms; it has no dependency on WooCommerce order storage.

### Can I duplicate a menu with draft items?

Yes, the plugin duplicates all menu items regardless of their status.

## Troubleshooting

### Button is not visible

Make sure:
- You have selected a menu from the dropdown
- You are on the **Appearance → Menus** screen
- Your user role has `edit_theme_options` capability

### Duplication fails

Check:
- PHP error logs for any errors
- That the menu has at least one published item
- Server memory limits are not exceeded

## Uninstallation

When the plugin is deleted:
- All plugin options are automatically removed
- All transients are cleaned up
- No menu data is deleted (menus belong to the site owner)

## Changelog

### 1.0.0
- Initial release
