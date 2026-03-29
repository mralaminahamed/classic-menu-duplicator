<?php
/**
 * Bootstrap file for Swift Menu Duplicator tests.
 *
 * @package SwiftMenuDuplicator
 */

// Hide PHP notices, warnings, and deprecated messages
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED & ~E_STRICT);

define( 'TEST_SWIFT_MENU_DUPLICATOR_PLUGIN_FILE', dirname( __DIR__, 2 ) . '/swift-menu-duplicator.php' );
define( 'TEST_SWIFT_MENU_DUPLICATOR_PLUGIN_DIR', dirname( __DIR__, 2 ) );

require_once TEST_SWIFT_MENU_DUPLICATOR_PLUGIN_DIR . '/vendor/autoload.php';

$_tests_dir = getenv( 'WP_TESTS_DIR' ) ? getenv( 'WP_TESTS_DIR' ) : getenv( 'WP_PHPUNIT__DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

define( 'WP_TESTS_DIR', $_tests_dir );

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find $_tests_dir/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

function classic_menu_duplicator_truncate_tables(): void {
	global $wpdb;

	$tables = array(
		$wpdb->prefix . 'terms',
		$wpdb->prefix . 'term_taxonomy',
		$wpdb->prefix . 'term_relationships',
		$wpdb->prefix . 'termmeta',
		$wpdb->prefix . 'posts',
		$wpdb->prefix . 'postmeta',
	);

	$wpdb->query( 'SET FOREIGN_KEY_CHECKS = 0;' );

	foreach ( $tables as $table ) {
		$wpdb->query( "DELETE FROM {$table} WHERE 1=1" );
	}

	$wpdb->query( 'SET FOREIGN_KEY_CHECKS = 1;' );
}

require_once $_tests_dir . '/includes/functions.php';

function _manually_load_plugin() {
	require TEST_SWIFT_MENU_DUPLICATOR_PLUGIN_FILE;
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';
