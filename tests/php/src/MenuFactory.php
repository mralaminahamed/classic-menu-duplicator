<?php
/**
 * Shared menu fixtures for Swift Menu Duplicator test cases.
 *
 * @package SwiftMenuDuplicator
 */

namespace SwiftMenuDuplicator\Test;

/**
 * Builds nav_menu fixtures.
 *
 * Lives in a trait rather than the base test case so the AJAX suite — which
 * must extend WP_Ajax_UnitTestCase — can share the same fixtures.
 */
trait MenuFactory {

	/**
	 * Create a navigation menu with items.
	 *
	 * @param string $name       Menu name.
	 * @param int    $item_count Number of items to create.
	 *
	 * @return int Menu term ID.
	 */
	protected function create_menu_with_items( string $name = 'Test Menu', int $item_count = 3 ): int {
		$menu_id = wp_create_nav_menu( $name );

		if ( is_wp_error( $menu_id ) ) {
			return 0;
		}

		$page_id = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Test Page',
			)
		);

		for ( $i = 0; $i < $item_count; ++$i ) {
			wp_update_nav_menu_item(
				$menu_id,
				0,
				array(
					'menu-item-title'     => 'Menu Item ' . ( $i + 1 ),
					'menu-item-object'    => 'page',
					'menu-item-object-id' => $page_id,
					'menu-item-type'      => 'post_type',
					'menu-item-status'    => 'publish',
					'menu-item-position'  => $i + 1,
				)
			);
		}

		return $menu_id;
	}

	/**
	 * Create a nested navigation menu with parent-child items.
	 *
	 * @param string $name Menu name.
	 *
	 * @return int Menu term ID.
	 */
	protected function create_nested_menu( string $name = 'Nested Menu' ): int {
		$menu_id = wp_create_nav_menu( $name );

		if ( is_wp_error( $menu_id ) ) {
			return 0;
		}

		$page_id = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Parent Page',
			)
		);

		$parent_item_id = wp_update_nav_menu_item(
			$menu_id,
			0,
			array(
				'menu-item-title'     => 'Parent Item',
				'menu-item-object'    => 'page',
				'menu-item-object-id' => $page_id,
				'menu-item-type'      => 'post_type',
				'menu-item-status'    => 'publish',
			)
		);

		wp_update_nav_menu_item(
			$menu_id,
			0,
			array(
				'menu-item-title'     => 'Child Item',
				'menu-item-object'    => 'page',
				'menu-item-object-id' => $page_id,
				'menu-item-type'      => 'post_type',
				'menu-item-status'    => 'publish',
				'menu-item-parent-id' => $parent_item_id,
			)
		);

		return $menu_id;
	}

	/**
	 * Create a menu holding a single custom-link item.
	 *
	 * @param string $name Menu name.
	 * @param string $url  Custom link URL.
	 *
	 * @return int Menu term ID.
	 */
	protected function create_custom_link_menu( string $name, string $url ): int {
		$menu_id = wp_create_nav_menu( $name );

		if ( is_wp_error( $menu_id ) ) {
			return 0;
		}

		wp_update_nav_menu_item(
			$menu_id,
			0,
			array(
				'menu-item-title'  => 'Custom Link',
				'menu-item-url'    => $url,
				'menu-item-type'   => 'custom',
				'menu-item-status' => 'publish',
			)
		);

		return $menu_id;
	}

	/**
	 * Remove every menu, menu item, and term meta row created by a test.
	 *
	 * @return void
	 */
	protected function delete_all_menus(): void {
		global $wpdb;

		$wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_type = 'nav_menu_item'" );
		$wpdb->query( "DELETE FROM {$wpdb->term_taxonomy} WHERE taxonomy = 'nav_menu'" );
		$wpdb->query( "DELETE FROM {$wpdb->terms} WHERE 1=1" );
		$wpdb->query( "DELETE FROM {$wpdb->termmeta} WHERE 1=1" );
	}
}
