<?php
/**
 * Test suite for Menu_Admin class.
 *
 * @package SwiftMenuDuplicator
 */

namespace SwiftMenuDuplicator\Test\Admin;

use SwiftMenuDuplicator\Admin\Menu_Admin;
use SwiftMenuDuplicator\Core\Menu_Duplicator;
use SwiftMenuDuplicator\Test\SwiftMenuDuplicatorTestCase;

/**
 * Tests the non-AJAX surface of Menu_Admin: hook registration, asset
 * enqueuing, and the auto-snapshot hook.
 *
 * These run against the real WordPress test environment — core functions are
 * never stubbed, so what the tests assert is what WordPress actually does.
 * AJAX handlers live in Menu_Admin_Ajax_Test, which needs the AJAX test case.
 */
class Menu_Admin_Test extends SwiftMenuDuplicatorTestCase {

	private Menu_Admin $admin;

	/**
	 * Setup test environment.
	 */
	public function set_up() {
		parent::set_up();

		$this->admin = new Menu_Admin();
	}

	/**
	 * Clean up test data.
	 */
	public function tear_down() {
		$this->delete_all_menus();

		parent::tear_down();
	}

	// -----------------------------------------------------------------------
	// Hook registration.
	// -----------------------------------------------------------------------

	/**
	 * @covers Menu_Admin::register_hooks
	 */
	public function test_register_hooks_adds_actions(): void {
		$this->admin->register_hooks();

		$this->assertNotFalse( has_action( 'admin_enqueue_scripts', array( $this->admin, 'enqueue_scripts' ) ) );
		$this->assertNotFalse( has_action( 'wp_ajax_swmd_duplicate_menu', array( $this->admin, 'handle_ajax_duplicate_menu' ) ) );
		$this->assertNotFalse( has_action( 'wp_ajax_swmd_export_menu', array( $this->admin, 'handle_ajax_export_menu' ) ) );
		$this->assertNotFalse( has_action( 'wp_ajax_swmd_duplicate_item', array( $this->admin, 'handle_ajax_duplicate_item' ) ) );
		$this->assertNotFalse( has_action( 'wp_ajax_swmd_save_snapshot', array( $this->admin, 'handle_ajax_save_snapshot' ) ) );
		$this->assertNotFalse( has_action( 'wp_ajax_swmd_get_snapshots', array( $this->admin, 'handle_ajax_get_snapshots' ) ) );
		$this->assertNotFalse( has_action( 'wp_ajax_swmd_restore_snapshot', array( $this->admin, 'handle_ajax_restore_snapshot' ) ) );
		$this->assertNotFalse( has_action( 'wp_ajax_swmd_delete_snapshot', array( $this->admin, 'handle_ajax_delete_snapshot' ) ) );
		$this->assertNotFalse( has_action( 'wp_update_nav_menu', array( $this->admin, 'auto_snapshot_on_save' ) ) );
	}

	// -----------------------------------------------------------------------
	// Asset enqueuing.
	// -----------------------------------------------------------------------

	/**
	 * @covers Menu_Admin::enqueue_scripts
	 */
	public function test_enqueue_scripts_skips_other_admin_pages(): void {
		$this->admin->enqueue_scripts( 'index.php' );

		$this->assertFalse( wp_script_is( 'swmd-admin', 'enqueued' ) );
		$this->assertFalse( wp_style_is( 'swmd-admin', 'enqueued' ) );
	}

	/**
	 * @covers Menu_Admin::enqueue_scripts
	 */
	public function test_enqueue_scripts_enqueues_on_nav_menus(): void {
		$this->admin->enqueue_scripts( 'nav-menus.php' );

		$this->assertTrue( wp_script_is( 'swmd-admin', 'enqueued' ) );
		$this->assertTrue( wp_style_is( 'swmd-admin', 'enqueued' ) );
	}

	/**
	 * @covers Menu_Admin::enqueue_scripts
	 */
	public function test_enqueue_scripts_localizes_expected_keys(): void {
		$this->admin->enqueue_scripts( 'nav-menus.php' );

		$data = wp_scripts()->get_data( 'swmd-admin', 'data' );

		$this->assertIsString( $data );

		foreach ( array( 'ajaxUrl', 'nonce', 'buttonLabel', 'restoreLabel', 'confirmRestoreText' ) as $key ) {
			$this->assertStringContainsString( $key, $data );
		}
	}

	/**
	 * @covers Menu_Admin::enqueue_scripts
	 */
	public function test_enqueue_scripts_localizes_accessible_names(): void {
		$this->admin->enqueue_scripts( 'nav-menus.php' );

		$data = wp_scripts()->get_data( 'swmd-admin', 'data' );

		// Accessible names must be translatable, not hardcoded in the script.
		$this->assertStringContainsString( 'deleteSnapshotLabel', $data );
		$this->assertStringContainsString( 'closeLabel', $data );
	}

	/**
	 * @covers Menu_Admin::enqueue_scripts
	 */
	public function test_enqueue_scripts_depends_on_wp_a11y(): void {
		$this->admin->enqueue_scripts( 'nav-menus.php' );

		// wp.a11y.speak() announces toasts to screen readers.
		$this->assertContains( 'wp-a11y', wp_scripts()->registered['swmd-admin']->deps );
	}

	// -----------------------------------------------------------------------
	// Menus table.
	// -----------------------------------------------------------------------

	/**
	 * @covers \SwiftMenuDuplicator\Admin\Menu_Table::get_columns
	 */
	public function test_table_exposes_the_expected_columns(): void {
		$columns = ( new \SwiftMenuDuplicator\Admin\Menu_Table() )->get_columns();

		foreach ( array( 'cb', 'name', 'slug', 'description', 'item_count', 'locations', 'snapshots', 'created' ) as $key ) {
			$this->assertArrayHasKey( $key, $columns );
		}
	}

	/**
	 * @covers \SwiftMenuDuplicator\Admin\Menu_Table::default_hidden_columns
	 */
	public function test_verbose_columns_are_hidden_by_default(): void {
		$hidden = \SwiftMenuDuplicator\Admin\Menu_Table::default_hidden_columns();

		$this->assertContains( 'slug', $hidden );
		$this->assertContains( 'description', $hidden );
		$this->assertNotContains( 'name', $hidden );
	}

	/**
	 * @covers \SwiftMenuDuplicator\Admin\Menu_Table::prepare_items
	 */
	public function test_table_sorts_by_item_count(): void {
		$this->create_menu_with_items( 'Small Menu', 1 );
		$this->create_menu_with_items( 'Large Menu', 3 );

		$_GET['orderby'] = 'item_count';
		$_GET['order']   = 'desc';

		$table = new \SwiftMenuDuplicator\Admin\Menu_Table();
		$table->prepare_items();

		unset( $_GET['orderby'], $_GET['order'] );

		$this->assertSame( 'Large Menu', $table->items[0]->name );
	}

	// -----------------------------------------------------------------------
	// Auto-snapshot.
	// -----------------------------------------------------------------------

	/**
	 * @covers Menu_Admin::auto_snapshot_on_save
	 */
	public function test_auto_snapshot_on_save_saves_snapshot(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$menu_id = $this->create_menu_with_items( 'Snapshot Test Menu', 1 );

		// The menu fixture itself may have triggered the hook; start from a
		// known baseline so the assertion measures this call only.
		delete_term_meta( $menu_id, '_swmd_snapshot' );
		delete_term_meta( $menu_id, '_swmd_snapshots' );

		$this->admin->auto_snapshot_on_save( $menu_id );

		$snapshots = ( new Menu_Duplicator() )->get_snapshots( $menu_id );

		$this->assertCount( 1, $snapshots );
		$this->assertSame( 1, count( $snapshots[0]['data']['items'] ) );
	}

	/**
	 * @covers Menu_Admin::auto_snapshot_on_save
	 */
	public function test_auto_snapshot_on_save_skips_without_capability(): void {
		$menu_id = $this->create_menu_with_items( 'Capability Test Menu', 1 );

		delete_term_meta( $menu_id, '_swmd_snapshot' );
		delete_term_meta( $menu_id, '_swmd_snapshots' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->admin->auto_snapshot_on_save( $menu_id );

		$this->assertSame( array(), ( new Menu_Duplicator() )->get_snapshots( $menu_id ) );
	}

	/**
	 * @covers Menu_Admin::auto_snapshot_on_save
	 */
	public function test_auto_snapshot_on_save_skips_empty_menu(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$menu_id = wp_create_nav_menu( 'Empty Auto Snapshot Menu' );

		$this->admin->auto_snapshot_on_save( $menu_id );

		$this->assertSame( array(), ( new Menu_Duplicator() )->get_snapshots( $menu_id ) );
	}
}
