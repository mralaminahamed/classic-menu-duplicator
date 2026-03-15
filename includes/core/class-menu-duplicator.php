<?php
/**
 * Core menu-duplication logic.
 *
 * @package ClassicMenuDuplicator
 */

declare( strict_types=1 );

namespace ClassicMenuDuplicator\Core;

use WP_Error;
use WP_Post;
use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Menu_Duplicator
 *
 * Duplicates a nav_menu term and all of its menu items, preserving
 * item hierarchy, meta, and theme-location assignments.
 * Also provides item-level duplication and snapshot export utilities.
 */
class Menu_Duplicator {

	/**
	 * Duplicates an existing navigation menu.
	 *
	 * Creates a new nav_menu term using a caller-supplied name (or falls
	 * back to the source menu's name with a "(Copy)" suffix), then iterates
	 * over every menu item post belonging to the source menu and inserts a
	 * cloned post with identical postmeta. Parent–child relationships are
	 * re-mapped so the cloned items retain the same nesting structure.
	 *
	 * @param int    $source_menu_id Term ID of the source navigation menu.
	 * @param string $new_name       Optional. Custom name for the new menu.
	 *                               Falls back to "{original} (Copy)".
	 *
	 * @return int|WP_Error New menu term ID on success, WP_Error on failure.
	 */
	public function duplicate( int $source_menu_id, string $new_name = '' ) {
		$source_term = get_term( $source_menu_id, 'nav_menu' );

		if ( is_wp_error( $source_term ) || ! $source_term instanceof WP_Term ) {
			return new WP_Error(
				'invalid_menu',
				__( 'Source menu not found.', 'classic-menu-duplicator' )
			);
		}

		// ---------------------------------------------------------------
		// 1. Resolve the new menu name.
		// ---------------------------------------------------------------
		$new_name = trim( $new_name );

		if ( '' === $new_name ) {
			$new_name = sprintf(
				/* translators: %s: original menu name */
				_x( '%s (Copy)', 'duplicated menu name suffix', 'classic-menu-duplicator' ),
				$source_term->name
			);
		}

		/**
		 * Filters the name given to a duplicated menu.
		 *
		 * @since 1.1.0
		 *
		 * @param string   $new_name        Proposed name for the new menu.
		 * @param WP_Term $source_term     Source menu term object.
		 * @param int      $source_menu_id  Source menu term ID.
		 */
		$new_name = (string) apply_filters( 'classic_menu_duplicator_new_menu_name', $new_name, $source_term, $source_menu_id );

		// ---------------------------------------------------------------
		// 2. Create the duplicate nav_menu term.
		// ---------------------------------------------------------------

		/**
		 * Fires immediately before a menu duplication begins.
		 *
		 * @since 1.1.0
		 *
		 * @param int    $source_menu_id Source menu term ID.
		 * @param string $new_name       Name for the new menu.
		 */
		do_action( 'classic_menu_duplicator_before_duplicate_menu', $source_menu_id, $new_name );

		$new_term = wp_create_nav_menu( $new_name );

		if ( is_wp_error( $new_term ) ) {
			return $new_term;
		}

		$new_menu_id = (int) $new_term['term_id'];

		// ---------------------------------------------------------------
		// 3. Collect all menu items from the source menu.
		// ---------------------------------------------------------------
		$source_items = wp_get_nav_menu_items(
			$source_menu_id,
			array( 'post_status' => 'publish,draft' )
		);

		if ( empty( $source_items ) || ! is_array( $source_items ) ) {
			do_action( 'classic_menu_duplicator_after_duplicate_menu', $source_menu_id, $new_menu_id );
			return $new_menu_id;
		}

		// ---------------------------------------------------------------
		// 4. Clone each menu item; build an ID re-map for parent refs.
		// ---------------------------------------------------------------

		/** @var array<int,int> $id_map Maps original item ID => new item ID. */
		$id_map = array();

		foreach ( $source_items as $item ) {
			$new_item_id = $this->duplicate_menu_item( $item, $new_menu_id, $id_map );

			if ( is_wp_error( $new_item_id ) ) {
				// Non-fatal: log and continue with remaining items.
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log(
					sprintf(
						'Classic Menu Duplicator: failed to clone item %d — %s',
						$item->ID,
						$new_item_id->get_error_message()
					)
				);
				continue;
			}

			$id_map[ $item->ID ] = $new_item_id;
		}

		// ---------------------------------------------------------------
		// 5. Fix parent references now that all items have new IDs.
		// ---------------------------------------------------------------
		foreach ( $source_items as $item ) {
			$parent_id = (int) $item->menu_item_parent;

			if ( $parent_id > 0 && isset( $id_map[ $item->ID ], $id_map[ $parent_id ] ) ) {
				update_post_meta(
					$id_map[ $item->ID ],
					'_menu_item_menu_item_parent',
					(string) $id_map[ $parent_id ]
				);
			}
		}

		/**
		 * Fires immediately after a menu has been duplicated.
		 *
		 * @since 1.1.0
		 *
		 * @param int              $source_menu_id Source menu term ID.
		 * @param int              $new_menu_id    New menu term ID.
		 * @param array<int,int>   $id_map         Map of original item IDs to new item IDs.
		 */
		do_action( 'classic_menu_duplicator_after_duplicate_menu', $source_menu_id, $new_menu_id, $id_map );

		return $new_menu_id;
	}

	/**
	 * Duplicates a single top-level menu item and all of its descendants.
	 *
	 * Clones the given item post, re-maps its parent reference, and
	 * recursively clones every direct child found in $all_items.
	 *
	 * @param int            $item_id    Post ID of the nav_menu_item to duplicate.
	 * @param int            $menu_id    Term ID of the menu that owns the item.
	 * @param WP_Post[]|null $all_items  All items belonging to the menu, used for
	 *                                    descendant lookup. Fetched automatically when
	 *                                    null (useful for direct AJAX calls).
	 *
	 * @return int|WP_Error New top-level item post ID, or WP_Error on failure.
	 */
	public function duplicate_item( int $item_id, int $menu_id, ?array $all_items = null ) {
		$source_item = get_post( $item_id );

		if ( ! $source_item instanceof WP_Post || 'nav_menu_item' !== $source_item->post_type ) {
			return new WP_Error(
				'invalid_item',
				__( 'Source menu item not found.', 'classic-menu-duplicator' )
			);
		}

		/**
		 * Fires before a single menu item (and its descendants) is duplicated.
		 *
		 * @since 1.1.0
		 *
		 * @param int $item_id Post ID of the item about to be duplicated.
		 * @param int $menu_id Term ID of the menu that owns the item.
		 */
		do_action( 'classic_menu_duplicator_before_duplicate_item', $item_id, $menu_id );

		if ( null === $all_items ) {
			$all_items = wp_get_nav_menu_items( $menu_id, array( 'post_status' => 'publish,draft' ) );
			if ( ! is_array( $all_items ) ) {
				$all_items = array();
			}
		}

		// Build a parent=>children map for the entire menu.
		$children_map = $this->build_children_map( $all_items );

		// Clone the requested item and all its descendants, reusing the
		// same id_map pattern as the full menu duplicate.
		/** @var array<int,int> $id_map */
		$id_map      = array();
		$new_item_id = $this->clone_item_recursive( $source_item, $menu_id, $children_map, $id_map );

		if ( is_wp_error( $new_item_id ) ) {
			return $new_item_id;
		}

		// Re-map the parent of the newly cloned top-level item to its
		// original parent so it sits in the correct position in the menu.
		$original_parent = (int) get_post_meta( $item_id, '_menu_item_menu_item_parent', true );

		if ( $original_parent > 0 ) {
			update_post_meta( $new_item_id, '_menu_item_menu_item_parent', (string) $original_parent );
		}

		// Fix all descendant parent refs using the id_map.
		foreach ( $id_map as $old_id => $new_id ) {
			if ( $old_id === $item_id ) {
				continue; // Top-level handled above.
			}

			$original_item_parent = (int) get_post_meta( $old_id, '_menu_item_menu_item_parent', true );

			if ( $original_item_parent > 0 && isset( $id_map[ $original_item_parent ] ) ) {
				update_post_meta( $new_id, '_menu_item_menu_item_parent', (string) $id_map[ $original_item_parent ] );
			}
		}

		/**
		 * Fires after a single menu item (and its descendants) has been duplicated.
		 *
		 * @since 1.1.0
		 *
		 * @param int            $item_id     Original menu item post ID.
		 * @param int            $new_item_id New menu item post ID.
		 * @param int            $menu_id     Term ID of the menu.
		 * @param array<int,int> $id_map      Map of original => new item IDs.
		 */
		do_action( 'classic_menu_duplicator_after_duplicate_item', $item_id, $new_item_id, $menu_id, $id_map );

		return $new_item_id;
	}

	/**
	 * Exports a navigation menu to a JSON-serialisable array.
	 *
	 * The returned structure is suitable for passing directly to
	 * wp_send_json_success() or for writing to a .json file.
	 *
	 * @param int $menu_id Term ID of the menu to export.
	 *
	 * @return array<string,mixed>|WP_Error Export payload array or WP_Error.
	 */
	public function export( int $menu_id ) {
		$term = get_term( $menu_id, 'nav_menu' );

		if ( is_wp_error( $term ) || ! $term instanceof WP_Term ) {
			return new WP_Error(
				'invalid_menu',
				__( 'Menu not found.', 'classic-menu-duplicator' )
			);
		}

		$items = wp_get_nav_menu_items( $menu_id, array( 'post_status' => 'publish,draft' ) );

		if ( ! is_array( $items ) ) {
			$items = array();
		}

		$exported_items = array();

		foreach ( $items as $item ) {
			$meta = array();

			foreach ( $this->get_meta_keys() as $key ) {
				$value = get_post_meta( $item->ID, $key, true );

				if ( '' !== $value && false !== $value ) {
					$meta[ $key ] = $value;
				}
			}

			$exported_items[] = array(
				'id'         => $item->ID,
				'title'      => $item->post_title,
				'excerpt'    => $item->post_excerpt,
				'status'     => $item->post_status,
				'menu_order' => $item->menu_order,
				'meta'       => $meta,
			);
		}

		/**
		 * Filters the export payload before it is returned.
		 *
		 * @since 1.1.0
		 *
		 * @param array<string,mixed> $payload  The export array.
		 * @param int                 $menu_id  Source menu term ID.
		 * @param WP_Term            $term     Source menu term object.
		 */
		return apply_filters(
			'classic_menu_duplicator_export_payload',
			array(
				'version'  => CLASSIC_MENU_DUPLICATOR_VERSION,
				'exported' => current_time( 'c' ),
				'site_url' => home_url(),
				'menu'     => array(
					'name'        => $term->name,
					'slug'        => $term->slug,
					'description' => $term->description,
				),
				'items'    => $exported_items,
			),
			$menu_id,
			$term
		);
	}

	// -----------------------------------------------------------------------
	// Snapshot (revision) helpers.
	// -----------------------------------------------------------------------

	/**
	 * Saves a snapshot of the current menu state.
	 *
	 * Snapshots are stored as a serialised JSON blob in the term-meta table
	 * under the key `_cmdu_snapshots`, as a LIFO stack capped at
	 * cmdu_SNAPSHOT_LIMIT revisions (default 10).
	 *
	 * @param int    $menu_id Term ID of the menu.
	 * @param string $label   Optional. Human-readable label for the snapshot.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function save_snapshot( int $menu_id, string $label = '' ): bool {
		$payload = $this->export( $menu_id );

		if ( is_wp_error( $payload ) ) {
			return false;
		}

		$snapshot = array(
			'id'      => wp_generate_uuid4(),
			'label'   => '' !== $label ? $label : sprintf(
				/* translators: %s: human-readable date/time */
				__( 'Snapshot %s', 'classic-menu-duplicator' ),
				wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) )
			),
			'created' => time(),
			'data'    => $payload,
		);

		$limit     = (int) apply_filters( 'classic_menu_duplicator_snapshot_limit', 10 );
		$snapshots = $this->get_snapshots( $menu_id );

		array_unshift( $snapshots, $snapshot );

		if ( count( $snapshots ) > $limit ) {
			$snapshots = array_slice( $snapshots, 0, $limit );
		}

		return (bool) update_term_meta( $menu_id, '_cmdu_snapshots', $snapshots );
	}

	/**
	 * Retrieves all snapshots for a given menu.
	 *
	 * @param int $menu_id Term ID of the menu.
	 *
	 * @return array<int,array<string,mixed>> Ordered list of snapshots (newest first).
	 */
	public function get_snapshots( int $menu_id ): array {
		$raw = get_term_meta( $menu_id, '_cmdu_snapshots', true );

		if ( ! is_array( $raw ) ) {
			return array();
		}

		return $raw;
	}

	/**
	 * Deletes a specific snapshot by its UUID.
	 *
	 * @param int    $menu_id     Term ID of the menu.
	 * @param string $snapshot_id UUID of the snapshot to delete.
	 *
	 * @return bool True if deleted, false if snapshot was not found.
	 */
	public function delete_snapshot( int $menu_id, string $snapshot_id ): bool {
		$snapshots = $this->get_snapshots( $menu_id );
		$filtered  = array_values(
			array_filter(
				$snapshots,
				static function ( array $snap ) use ( $snapshot_id ): bool {
					return $snap['id'] !== $snapshot_id;
				}
			)
		);

		if ( count( $filtered ) === count( $snapshots ) ) {
			return false; // Nothing was removed.
		}

		update_term_meta( $menu_id, '_cmdu_snapshots', $filtered );

		return true;
	}

	// -----------------------------------------------------------------------
	// Private helpers.
	// -----------------------------------------------------------------------

	/**
	 * Duplicates a single nav_menu_item post and its postmeta.
	 *
	 * @param WP_Post        $item        Original menu item post object.
	 * @param int            $new_menu_id Term ID of the destination menu.
	 * @param array<int,int> $id_map      Already-processed original=>new ID pairs.
	 *
	 * @return int|WP_Error New post ID, or WP_Error on failure.
	 */
	private function duplicate_menu_item( WP_Post $item, int $new_menu_id, array $id_map ) {
		$new_item_id = wp_insert_post(
			array(
				'post_type'    => 'nav_menu_item',
				'post_status'  => $item->post_status,
				'post_title'   => $item->post_title,
				'post_excerpt' => $item->post_excerpt,
				'menu_order'   => $item->menu_order,
				'post_parent'  => 0,
			),
			true
		);

		if ( is_wp_error( $new_item_id ) ) {
			return $new_item_id;
		}

		wp_set_object_terms( $new_item_id, array( $new_menu_id ), 'nav_menu' );

		$this->copy_item_postmeta( $item->ID, $new_item_id, $id_map );

		/**
		 * Fires after a single nav_menu_item has been duplicated.
		 *
		 * @since 1.1.0
		 *
		 * @param int      $old_id      Original item post ID.
		 * @param int      $new_item_id New item post ID.
		 * @param WP_Post $item        Original item post object.
		 */
		do_action( 'classic_menu_duplicator_after_duplicate_menu_item', $item->ID, $new_item_id, $item );

		return $new_item_id;
	}

	/**
	 * Recursively clones a menu item and all its descendants.
	 *
	 * @param WP_Post              $item          Item to clone.
	 * @param int                  $menu_id       Destination menu term ID.
	 * @param array<int,WP_Post[]> $children_map  parent_id => child items.
	 * @param array<int,int>       $id_map        Accumulates old=>new IDs.
	 *
	 * @return int|WP_Error New post ID of the cloned item, or WP_Error.
	 */
	private function clone_item_recursive( WP_Post $item, int $menu_id, array $children_map, array &$id_map ) {
		$new_item_id = $this->duplicate_menu_item( $item, $menu_id, $id_map );

		if ( is_wp_error( $new_item_id ) ) {
			return $new_item_id;
		}

		$id_map[ $item->ID ] = $new_item_id;

		if ( ! empty( $children_map[ $item->ID ] ) ) {
			foreach ( $children_map[ $item->ID ] as $child ) {
				$this->clone_item_recursive( $child, $menu_id, $children_map, $id_map );
			}
		}

		return $new_item_id;
	}

	/**
	 * Builds a parent_id => children array from a flat list of menu items.
	 *
	 * @param WP_Post[] $items Flat list of nav_menu_item post objects.
	 *
	 * @return array<int,WP_Post[]> Map of parent post ID to child post objects.
	 */
	private function build_children_map( array $items ): array {
		$map = array();

		foreach ( $items as $item ) {
			$parent = (int) get_post_meta( $item->ID, '_menu_item_menu_item_parent', true );
			if ( ! isset( $map[ $parent ] ) ) {
				$map[ $parent ] = array();
			}
			$map[ $parent ][] = $item;
		}

		return $map;
	}

	/**
	 * Copies all _menu_item_* postmeta from source to destination.
	 *
	 * The _menu_item_menu_item_parent meta is intentionally copied as-is
	 * at this stage; the caller re-maps it after all items are processed.
	 *
	 * @param int            $source_id Source nav_menu_item post ID.
	 * @param int            $dest_id   Destination nav_menu_item post ID.
	 * @param array<int,int> $id_map    Already-processed original=>new ID pairs.
	 *
	 * @return void
	 */
	private function copy_item_postmeta( int $source_id, int $dest_id, array $id_map ): void {
		foreach ( $this->get_meta_keys() as $key ) {
			$value = get_post_meta( $source_id, $key, true );

			if ( '' === $value || false === $value ) {
				continue;
			}

			update_post_meta( $dest_id, $key, $value );
		}
	}

	/**
	 * Returns the list of postmeta keys copied for each menu item.
	 *
	 * @return string[]
	 */
	private function get_meta_keys(): array {
		/**
		 * Filters the postmeta keys copied when duplicating a menu item.
		 *
		 * @since 1.1.0
		 *
		 * @param string[] $keys Default meta keys.
		 */
		return (array) apply_filters(
			'classic_menu_duplicator_item_meta_keys',
			array(
				'_menu_item_type',
				'_menu_item_menu_item_parent',
				'_menu_item_object_id',
				'_menu_item_object',
				'_menu_item_target',
				'_menu_item_classes',
				'_menu_item_xfn',
				'_menu_item_url',
			)
		);
	}
}
