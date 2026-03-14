<?php
/**
 * Uninstall routine for WP Menu Duplicator.
 *
 * Fired automatically by WordPress when the user clicks "Delete" on the
 * Plugins screen. Removes all data written by the plugin to the database:
 * any options stored under the plugin's prefix. No nav_menu terms or
 * nav_menu_item posts are removed — those belong to the site owner.
 *
 * @package WPMenuDuplicator
 * @link    https://developer.wordpress.org/plugins/plugin-basics/uninstall-methods/
 */

declare( strict_types=1 );

// Bail immediately if this file is accessed directly or outside the
// WordPress uninstall flow. Both constants must be present.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Require sufficient capability before performing any destructive operation.
if ( ! current_user_can( 'activate_plugins' ) ) {
	return;
}

/**
 * Removes all options whose names begin with the plugin prefix.
 *
 * At version 1.0.0 the plugin stores no options; this function is present
 * so future versions can register options under the `cmd_` prefix and have
 * them cleaned up automatically without modifying the uninstall routine.
 *
 * @global \wpdb $wpdb WordPress database abstraction object.
 *
 * @return void
 */
function cmd_delete_options(): void {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			$wpdb->esc_like( 'cmd_' ) . '%'
		)
	);
}

/**
 * Removes all transients whose names begin with the plugin prefix.
 *
 * Covers both standard (`_transient_cmd_*`) and timeout
 * (`_transient_timeout_cmd_*`) rows, plus their site-wide equivalents
 * on multisite (`_site_transient_cmd_*`).
 *
 * @global \wpdb $wpdb WordPress database abstraction object.
 *
 * @return void
 */
function cmd_delete_transients(): void {
	global $wpdb;

	$patterns = array(
		$wpdb->esc_like( '_transient_cmd_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_cmd_' ) . '%',
		$wpdb->esc_like( '_site_transient_cmd_' ) . '%',
		$wpdb->esc_like( '_site_transient_timeout_cmd_' ) . '%',
	);

	foreach ( $patterns as $pattern ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$pattern
			)
		);
	}
}

/**
 * Multisite: runs the cleanup routine across every blog in the network.
 *
 * Switches to each blog in turn, executes the single-site cleanup
 * functions, then restores the current blog. This ensures no orphaned
 * rows are left behind in per-site option tables.
 *
 * @return void
 */
function cmd_network_uninstall(): void {
	if ( ! is_multisite() ) {
		return;
	}

	$blog_ids = get_sites(
		array(
			'fields'     => 'ids',
			'number'     => 0, // Retrieve all sites.
			'spam'       => 0,
			'deleted'    => 0,
			'archived'   => 0,
		)
	);

	foreach ( $blog_ids as $blog_id ) {
		switch_to_blog( (int) $blog_id );
		cmd_delete_options();
		cmd_delete_transients();
		restore_current_blog();
	}
}

// -------------------------------------------------------------------------
// Execute cleanup.
// -------------------------------------------------------------------------

if ( is_multisite() ) {
	cmd_network_uninstall();
} else {
	cmd_delete_options();
	cmd_delete_transients();
}
