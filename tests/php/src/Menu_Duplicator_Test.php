<?php
/**
 * Test suite for Menu_Duplicator class.
 *
 * @package ClassicMenuDuplicator
 */

namespace ClassicMenuDuplicator\Test;

use ClassicMenuDuplicator\Menu_Duplicator;
use Brain\Monkey\Functions;

/**
 * Comprehensive test suite for Menu_Duplicator class.
 *
 * Tests all public methods with various scenarios including:
 * - Menu duplication with items
 * - Menu duplication without items
 * - Nested menu duplication (parent-child relationships)
 * - Invalid menu ID handling
 * - Error handling
 */
class Menu_Duplicator_Test extends ClassicMenuDuplicatorTestCase {

	private Menu_Duplicator $duplicator;

	/**
	 * Setup test environment.
	 */
	public function set_up() {
		parent::set_up();

		$this->duplicator = new Menu_Duplicator();
	}

	/**
	 * Clean up test data.
	 */
	public function tear_down() {
		global $wpdb;

		$wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_type = 'nav_menu_item'" );
		$wpdb->query( "DELETE FROM {$wpdb->term_taxonomy} WHERE taxonomy = 'nav_menu'" );
		$wpdb->query( "DELETE FROM {$wpdb->terms} WHERE 1=1" );
		$wpdb->query( "DELETE FROM {$wpdb->termmeta} WHERE 1=1" );

		parent::tear_down();
	}

	/**
	 * Test duplication with invalid menu ID returns WP_Error.
	 */
	public function test_duplicate_invalid_menu_id_returns_error(): void {
		$result = $this->duplicator->duplicate( 0 );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'invalid_menu', $result->get_error_code() );
	}

	/**
	 * Test duplication with non-existent menu ID returns WP_Error.
	 */
	public function test_duplicate_non_existent_menu_returns_error(): void {
		$result = $this->duplicator->duplicate( 99999 );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'invalid_menu', $result->get_error_code() );
	}

	/**
	 * Test duplication of empty menu (no items) succeeds.
	 */
	public function test_duplicate_empty_menu_succeeds(): void {
		$menu_id = wp_create_nav_menu( 'Empty Menu' );

		$this->assertIsInt( $menu_id );
		$this->assertGreaterThan( 0, $menu_id );

		$result = $this->duplicator->duplicate( $menu_id );

		$this->assertIsInt( $result );
		$this->assertGreaterThan( 0, $result );

		$new_menu = get_term( $result, 'nav_menu' );
		$this->assertInstanceOf( \WP_Term::class, $new_menu );
		$this->assertStringEndsWith( '(Copy)', $new_menu->name );
	}

	/**
	 * Test duplication of menu with items succeeds.
	 */
	public function test_duplicate_menu_with_items_succeeds(): void {
		$menu_id = $this->create_menu_with_items( 'Test Menu', 3 );

		$this->assertIsInt( $menu_id );
		$this->assertGreaterThan( 0, $menu_id );

		$source_items = wp_get_nav_menu_items( $menu_id );
		$this->assertCount( 3, $source_items );

		$result = $this->duplicator->duplicate( $menu_id );

		$this->assertIsInt( $result );
		$this->assertGreaterThan( 0, $result );

		$new_menu = get_term( $result, 'nav_menu' );
		$this->assertInstanceOf( \WP_Term::class, $new_menu );
		$this->assertStringEndsWith( '(Copy)', $new_menu->name );

		$new_items = wp_get_nav_menu_items( $result );
		$this->assertCount( 3, $new_items );
	}

	/**
	 * Test duplication preserves item metadata.
	 */
	public function test_duplicate_preserves_item_metadata(): void {
		$menu_id = $this->create_menu_with_items( 'Menu with Meta', 1 );

		$source_items = wp_get_nav_menu_items( $menu_id );
		$source_item = $source_items[0];

		$result = $this->duplicator->duplicate( $menu_id );

		$new_items = wp_get_nav_menu_items( $result );
		$new_item = $new_items[0];

		$this->assertEquals( $source_item->post_title, $new_item->post_title );
		$this->assertEquals( $source_item->menu_order, $new_item->menu_order );

		$source_meta = get_post_meta( $source_item->ID );
		$new_meta = get_post_meta( $new_item->ID );

		$this->assertEquals( $source_meta['_menu_item_type'][0], $new_meta['_menu_item_type'][0] );
		$this->assertEquals( $source_meta['_menu_item_object'][0], $new_meta['_menu_item_object'][0] );
	}

	/**
	 * Test duplication of nested menu preserves parent-child relationships.
	 */
	public function test_duplicate_nested_menu_preserves_hierarchy(): void {
		$menu_id = $this->create_nested_menu( 'Nested Menu' );

		$source_items = wp_get_nav_menu_items( $menu_id );

		$parent_item = null;
		$child_item = null;

		foreach ( $source_items as $item ) {
			if ( 0 === (int) $item->menu_item_parent ) {
				$parent_item = $item;
			} else {
				$child_item = $item;
			}
		}

		$this->assertNotNull( $parent_item );
		$this->assertNotNull( $child_item );

		$result = $this->duplicator->duplicate( $menu_id );

		$new_items = wp_get_nav_menu_items( $result );

		$new_parent = null;
		$new_child = null;

		foreach ( $new_items as $item ) {
			if ( 0 === (int) $item->menu_item_parent ) {
				$new_parent = $item;
			} else {
				$new_child = $item;
			}
		}

		$this->assertNotNull( $new_parent );
		$this->assertNotNull( $new_child );

		$this->assertEquals( $new_parent->ID, (int) $new_child->menu_item_parent );
	}

	/**
	 * Test duplicated menu has correct "(Copy)" suffix.
	 */
	public function test_duplicate_menu_has_copy_suffix(): void {
		$menu_id = $this->create_menu_with_items( 'Original Menu', 1 );

		$result = $this->duplicator->duplicate( $menu_id );

		$new_menu = get_term( $result, 'nav_menu' );

		$this->assertStringEndsWith( '(Copy)', $new_menu->name );
		$this->assertStringStartsWith( 'Original Menu', $new_menu->name );
	}

	/**
	 * Test duplicate menu is assigned to nav_menu taxonomy.
	 */
	public function test_duplicate_menu_is_assigned_to_nav_menu_taxonomy(): void {
		$menu_id = $this->create_menu_with_items( 'Taxonomy Test Menu', 1 );

		$result = $this->duplicator->duplicate( $menu_id );

		$new_items = wp_get_nav_menu_items( $result );

		foreach ( $new_items as $item ) {
			$terms = get_the_terms( $item->ID, 'nav_menu' );
			$this->assertIsArray( $terms );
			$this->assertCount( 1, $terms );
			$this->assertEquals( $result, $terms[0]->term_id );
		}
	}
}
