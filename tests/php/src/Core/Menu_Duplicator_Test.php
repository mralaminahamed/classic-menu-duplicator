<?php
/**
 * Test suite for Menu_Duplicator class — Tier 1 features.
 *
 * @package SwiftMenuDuplicator
 */

namespace SwiftMenuDuplicator\Test\Core;

use SwiftMenuDuplicator\Core\Menu_Duplicator;
use SwiftMenuDuplicator\Test\SwiftMenuDuplicatorTestCase;

/**
 * Comprehensive test suite for Menu_Duplicator class.
 *
 * Covers:
 * - Menu duplication (existing behaviour + custom name)
 * - Item-level duplication with hierarchy preservation
 * - JSON export payload structure
 * - Snapshot CRUD lifecycle
 */
class Menu_Duplicator_Test extends SwiftMenuDuplicatorTestCase {

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

	// -----------------------------------------------------------------------
	// Menu duplication — core behaviour (regression).
	// -----------------------------------------------------------------------

	/**
	 * @covers Menu_Duplicator::duplicate
	 */
	public function test_duplicate_invalid_menu_id_returns_error(): void {
		$result = $this->duplicator->duplicate( 0 );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'invalid_menu', $result->get_error_code() );
	}

	/**
	 * @covers Menu_Duplicator::duplicate
	 */
	public function test_duplicate_non_existent_menu_returns_error(): void {
		$result = $this->duplicator->duplicate( 99999 );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'invalid_menu', $result->get_error_code() );
	}

	/**
	 * @covers Menu_Duplicator::duplicate
	 */
	public function test_duplicate_empty_menu_succeeds(): void {
		$menu_id = wp_create_nav_menu( 'Empty Menu' );

		$result = $this->duplicator->duplicate( $menu_id );

		$this->assertIsInt( $result );
		$this->assertGreaterThan( 0, $result );
	}

	/**
	 * @covers Menu_Duplicator::duplicate
	 */
	public function test_duplicate_uses_copy_suffix_when_no_name_supplied(): void {
		$menu_id = $this->create_menu_with_items( 'Original', 1 );

		$result   = $this->duplicator->duplicate( $menu_id );
		$new_term = get_term( $result, 'nav_menu' );

		$this->assertStringContainsString( '(Copy)', $new_term->name );
	}

	// -----------------------------------------------------------------------
	// Feature 1 — Custom name on duplication.
	// -----------------------------------------------------------------------

	/**
	 * @covers Menu_Duplicator::duplicate
	 */
	public function test_duplicate_uses_supplied_custom_name(): void {
		$menu_id = $this->create_menu_with_items( 'Original Menu', 1 );

		$result   = $this->duplicator->duplicate( $menu_id, 'My Custom Name' );
		$new_term = get_term( $result, 'nav_menu' );

		$this->assertInstanceOf( \WP_Term::class, $new_term );
		$this->assertEquals( 'My Custom Name', $new_term->name );
	}

	/**
	 * @covers Menu_Duplicator::duplicate
	 */
	public function test_duplicate_trims_custom_name_and_falls_back_when_empty(): void {
		$menu_id = $this->create_menu_with_items( 'Original', 1 );

		$result   = $this->duplicator->duplicate( $menu_id, '   ' );
		$new_term = get_term( $result, 'nav_menu' );

		$this->assertStringContainsString( '(Copy)', $new_term->name );
	}

	/**
	 * @covers Menu_Duplicator::duplicate
	 */
	public function test_swmd_new_menu_name_filter_is_applied(): void {
		$menu_id = $this->create_menu_with_items( 'Menu', 1 );

		add_filter( 'swift_menu_duplicator_new_menu_name', static fn () => 'Filtered Name', 10, 1 );

		$result   = $this->duplicator->duplicate( $menu_id );
		$new_term = get_term( $result, 'nav_menu' );

		remove_all_filters( 'swift_menu_duplicator_new_menu_name' );

		$this->assertEquals( 'Filtered Name', $new_term->name );
	}

	// -----------------------------------------------------------------------
	// Feature 2 — Item-level duplication.
	// -----------------------------------------------------------------------

	/**
	 * @covers Menu_Duplicator::duplicate_item
	 */
	public function test_duplicate_item_invalid_id_returns_error(): void {
		$menu_id = $this->create_menu_with_items( 'Menu', 1 );

		$result = $this->duplicator->duplicate_item( 0, $menu_id );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'invalid_item', $result->get_error_code() );
	}

	/**
	 * @covers Menu_Duplicator::duplicate_item
	 */
	public function test_duplicate_item_creates_new_item_in_same_menu(): void {
		$menu_id      = $this->create_menu_with_items( 'Menu', 3 );
		$source_items = wp_get_nav_menu_items( $menu_id );
		$item_id      = $source_items[0]->ID;

		$new_id = $this->duplicator->duplicate_item( $item_id, $menu_id );

		$this->assertIsInt( $new_id );
		$this->assertGreaterThan( 0, $new_id );

		$updated_items = wp_get_nav_menu_items( $menu_id );
		$this->assertCount( 4, $updated_items );
	}

	/**
	 * @covers Menu_Duplicator::duplicate_item
	 */
	public function test_duplicate_item_preserves_child_items(): void {
		$menu_id = $this->create_nested_menu( 'Nested' );

		$source_items = wp_get_nav_menu_items( $menu_id );
		$parent_item  = null;

		foreach ( $source_items as $item ) {
			if ( 0 === (int) $item->menu_item_parent ) {
				$parent_item = $item;
				break;
			}
		}

		$this->assertNotNull( $parent_item );

		$new_parent_id = $this->duplicator->duplicate_item( $parent_item->ID, $menu_id );

		$all_items = wp_get_nav_menu_items( $menu_id );

		// Should now have: 1 original parent + 1 original child + 1 new parent + 1 new child.
		$this->assertCount( 4, $all_items );

		// The new child's parent should reference the new parent, not the original.
		$new_children = array_filter(
			$all_items,
			static fn ( $i ) => (int) $i->menu_item_parent === $new_parent_id
		);

		$this->assertCount( 1, $new_children );
	}

	/**
	 * @covers Menu_Duplicator::duplicate_item
	 */
	public function test_swmd_after_duplicate_item_action_fires(): void {
		$menu_id = $this->create_menu_with_items( 'Menu', 1 );
		$items   = wp_get_nav_menu_items( $menu_id );
		$item_id = $items[0]->ID;

		$fired = false;
		add_action( 'swift_menu_duplicator_after_duplicate_item', static function () use ( &$fired ) {
			$fired = true;
		} );

		$this->duplicator->duplicate_item( $item_id, $menu_id );

		remove_all_actions( 'swift_menu_duplicator_after_duplicate_item' );

		$this->assertTrue( $fired );
	}

	// -----------------------------------------------------------------------
	// Feature 3 — Snapshot lifecycle.
	// -----------------------------------------------------------------------

	/**
	 * @covers Menu_Duplicator::save_snapshot
	 * @covers Menu_Duplicator::get_snapshots
	 */
	public function test_save_snapshot_returns_true_and_snapshot_is_retrievable(): void {
		$menu_id = $this->create_menu_with_items( 'Menu', 2 );

		$saved = $this->duplicator->save_snapshot( $menu_id, 'Test Snapshot' );

		$this->assertTrue( $saved );

		$snapshots = $this->duplicator->get_snapshots( $menu_id );
		$this->assertCount( 1, $snapshots );
		$this->assertEquals( 'Test Snapshot', $snapshots[0]['label'] );
	}

	/**
	 * @covers Menu_Duplicator::save_snapshot
	 */
	public function test_snapshots_are_stored_newest_first(): void {
		$menu_id = $this->create_menu_with_items( 'Menu', 1 );

		$this->duplicator->save_snapshot( $menu_id, 'First' );
		$this->duplicator->save_snapshot( $menu_id, 'Second' );

		$snapshots = $this->duplicator->get_snapshots( $menu_id );

		$this->assertEquals( 'Second', $snapshots[0]['label'] );
		$this->assertEquals( 'First', $snapshots[1]['label'] );
	}

	/**
	 * @covers Menu_Duplicator::save_snapshot
	 */
	public function test_snapshot_limit_is_enforced(): void {
		$menu_id = $this->create_menu_with_items( 'Menu', 1 );

		add_filter( 'swift_menu_duplicator_snapshot_limit', static fn () => 3 );

		for ( $i = 1; $i <= 5; ++$i ) {
			$this->duplicator->save_snapshot( $menu_id, "Snap {$i}" );
		}

		remove_all_filters( 'swift_menu_duplicator_snapshot_limit' );

		$snapshots = $this->duplicator->get_snapshots( $menu_id );

		$this->assertCount( 3, $snapshots );
		$this->assertEquals( 'Snap 5', $snapshots[0]['label'] ); // Newest is first.
	}

	/**
	 * @covers Menu_Duplicator::delete_snapshot
	 */
	public function test_delete_snapshot_removes_correct_entry(): void {
		$menu_id = $this->create_menu_with_items( 'Menu', 1 );

		$this->duplicator->save_snapshot( $menu_id, 'Keep' );
		$this->duplicator->save_snapshot( $menu_id, 'Delete Me' );

		$snapshots   = $this->duplicator->get_snapshots( $menu_id );
		$delete_uuid = $snapshots[0]['id']; // Newest (Delete Me).

		$deleted = $this->duplicator->delete_snapshot( $menu_id, $delete_uuid );

		$this->assertTrue( $deleted );

		$remaining = $this->duplicator->get_snapshots( $menu_id );

		$this->assertCount( 1, $remaining );
		$this->assertEquals( 'Keep', $remaining[0]['label'] );
	}

	/**
	 * @covers Menu_Duplicator::delete_snapshot
	 */
	public function test_delete_nonexistent_snapshot_returns_false(): void {
		$menu_id = $this->create_menu_with_items( 'Menu', 1 );

		$deleted = $this->duplicator->delete_snapshot( $menu_id, 'non-existent-uuid' );

		$this->assertFalse( $deleted );
	}

	// -----------------------------------------------------------------------
	// Feature 4 — JSON export.
	// -----------------------------------------------------------------------

	/**
	 * @covers Menu_Duplicator::export
	 */
	public function test_export_invalid_menu_returns_error(): void {
		$result = $this->duplicator->export( 99999 );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'invalid_menu', $result->get_error_code() );
	}

	/**
	 * @covers Menu_Duplicator::export
	 */
	public function test_export_payload_has_required_keys(): void {
		$menu_id = $this->create_menu_with_items( 'Export Menu', 2 );

		$payload = $this->duplicator->export( $menu_id );

		$this->assertIsArray( $payload );
		$this->assertArrayHasKey( 'version', $payload );
		$this->assertArrayHasKey( 'exported', $payload );
		$this->assertArrayHasKey( 'site_url', $payload );
		$this->assertArrayHasKey( 'menu', $payload );
		$this->assertArrayHasKey( 'items', $payload );
	}

	/**
	 * @covers Menu_Duplicator::export
	 */
	public function test_export_menu_section_contains_name_and_slug(): void {
		$menu_id = $this->create_menu_with_items( 'Export Test', 1 );

		$payload = $this->duplicator->export( $menu_id );

		$this->assertArrayHasKey( 'name', $payload['menu'] );
		$this->assertArrayHasKey( 'slug', $payload['menu'] );
		$this->assertEquals( 'Export Test', $payload['menu']['name'] );
	}

	/**
	 * @covers Menu_Duplicator::export
	 */
	public function test_export_items_count_matches_source(): void {
		$menu_id = $this->create_menu_with_items( 'Three Items', 3 );

		$payload = $this->duplicator->export( $menu_id );

		$this->assertCount( 3, $payload['items'] );
	}

	/**
	 * @covers Menu_Duplicator::export
	 */
	public function test_export_each_item_has_meta_key(): void {
		$menu_id = $this->create_menu_with_items( 'Meta Test', 1 );

		$payload = $this->duplicator->export( $menu_id );

		$this->assertArrayHasKey( 'meta', $payload['items'][0] );
	}

	/**
	 * @covers Menu_Duplicator::export
	 */
	public function test_export_payload_is_json_encodable(): void {
		$menu_id = $this->create_menu_with_items( 'JSON Menu', 2 );

		$payload = $this->duplicator->export( $menu_id );
		$json    = wp_json_encode( $payload );

		$this->assertIsString( $json );
		$this->assertJson( $json );
	}

	/**
	 * @covers Menu_Duplicator::export
	 */
	public function test_swmd_export_payload_filter_is_applied(): void {
		$menu_id = $this->create_menu_with_items( 'Filter Test', 1 );

		add_filter(
			'swift_menu_duplicator_export_payload',
			static function ( array $payload ): array {
				$payload['custom_key'] = 'custom_value';
				return $payload;
			}
		);

		$payload = $this->duplicator->export( $menu_id );

		remove_all_filters( 'swift_menu_duplicator_export_payload' );

		$this->assertArrayHasKey( 'custom_key', $payload );
		$this->assertEquals( 'custom_value', $payload['custom_key'] );
	}

	// -----------------------------------------------------------------------
	// Item descriptions (post_content).
	// -----------------------------------------------------------------------

	/**
	 * @covers Menu_Duplicator::duplicate
	 */
	public function test_duplicate_preserves_item_description(): void {
		$menu_id = $this->create_menu_with_items( 'Described Menu', 1 );
		$items   = wp_get_nav_menu_items( $menu_id );

		wp_update_post(
			array(
				'ID'           => $items[0]->ID,
				'post_content' => 'Shown under the item title.',
			)
		);

		$new_menu_id = $this->duplicator->duplicate( $menu_id );
		$new_items   = wp_get_nav_menu_items( $new_menu_id );

		$this->assertSame( 'Shown under the item title.', $new_items[0]->post_content );
	}

	/**
	 * @covers Menu_Duplicator::duplicate_item
	 */
	public function test_duplicate_item_preserves_description(): void {
		$menu_id = $this->create_menu_with_items( 'Item Description Menu', 1 );
		$items   = wp_get_nav_menu_items( $menu_id );

		wp_update_post(
			array(
				'ID'           => $items[0]->ID,
				'post_content' => 'Item level description.',
			)
		);

		$new_item_id = $this->duplicator->duplicate_item( $items[0]->ID, $menu_id );

		$this->assertIsInt( $new_item_id );
		$this->assertSame( 'Item level description.', get_post( $new_item_id )->post_content );
	}

	/**
	 * @covers Menu_Duplicator::export
	 */
	public function test_export_includes_item_description(): void {
		$menu_id = $this->create_menu_with_items( 'Export Description Menu', 1 );
		$items   = wp_get_nav_menu_items( $menu_id );

		wp_update_post(
			array(
				'ID'           => $items[0]->ID,
				'post_content' => 'Exported description.',
			)
		);

		$payload = $this->duplicator->export( $menu_id );

		$this->assertArrayHasKey( 'content', $payload['items'][0] );
		$this->assertSame( 'Exported description.', $payload['items'][0]['content'] );
	}

	// -----------------------------------------------------------------------
	// Snapshot restore.
	// -----------------------------------------------------------------------

	/**
	 * @covers Menu_Duplicator::restore_snapshot
	 */
	public function test_restore_snapshot_puts_back_removed_items(): void {
		$menu_id = $this->create_menu_with_items( 'Restore Source', 3 );

		$this->duplicator->save_snapshot( $menu_id, 'Full menu' );
		$snapshot_id = $this->duplicator->get_snapshots( $menu_id )[0]['id'];

		foreach ( wp_get_nav_menu_items( $menu_id ) as $item ) {
			wp_delete_post( $item->ID, true );
		}

		$restored = $this->duplicator->restore_snapshot( $menu_id, $snapshot_id );

		$this->assertSame( 3, $restored );
		$this->assertCount( 3, wp_get_nav_menu_items( $menu_id ) );
	}

	/**
	 * @covers Menu_Duplicator::restore_snapshot
	 */
	public function test_restore_snapshot_keeps_the_same_menu_term(): void {
		$menu_id = $this->create_menu_with_items( 'Same Term Menu', 2 );

		$this->duplicator->save_snapshot( $menu_id, 'Snapshot' );
		$snapshot_id = $this->duplicator->get_snapshots( $menu_id )[0]['id'];

		$this->duplicator->restore_snapshot( $menu_id, $snapshot_id );

		$this->assertInstanceOf( \WP_Term::class, get_term( $menu_id, 'nav_menu' ) );
		$this->assertSame( 'Same Term Menu', get_term( $menu_id, 'nav_menu' )->name );
	}

	/**
	 * @covers Menu_Duplicator::restore_snapshot
	 */
	public function test_restore_snapshot_preserves_hierarchy(): void {
		$menu_id = $this->create_nested_menu( 'Nested Restore' );

		$this->duplicator->save_snapshot( $menu_id, 'Nested snapshot' );
		$snapshot_id = $this->duplicator->get_snapshots( $menu_id )[0]['id'];

		foreach ( wp_get_nav_menu_items( $menu_id ) as $item ) {
			wp_delete_post( $item->ID, true );
		}

		$this->duplicator->restore_snapshot( $menu_id, $snapshot_id );

		$items    = wp_get_nav_menu_items( $menu_id );
		$children = array_filter( $items, static fn ( $i ) => 0 !== (int) $i->menu_item_parent );

		$this->assertCount( 2, $items );
		$this->assertCount( 1, $children );

		$child       = array_values( $children )[0];
		$parent_ids  = wp_list_pluck( $items, 'ID' );

		$this->assertContains( (int) $child->menu_item_parent, $parent_ids );
	}

	/**
	 * @covers Menu_Duplicator::restore_snapshot
	 */
	public function test_restore_snapshot_saves_a_safety_snapshot_first(): void {
		$menu_id = $this->create_menu_with_items( 'Safety Net Menu', 1 );

		delete_term_meta( $menu_id, '_swmd_snapshots' );

		$this->duplicator->save_snapshot( $menu_id, 'Original' );
		$snapshot_id = $this->duplicator->get_snapshots( $menu_id )[0]['id'];

		$this->duplicator->restore_snapshot( $menu_id, $snapshot_id );

		$snapshots = $this->duplicator->get_snapshots( $menu_id );

		$this->assertCount( 2, $snapshots );
		$this->assertStringContainsString( 'before restore', $snapshots[0]['label'] );
	}

	/**
	 * @covers Menu_Duplicator::restore_snapshot
	 */
	public function test_restore_snapshot_with_unknown_id_returns_error(): void {
		$menu_id = $this->create_menu_with_items( 'Unknown Snapshot', 1 );

		$result = $this->duplicator->restore_snapshot( $menu_id, 'no-such-uuid' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'invalid_snapshot', $result->get_error_code() );
	}

	/**
	 * @covers Menu_Duplicator::restore_snapshot
	 */
	public function test_restore_snapshot_with_invalid_menu_returns_error(): void {
		$result = $this->duplicator->restore_snapshot( 99999, 'any-uuid' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'invalid_menu', $result->get_error_code() );
	}
}
