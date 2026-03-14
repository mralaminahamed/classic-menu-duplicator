<?php
/**
 * Classic Menu Duplicator
 *
 * A simple yet powerful WordPress plugin that allows users to duplicate
 * navigation menus with a single click. Perfect for creating menu backups
 * or quickly creating variations of existing menus.
 *
 * Key Features:
 * - One-Click Duplication: Instantly clone any navigation menu
 * - Preserves Hierarchy: Maintains all parent-child relationships
 * - Menu Items: Duplicates all menu items including custom links, pages, posts, and categories
 * - Theme Locations: Automatically assigns duplicated menu to theme locations if the original was
 * - User-Friendly: Simple admin interface with clear feedback
 * - Secure: Uses WordPress nonces and capability checks
 *
 * This file serves as the plugin bootstrap, loading all dependencies,
 * registering activation/deactivation hooks, and initializing the main
 * plugin functionality.
 *
 * @link              https://github.com/mralaminahamed/classic-menu-duplicator
 * @since             1.0.0
 * @package           ClassicMenuDuplicator
 *
 * @wordpress-plugin
 * Plugin Name:       Classic Menu Duplicator
 * Plugin URI:        https://github.com/mralaminahamed/classic-menu-duplicator
 * Description:       A simple yet powerful WordPress plugin that allows users to duplicate navigation menus with a single click.
 * Version:           1.0.0
 * Author:            Al Amin Ahamed
 * Author URI:        https://github.com/mralaminahamed
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       classic-menu-duplicator
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Tested up to:      6.9
 * Requires PHP:      7.4
 */

declare( strict_types=1 );

use ClassicMenuDuplicator\Menu_Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CLASSIC_MENU_DUPLICATOR_VERSION', '1.0.0' );
define( 'CLASSIC_MENU_DUPLICATOR_FILE', __FILE__ );
define( 'CLASSIC_MENU_DUPLICATOR_DIR', plugin_dir_path( __FILE__ ) );
define( 'CLASSIC_MENU_DUPLICATOR_URL', plugin_dir_url( __FILE__ ) );

require_once CLASSIC_MENU_DUPLICATOR_DIR . 'vendor/autoload.php';

/**
 * Initialises the plugin on plugins_loaded.
 *
 * @return void
 */
function classic_menu_duplicator_bootstrap(): void {
	( new Menu_Admin() )->register_hooks();
}

add_action( 'plugins_loaded', 'classic_menu_duplicator_bootstrap' );
