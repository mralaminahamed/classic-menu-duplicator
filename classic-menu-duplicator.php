<?php
/**
 * Plugin Name:       Classic Menu Duplicator
 * Plugin URI:        https://github.com/mralaminahamed/classic-menu-duplicator
 * Description:       Adds a Duplicate Menu button to the nav-menus.php screen.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Al Amin Ahamed
 * Author URI:        https://github.com/mralaminahamed
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       classic-menu-duplicator
 * Domain Path:       /languages
 *
 * @package ClassicMenuDuplicator
 */

declare( strict_types=1 );

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

add_action( 'plugins_loaded', __NAMESPACE__ . '\\classic_menu_duplicator_bootstrap' );
