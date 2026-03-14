<?php
/**
 * Plugin Name:       WP Menu Duplicator
 * Plugin URI:        https://github.com/mralaminahamed/wp-menu-duplicator
 * Description:       Adds a Duplicate Menu button to the nav-menus.php screen.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Al Amin Ahamed
 * Author URI:        https://github.com/mralaminahamed
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-menu-duplicator
 * Domain Path:       /languages
 *
 * @package WPMenuDuplicator
 */

declare( strict_types=1 );

namespace WPMenuDuplicator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WP_MENU_DUPLICATOR_VERSION', '1.0.0' );
define( 'WP_MENU_DUPLICATOR_FILE',    __FILE__ );
define( 'WP_MENU_DUPLICATOR_DIR',     plugin_dir_path( __FILE__ ) );
define( 'WP_MENU_DUPLICATOR_URL',     plugin_dir_url( __FILE__ ) );

require_once WP_MENU_DUPLICATOR_DIR . 'includes/class-menu-duplicator.php';
require_once WP_MENU_DUPLICATOR_DIR . 'includes/class-menu-admin.php';

/**
 * Initialises the plugin on plugins_loaded.
 *
 * @return void
 */
function bootstrap(): void {
	( new Menu_Admin() )->register_hooks();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\bootstrap' );
