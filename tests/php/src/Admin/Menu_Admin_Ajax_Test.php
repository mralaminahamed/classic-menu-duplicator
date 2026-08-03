<?php
/**
 * AJAX test suite for Menu_Admin.
 *
 * @package SwiftMenuDuplicator
 */

namespace SwiftMenuDuplicator\Test\Admin;

use SwiftMenuDuplicator\Admin\Menu_Admin;
use SwiftMenuDuplicator\Core\Menu_Duplicator;
use SwiftMenuDuplicator\Test\MenuFactory;
use WP_Ajax_UnitTestCase;

/**
 * Exercises the plugin's wp_ajax_* handlers end to end.
 *
 * Extends WP_Ajax_UnitTestCase so wp_send_json_*() terminates through the AJAX
 * die handler instead of killing the test run; the JSON body is then available
 * in $this->_last_response. Nonces and capabilities are the real ones — nothing
 * is stubbed.
 */
class Menu_Admin_Ajax_Test extends WP_Ajax_UnitTestCase {
	use MenuFactory;

	private Menu_Admin $admin;

	/**
	 * Setup test environment.
	 */
	public function set_up() {
		parent::set_up();

		$this->admin = new Menu_Admin();
		$this->admin->register_hooks();
	}

	/**
	 * Clean up test data.
	 */
	public function tear_down() {
		$_POST = array();

		$this->delete_all_menus();

		parent::tear_down();
	}

	// -----------------------------------------------------------------------
	// Helpers.
	// -----------------------------------------------------------------------

	/**
	 * Runs an AJAX action and returns the decoded JSON response.
	 *
	 * @param string $action AJAX action name (without the wp_ajax_ prefix).
	 *
	 * @return array<string,mixed> Decoded response.
	 */
	private function dispatch( string $action ): array {
		try {
			$this->_handleAjax( $action );
		} catch ( \WPAjaxDieContinueException $e ) {
			unset( $e );
		} catch ( \WPAjaxDieStopException $e ) {
			unset( $e );
		}

		return (array) json_decode( $this->_last_response, true );
	}

	/**
	 * Logs in a user able to manage menus and puts a valid nonce in $_POST.
	 *
	 * @return void
	 */
	private function login_as_menu_editor(): void {
		$this->_setRole( 'administrator' );

		$_POST['nonce'] = wp_create_nonce( 'swmd_menu_actions' );
	}

	// -----------------------------------------------------------------------
	// Nonce / capability gates.
	// -----------------------------------------------------------------------

	/**
	 * @covers Menu_Admin::handle_ajax_duplicate_menu
	 */
	public function test_duplicate_menu_fails_without_nonce(): void {
		$this->_setRole( 'administrator' );

		$_POST = array( 'menu_id' => $this->create_menu_with_items( 'No Nonce Menu', 1 ) );

		$response = $this->dispatch( 'swmd_duplicate_menu' );

		$this->assertFalse( $response['success'] );
	}

	/**
	 * @covers Menu_Admin::handle_ajax_duplicate_menu
	 */
	public function test_duplicate_menu_fails_with_invalid_nonce(): void {
		$this->_setRole( 'administrator' );

		$_POST = array(
			'nonce'   => 'not-a-real-nonce',
			'menu_id' => $this->create_menu_with_items( 'Bad Nonce Menu', 1 ),
		);

		$response = $this->dispatch( 'swmd_duplicate_menu' );

		$this->assertFalse( $response['success'] );
	}

	/**
	 * @covers Menu_Admin::handle_ajax_duplicate_menu
	 */
	public function test_duplicate_menu_fails_without_capability(): void {
		$menu_id = $this->create_menu_with_items( 'No Cap Menu', 1 );

		$this->_setRole( 'subscriber' );

		$_POST = array(
			'nonce'   => wp_create_nonce( 'swmd_menu_actions' ),
			'menu_id' => $menu_id,
		);

		$response = $this->dispatch( 'swmd_duplicate_menu' );

		$this->assertFalse( $response['success'] );
	}

	/**
	 * @covers Menu_Admin::handle_ajax_duplicate_menu
	 */
	public function test_duplicate_menu_fails_with_invalid_menu_id(): void {
		$this->login_as_menu_editor();

		$_POST['menu_id'] = 0;

		$response = $this->dispatch( 'swmd_duplicate_menu' );

		$this->assertFalse( $response['success'] );
	}

	// -----------------------------------------------------------------------
	// Duplication.
	// -----------------------------------------------------------------------

	/**
	 * @covers Menu_Admin::handle_ajax_duplicate_menu
	 */
	public function test_duplicate_menu_succeeds_with_valid_data(): void {
		$menu_id = $this->create_menu_with_items( 'AJAX Test Menu', 2 );

		$this->login_as_menu_editor();

		$_POST['menu_id']   = $menu_id;
		$_POST['menu_name'] = 'AJAX Copy';

		$response = $this->dispatch( 'swmd_duplicate_menu' );

		$this->assertTrue( $response['success'] );

		$new_menu_id = (int) $response['data']['new_menu_id'];

		$this->assertGreaterThan( 0, $new_menu_id );
		$this->assertSame( 'AJAX Copy', get_term( $new_menu_id, 'nav_menu' )->name );
		$this->assertCount( 2, wp_get_nav_menu_items( $new_menu_id ) );
	}

	/**
	 * @covers Menu_Admin::handle_ajax_duplicate_item
	 */
	public function test_duplicate_item_succeeds(): void {
		$menu_id = $this->create_menu_with_items( 'Item Menu', 1 );
		$items   = wp_get_nav_menu_items( $menu_id );

		$this->login_as_menu_editor();

		$_POST['menu_id'] = $menu_id;
		$_POST['item_id'] = $items[0]->ID;

		$response = $this->dispatch( 'swmd_duplicate_item' );

		$this->assertTrue( $response['success'] );
		$this->assertGreaterThan( 0, (int) $response['data']['new_item_id'] );
		$this->assertCount( 2, wp_get_nav_menu_items( $menu_id ) );
	}

	// -----------------------------------------------------------------------
	// Snapshots.
	// -----------------------------------------------------------------------

	/**
	 * @covers Menu_Admin::handle_ajax_save_snapshot
	 * @covers Menu_Admin::handle_ajax_get_snapshots
	 */
	public function test_save_and_get_snapshots(): void {
		$menu_id = $this->create_menu_with_items( 'Snapshot Menu', 2 );

		delete_term_meta( $menu_id, '_swmd_snapshot' );
		delete_term_meta( $menu_id, '_swmd_snapshots' );

		$this->login_as_menu_editor();

		$_POST['menu_id'] = $menu_id;
		$_POST['label']   = 'Manual snapshot';

		$saved = $this->dispatch( 'swmd_save_snapshot' );

		$this->assertTrue( $saved['success'] );
		$this->assertCount( 1, $saved['data']['snapshots'] );
		$this->assertSame( 'Manual snapshot', $saved['data']['snapshots'][0]['label'] );

		// The response must not leak the full export payload to the browser.
		$this->assertArrayNotHasKey( 'data', $saved['data']['snapshots'][0] );

		$this->_last_response = '';

		$fetched = $this->dispatch( 'swmd_get_snapshots' );

		$this->assertTrue( $fetched['success'] );
		$this->assertCount( 1, $fetched['data']['snapshots'] );
	}

	/**
	 * @covers Menu_Admin::handle_ajax_restore_snapshot
	 */
	public function test_restore_snapshot_restores_items(): void {
		$menu_id = $this->create_menu_with_items( 'Restore Menu', 2 );

		$duplicator = new Menu_Duplicator();
		$duplicator->save_snapshot( $menu_id, 'Before edits' );

		$snapshots = $duplicator->get_snapshots( $menu_id );

		// Drop an item so the restore has something to put back.
		$items = wp_get_nav_menu_items( $menu_id );
		wp_delete_post( $items[0]->ID, true );

		$this->assertCount( 1, wp_get_nav_menu_items( $menu_id ) );

		$this->login_as_menu_editor();

		$_POST['menu_id']     = $menu_id;
		$_POST['snapshot_id'] = $snapshots[0]['id'];

		$response = $this->dispatch( 'swmd_restore_snapshot' );

		$this->assertTrue( $response['success'] );
		$this->assertSame( 2, (int) $response['data']['restored'] );
		$this->assertCount( 2, wp_get_nav_menu_items( $menu_id ) );
	}

	/**
	 * @covers Menu_Admin::handle_ajax_restore_snapshot
	 */
	public function test_restore_snapshot_with_unknown_id_fails(): void {
		$menu_id = $this->create_menu_with_items( 'Unknown Snapshot Menu', 1 );

		$this->login_as_menu_editor();

		$_POST['menu_id']     = $menu_id;
		$_POST['snapshot_id'] = 'does-not-exist';

		$response = $this->dispatch( 'swmd_restore_snapshot' );

		$this->assertFalse( $response['success'] );
	}

	/**
	 * @covers Menu_Admin::handle_ajax_delete_snapshot
	 */
	public function test_delete_snapshot_removes_it(): void {
		$menu_id = $this->create_menu_with_items( 'Delete Snapshot Menu', 1 );

		delete_term_meta( $menu_id, '_swmd_snapshot' );
		delete_term_meta( $menu_id, '_swmd_snapshots' );

		$duplicator = new Menu_Duplicator();
		$duplicator->save_snapshot( $menu_id, 'Doomed' );

		$snapshots = $duplicator->get_snapshots( $menu_id );

		$this->login_as_menu_editor();

		$_POST['menu_id']     = $menu_id;
		$_POST['snapshot_id'] = $snapshots[0]['id'];

		$response = $this->dispatch( 'swmd_delete_snapshot' );

		$this->assertTrue( $response['success'] );
		$this->assertSame( array(), $response['data']['snapshots'] );
	}
}
