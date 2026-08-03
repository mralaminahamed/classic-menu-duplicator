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
 * and the swift_menu_duplicator_rest_permission filter.
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

		$wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_type IN ( 'nav_menu_item', 'wp_navigation' )" );
		$wpdb->query( "DELETE FROM {$wpdb->term_taxonomy} WHERE taxonomy = 'nav_menu'" );
		$wpdb->query( "DELETE FROM {$wpdb->terms} WHERE 1=1" );
		$wpdb->query( "DELETE FROM {$wpdb->termmeta} WHERE 1=1" );

		remove_all_filters( 'swift_menu_duplicator_rest_permission' );

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

		$this->assertArrayHasKey( '/swift-menu-duplicator/v1/menus/(?P<id>[\d]+)/duplicate', $routes );
	}

	/**
	 * @covers Menu_REST_Controller::register_routes
	 */
	public function test_export_menu_route_is_registered(): void {
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/swift-menu-duplicator/v1/menus/(?P<id>[\d]+)/export', $routes );
	}

	/**
	 * @covers Menu_REST_Controller::register_routes
	 */
	public function test_duplicate_item_route_is_registered(): void {
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/swift-menu-duplicator/v1/menus/(?P<id>[\d]+)/items/(?P<item_id>[\d]+)/duplicate', $routes );
	}

	// -----------------------------------------------------------------------
	// Permission callback.
	// -----------------------------------------------------------------------

	/**
	 * @covers Menu_REST_Controller::check_permission
	 */
	public function test_unauthenticated_request_is_rejected(): void {
		wp_set_current_user( 0 );

		$menu_id = $this->create_menu_with_items( 'Perm Test', 1 );
		$request = new WP_REST_Request( 'POST', '/swift-menu-duplicator/v1/menus/' . $menu_id . '/duplicate' );

		$response = rest_get_server()->dispatch( $request );

		// rest_authorization_required_code() answers 401 for logged-out callers
		// and 403 once a user is authenticated but lacks the capability.
		$this->assertEquals( 401, $response->get_status() );
		$this->assertSame( 'rest_forbidden', $response->get_data()['code'] );
	}

	/**
	 * @covers Menu_REST_Controller::check_permission
	 */
	public function test_authenticated_editor_can_call_duplicate(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$menu_id = $this->create_menu_with_items( 'Auth Test', 1 );
		$request = new WP_REST_Request( 'POST', '/swift-menu-duplicator/v1/menus/' . $menu_id . '/duplicate' );

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

		add_filter( 'swift_menu_duplicator_rest_permission', '__return_false' );

		$menu_id  = $this->create_menu_with_items( 'Filter Deny', 1 );
		$request  = new WP_REST_Request( 'POST', '/swift-menu-duplicator/v1/menus/' . $menu_id . '/duplicate' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 403, $response->get_status() );

		wp_set_current_user( 0 );
		remove_all_filters( 'swift_menu_duplicator_rest_permission' );
	}

	/**
	 * @covers Menu_REST_Controller::check_permission
	 */
	public function test_swmd_rest_permission_filter_can_grant_access(): void {
		// Subscriber normally cannot edit_theme_options.
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		add_filter( 'swift_menu_duplicator_rest_permission', '__return_true' );

		$menu_id  = $this->create_menu_with_items( 'Filter Grant', 1 );
		$request  = new WP_REST_Request( 'POST', '/swift-menu-duplicator/v1/menus/' . $menu_id . '/duplicate' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		wp_set_current_user( 0 );
		remove_all_filters( 'swift_menu_duplicator_rest_permission' );
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
		$request = new WP_REST_Request( 'POST', '/swift-menu-duplicator/v1/menus/' . $menu_id . '/duplicate' );

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
		$request = new WP_REST_Request( 'POST', '/swift-menu-duplicator/v1/menus/' . $menu_id . '/duplicate' );
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

		$request  = new WP_REST_Request( 'POST', '/swift-menu-duplicator/v1/menus/99999/duplicate' );
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
		$request = new WP_REST_Request( 'GET', '/swift-menu-duplicator/v1/menus/' . $menu_id . '/export' );

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

		$request  = new WP_REST_Request( 'GET', '/swift-menu-duplicator/v1/menus/99999/export' );
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
			'/swift-menu-duplicator/v1/menus/' . $menu_id . '/items/' . $item_id . '/duplicate'
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
			'/swift-menu-duplicator/v1/menus/' . $menu_id . '/items/99999/duplicate'
		);

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 404, $response->get_status() );

		wp_set_current_user( 0 );
	}

	// -----------------------------------------------------------------------
	// Import and snapshot routes.
	// -----------------------------------------------------------------------

	/**
	 * @covers Menu_REST_Controller::register_routes
	 */
	public function test_import_and_snapshot_routes_are_registered(): void {
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/swift-menu-duplicator/v1/menus/import', $routes );
		$this->assertArrayHasKey( '/swift-menu-duplicator/v1/menus/(?P<id>[\d]+)/snapshots', $routes );
		$this->assertArrayHasKey( '/swift-menu-duplicator/v1/menus/(?P<id>[\d]+)/snapshots/(?P<snapshot_id>[a-f0-9\-]+)', $routes );
	}

	/**
	 * @covers Menu_REST_Controller::import_menu
	 */
	public function test_import_endpoint_creates_a_menu(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$source_id = $this->create_menu_with_items( 'REST Import Source', 2 );
		$payload   = ( new \SwiftMenuDuplicator\Core\Menu_Duplicator() )->export( $source_id );

		$request = new WP_REST_Request( 'POST', '/swift-menu-duplicator/v1/menus/import' );
		$request->set_body_params(
			array(
				'payload' => $payload,
				'name'    => 'REST Import Target',
			)
		);

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertSame( 'REST Import Target', $response->get_data()['name'] );
		$this->assertCount( 2, wp_get_nav_menu_items( $response->get_data()['id'] ) );
	}

	/**
	 * @covers Menu_REST_Controller::import_menu
	 */
	public function test_import_endpoint_rejects_a_malformed_payload(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$request = new WP_REST_Request( 'POST', '/swift-menu-duplicator/v1/menus/import' );
		$request->set_body_params( array( 'payload' => array( 'nope' => true ) ) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
	}

	/**
	 * @covers Menu_REST_Controller::create_snapshot
	 * @covers Menu_REST_Controller::list_snapshots
	 */
	public function test_snapshot_can_be_created_and_listed(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$menu_id = $this->create_menu_with_items( 'REST Snapshot Menu', 2 );

		delete_term_meta( $menu_id, '_swmd_snapshot' );
		delete_term_meta( $menu_id, '_swmd_snapshots' );

		$create = new WP_REST_Request( 'POST', '/swift-menu-duplicator/v1/menus/' . $menu_id . '/snapshots' );
		$create->set_body_params( array( 'label' => 'Via REST' ) );

		$created = rest_get_server()->dispatch( $create );

		$this->assertEquals( 201, $created->get_status() );
		$this->assertSame( 'Via REST', $created->get_data()[0]['label'] );
		$this->assertSame( 2, $created->get_data()[0]['items'] );

		// The stored payload must not leak into the listing.
		$this->assertArrayNotHasKey( 'data', $created->get_data()[0] );

		$listed = rest_get_server()->dispatch(
			new WP_REST_Request( 'GET', '/swift-menu-duplicator/v1/menus/' . $menu_id . '/snapshots' )
		);

		$this->assertEquals( 200, $listed->get_status() );
		$this->assertCount( 1, $listed->get_data() );
	}

	/**
	 * @covers Menu_REST_Controller::restore_snapshot
	 * @covers Menu_REST_Controller::delete_snapshot
	 */
	public function test_snapshot_can_be_restored_and_deleted(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$menu_id    = $this->create_menu_with_items( 'REST Restore Menu', 2 );
		$duplicator = new \SwiftMenuDuplicator\Core\Menu_Duplicator();

		$duplicator->save_snapshot( $menu_id, 'Restore point' );
		$snapshot_id = $duplicator->get_snapshots( $menu_id )[0]['id'];

		foreach ( wp_get_nav_menu_items( $menu_id ) as $item ) {
			wp_delete_post( $item->ID, true );
		}

		$restore = rest_get_server()->dispatch(
			new WP_REST_Request( 'POST', '/swift-menu-duplicator/v1/menus/' . $menu_id . '/snapshots/' . $snapshot_id )
		);

		$this->assertEquals( 200, $restore->get_status() );
		$this->assertSame( 2, $restore->get_data()['restored'] );
		$this->assertCount( 2, wp_get_nav_menu_items( $menu_id ) );

		$delete = rest_get_server()->dispatch(
			new WP_REST_Request( 'DELETE', '/swift-menu-duplicator/v1/menus/' . $menu_id . '/snapshots/' . $snapshot_id )
		);

		$this->assertEquals( 200, $delete->get_status() );
		$this->assertTrue( $delete->get_data()['deleted'] );
	}

	/**
	 * @covers Menu_REST_Controller::restore_snapshot
	 */
	public function test_restoring_an_unknown_snapshot_returns_404(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$menu_id = $this->create_menu_with_items( 'REST Missing Snapshot', 1 );

		$response = rest_get_server()->dispatch(
			new WP_REST_Request( 'POST', '/swift-menu-duplicator/v1/menus/' . $menu_id . '/snapshots/abc-def' )
		);

		$this->assertEquals( 404, $response->get_status() );
	}

	/**
	 * @covers Menu_REST_Controller::get_menu_schema
	 */
	public function test_routes_expose_a_schema(): void {
		$server = rest_get_server();

		// get_routes() moves non-numeric keys into the route options, so the
		// schema is read back from there rather than from the route array.
		$server->get_routes();

		foreach (
			array(
				'/swift-menu-duplicator/v1/menus/(?P<id>[\d]+)/duplicate',
				'/swift-menu-duplicator/v1/menus/(?P<id>[\d]+)/export',
				'/swift-menu-duplicator/v1/menus/import',
				'/swift-menu-duplicator/v1/menus/(?P<id>[\d]+)/snapshots',
			) as $route
		) {
			$options = $server->get_route_options( $route );

			$this->assertIsArray( $options, $route );
			$this->assertArrayHasKey( 'schema', $options, $route );
			$this->assertIsArray( call_user_func( $options['schema'] ), $route );
		}
	}

	// -----------------------------------------------------------------------
	// Block navigation routes.
	// -----------------------------------------------------------------------

	/**
	 * Creates a block navigation menu.
	 *
	 * @param string $title Navigation title.
	 *
	 * @return int Post ID.
	 */
	private function create_navigation( string $title = 'Header' ): int {
		return (int) self::factory()->post->create(
			array(
				'post_type'    => 'wp_navigation',
				'post_title'   => $title,
				'post_content' => '<!-- wp:navigation-link {"label":"Home"} /-->',
				'post_status'  => 'publish',
			)
		);
	}

	/**
	 * @covers Menu_REST_Controller::list_navigations
	 * @covers Menu_REST_Controller::duplicate_navigation
	 */
	public function test_navigation_can_be_listed_and_duplicated(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$navigation_id = $this->create_navigation( 'REST Header' );

		$listed = rest_get_server()->dispatch(
			new WP_REST_Request( 'GET', '/swift-menu-duplicator/v1/navigations' )
		);

		$this->assertEquals( 200, $listed->get_status() );
		$this->assertSame( 'REST Header', $listed->get_data()[0]['title'] );
		$this->assertStringContainsString( 'site-editor.php', $listed->get_data()[0]['edit_url'] );

		$duplicate = new WP_REST_Request( 'POST', '/swift-menu-duplicator/v1/navigations/' . $navigation_id . '/duplicate' );
		$duplicate->set_body_params( array( 'title' => 'REST Header Copy' ) );

		$duplicated = rest_get_server()->dispatch( $duplicate );

		$this->assertEquals( 200, $duplicated->get_status() );
		$this->assertSame( 'REST Header Copy', $duplicated->get_data()['title'] );
		$this->assertNotSame( $navigation_id, $duplicated->get_data()['id'] );
	}

	/**
	 * @covers Menu_REST_Controller::export_navigation
	 * @covers Menu_REST_Controller::import_navigation
	 */
	public function test_navigation_export_import_round_trip(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$navigation_id = $this->create_navigation( 'Round Trip' );

		$exported = rest_get_server()->dispatch(
			new WP_REST_Request( 'GET', '/swift-menu-duplicator/v1/navigations/' . $navigation_id . '/export' )
		);

		$this->assertEquals( 200, $exported->get_status() );
		$this->assertSame( 'wp_navigation', $exported->get_data()['type'] );

		$import = new WP_REST_Request( 'POST', '/swift-menu-duplicator/v1/navigations/import' );
		$import->set_body_params(
			array(
				'payload' => $exported->get_data(),
				'title'   => 'Round Trip Imported',
			)
		);

		$imported = rest_get_server()->dispatch( $import );

		$this->assertEquals( 200, $imported->get_status() );
		$this->assertSame( 'Round Trip Imported', $imported->get_data()['title'] );
		$this->assertSame(
			$exported->get_data()['content'],
			get_post( $imported->get_data()['id'] )->post_content
		);
	}

	/**
	 * @covers Menu_REST_Controller::duplicate_navigation
	 */
	public function test_duplicating_an_unknown_navigation_returns_404(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$response = rest_get_server()->dispatch(
			new WP_REST_Request( 'POST', '/swift-menu-duplicator/v1/navigations/999999/duplicate' )
		);

		$this->assertEquals( 404, $response->get_status() );
	}

	/**
	 * @covers Menu_REST_Controller::check_permission
	 */
	public function test_navigation_routes_require_the_capability(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$response = rest_get_server()->dispatch(
			new WP_REST_Request( 'GET', '/swift-menu-duplicator/v1/navigations' )
		);

		$this->assertEquals( 403, $response->get_status() );
	}
}
