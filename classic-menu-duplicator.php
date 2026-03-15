<?php
/**
 * Classic Menu Duplicator
 *
 * A simple yet powerful WordPress plugin that allows users to duplicate
 * navigation menus with a single click. Perfect for creating menu backups
 * or quickly creating variations of existing menus.
 *
 * Key Features:
 * - One-Click Duplication with custom name input
 * - Duplicate individual menu items (including sub-items)
 * - Snapshot / revision history with auto-save before each manual save
 * - Export any menu to a portable JSON file
 * - Preserves Hierarchy: Maintains all parent-child relationships
 * - Theme Locations: Not copied (prevents silently replacing live menus)
 * - Secure: Uses WordPress nonces and capability checks
 *
 * @link              https://github.com/mralaminahamed/classic-menu-duplicator
 * @since             1.0.0
 * @package           ClassicMenuDuplicator
 *
 * @wordpress-plugin
 * Plugin Name:       Classic Menu Duplicator
 * Plugin URI:        https://github.com/mralaminahamed/classic-menu-duplicator
 * Description:       Duplicate menus and individual items, save revision snapshots, and export to JSON — all from the native WordPress menu editor.
 * Version:           1.1.0
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

define( 'CLASSIC_MENU_DUPLICATOR_VERSION', '1.1.0' );
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
