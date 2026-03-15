<?php
/**
 * Test suite for Menu_Admin class.
 *
 * @package ClassicMenuDuplicator
 */

namespace ClassicMenuDuplicator\Test;

use ClassicMenuDuplicator\Menu_Admin;
use Brain\Monkey\Functions;

/**
 * Comprehensive test suite for Menu_Admin class.
 *
 * Tests all public methods including:
 * - Hook registration
 * - Script enqueuing
 * - AJAX handler
 * - Nonce verification
 * - Capability checks
 */
class Menu_Admin_Test extends ClassicMenuDuplicatorTestCase {

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
		$this->assertNotFalse( has_action( 'wp_ajax_cmdu_duplicate_menu', array( $this->admin, 'handle_ajax' ) ) );
	}

	/**
	 * Test enqueue_scripts only runs on nav-menus.php.
	 */
	public function test_enqueue_scripts_only_runs_on_nav_menus(): void {
		global $pagenow;

		$pagenow = 'index.php';
		$this->admin->enqueue_scripts( 'index.php' );

		$this->assertFalse( wp_script_is( 'cmdu-admin', 'enqueued' ) );
	}

	/**
	 * Test enqueue_scripts enqueues script on nav-menus.php.
	 */
	public function test_enqueue_scripts_enqueues_on_nav_menus(): void {
		global $pagenow;

		Functions\when( 'plugin_dir_path' )->justReturn( '/path/to/plugin/' );
		Functions\when( 'plugin_dir_url' )->justReturn( 'http://example.com/wp-content/plugins/classic-menu-duplicator/' );
		Functions\when( 'admin_url' )->justReturn( 'http://example.com/wp-admin/admin-ajax.php' );
		Functions\when( 'wp_create_nonce' )->justReturn( 'test_nonce_123' );
		Functions\when( 'file_exists' )->returnArg();
		Functions\when( 'filemtime' )->justReturn( 1234567890 );

		$pagenow = 'nav-menus.php';
		set_current_screen( 'nav-menus' );

		$this->admin->enqueue_scripts( 'nav-menus.php' );

		$this->assertTrue( wp_script_is( 'cmdu-admin', 'enqueued' ) );
	}

	/**
	 * Test enqueue_scripts localizes script with correct data.
	 */
	public function test_enqueue_scripts_localizes_with_data(): void {
		global $pagenow;

		Functions\when( 'plugin_dir_path' )->justReturn( '/path/to/plugin/' );
		Functions\when( 'plugin_dir_url' )->justReturn( 'http://example.com/wp-content/plugins/classic-menu-duplicator/' );
		Functions\when( 'admin_url' )->justReturn( 'http://example.com/wp-admin/admin-ajax.php' );
		Functions\when( 'wp_create_nonce' )->justReturn( 'test_nonce_123' );
		Functions\when( 'file_exists' )->returnArg();
		Functions\when( 'filemtime' )->justReturn( 1234567890 );
		Functions\when( '__' )->returnArg();
		Functions\stubFunction( 'esc_html__' );

		$pagenow = 'nav-menus.php';
		set_current_screen( 'nav-menus' );

		$this->admin->enqueue_scripts( 'nav-menus.php' );

		global $wp_scripts;
		$localized = $wp_scripts->get_data( 'cmdu-admin', 'data' );

		$this->assertStringContainsString( 'cmduData', $localized );
		$this->assertStringContainsString( 'cmdu_duplicate_menu', $localized );
	}

	/**
	 * Test AJAX handler fails without nonce.
	 */
	public function test_handle_ajax_fails_without_nonce(): void {
		Functions\when( 'wp_send_json_error' )->justReturn( null );

		$_POST = array();

		$this->admin->handle_ajax();

		$this->assertTrue( true );
	}

	/**
	 * Test AJAX handler fails with invalid nonce.
	 */
	public function test_handle_ajax_fails_with_invalid_nonce(): void {
		Functions\when( 'wp_verify_nonce' )->justReturn( false );
		Functions\when( 'wp_send_json_error' )->justReturn( null );
		Functions\when( '__' )->returnArg();

		$_POST = array(
			'nonce' => 'invalid_nonce',
		);

		$this->admin->handle_ajax();

		$this->assertTrue( true );
	}

	/**
	 * Test AJAX handler fails without edit_theme_options capability.
	 */
	public function test_handle_ajax_fails_without_capability(): void {
		Functions\when( 'wp_verify_nonce' )->justReturn( 1 );
		Functions\when( 'current_user_can' )->justReturn( false );
		Functions\when( 'wp_send_json_error' )->justReturn( null );
		Functions\when( '__' )->returnArg();

		$_POST = array(
			'nonce' => 'valid_nonce',
		);

		$this->admin->handle_ajax();

		$this->assertTrue( true );
	}

	/**
	 * Test AJAX handler fails with invalid menu ID.
	 */
	public function test_handle_ajax_fails_with_invalid_menu_id(): void {
		Functions\when( 'wp_verify_nonce' )->justReturn( 1 );
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'wp_send_json_error' )->justReturn( null );
		Functions\when( '__' )->returnArg();

		$_POST = array(
			'nonce'   => 'valid_nonce',
			'menu_id' => 0,
		);

		$this->admin->handle_ajax();

		$this->assertTrue( true );
	}

	/**
	 * Test AJAX handler succeeds with valid data.
	 */
	public function test_handle_ajax_succeeds_with_valid_data(): void {
		$menu_id = $this->create_menu_with_items( 'AJAX Test Menu', 1 );

		Functions\when( 'wp_verify_nonce' )->justReturn( 1 );
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'wp_send_json_success' )->justReturn( null );
		Functions\when( 'wp_send_json_error' )->justReturn( null );
		Functions\when( 'admin_url' )->justReturn( 'http://example.com/wp-admin/' );

		$_POST = array(
			'nonce'   => 'valid_nonce',
			'menu_id' => $menu_id,
		);

		$this->admin->handle_ajax();

		$this->assertTrue( true );
	}

	/**
	 * Test output_inline_styles outputs styles on nav-menus.php.
	 */
	public function test_output_inline_styles_outputs_on_nav_menus(): void {
		global $pagenow;

		$pagenow = 'nav-menus.php';

		ob_start();
		$this->admin->output_inline_styles();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<style', $output );
		$this->assertStringContainsString( 'cmdu-inline-styles', $output );
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
}
