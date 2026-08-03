<?php
/**
 * REST API controller for Swift Menu Duplicator.
 *
 * @package SwiftMenuDuplicator
 */

declare( strict_types=1 );

namespace SwiftMenuDuplicator\Rest;

use SwiftMenuDuplicator\Core\Menu_Duplicator;
use SwiftMenuDuplicator\Core\Navigation_Duplicator;
use SwiftMenuDuplicator\Import\Menu_Importer;
use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Menu_REST_Controller
 *
 * Registers a versioned REST namespace `/swift-menu-duplicator/v1` and exposes three
 * endpoints that mirror the existing AJAX surface, making the plugin's
 * core operations consumable by headless frontends, block-editor
 * extensions, and external automation pipelines.
 *
 * Routes:
 *   POST /swift-menu-duplicator/v1/menus/{id}/duplicate
 *   GET  /swift-menu-duplicator/v1/menus/{id}/export
 *   POST /swift-menu-duplicator/v1/menus/{id}/items/{item_id}/duplicate
 *
 * All routes require the `edit_theme_options` capability by default.
 * The permission callback is filterable via `swift_menu_duplicator_rest_permission`.
 */
class Menu_REST_Controller {

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	private const NAMESPACE = 'swift-menu-duplicator/v1';

	/**
	 * Registers REST routes on the rest_api_init hook.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Registers all /swift-menu-duplicator/v1/ routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// POST /swift-menu-duplicator/v1/menus/{id}/duplicate
		register_rest_route(
			self::NAMESPACE,
			'/menus/(?P<id>[\d]+)/duplicate',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'duplicate_menu' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'id'   => array(
							'description'       => __( 'Term ID of the menu to duplicate.', 'swift-menu-duplicator' ),
							'type'              => 'integer',
							'required'          => true,
							'minimum'           => 1,
							'sanitize_callback' => 'absint',
							'validate_callback' => 'rest_validate_request_arg',
						),
						'name' => array(
							'description'       => __( 'Optional name for the duplicated menu. Defaults to "{original} (Copy)".', 'swift-menu-duplicator' ),
							'type'              => 'string',
							'required'          => false,
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
				'schema' => array( $this, 'get_menu_schema' ),
			)
		);

		// GET /swift-menu-duplicator/v1/menus/{id}/export
		register_rest_route(
			self::NAMESPACE,
			'/menus/(?P<id>[\d]+)/export',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'export_menu' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'id' => array(
							'description'       => __( 'Term ID of the menu to export.', 'swift-menu-duplicator' ),
							'type'              => 'integer',
							'required'          => true,
							'minimum'           => 1,
							'sanitize_callback' => 'absint',
							'validate_callback' => 'rest_validate_request_arg',
						),
					),
				),
				'schema' => array( $this, 'get_export_schema' ),
			)
		);

		// POST /swift-menu-duplicator/v1/menus/{id}/items/{item_id}/duplicate
		register_rest_route(
			self::NAMESPACE,
			'/menus/(?P<id>[\d]+)/items/(?P<item_id>[\d]+)/duplicate',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'duplicate_item' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'id'      => array(
							'description'       => __( 'Term ID of the menu that owns the item.', 'swift-menu-duplicator' ),
							'type'              => 'integer',
							'required'          => true,
							'minimum'           => 1,
							'sanitize_callback' => 'absint',
							'validate_callback' => 'rest_validate_request_arg',
						),
						'item_id' => array(
							'description'       => __( 'Post ID of the nav_menu_item to duplicate.', 'swift-menu-duplicator' ),
							'type'              => 'integer',
							'required'          => true,
							'minimum'           => 1,
							'sanitize_callback' => 'absint',
							'validate_callback' => 'rest_validate_request_arg',
						),
					),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);

		// POST /swift-menu-duplicator/v1/menus/import
		register_rest_route(
			self::NAMESPACE,
			'/menus/import',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'import_menu' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'payload' => array(
							'description' => __( 'Export payload produced by the export endpoint.', 'swift-menu-duplicator' ),
							'type'        => 'object',
							'required'    => true,
						),
						'name'    => array(
							'description'       => __( 'Optional name for the imported menu.', 'swift-menu-duplicator' ),
							'type'              => 'string',
							'required'          => false,
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'find'    => array(
							'description'       => __( 'URL fragment to search for in item URLs.', 'swift-menu-duplicator' ),
							'type'              => 'string',
							'required'          => false,
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'replace' => array(
							'description'       => __( 'Replacement for the "find" fragment.', 'swift-menu-duplicator' ),
							'type'              => 'string',
							'required'          => false,
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
				'schema' => array( $this, 'get_menu_schema' ),
			)
		);

		// GET|POST /swift-menu-duplicator/v1/menus/{id}/snapshots
		register_rest_route(
			self::NAMESPACE,
			'/menus/(?P<id>[\d]+)/snapshots',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_snapshots' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array( 'id' => $this->menu_id_arg() ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_snapshot' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'id'    => $this->menu_id_arg(),
						'label' => array(
							'description'       => __( 'Optional label for the snapshot.', 'swift-menu-duplicator' ),
							'type'              => 'string',
							'required'          => false,
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
				'schema' => array( $this, 'get_snapshot_schema' ),
			)
		);

		// POST|DELETE /swift-menu-duplicator/v1/menus/{id}/snapshots/{snapshot_id}
		register_rest_route(
			self::NAMESPACE,
			'/menus/(?P<id>[\d]+)/snapshots/(?P<snapshot_id>[a-f0-9\-]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'restore_snapshot' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'id'          => $this->menu_id_arg(),
						'snapshot_id' => $this->snapshot_id_arg(),
					),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_snapshot' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'id'          => $this->menu_id_arg(),
						'snapshot_id' => $this->snapshot_id_arg(),
					),
				),
				'schema' => array( $this, 'get_snapshot_schema' ),
			)
		);
		// GET /swift-menu-duplicator/v1/navigations
		register_rest_route(
			self::NAMESPACE,
			'/navigations',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_navigations' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				'schema' => array( $this, 'get_navigation_schema' ),
			)
		);

		// POST /swift-menu-duplicator/v1/navigations/{id}/duplicate
		register_rest_route(
			self::NAMESPACE,
			'/navigations/(?P<id>[\d]+)/duplicate',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'duplicate_navigation' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'id'    => $this->navigation_id_arg(),
						'title' => array(
							'description'       => __( 'Optional title for the duplicate.', 'swift-menu-duplicator' ),
							'type'              => 'string',
							'required'          => false,
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
				'schema' => array( $this, 'get_navigation_schema' ),
			)
		);

		// GET /swift-menu-duplicator/v1/navigations/{id}/export
		register_rest_route(
			self::NAMESPACE,
			'/navigations/(?P<id>[\d]+)/export',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'export_navigation' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array( 'id' => $this->navigation_id_arg() ),
				),
				'schema' => array( $this, 'get_navigation_export_schema' ),
			)
		);

		// POST /swift-menu-duplicator/v1/navigations/import
		register_rest_route(
			self::NAMESPACE,
			'/navigations/import',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'import_navigation' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'payload' => array(
							'description' => __( 'Navigation export payload.', 'swift-menu-duplicator' ),
							'type'        => 'object',
							'required'    => true,
						),
						'title'   => array(
							'description'       => __( 'Optional title for the imported menu.', 'swift-menu-duplicator' ),
							'type'              => 'string',
							'required'          => false,
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
				'schema' => array( $this, 'get_navigation_schema' ),
			)
		);
	}

	/**
	 * Shared arg definition for a navigation post ID.
	 *
	 * @return array<string,mixed>
	 */
	private function navigation_id_arg(): array {
		return array(
			'description'       => __( 'Post ID of the block navigation menu.', 'swift-menu-duplicator' ),
			'type'              => 'integer',
			'required'          => true,
			'minimum'           => 1,
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		);
	}

	/**
	 * Shared arg definition for the menu term ID.
	/**
	 * Shared arg definition for the menu term ID.
	 *
	 * @return array<string,mixed>
	 */
	private function menu_id_arg(): array {
		return array(
			'description'       => __( 'Term ID of the menu.', 'swift-menu-duplicator' ),
			'type'              => 'integer',
			'required'          => true,
			'minimum'           => 1,
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		);
	}

	/**
	 * Shared arg definition for a snapshot UUID.
	 *
	 * @return array<string,mixed>
	 */
	private function snapshot_id_arg(): array {
		return array(
			'description'       => __( 'UUID of the snapshot.', 'swift-menu-duplicator' ),
			'type'              => 'string',
			'required'          => true,
			'sanitize_callback' => 'sanitize_text_field',
		);
	}

	// -----------------------------------------------------------------------
	// Route callbacks.
	// -----------------------------------------------------------------------

	/**
	 * Duplicates a menu and returns the new menu term data.
	 *
	 * @param WP_REST_Request $request Full request object.
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function duplicate_menu( WP_REST_Request $request ) {
		$menu_id = (int) $request->get_param( 'id' );
		$name    = (string) $request->get_param( 'name' );

		$duplicator = new Menu_Duplicator();
		$result     = $duplicator->duplicate( $menu_id, $name );

		if ( is_wp_error( $result ) ) {
			return $this->error_response( $result );
		}

		$new_term = get_term( $result, 'nav_menu' );

		return rest_ensure_response(
			array(
				'id'       => $result,
				'name'     => $new_term instanceof WP_Term ? $new_term->name : '',
				'slug'     => $new_term instanceof WP_Term ? $new_term->slug : '',
				'edit_url' => admin_url( 'nav-menus.php?action=edit&menu=' . $result ),
			)
		);
	}

	/**
	 * Exports a menu as a JSON payload (returned as a REST response body,
	 * not as a file download — file downloads belong in the AJAX handler).
	 *
	 * @param WP_REST_Request $request Full request object.
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function export_menu( WP_REST_Request $request ) {
		$menu_id    = (int) $request->get_param( 'id' );
		$duplicator = new Menu_Duplicator();
		$payload    = $duplicator->export( $menu_id );

		if ( is_wp_error( $payload ) ) {
			return $this->error_response( $payload );
		}

		return rest_ensure_response( $payload );
	}

	/**
	 * Duplicates a single menu item (and its descendants) within a menu.
	 *
	 * @param WP_REST_Request $request Full request object.
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function duplicate_item( WP_REST_Request $request ) {
		$menu_id = (int) $request->get_param( 'id' );
		$item_id = (int) $request->get_param( 'item_id' );

		$duplicator  = new Menu_Duplicator();
		$new_item_id = $duplicator->duplicate_item( $item_id, $menu_id );

		if ( is_wp_error( $new_item_id ) ) {
			return $this->error_response( $new_item_id );
		}

		$new_item = get_post( $new_item_id );

		return rest_ensure_response(
			array(
				'id'         => $new_item_id,
				'title'      => $new_item instanceof WP_Post ? $new_item->post_title : '',
				'menu_order' => $new_item instanceof WP_Post ? (int) $new_item->menu_order : 0,
			)
		);
	}

	/**
	 * Imports a menu from an export payload.
	 *
	 * @param WP_REST_Request $request Full request object.
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function import_menu( WP_REST_Request $request ) {
		$importer = new Menu_Importer();
		$payload  = $importer->validate( (array) $request->get_param( 'payload' ) );

		if ( is_wp_error( $payload ) ) {
			return $this->error_response( $payload );
		}

		$menu_id = $importer->import(
			$payload,
			(string) $request->get_param( 'name' ),
			(string) $request->get_param( 'find' ),
			(string) $request->get_param( 'replace' )
		);

		if ( is_wp_error( $menu_id ) ) {
			return $this->error_response( $menu_id );
		}

		$term = get_term( $menu_id, 'nav_menu' );

		return rest_ensure_response(
			array(
				'id'       => $menu_id,
				'name'     => $term instanceof WP_Term ? $term->name : '',
				'slug'     => $term instanceof WP_Term ? $term->slug : '',
				'edit_url' => admin_url( 'nav-menus.php?action=edit&menu=' . $menu_id ),
			)
		);
	}

	/**
	 * Lists a menu's snapshots (metadata only, without the stored payload).
	 *
	 * @param WP_REST_Request $request Full request object.
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function list_snapshots( WP_REST_Request $request ) {
		$menu_id = (int) $request->get_param( 'id' );

		if ( ! $this->menu_exists( $menu_id ) ) {
			return $this->error_response(
				new WP_Error( 'invalid_menu', __( 'Menu not found.', 'swift-menu-duplicator' ) )
			);
		}

		return rest_ensure_response( $this->prepare_snapshots( $menu_id ) );
	}

	/**
	 * Saves a snapshot of the menu's current state.
	 *
	 * @param WP_REST_Request $request Full request object.
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function create_snapshot( WP_REST_Request $request ) {
		$menu_id = (int) $request->get_param( 'id' );

		if ( ! $this->menu_exists( $menu_id ) ) {
			return $this->error_response(
				new WP_Error( 'invalid_menu', __( 'Menu not found.', 'swift-menu-duplicator' ) )
			);
		}

		$duplicator = new Menu_Duplicator();

		if ( ! $duplicator->save_snapshot( $menu_id, (string) $request->get_param( 'label' ) ) ) {
			return $this->error_response(
				new WP_Error( 'snapshot_failed', __( 'Could not save snapshot.', 'swift-menu-duplicator' ) )
			);
		}

		$response = rest_ensure_response( $this->prepare_snapshots( $menu_id ) );
		$response->set_status( 201 );

		return $response;
	}

	/**
	 * Restores a menu from one of its snapshots.
	 *
	 * @param WP_REST_Request $request Full request object.
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function restore_snapshot( WP_REST_Request $request ) {
		$duplicator = new Menu_Duplicator();
		$restored   = $duplicator->restore_snapshot(
			(int) $request->get_param( 'id' ),
			(string) $request->get_param( 'snapshot_id' )
		);

		if ( is_wp_error( $restored ) ) {
			return $this->error_response( $restored );
		}

		return rest_ensure_response(
			array(
				'restored'  => $restored,
				'snapshots' => $this->prepare_snapshots( (int) $request->get_param( 'id' ) ),
			)
		);
	}

	/**
	 * Deletes a single snapshot.
	 *
	 * @param WP_REST_Request $request Full request object.
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function delete_snapshot( WP_REST_Request $request ) {
		$menu_id    = (int) $request->get_param( 'id' );
		$duplicator = new Menu_Duplicator();

		if ( ! $duplicator->delete_snapshot( $menu_id, (string) $request->get_param( 'snapshot_id' ) ) ) {
			return $this->error_response(
				new WP_Error( 'invalid_snapshot', __( 'Snapshot not found.', 'swift-menu-duplicator' ) )
			);
		}

		return rest_ensure_response(
			array(
				'deleted'   => true,
				'snapshots' => $this->prepare_snapshots( $menu_id ),
			)
		);
	}

	/**
	 * Lists the site's block navigation menus.
	 *
	 * @param WP_REST_Request $request Full request object.
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function list_navigations( WP_REST_Request $request ) {
		unset( $request );

		$duplicator = new Navigation_Duplicator();

		return rest_ensure_response(
			array_map(
				static function ( WP_Post $post ) use ( $duplicator ): array {
					return array(
						'id'       => $post->ID,
						'title'    => $post->post_title,
						'slug'     => $post->post_name,
						'status'   => $post->post_status,
						'edit_url' => $duplicator->get_edit_url( $post->ID ),
					);
				},
				$duplicator->get_all()
			)
		);
	}

	/**
	 * Duplicates a block navigation menu.
	 *
	 * @param WP_REST_Request $request Full request object.
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function duplicate_navigation( WP_REST_Request $request ) {
		$duplicator = new Navigation_Duplicator();
		$new_id     = $duplicator->duplicate(
			(int) $request->get_param( 'id' ),
			(string) $request->get_param( 'title' )
		);

		if ( is_wp_error( $new_id ) ) {
			return $this->error_response( $new_id );
		}

		return $this->navigation_response( $new_id, $duplicator );
	}

	/**
	 * Exports a block navigation menu as a JSON payload.
	 *
	 * @param WP_REST_Request $request Full request object.
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function export_navigation( WP_REST_Request $request ) {
		$payload = ( new Navigation_Duplicator() )->export( (int) $request->get_param( 'id' ) );

		if ( is_wp_error( $payload ) ) {
			return $this->error_response( $payload );
		}

		return rest_ensure_response( $payload );
	}

	/**
	 * Imports a block navigation menu from an export payload.
	 *
	 * @param WP_REST_Request $request Full request object.
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function import_navigation( WP_REST_Request $request ) {
		$duplicator = new Navigation_Duplicator();
		$payload    = $duplicator->validate( (array) $request->get_param( 'payload' ) );

		if ( is_wp_error( $payload ) ) {
			return $this->error_response( $payload );
		}

		$new_id = $duplicator->import( $payload, (string) $request->get_param( 'title' ) );

		if ( is_wp_error( $new_id ) ) {
			return $this->error_response( $new_id );
		}

		return $this->navigation_response( $new_id, $duplicator );
	}

	// -----------------------------------------------------------------------
	// Schemas.
	// -----------------------------------------------------------------------

	/**
	 * Schema for responses describing a menu.
	 *
	 * @return array<string,mixed>
	 */
	public function get_menu_schema(): array {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'swift-menu-duplicator-menu',
			'type'       => 'object',
			'properties' => array(
				'id'       => array(
					'description' => __( 'Term ID of the menu.', 'swift-menu-duplicator' ),
					'type'        => 'integer',
					'readonly'    => true,
				),
				'name'     => array(
					'description' => __( 'Menu name.', 'swift-menu-duplicator' ),
					'type'        => 'string',
					'readonly'    => true,
				),
				'slug'     => array(
					'description' => __( 'Menu slug.', 'swift-menu-duplicator' ),
					'type'        => 'string',
					'readonly'    => true,
				),
				'edit_url' => array(
					'description' => __( 'Admin URL for editing the menu.', 'swift-menu-duplicator' ),
					'type'        => 'string',
					'format'      => 'uri',
					'readonly'    => true,
				),
			),
		);
	}

	/**
	 * Schema for responses describing a duplicated menu item.
	 *
	 * @return array<string,mixed>
	 */
	public function get_item_schema(): array {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'swift-menu-duplicator-menu-item',
			'type'       => 'object',
			'properties' => array(
				'id'         => array(
					'description' => __( 'Post ID of the new menu item.', 'swift-menu-duplicator' ),
					'type'        => 'integer',
					'readonly'    => true,
				),
				'title'      => array(
					'description' => __( 'Menu item title.', 'swift-menu-duplicator' ),
					'type'        => 'string',
					'readonly'    => true,
				),
				'menu_order' => array(
					'description' => __( 'Position of the item within the menu.', 'swift-menu-duplicator' ),
					'type'        => 'integer',
					'readonly'    => true,
				),
			),
		);
	}

	/**
	 * Schema for the export payload.
	 *
	 * @return array<string,mixed>
	 */
	public function get_export_schema(): array {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'swift-menu-duplicator-export',
			'type'       => 'object',
			'properties' => array(
				'version'  => array(
					'description' => __( 'Plugin version that produced the export.', 'swift-menu-duplicator' ),
					'type'        => 'string',
					'readonly'    => true,
				),
				'exported' => array(
					'description' => __( 'Export timestamp.', 'swift-menu-duplicator' ),
					'type'        => 'string',
					'readonly'    => true,
				),
				'site_url' => array(
					'description' => __( 'Home URL of the site the export came from.', 'swift-menu-duplicator' ),
					'type'        => 'string',
					'format'      => 'uri',
					'readonly'    => true,
				),
				'menu'     => array(
					'description' => __( 'Menu name, slug, and description.', 'swift-menu-duplicator' ),
					'type'        => 'object',
					'readonly'    => true,
				),
				'items'    => array(
					'description' => __( 'Menu items, each with meta and a portable object reference.', 'swift-menu-duplicator' ),
					'type'        => 'array',
					'readonly'    => true,
					'items'       => array( 'type' => 'object' ),
				),
			),
		);
	}

	/**
	 * Schema for snapshot descriptors.
	 *
	 * @return array<string,mixed>
	 */
	public function get_snapshot_schema(): array {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'swift-menu-duplicator-snapshot',
			'type'       => 'object',
			'properties' => array(
				'id'      => array(
					'description' => __( 'Snapshot UUID.', 'swift-menu-duplicator' ),
					'type'        => 'string',
					'readonly'    => true,
				),
				'label'   => array(
					'description' => __( 'Human-readable snapshot label.', 'swift-menu-duplicator' ),
					'type'        => 'string',
					'readonly'    => true,
				),
				'created' => array(
					'description' => __( 'Unix timestamp of when the snapshot was taken.', 'swift-menu-duplicator' ),
					'type'        => 'integer',
					'readonly'    => true,
				),
				'items'   => array(
					'description' => __( 'Number of menu items captured.', 'swift-menu-duplicator' ),
					'type'        => 'integer',
					'readonly'    => true,
				),
			),
		);
	}

	/**
	 * Schema for responses describing a block navigation menu.
	 *
	 * @return array<string,mixed>
	 */
	public function get_navigation_schema(): array {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'swift-menu-duplicator-navigation',
			'type'       => 'object',
			'properties' => array(
				'id'       => array(
					'description' => __( 'Post ID of the navigation menu.', 'swift-menu-duplicator' ),
					'type'        => 'integer',
					'readonly'    => true,
				),
				'title'    => array(
					'description' => __( 'Navigation menu title.', 'swift-menu-duplicator' ),
					'type'        => 'string',
					'readonly'    => true,
				),
				'slug'     => array(
					'description' => __( 'Navigation menu slug.', 'swift-menu-duplicator' ),
					'type'        => 'string',
					'readonly'    => true,
				),
				'status'   => array(
					'description' => __( 'Post status.', 'swift-menu-duplicator' ),
					'type'        => 'string',
					'readonly'    => true,
				),
				'edit_url' => array(
					'description' => __( 'Site Editor URL for the navigation menu.', 'swift-menu-duplicator' ),
					'type'        => 'string',
					'format'      => 'uri',
					'readonly'    => true,
				),
			),
		);
	}

	/**
	 * Schema for the block navigation export payload.
	 *
	 * @return array<string,mixed>
	 */
	public function get_navigation_export_schema(): array {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'swift-menu-duplicator-navigation-export',
			'type'       => 'object',
			'properties' => array(
				'version'    => array(
					'description' => __( 'Plugin version that produced the export.', 'swift-menu-duplicator' ),
					'type'        => 'string',
					'readonly'    => true,
				),
				'type'       => array(
					'description' => __( 'Payload type; always "wp_navigation" here.', 'swift-menu-duplicator' ),
					'type'        => 'string',
					'readonly'    => true,
				),
				'navigation' => array(
					'description' => __( 'Navigation title, slug, and status.', 'swift-menu-duplicator' ),
					'type'        => 'object',
					'readonly'    => true,
				),
				'content'    => array(
					'description' => __( 'Block markup of the navigation menu.', 'swift-menu-duplicator' ),
					'type'        => 'string',
					'readonly'    => true,
				),
			),
		);
	}

	// -----------------------------------------------------------------------
	// Permission callback.
	// -----------------------------------------------------------------------

	/**
	 * Default permission callback: requires edit_theme_options.
	 *
	 * Filterable via `swift_menu_duplicator_rest_permission` for integrations that need to
	 * customise access control (e.g. WPML language-specific permissions).
	 *
	 * @param WP_REST_Request $request Current request.
	 *
	 * @return bool|WP_Error True if allowed, WP_Error or false otherwise.
	 */
	public function check_permission( WP_REST_Request $request ) {
		$allowed = current_user_can( 'edit_theme_options' );

		/**
		 * Filters the REST API permission check for all swift-menu-duplicator/v1 routes.
		 *
		 * @since 1.0.0
		 *
		 * @param bool             $allowed  Whether the current user is permitted.
		 * @param WP_REST_Request $request  The incoming REST request.
		 */
		$allowed = (bool) apply_filters( 'swift_menu_duplicator_rest_permission', $allowed, $request );

		if ( ! $allowed ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to perform this action.', 'swift-menu-duplicator' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	// -----------------------------------------------------------------------
	// Private helpers.
	// -----------------------------------------------------------------------

	/**
	 * Whether a nav_menu term exists.
	 *
	 * @param int $menu_id Term ID.
	 *
	 * @return bool
	 */
	private function menu_exists( int $menu_id ): bool {
		$term = get_term( $menu_id, 'nav_menu' );

		return ! is_wp_error( $term ) && $term instanceof WP_Term;
	}

	/**
	 * Builds the standard response body for a navigation menu.
	 *
	 * @param int                   $post_id    Navigation post ID.
	 * @param Navigation_Duplicator $duplicator Duplicator instance, for the edit URL.
	 *
	 * @return \WP_REST_Response
	 */
	private function navigation_response( int $post_id, Navigation_Duplicator $duplicator ) {
		$post = get_post( $post_id );

		return rest_ensure_response(
			array(
				'id'       => $post_id,
				'title'    => $post instanceof WP_Post ? $post->post_title : '',
				'slug'     => $post instanceof WP_Post ? $post->post_name : '',
				'status'   => $post instanceof WP_Post ? $post->post_status : '',
				'edit_url' => $duplicator->get_edit_url( $post_id ),
			)
		);
	}

	/**
	 * Reduces stored snapshots to the descriptor fields the API exposes.
	 *
	 * The stored payload itself is deliberately left out — it can be large, and
	 * the export endpoint already serves menu contents.
	 *
	 * @param int $menu_id Menu term ID.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function prepare_snapshots( int $menu_id ): array {
		$snapshots = ( new Menu_Duplicator() )->get_snapshots( $menu_id );

		return array_map(
			static function ( array $snapshot ): array {
				return array(
					'id'      => (string) $snapshot['id'],
					'label'   => (string) $snapshot['label'],
					'created' => (int) $snapshot['created'],
					'items'   => isset( $snapshot['data']['items'] ) ? count( $snapshot['data']['items'] ) : 0,
				);
			},
			$snapshots
		);
	}

	/**
	 * Converts a WP_Error into a REST error response with an appropriate
	 * HTTP status code.
	 *
	 * @param WP_Error $error Source error.
	 *
	 * @return WP_Error REST-formatted error.
	 */
	private function error_response( WP_Error $error ): WP_Error {
		$code = $error->get_error_code();

		$status_map = array(
			'invalid_menu'             => 404,
			'invalid_item'             => 404,
			'invalid_snapshot'         => 404,
			'invalid_navigation'       => 404,
			'missing_key'              => 400,
			'missing_menu_name'        => 400,
			'missing_navigation_title' => 400,
			'invalid_items'            => 400,
			'too_many_items'           => 400,
			'too_large'                => 400,
		);

		$status = $status_map[ $code ] ?? 500;

		$error->add_data( array( 'status' => $status ) );

		return $error;
	}
}
