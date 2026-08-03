<?php
/**
 * Core menu-duplication logic.
 *
 * @package SwiftMenuDuplicator
 */

declare( strict_types=1 );

namespace SwiftMenuDuplicator\Core;

use SwiftMenuDuplicator\Import\Menu_Importer;
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
	 * Item postmeta keys that core's wp_update_nav_menu_item() owns.
	 *
	 * Listed so duplication can copy third-party meta without stamping over
	 * the values core just normalised.
	 *
	 * @var string[]
	 */
	/**
	 * Term meta key holding a single snapshot (one row per snapshot).
	 *
	 * @var string
	 */
	private const SNAPSHOT_META_KEY = '_swmd_snapshot';

	/**
	 * Term meta key used by 1.0.3 and earlier, holding the whole stack in one row.
	 *
	 * @var string
	 */
	private const LEGACY_SNAPSHOT_META_KEY = '_swmd_snapshots';

	private const CORE_META_KEYS = array(
		'_menu_item_type',
		'_menu_item_menu_item_parent',
		'_menu_item_object_id',
		'_menu_item_object',
		'_menu_item_target',
		'_menu_item_classes',
		'_menu_item_xfn',
		'_menu_item_url',
	);

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
				__( 'Source menu not found.', 'swift-menu-duplicator' )
			);
		}

		// ---------------------------------------------------------------
		// 1. Resolve the new menu name.
		// ---------------------------------------------------------------
		$new_name = trim( $new_name );

		if ( '' === $new_name ) {
			$new_name = sprintf(
				/* translators: %s: original menu name */
				_x( '%s (Copy)', 'duplicated menu name suffix', 'swift-menu-duplicator' ),
				$source_term->name
			);
		}

		/**
		 * Filters the name given to a duplicated menu.
		 *
		 * @since 1.0.0
		 *
		 * @param string   $new_name        Proposed name for the new menu.
		 * @param WP_Term $source_term     Source menu term object.
		 * @param int      $source_menu_id  Source menu term ID.
		 */
		$new_name = (string) apply_filters( 'swift_menu_duplicator_new_menu_name', $new_name, $source_term, $source_menu_id );

		// ---------------------------------------------------------------
		// 2. Create the duplicate nav_menu term.
		// ---------------------------------------------------------------

		/**
		 * Fires immediately before a menu duplication begins.
		 *
		 * @since 1.0.0
		 *
		 * @param int    $source_menu_id Source menu term ID.
		 * @param string $new_name       Name for the new menu.
		 */
		do_action( 'swift_menu_duplicator_before_duplicate_menu', $source_menu_id, $new_name );

		$new_term = wp_create_nav_menu( $new_name );

		if ( is_wp_error( $new_term ) ) {
			return $new_term;
		}

		// wp_create_nav_menu() returns the new term ID (int) or a WP_Error.
		$new_menu_id = (int) $new_term;

		// wp_create_nav_menu() only sets the name, so carry the source menu's
		// description across too.
		if ( '' !== $source_term->description ) {
			wp_update_nav_menu_object(
				$new_menu_id,
				array(
					'menu-name'   => $new_name,
					'description' => $source_term->description,
				)
			);
		}

		// ---------------------------------------------------------------
		// 3. Collect all menu items from the source menu.
		// ---------------------------------------------------------------
		$source_items = wp_get_nav_menu_items(
			$source_menu_id,
			array( 'post_status' => 'publish,draft' )
		);

		if ( empty( $source_items ) || ! is_array( $source_items ) ) {
			do_action( 'swift_menu_duplicator_after_duplicate_menu', $source_menu_id, $new_menu_id );
			return $new_menu_id;
		}

		// ---------------------------------------------------------------
		// 4. Clone each menu item; build an ID re-map for parent refs.
		// ---------------------------------------------------------------

		/**
		 * Maps each original item ID to its newly created item ID.
		 *
		 * @var array<int,int> $id_map
		 */
		$id_map = array();

		foreach ( $source_items as $item ) {
			$new_item_id = $this->duplicate_menu_item( $item, $new_menu_id );

			if ( is_wp_error( $new_item_id ) ) {
				// Non-fatal: log when WP_DEBUG_LOG is enabled and continue with remaining items.
				if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log(
						sprintf(
							'Swift Menu Duplicator: failed to clone item %d — %s',
							$item->ID,
							$new_item_id->get_error_message()
						)
					);
				}
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
		 * @since 1.0.0
		 *
		 * @param int              $source_menu_id Source menu term ID.
		 * @param int              $new_menu_id    New menu term ID.
		 * @param array<int,int>   $id_map         Map of original item IDs to new item IDs.
		 */
		do_action( 'swift_menu_duplicator_after_duplicate_menu', $source_menu_id, $new_menu_id, $id_map );

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
				__( 'Source menu item not found.', 'swift-menu-duplicator' )
			);
		}

		/**
		 * Fires before a single menu item (and its descendants) is duplicated.
		 *
		 * @since 1.0.0
		 *
		 * @param int $item_id Post ID of the item about to be duplicated.
		 * @param int $menu_id Term ID of the menu that owns the item.
		 */
		do_action( 'swift_menu_duplicator_before_duplicate_item', $item_id, $menu_id );

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
		/**
		 * Maps each original item ID to its newly created item ID.
		 *
		 * @var array<int,int> $id_map
		 */
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
		 * @since 1.0.0
		 *
		 * @param int            $item_id     Original menu item post ID.
		 * @param int            $new_item_id New menu item post ID.
		 * @param int            $menu_id     Term ID of the menu.
		 * @param array<int,int> $id_map      Map of original => new item IDs.
		 */
		do_action( 'swift_menu_duplicator_after_duplicate_item', $item_id, $new_item_id, $menu_id, $id_map );

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
				__( 'Menu not found.', 'swift-menu-duplicator' )
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

			$exported_items[] = array_merge(
				array(
					'id'         => $item->ID,
					'title'      => $item->post_title,
					// Core stores the item's "Description" field in post_content.
					'content'    => $item->post_content,
					'excerpt'    => $item->post_excerpt,
					'status'     => $item->post_status,
					'menu_order' => $item->menu_order,
					'meta'       => $meta,
				),
				// Object IDs are meaningless on another site, so record what the
				// item points at in portable terms. The importer uses these to
				// re-resolve the target, falling back to a custom link.
				$this->describe_item_target( $item, $meta )
			);
		}

		/**
		 * Filters the export payload before it is returned.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string,mixed> $payload  The export array.
		 * @param int                 $menu_id  Source menu term ID.
		 * @param WP_Term            $term     Source menu term object.
		 */
		return apply_filters(
			'swift_menu_duplicator_export_payload',
			array(
				'version'  => SWIFT_MENU_DUPLICATOR_VERSION,
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
	 * Each snapshot is its own term meta row under the key `_swmd_snapshot`,
	 * capped by the `swift_menu_duplicator_snapshot_limit` filter (default 10).
	 * Older releases packed the whole stack into a single `_swmd_snapshots`
	 * row, which meant rewriting every stored payload on each save; those rows
	 * are migrated the first time a menu is snapshotted again.
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

		$this->migrate_legacy_snapshots( $menu_id );

		$snapshot = array(
			'id'      => wp_generate_uuid4(),
			'label'   => '' !== $label ? $label : sprintf(
				/* translators: %s: human-readable date/time */
				__( 'Snapshot %s', 'swift-menu-duplicator' ),
				wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) )
			),
			'created' => time(),
			'data'    => $payload,
		);

		if ( ! add_term_meta( $menu_id, self::SNAPSHOT_META_KEY, $snapshot, false ) ) {
			return false;
		}

		$this->trim_snapshots( $menu_id );

		return true;
	}

	/**
	 * Retrieves all snapshots for a given menu, newest first.
	 *
	 * @param int $menu_id Term ID of the menu.
	 *
	 * @return array<int,array<string,mixed>> Ordered list of snapshots.
	 */
	public function get_snapshots( int $menu_id ): array {
		$rows = array_values(
			array_filter(
				(array) get_term_meta( $menu_id, self::SNAPSHOT_META_KEY, false ),
				static function ( $snapshot ): bool {
					return is_array( $snapshot ) && isset( $snapshot['id'], $snapshot['created'] );
				}
			)
		);

		// Rows written by 1.0.3 and earlier, before snapshots got a row each.
		// That stack was newest-first; reverse it so the merged list runs
		// oldest-first and predates anything stored per row.
		$legacy = get_term_meta( $menu_id, self::LEGACY_SNAPSHOT_META_KEY, true );
		$legacy = is_array( $legacy ) ? array_reverse( $legacy ) : array();

		$ordered = array_merge( $legacy, $rows );

		// Snapshots taken within the same second share a timestamp, so fall
		// back to insertion order to keep "newest first" deterministic.
		$indexed = array();

		foreach ( $ordered as $index => $snapshot ) {
			$indexed[] = array( $index, $snapshot );
		}

		usort(
			$indexed,
			static function ( array $a, array $b ): int {
				$by_time = (int) $b[1]['created'] <=> (int) $a[1]['created'];

				return 0 !== $by_time ? $by_time : ( $b[0] <=> $a[0] );
			}
		);

		return array_column( $indexed, 1 );
	}

	/**
	 * Moves any legacy single-row snapshot stack to one row per snapshot.
	 *
	 * @param int $menu_id Term ID of the menu.
	 *
	 * @return void
	 */
	private function migrate_legacy_snapshots( int $menu_id ): void {
		$legacy = get_term_meta( $menu_id, self::LEGACY_SNAPSHOT_META_KEY, true );

		if ( ! is_array( $legacy ) || empty( $legacy ) ) {
			return;
		}

		foreach ( $legacy as $snapshot ) {
			if ( is_array( $snapshot ) && isset( $snapshot['id'] ) ) {
				add_term_meta( $menu_id, self::SNAPSHOT_META_KEY, $snapshot, false );
			}
		}

		delete_term_meta( $menu_id, self::LEGACY_SNAPSHOT_META_KEY );
	}

	/**
	 * Drops the oldest snapshots beyond the configured limit.
	 *
	 * @param int $menu_id Term ID of the menu.
	 *
	 * @return void
	 */
	private function trim_snapshots( int $menu_id ): void {
		$limit = (int) apply_filters( 'swift_menu_duplicator_snapshot_limit', 10 );

		if ( $limit < 1 ) {
			$limit = 1;
		}

		$snapshots = $this->get_snapshots( $menu_id );

		foreach ( array_slice( $snapshots, $limit ) as $snapshot ) {
			delete_term_meta( $menu_id, self::SNAPSHOT_META_KEY, $snapshot );
		}
	}

	/**
	 * Restores a menu to the state captured in one of its snapshots.
	 *
	 * A safety snapshot of the current state is taken first, then every
	 * existing item in the menu is deleted and the snapshot's items are
	 * re-inserted with their hierarchy re-mapped. The menu term itself is
	 * kept — only its items are replaced — so theme-location assignments
	 * and the menu ID survive the restore.
	 *
	 * @param int    $menu_id     Term ID of the menu to restore.
	 * @param string $snapshot_id UUID of the snapshot to restore from.
	 *
	 * @return int|WP_Error Number of items restored, or WP_Error on failure.
	 */
	public function restore_snapshot( int $menu_id, string $snapshot_id ) {
		$term = get_term( $menu_id, 'nav_menu' );

		if ( is_wp_error( $term ) || ! $term instanceof WP_Term ) {
			return new WP_Error(
				'invalid_menu',
				__( 'Menu not found.', 'swift-menu-duplicator' )
			);
		}

		$snapshot = null;

		foreach ( $this->get_snapshots( $menu_id ) as $candidate ) {
			if ( isset( $candidate['id'] ) && $candidate['id'] === $snapshot_id ) {
				$snapshot = $candidate;
				break;
			}
		}

		if ( null === $snapshot || empty( $snapshot['data']['items'] ) ) {
			return new WP_Error(
				'invalid_snapshot',
				__( 'Snapshot not found.', 'swift-menu-duplicator' )
			);
		}

		/**
		 * Fires before a menu is rolled back to a snapshot.
		 *
		 * @since 1.0.3
		 *
		 * @param int    $menu_id     Term ID of the menu being restored.
		 * @param string $snapshot_id UUID of the snapshot being restored.
		 */
		do_action( 'swift_menu_duplicator_before_restore_snapshot', $menu_id, $snapshot_id );

		// Safety net: capture the state we are about to overwrite.
		$this->save_snapshot(
			$menu_id,
			__( 'Auto-snapshot (before restore)', 'swift-menu-duplicator' )
		);

		// Remove the menu's current items.
		$current_items = wp_get_nav_menu_items( $menu_id, array( 'post_status' => 'publish,draft' ) );

		if ( is_array( $current_items ) ) {
			foreach ( $current_items as $current_item ) {
				wp_delete_post( $current_item->ID, true );
			}
		}

		// Re-insert the snapshot's items into the same menu term.
		$importer = new Menu_Importer();
		$id_map   = $importer->import_items( $snapshot['data'], $menu_id );

		/**
		 * Fires after a menu has been rolled back to a snapshot.
		 *
		 * @since 1.0.3
		 *
		 * @param int            $menu_id     Term ID of the restored menu.
		 * @param string         $snapshot_id UUID of the restored snapshot.
		 * @param array<int,int> $id_map      Map of snapshot item IDs to new item IDs.
		 */
		do_action( 'swift_menu_duplicator_after_restore_snapshot', $menu_id, $snapshot_id, $id_map );

		return count( $id_map );
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
		$deleted = false;

		foreach ( (array) get_term_meta( $menu_id, self::SNAPSHOT_META_KEY, false ) as $snapshot ) {
			if ( is_array( $snapshot ) && isset( $snapshot['id'] ) && $snapshot['id'] === $snapshot_id ) {
				delete_term_meta( $menu_id, self::SNAPSHOT_META_KEY, $snapshot );
				$deleted = true;
			}
		}

		// Also handle a stack still stored in the pre-1.0.4 single row.
		$legacy = get_term_meta( $menu_id, self::LEGACY_SNAPSHOT_META_KEY, true );

		if ( is_array( $legacy ) ) {
			$filtered = array_values(
				array_filter(
					$legacy,
					static function ( array $snapshot ) use ( $snapshot_id ): bool {
						return $snapshot['id'] !== $snapshot_id;
					}
				)
			);

			if ( count( $filtered ) !== count( $legacy ) ) {
				update_term_meta( $menu_id, self::LEGACY_SNAPSHOT_META_KEY, $filtered );
				$deleted = true;
			}
		}

		return $deleted;
	}

	// -----------------------------------------------------------------------
	// Private helpers.
	// -----------------------------------------------------------------------

	/**
	 * Duplicates a single nav_menu_item post.
	 *
	 * Delegates the write to core's wp_update_nav_menu_item() rather than
	 * inserting the post by hand. Core owns the meta contract for menu items —
	 * it normalises `_menu_item_object_id` for custom links, clears
	 * `_menu_item_orphaned`, and fires `wp_add_nav_menu_item` /
	 * `wp_update_nav_menu_item`, which is how WPML, Polylang, caches, and
	 * mega-menu plugins learn that an item exists. Writing the postmeta
	 * directly skipped all of that.
	 *
	 * Fields map to core's arg names: the description lives in post_content and
	 * the title attribute in post_excerpt.
	 *
	 * @param WP_Post $item        Original menu item post object.
	 * @param int     $new_menu_id Term ID of the destination menu.
	 *
	 * @return int|WP_Error New post ID, or WP_Error on failure.
	 */
	private function duplicate_menu_item( WP_Post $item, int $new_menu_id ) {
		$classes = get_post_meta( $item->ID, '_menu_item_classes', true );

		$new_item_id = wp_update_nav_menu_item(
			$new_menu_id,
			0,
			array(
				'menu-item-object-id'   => (int) get_post_meta( $item->ID, '_menu_item_object_id', true ),
				'menu-item-object'      => (string) get_post_meta( $item->ID, '_menu_item_object', true ),
				// Parent references are remapped by the caller once every item exists.
				'menu-item-parent-id'   => 0,
				'menu-item-position'    => (int) $item->menu_order,
				'menu-item-type'        => (string) get_post_meta( $item->ID, '_menu_item_type', true ),
				'menu-item-title'       => $item->post_title,
				'menu-item-url'         => (string) get_post_meta( $item->ID, '_menu_item_url', true ),
				'menu-item-description' => $item->post_content,
				'menu-item-attr-title'  => $item->post_excerpt,
				'menu-item-target'      => (string) get_post_meta( $item->ID, '_menu_item_target', true ),
				'menu-item-classes'     => is_array( $classes ) ? implode( ' ', $classes ) : (string) $classes,
				'menu-item-xfn'         => (string) get_post_meta( $item->ID, '_menu_item_xfn', true ),
				'menu-item-status'      => $item->post_status,
			)
		);

		if ( is_wp_error( $new_item_id ) ) {
			return $new_item_id;
		}

		$this->copy_item_postmeta( $item->ID, $new_item_id );

		/**
		 * Fires after a single nav_menu_item has been duplicated.
		 *
		 * @since 1.0.0
		 *
		 * @param int      $old_id      Original item post ID.
		 * @param int      $new_item_id New item post ID.
		 * @param WP_Post $item        Original item post object.
		 */
		do_action( 'swift_menu_duplicator_after_duplicate_menu_item', $item->ID, $new_item_id, $item );

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
		$new_item_id = $this->duplicate_menu_item( $item, $menu_id );

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
	 * Copies any non-core item postmeta from source to destination.
	 *
	 * The keys in self::CORE_META_KEYS are written by wp_update_nav_menu_item()
	 * and are deliberately skipped: core normalises several of them (a custom
	 * link's `_menu_item_object_id` must point at the item itself), so copying
	 * the source values over the top would reintroduce the very drift this
	 * method used to cause. Anything a third party adds through the
	 * `swift_menu_duplicator_item_meta_keys` filter is still copied verbatim.
	 *
	 * @param int $source_id Source nav_menu_item post ID.
	 * @param int $dest_id   Destination nav_menu_item post ID.
	 *
	 * @return void
	 */
	private function copy_item_postmeta( int $source_id, int $dest_id ): void {
		$extra_keys = array_diff( $this->get_meta_keys(), self::CORE_META_KEYS );

		foreach ( $extra_keys as $key ) {
			$value = get_post_meta( $source_id, $key, true );

			if ( '' === $value || false === $value ) {
				continue;
			}

			update_post_meta( $dest_id, $key, $value );
		}
	}

	/**
	 * Describes what a menu item points at, in site-independent terms.
	 *
	 * A `post_type` or `taxonomy` item stores only a local object ID, which
	 * addresses different content (or nothing) on another site. Recording the
	 * slug and the resolved URL lets an import re-find the object by slug and,
	 * failing that, degrade to a custom link that still goes somewhere sensible.
	 *
	 * @param WP_Post             $item Menu item post object.
	 * @param array<string,mixed> $meta Item meta already collected for export.
	 *
	 * @return array<string,string> Extra export fields; empty for custom links.
	 */
	private function describe_item_target( WP_Post $item, array $meta ): array {
		$type      = $meta['_menu_item_type'] ?? '';
		$object    = $meta['_menu_item_object'] ?? '';
		$object_id = (int) ( $meta['_menu_item_object_id'] ?? 0 );

		if ( $object_id <= 0 || '' === $object ) {
			return array();
		}

		if ( 'post_type' === $type ) {
			$post = get_post( $object_id );

			if ( ! $post instanceof WP_Post ) {
				return array();
			}

			return array(
				'object_slug' => $post->post_name,
				'object_url'  => (string) get_permalink( $post ),
			);
		}

		if ( 'taxonomy' === $type ) {
			$term = get_term( $object_id, $object );

			if ( is_wp_error( $term ) || ! $term instanceof WP_Term ) {
				return array();
			}

			$link = get_term_link( $term );

			return array(
				'object_slug' => $term->slug,
				'object_url'  => is_wp_error( $link ) ? '' : (string) $link,
			);
		}

		return array();
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
		 * @since 1.0.0
		 *
		 * @param string[] $keys Default meta keys.
		 */
		return (array) apply_filters(
			'swift_menu_duplicator_item_meta_keys',
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
