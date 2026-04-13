<?php
/**
 * Test suite for Menu_REST_Controller — Tier 3.
 *
 * @package SwiftMenuDuplicator
 */

namespace SwiftMenuDuplicator\Test\Rest;

use SwiftMenuDuplicator\Rest\Menu_REST_Controller;
use SwiftMenuDuplicator\Test\SwiftMenuDuplicatorTestCase;
use WP_REST_Request;

/**
 * Tests REST route registration, callback correctness, HTTP statuses,
 * and the swmd_rest_permission filter.
 *
 * Uses WP_UnitTestCase's built-in REST server factory so actual route
 * dispatching is exercised without a real HTTP stack.
 */
class Menu_REST_Controller_Test extends SwiftMenuDuplicatorTestCase {

	private Menu_REST_Controller $controller;

	/**
	 * @inheritDoc
	 */
	public function set_up() {
		parent::set_up();

		$this->controller = new Menu_REST_Controller();
		$this->controller->register_hooks();

		// Ensure routes are registered for the current test.
		do_action( 'rest_api_init' );
	}

	/**
	 * @inheritDoc
	 */
	public function tear_down() {
		global $wpdb;

		$wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_type = 'nav_menu_item'" );
		$wpdb->query( "DELETE FROM {$wpdb->term_taxonomy} WHERE taxonomy = 'nav_menu'" );
		$wpdb->query( "DELETE FROM {$wpdb->terms} WHERE 1=1" );
		$wpdb->query( "DELETE FROM {$wpdb->termmeta} WHERE 1=1" );

		remove_all_filters( 'swmd_rest_permission' );

		parent::tear_down();
	}

	// -----------------------------------------------------------------------
	// Route registration.
	// -----------------------------------------------------------------------

	/**
	 * @covers Menu_REST_Controller::register_routes
	 */
	public function test_duplicate_menu_route_is_registered(): void {
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/cmd/v1/menus/(?P<id>[\d]+)/duplicate', $routes );
	}

	/**
	 * @covers Menu_REST_Controller::register_routes
	 */
	public function test_export_menu_route_is_registered(): void {
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/cmd/v1/menus/(?P<id>[\d]+)/export', $routes );
	}

	/**
	 * @covers Menu_REST_Controller::register_routes
	 */
	public function test_duplicate_item_route_is_registered(): void {
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/cmd/v1/menus/(?P<id>[\d]+)/items/(?P<item_id>[\d]+)/duplicate', $routes );
	}

	// -----------------------------------------------------------------------
	// Permission callback.
	// -----------------------------------------------------------------------

	/**
	 * @covers Menu_REST_Controller::check_permission
	 */
	public function test_unauthenticated_request_returns_403(): void {
		wp_set_current_user( 0 );

		$menu_id = $this->create_menu_with_items( 'Perm Test', 1 );
		$request = new WP_REST_Request( 'POST', '/cmd/v1/menus/' . $menu_id . '/duplicate' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 403, $response->get_status() );
	}

	/**
	 * @covers Menu_REST_Controller::check_permission
	 */
	public function test_authenticated_editor_can_call_duplicate(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$menu_id = $this->create_menu_with_items( 'Auth Test', 1 );
		$request = new WP_REST_Request( 'POST', '/cmd/v1/menus/' . $menu_id . '/duplicate' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		wp_set_current_user( 0 );
	}

	/**
	 * @covers Menu_REST_Controller::check_permission
	 */
	public function test_swmd_rest_permission_filter_can_deny_access(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		add_filter( 'swmd_rest_permission', '__return_false' );

		$menu_id  = $this->create_menu_with_items( 'Filter Deny', 1 );
		$request  = new WP_REST_Request( 'POST', '/cmd/v1/menus/' . $menu_id . '/duplicate' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 403, $response->get_status() );

		wp_set_current_user( 0 );
		remove_all_filters( 'swmd_rest_permission' );
	}

	/**
	 * @covers Menu_REST_Controller::check_permission
	 */
	public function test_swmd_rest_permission_filter_can_grant_access(): void {
		// Subscriber normally cannot edit_theme_options.
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		add_filter( 'swmd_rest_permission', '__return_true' );

		$menu_id  = $this->create_menu_with_items( 'Filter Grant', 1 );
		$request  = new WP_REST_Request( 'POST', '/cmd/v1/menus/' . $menu_id . '/duplicate' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		wp_set_current_user( 0 );
		remove_all_filters( 'swmd_rest_permission' );
	}

	// -----------------------------------------------------------------------
	// duplicate_menu endpoint.
	// -----------------------------------------------------------------------

	/**
	 * @covers Menu_REST_Controller::duplicate_menu
	 */
	public function test_duplicate_menu_returns_new_id_and_name(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$menu_id = $this->create_menu_with_items( 'REST Dup', 2 );
		$request = new WP_REST_Request( 'POST', '/cmd/v1/menus/' . $menu_id . '/duplicate' );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertArrayHasKey( 'id', $data );
		$this->assertArrayHasKey( 'name', $data );
		$this->assertArrayHasKey( 'edit_url', $data );
		$this->assertGreaterThan( 0, $data['id'] );
		$this->assertStringContainsString( '(Copy)', $data['name'] );

		wp_set_current_user( 0 );
	}

	/**
	 * @covers Menu_REST_Controller::duplicate_menu
	 */
	public function test_duplicate_menu_accepts_custom_name_param(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$menu_id = $this->create_menu_with_items( 'Custom REST', 1 );
		$request = new WP_REST_Request( 'POST', '/cmd/v1/menus/' . $menu_id . '/duplicate' );
		$request->set_param( 'name', 'My REST Copy' );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 'My REST Copy', $data['name'] );

		wp_set_current_user( 0 );
	}

	/**
	 * @covers Menu_REST_Controller::duplicate_menu
	 */
	public function test_duplicate_menu_nonexistent_id_returns_404(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$request  = new WP_REST_Request( 'POST', '/cmd/v1/menus/99999/duplicate' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 404, $response->get_status() );

		wp_set_current_user( 0 );
	}

	// -----------------------------------------------------------------------
	// export_menu endpoint.
	// -----------------------------------------------------------------------

	/**
	 * @covers Menu_REST_Controller::export_menu
	 */
	public function test_export_menu_returns_valid_payload_structure(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$menu_id = $this->create_menu_with_items( 'REST Export', 3 );
		$request = new WP_REST_Request( 'GET', '/cmd/v1/menus/' . $menu_id . '/export' );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertArrayHasKey( 'menu', $data );
		$this->assertArrayHasKey( 'items', $data );
		$this->assertArrayHasKey( 'version', $data );
		$this->assertCount( 3, $data['items'] );

		wp_set_current_user( 0 );
	}

	/**
	 * @covers Menu_REST_Controller::export_menu
	 */
	public function test_export_nonexistent_menu_returns_404(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$request  = new WP_REST_Request( 'GET', '/cmd/v1/menus/99999/export' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 404, $response->get_status() );

		wp_set_current_user( 0 );
	}

	// -----------------------------------------------------------------------
	// duplicate_item endpoint.
	// -----------------------------------------------------------------------

	/**
	 * @covers Menu_REST_Controller::duplicate_item
	 */
	public function test_duplicate_item_returns_new_item_id(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$menu_id = $this->create_menu_with_items( 'REST Item', 2 );
		$items   = wp_get_nav_menu_items( $menu_id );
		$item_id = $items[0]->ID;

		$request = new WP_REST_Request(
			'POST',
			'/cmd/v1/menus/' . $menu_id . '/items/' . $item_id . '/duplicate'
		);

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertArrayHasKey( 'id', $data );
		$this->assertGreaterThan( 0, $data['id'] );
		$this->assertNotEquals( $item_id, $data['id'] );

		wp_set_current_user( 0 );
	}

	/**
	 * @covers Menu_REST_Controller::duplicate_item
	 */
	public function test_duplicate_nonexistent_item_returns_404(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$menu_id = $this->create_menu_with_items( 'REST 404 Item', 1 );
		$request = new WP_REST_Request(
			'POST',
			'/cmd/v1/menus/' . $menu_id . '/items/99999/duplicate'
		);

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 404, $response->get_status() );

		wp_set_current_user( 0 );
	}
}
