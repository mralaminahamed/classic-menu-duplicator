<?php
/**
 * Swift Menu Duplicator
 *
 * Duplicate menus and individual items, save revision snapshots, export/import
 * JSON, manage all menus from a dedicated admin page, copy menus across
 * multisite sub-sites, and control everything from WP-CLI.
 *
 * @link              https://github.com/mralaminahamed/swift-menu-duplicator
 * @since             1.0.0
 * @package           SwiftMenuDuplicator
 *
 * @wordpress-plugin
 * Plugin Name:       Swift Menu Duplicator
 * Plugin URI:        https://github.com/mralaminahamed/swift-menu-duplicator
 * Description:       Duplicate menus and items, snapshot revisions, export/import JSON, manage all menus in bulk, copy across multisite, automate with WP-CLI, and integrate via REST API.
 * Version:           1.0.0
 * Author:            Al Amin Ahamed
 * Author URI:        https://github.com/mralaminahamed
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       swift-menu-duplicator
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Tested up to:      6.9
 * Requires PHP:      7.4
 */

declare( strict_types=1 );

use SwiftMenuDuplicator\Admin\Menu_Admin;
use SwiftMenuDuplicator\Admin\Menu_Admin_Page;
use SwiftMenuDuplicator\Cli\Menu_CLI_Command;
use SwiftMenuDuplicator\Compat\Menu_Compat;
use SwiftMenuDuplicator\Rest\Menu_REST_Controller;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SWIFT_MENU_DUPLICATOR_VERSION', '1.0.0' );
define( 'SWIFT_MENU_DUPLICATOR_FILE', __FILE__ );
define( 'SWIFT_MENU_DUPLICATOR_DIR', plugin_dir_path( __FILE__ ) );
define( 'SWIFT_MENU_DUPLICATOR_URL', plugin_dir_url( __FILE__ ) );

require_once SWIFT_MENU_DUPLICATOR_DIR . 'vendor/autoload.php';

/**
 * Initialises the plugin on plugins_loaded.
 *
 * Boots the nav-menus.php toolbar integration (Tier 1), the dedicated
 * Menu Manager admin page (Tier 2), and — when WP-CLI is running —
 * registers the `menu-duplicator` command group.
 *
 * @return void
 */
function swift_menu_duplicator_bootstrap(): void {
	// Tier 1: nav-menus.php toolbar integration.
	( new Menu_Admin() )->register_hooks();

	// Tier 2: dedicated admin page (table, import, multisite copy).
	( new Menu_Admin_Page() )->register_hooks();

	// Tier 3: REST API endpoints.
	( new Menu_REST_Controller() )->register_hooks();

	// Tier 3: WPML / Polylang compatibility layer (no-ops when neither is active).
	( new Menu_Compat() )->register_hooks();
}

add_action( 'plugins_loaded', 'swift_menu_duplicator_bootstrap' );

/**
 * Registers the WP-CLI command group after plugins are loaded.
 *
 * Wrapped in a defined() check so the CLI bootstrap is only registered
 * when WP-CLI is active, without coupling the plugin to the WP_CLI class
 * at load time.
 */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'menu-duplicator', Menu_CLI_Command::class );
}
