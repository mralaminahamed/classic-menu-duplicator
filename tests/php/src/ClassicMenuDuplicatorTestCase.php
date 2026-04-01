<?php
/**
 * Abstract base class for Swift Menu Duplicator test cases.
 *
 * @package SwiftMenuDuplicator
 */

namespace SwiftMenuDuplicator\Test;

use Brain\Monkey;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use WP_UnitTestCase;

/**
 * Abstract base class for Swift Menu Duplicator unit test cases.
 *
 * PHPUnit Docs: @see https://docs.phpunit.de/en/9.6/
 * Brain Monkey: @see https://giuseppe-mazzapica.gitbook.io/brain-monkey
 * Mockery: @see http://docs.mockery.io/en/latest/
 */
abstract class SwiftMenuDuplicatorTestCase extends WP_UnitTestCase {
	use MockeryPHPUnitIntegration;

	/**
	 * Setup test environment.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		Monkey\setUp();
	}

	/**
	 * Tear down test environment.
	 *
	 * @return void
	 */
	public function tear_down() {
		Monkey\tearDown();
		parent::tear_down();
	}

	/**
	 * Create a navigation menu with items.
	 *
	 * @param string $name Menu name.
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
				'post_type' => 'page',
				'post_title' => 'Test Page',
			)
		);

		for ( $i = 0; $i < $item_count; ++$i ) {
			$item_id = wp_update_nav_menu_item(
				$menu_id,
				0,
				array(
					'menu-item-title'   => 'Menu Item ' . ( $i + 1 ),
					'menu-item-object'  => 'page',
					'menu-item-object-id' => $page_id,
					'menu-item-type'    => 'post_type',
					'menu-item-status'  => 'publish',
					'menu-item-position' => $i + 1,
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
				'post_type' => 'page',
				'post_title' => 'Parent Page',
			)
		);

		$parent_item_id = wp_update_nav_menu_item(
			$menu_id,
			0,
			array(
				'menu-item-title'   => 'Parent Item',
				'menu-item-object'  => 'page',
				'menu-item-object-id' => $page_id,
				'menu-item-type'    => 'post_type',
				'menu-item-status'  => 'publish',
			)
		);

		wp_update_nav_menu_item(
			$menu_id,
			0,
			array(
				'menu-item-title'       => 'Child Item',
				'menu-item-object'      => 'page',
				'menu-item-object-id'   => $page_id,
				'menu-item-type'        => 'post_type',
				'menu-item-status'      => 'publish',
				'menu-item-parent-id'   => $parent_item_id,
			)
		);

		return $menu_id;
	}
}
