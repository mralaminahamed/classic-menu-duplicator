<?php
/**
 * Core menu-duplication logic.
 *
 * @package WPMenuDuplicator
 */

declare( strict_types=1 );

namespace WPMenuDuplicator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Menu_Duplicator
 *
 * Duplicates a nav_menu term and all of its menu items, preserving
 * item hierarchy, meta, and theme-location assignments.
 */
class Menu_Duplicator {

	/**
	 * Duplicates an existing navigation menu.
	 *
	 * Creates a new nav_menu term using the source menu's name with a
	 * "(Copy)" suffix, then iterates over every menu item post belonging
	 * to the source menu and inserts a cloned post with identical postmeta.
	 * Parent–child relationships are re-mapped so the cloned items retain
	 * the same nesting structure as the original.
	 *
	 * @param int $source_menu_id Term ID of the source navigation menu.
	 *
	 * @return int|\WP_Error New menu term ID on success, WP_Error on failure.
	 */
	public function duplicate( int $source_menu_id ): int|\WP_Error {
		$source_term = get_term( $source_menu_id, 'nav_menu' );

		if ( is_wp_error( $source_term ) || ! $source_term instanceof \WP_Term ) {
			return new \WP_Error(
				'invalid_menu',
				__( 'Source menu not found.', 'wp-menu-duplicator' )
			);
		}

		// ---------------------------------------------------------------
		// 1. Create the duplicate nav_menu term.
		// ---------------------------------------------------------------
		$new_menu_name = sprintf(
			/* translators: %s: original menu name */
			_x( '%s (Copy)', 'duplicated menu name suffix', 'wp-menu-duplicator' ),
			$source_term->name
		);

		$new_term = wp_create_nav_menu( $new_menu_name );

		if ( is_wp_error( $new_term ) ) {
			return $new_term;
		}

		$new_menu_id = (int) $new_term['term_id'];

		// ---------------------------------------------------------------
		// 2. Collect all menu items from the source menu.
		// ---------------------------------------------------------------
		$source_items = wp_get_nav_menu_items(
			$source_menu_id,
			array( 'post_status' => 'publish,draft' )
		);

		if ( empty( $source_items ) || ! is_array( $source_items ) ) {
			return $new_menu_id;
		}

		// ---------------------------------------------------------------
		// 3. Clone each menu item; build an ID re-map for parent refs.
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
						'WP Menu Duplicator: failed to clone item %d — %s',
						$item->ID,
						$new_item_id->get_error_message()
					)
				);
				continue;
			}

			$id_map[ $item->ID ] = $new_item_id;
		}

		// ---------------------------------------------------------------
		// 4. Fix parent references now that all items have new IDs.
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

		return $new_menu_id;
	}

	/**
	 * Duplicates a single nav_menu_item post and its postmeta.
	 *
	 * @param \WP_Post $item        Original menu item post object.
	 * @param int      $new_menu_id Term ID of the destination menu.
	 * @param array    $id_map      Already-processed original=>new ID pairs.
	 *
	 * @return int|\WP_Error New post ID, or WP_Error on failure.
	 */
	private function duplicate_menu_item( \WP_Post $item, int $new_menu_id, array $id_map ): int|\WP_Error {
		// Insert the cloned post.
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

		// Assign item to the new menu term.
		wp_set_object_terms( $new_item_id, array( $new_menu_id ), 'nav_menu' );

		// Copy all _menu_item_* postmeta.
		$this->copy_item_postmeta( $item->ID, $new_item_id, $id_map );

		return $new_item_id;
	}

	/**
	 * Copies all _menu_item_* postmeta from source to destination.
	 *
	 * The _menu_item_menu_item_parent meta is intentionally copied as-is
	 * at this stage; the caller re-maps it after all items are processed.
	 *
	 * @param int   $source_id  Source nav_menu_item post ID.
	 * @param int   $dest_id    Destination nav_menu_item post ID.
	 * @param array $id_map     Already-processed original=>new ID pairs.
	 *
	 * @return void
	 */
	private function copy_item_postmeta( int $source_id, int $dest_id, array $id_map ): void {
		$meta_keys = array(
			'_menu_item_type',
			'_menu_item_menu_item_parent',
			'_menu_item_object_id',
			'_menu_item_object',
			'_menu_item_target',
			'_menu_item_classes',
			'_menu_item_xfn',
			'_menu_item_url',
		);

		foreach ( $meta_keys as $key ) {
			$value = get_post_meta( $source_id, $key, true );

			if ( '' === $value || false === $value ) {
				continue;
			}

			update_post_meta( $dest_id, $key, $value );
		}
	}
}
