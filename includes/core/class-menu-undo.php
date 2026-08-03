<?php
/**
 * Recovery buffer for deleted menus.
 *
 * @package SwiftMenuDuplicator
 */

declare( strict_types=1 );

namespace SwiftMenuDuplicator\Core;

use SwiftMenuDuplicator\Import\Menu_Importer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Menu_Undo
 *
 * Deleting a menu is the one irreversible thing this plugin does, and it takes
 * the menu's snapshots with it — they live in that term's meta. This captures
 * a full export just before deletion and keeps it in a short-lived, per-user
 * transient so the action can be undone.
 *
 * A restored menu is a new term: WordPress does not let a term ID be reused.
 * Theme location assignments are captured alongside the export and re-applied
 * to the new ID, so the visible result is as close to the original as the API
 * allows.
 */
class Menu_Undo {

	/**
	 * Transient key prefix; one buffer per user.
	 *
	 * @var string
	 */
	private const TRANSIENT_PREFIX = 'swmd_undo_';

	/**
	 * Captures a menu so it can be restored later.
	 *
	 * Must run *before* deletion: wp_delete_nav_menu() removes the menu's items
	 * before the term itself, so there is no hook late enough to see the menu
	 * intact and early enough to matter.
	 *
	 * @param int $menu_id Term ID of the menu about to be deleted.
	 *
	 * @return bool True when the menu was captured.
	 */
	public function capture( int $menu_id ): bool {
		$payload = ( new Menu_Duplicator() )->export( $menu_id );

		if ( is_wp_error( $payload ) ) {
			return false;
		}

		$entries   = $this->get_pending();
		$entries[] = array(
			'id'        => wp_generate_uuid4(),
			'name'      => $payload['menu']['name'],
			'locations' => $this->get_locations_for( $menu_id ),
			'deleted'   => time(),
			'payload'   => $payload,
		);

		/**
		 * Filters how many deleted menus the undo buffer holds.
		 *
		 * @since 1.0.7
		 *
		 * @param int $limit Maximum entries. Default 20.
		 */
		$limit = (int) apply_filters( 'swift_menu_duplicator_undo_limit', 20 );

		if ( count( $entries ) > $limit ) {
			$entries = array_slice( $entries, - $limit );
		}

		return (bool) set_transient( $this->key(), $entries, $this->ttl() );
	}

	/**
	 * Returns the menus waiting to be restored, oldest first.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_pending(): array {
		$entries = get_transient( $this->key() );

		return is_array( $entries ) ? $entries : array();
	}

	/**
	 * Restores every captured menu and empties the buffer.
	 *
	 * @return array<int,string> Names of the menus that were restored.
	 */
	public function restore(): array {
		$entries = $this->get_pending();

		if ( empty( $entries ) ) {
			return array();
		}

		$importer  = new Menu_Importer();
		$restored  = array();
		$locations = get_nav_menu_locations();

		foreach ( $entries as $entry ) {
			$menu_id = $importer->import( $entry['payload'], (string) $entry['name'] );

			if ( is_wp_error( $menu_id ) ) {
				continue;
			}

			$restored[] = (string) $entry['name'];

			// Put the menu back in the theme locations it used to occupy.
			foreach ( (array) $entry['locations'] as $location ) {
				$locations[ $location ] = $menu_id;
			}

			/**
			 * Fires after a deleted menu has been restored from the undo buffer.
			 *
			 * @since 1.0.7
			 *
			 * @param int                 $menu_id New menu term ID.
			 * @param array<string,mixed> $entry   Captured entry.
			 */
			do_action( 'swift_menu_duplicator_after_undo_delete', $menu_id, $entry );
		}

		if ( ! empty( $restored ) ) {
			set_theme_mod( 'nav_menu_locations', $locations );
		}

		$this->clear();

		return $restored;
	}

	/**
	 * Empties the undo buffer.
	 *
	 * @return void
	 */
	public function clear(): void {
		delete_transient( $this->key() );
	}

	// -----------------------------------------------------------------------
	// Private helpers.
	// -----------------------------------------------------------------------

	/**
	 * Returns the transient key for the current user.
	 *
	 * @return string
	 */
	private function key(): string {
		return self::TRANSIENT_PREFIX . get_current_user_id();
	}

	/**
	 * Returns how long a captured menu stays restorable.
	 *
	 * @return int Seconds.
	 */
	private function ttl(): int {
		/**
		 * Filters how long deleted menus remain restorable.
		 *
		 * @since 1.0.7
		 *
		 * @param int $ttl Lifetime in seconds. Default one hour.
		 */
		return (int) apply_filters( 'swift_menu_duplicator_undo_ttl', HOUR_IN_SECONDS );
	}

	/**
	 * Returns the theme locations a menu is currently assigned to.
	 *
	 * @param int $menu_id Menu term ID.
	 *
	 * @return string[]
	 */
	private function get_locations_for( int $menu_id ): array {
		$assigned = array();

		foreach ( get_nav_menu_locations() as $location => $assigned_id ) {
			if ( (int) $assigned_id === $menu_id ) {
				$assigned[] = (string) $location;
			}
		}

		return $assigned;
	}
}
