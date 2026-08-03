<?php
/**
 * Uninstall routine for Swift Menu Duplicator.
 *
 * Fired automatically by WordPress when the user clicks "Delete" on the
 * Plugins screen. Removes all data written by the plugin to the database:
 * options and transients stored under the plugin's prefix, plus the term
 * meta the plugin attaches to nav_menu terms (snapshots and creation
 * timestamps). No nav_menu terms or nav_menu_item posts are removed —
 * those belong to the site owner.
 *
 * @package SwiftMenuDuplicator
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
 * so future versions can register options under the `swmd_` prefix and have
 * them cleaned up automatically without modifying the uninstall routine.
 *
 * @global \wpdb $wpdb WordPress database abstraction object.
 *
 * @return void
 */
function swift_menu_duplicator_delete_options(): void {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			$wpdb->esc_like( 'swmd_' ) . '%'
		)
	);
}

/**
 * Removes all transients whose names begin with the plugin prefix.
 *
 * Covers both standard (`_transient_swmd_*`) and timeout
 * (`_transient_timeout_swmd_*`) rows, plus their site-wide equivalents
 * on multisite (`_site_transient_swmd_*`).
 *
 * @global \wpdb $wpdb WordPress database abstraction object.
 *
 * @return void
 */
function swift_menu_duplicator_delete_transients(): void {
	global $wpdb;

	$patterns = array(
		$wpdb->esc_like( '_transient_swmd_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_swmd_' ) . '%',
		$wpdb->esc_like( '_site_transient_swmd_' ) . '%',
		$wpdb->esc_like( '_site_transient_timeout_swmd_' ) . '%',
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
 * Removes the term meta the plugin attaches to nav_menu terms.
 *
 * Covers `_swmd_snapshots` (the snapshot stack written by the snapshot
 * feature) and `_swmd_created` (the creation timestamp shown in the Menu
 * Manager table). The menus themselves are left untouched.
 *
 * @return void
 */
function swift_menu_duplicator_delete_term_meta(): void {
	$menus = get_terms(
		array(
			'taxonomy'   => 'nav_menu',
			'hide_empty' => false,
			'fields'     => 'ids',
		)
	);

	if ( is_wp_error( $menus ) || ! is_array( $menus ) ) {
		return;
	}

	foreach ( $menus as $menu_id ) {
		delete_term_meta( (int) $menu_id, '_swmd_snapshots' );
		delete_term_meta( (int) $menu_id, '_swmd_created' );
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
function swift_menu_duplicator_network_uninstall(): void {
	if ( ! is_multisite() ) {
		return;
	}

	$blog_ids = get_sites(
		array(
			'fields'   => 'ids',
			'number'   => 0, // Retrieve all sites.
			'spam'     => 0,
			'deleted'  => 0,
			'archived' => 0,
		)
	);

	foreach ( $blog_ids as $blog_id ) {
		switch_to_blog( (int) $blog_id );
		swift_menu_duplicator_delete_options();
		swift_menu_duplicator_delete_transients();
		swift_menu_duplicator_delete_term_meta();
		restore_current_blog();
	}
}

// -------------------------------------------------------------------------
// Execute cleanup.
// -------------------------------------------------------------------------

if ( is_multisite() ) {
	swift_menu_duplicator_network_uninstall();
} else {
	swift_menu_duplicator_delete_options();
	swift_menu_duplicator_delete_transients();
	swift_menu_duplicator_delete_term_meta();
}
