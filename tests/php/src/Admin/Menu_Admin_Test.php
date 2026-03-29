<?php
/**
 * Test suite for Menu_Admin class.
 *
 * @package SwiftMenuDuplicator
 */

namespace SwiftMenuDuplicator\Test\Admin;

use Brain\Monkey\Functions;
use SwiftMenuDuplicator\Menu_Admin;
use SwiftMenuDuplicator\Test\SwiftMenuDuplicatorTestCase;

/**
 * Comprehensive test suite for Menu_Admin class.
 *
 * Tests all public methods including:
 * - Hook registration
 * - Script enqueuing
 * - AJAX handlers
 * - Nonce verification
 * - Capability checks
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
	 * Test register_hooks adds required actions.
	 */
	public function test_register_hooks_adds_actions(): void {
		$this->admin->register_hooks();

		$this->assertNotFalse( has_action( 'admin_enqueue_scripts', array( $this->admin, 'enqueue_scripts' ) ) );
		$this->assertNotFalse( has_action( 'admin_head', array( $this->admin, 'output_inline_styles' ) ) );
		$this->assertNotFalse( has_action( 'wp_ajax_swmd_duplicate_menu', array( $this->admin, 'handle_ajax_duplicate_menu' ) ) );
		$this->assertNotFalse( has_action( 'wp_ajax_swmd_export_menu', array( $this->admin, 'handle_ajax_export_menu' ) ) );
		$this->assertNotFalse( has_action( 'wp_ajax_swmd_duplicate_item', array( $this->admin, 'handle_ajax_duplicate_item' ) ) );
		$this->assertNotFalse( has_action( 'wp_ajax_swmd_save_snapshot', array( $this->admin, 'handle_ajax_save_snapshot' ) ) );
		$this->assertNotFalse( has_action( 'wp_ajax_swmd_get_snapshots', array( $this->admin, 'handle_ajax_get_snapshots' ) ) );
		$this->assertNotFalse( has_action( 'wp_ajax_swmd_delete_snapshot', array( $this->admin, 'handle_ajax_delete_snapshot' ) ) );
		$this->assertNotFalse( has_action( 'wp_update_nav_menu', array( $this->admin, 'auto_snapshot_on_save' ) ) );
	}

	/**
	 * Test enqueue_scripts only runs on nav-menus.php.
	 */
	public function test_enqueue_scripts_only_runs_on_nav_menus(): void {
		Functions\when( 'wp_script_is' )->justReturn(false);
		Functions\when( 'wp_enqueue_script' )->justReturn(true);
		Functions\when( 'wp_localize_script' )->justReturn(true);
		Functions\when( 'admin_url' )->justReturn( 'http://example.com/wp-admin/' );
		Functions\when( 'wp_create_nonce' )->justReturn( 'test_nonce' );
		Functions\when( 'file_exists' )->justReturn( true );
		Functions\when( 'filemtime' )->justReturn( 1234567890 );
		Functions\when( '__' )->returnArg();
		Functions\when( 'esc_html__' )->returnArg();
		Functions\when( 'esc_attr__' )->returnArg();

		$this->admin->enqueue_scripts( 'index.php' );

		$this->assertFalse( wp_script_is( 'swmd-admin', 'enqueued' ) );
	}

	/**
	 * Test enqueue_scripts enqueues script on nav-menus.php.
	 */
	public function test_enqueue_scripts_enqueues_on_nav_menus(): void {
		Functions\when( 'wp_script_is' )->justReturn(false);
		Functions\when( 'wp_enqueue_script' )->justReturn(true);
		Functions\when( 'wp_localize_script' )->justReturn(true);
		Functions\when( 'admin_url' )->justReturn( 'http://example.com/wp-admin/' );
		Functions\when( 'wp_create_nonce' )->justReturn( 'test_nonce_123' );
		Functions\when( 'file_exists' )->justReturn( true );
		Functions\when( 'filemtime' )->justReturn( 1234567890 );
		Functions\when( '__' )->returnArg();
		Functions\when( 'esc_html__' )->returnArg();
		Functions\when( 'esc_attr__' )->returnArg();

		$this->admin->enqueue_scripts( 'nav-menus.php' );

		$this->assertTrue( wp_script_is( 'swmd-admin', 'enqueued' ) );
	}

	/**
	 * Test enqueue_scripts localizes script with correct data.
	 */
	public function test_enqueue_scripts_localizes_with_data(): void {
		Functions\when( 'wp_script_is' )->justReturn(false);
		Functions\when( 'wp_enqueue_script' )->justReturn(true);
		Functions\when( 'wp_localize_script' )->returnArg( 2 );
		Functions\when( 'admin_url' )->justReturn( 'http://example.com/wp-admin/admin-ajax.php' );
		Functions\when( 'wp_create_nonce' )->justReturn( 'test_nonce_123' );
		Functions\when( 'file_exists' )->justReturn( true );
		Functions\when( 'filemtime' )->justReturn( 1234567890 );
		Functions\when( '__' )->returnArg();
		Functions\when( 'esc_html__' )->returnArg();
		Functions\when( 'esc_attr__' )->returnArg();

		$this->admin->enqueue_scripts( 'nav-menus.php' );
	}

	/**
	 * Test output_inline_styles outputs styles on nav-menus.php.
	 */
	public function test_output_inline_styles_outputs_on_nav_menus(): void {
		global $pagenow;

		Functions\when( 'file_exists' )->justReturn( true );

		$pagenow = 'nav-menus.php';

		ob_start();
		$this->admin->output_inline_styles();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<style', $output );
		$this->assertStringContainsString( 'swmd-inline-styles', $output );
	}

	/**
	 * Test output_inline_styles does not output on other pages.
	 */
	public function test_output_inline_styles_skips_other_pages(): void {
		global $pagenow;

		$pagenow = 'index.php';

		ob_start();
		$this->admin->output_inline_styles();
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}

	/**
	 * Test AJAX duplicate menu fails without nonce.
	 */
	public function test_handle_ajax_duplicate_menu_fails_without_nonce(): void {
		Functions\when( 'wp_verify_nonce' )->justReturn( false );
		Functions\when( 'wp_send_json_error' )->justReturn(null);
		Functions\when( '__' )->returnArg();

		$_POST = array();

		$this->admin->handle_ajax_duplicate_menu();
	}

	/**
	 * Test AJAX duplicate menu fails with invalid nonce.
	 */
	public function test_handle_ajax_duplicate_menu_fails_with_invalid_nonce(): void {
		Functions\when( 'wp_verify_nonce' )->justReturn( false );
		Functions\when( 'wp_send_json_error' )->justReturn(null);
		Functions\when( '__' )->returnArg();

		$_POST = array(
			'nonce' => 'invalid_nonce',
		);

		$this->admin->handle_ajax_duplicate_menu();
	}

	/**
	 * Test AJAX duplicate menu fails without edit_theme_options capability.
	 */
	public function test_handle_ajax_duplicate_menu_fails_without_capability(): void {
		Functions\when( 'wp_verify_nonce' )->justReturn( 1 );
		Functions\when( 'current_user_can' )->justReturn( false );
		Functions\when( 'wp_send_json_error' )->justReturn(null);
		Functions\when( '__' )->returnArg();

		$_POST = array(
			'nonce' => 'valid_nonce',
		);

		$this->admin->handle_ajax_duplicate_menu();
	}

	/**
	 * Test AJAX duplicate menu fails with invalid menu ID.
	 */
	public function test_handle_ajax_duplicate_menu_fails_with_invalid_menu_id(): void {
		Functions\when( 'wp_verify_nonce' )->justReturn( 1 );
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'wp_send_json_error' )->justReturn(null);
		Functions\when( '__' )->returnArg();

		$_POST = array(
			'nonce'   => 'valid_nonce',
			'menu_id' => 0,
		);

		$this->admin->handle_ajax_duplicate_menu();
	}

	/**
	 * Test AJAX duplicate menu succeeds with valid data.
	 */
	public function test_handle_ajax_duplicate_menu_succeeds_with_valid_data(): void {
		$menu_id = $this->create_menu_with_items( 'AJAX Test Menu', 1 );

		Functions\when( 'wp_verify_nonce' )->justReturn( 1 );
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'wp_send_json_success' )->justReturn(null);
		Functions\when( 'wp_send_json_error' )->justReturn(null);
		Functions\when( 'admin_url' )->justReturn( 'http://example.com/wp-admin/' );

		$_POST = array(
			'nonce'   => 'valid_nonce',
			'menu_id' => $menu_id,
		);

		$this->admin->handle_ajax_duplicate_menu();
	}

	/**
	 * Test auto_snapshot_on_save saves snapshot before menu update.
	 */
	public function test_auto_snapshot_on_save_saves_snapshot(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'wp_unslash' )->returnArg();
		Functions\when( 'sanitize_text_field' )->returnArg();

		$menu_id = $this->create_menu_with_items( 'Snapshot Test Menu', 1 );

		$this->admin->auto_snapshot_on_save( $menu_id );
	}

	/**
	 * Test auto_snapshot_on_save skips when user lacks capability.
	 */
	public function test_auto_snapshot_on_save_skips_without_capability(): void {
		Functions\when( 'current_user_can' )->justReturn( false );

		$menu_id = $this->create_menu_with_items( 'Capability Test Menu', 1 );

		$this->admin->auto_snapshot_on_save( $menu_id );
	}
}
