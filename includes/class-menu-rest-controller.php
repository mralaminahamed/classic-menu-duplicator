<?php
/**
 * REST API controller for Classic Menu Duplicator.
 *
 * @package ClassicMenuDuplicator
 */

declare( strict_types=1 );

namespace ClassicMenuDuplicator;

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
 * Registers a versioned REST namespace `/cmd/v1` and exposes three
 * endpoints that mirror the existing AJAX surface, making the plugin's
 * core operations consumable by headless frontends, block-editor
 * extensions, and external automation pipelines.
 *
 * Routes:
 *   POST /cmd/v1/menus/{id}/duplicate
 *   GET  /cmd/v1/menus/{id}/export
 *   POST /cmd/v1/menus/{id}/items/{item_id}/duplicate
 *
 * All routes require the `edit_theme_options` capability by default.
 * The permission callback is filterable via `cmd_rest_permission`.
 */
class Menu_REST_Controller {

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	private const NAMESPACE = 'cmd/v1';

	/**
	 * Registers REST routes on the rest_api_init hook.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Registers all /cmd/v1/ routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// POST /cmd/v1/menus/{id}/duplicate
		register_rest_route(
			self::NAMESPACE,
			'/menus/(?P<id>[\d]+)/duplicate',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'duplicate_menu' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'id'   => array(
						'description'       => __( 'Term ID of the menu to duplicate.', 'classic-menu-duplicator' ),
						'type'              => 'integer',
						'required'          => true,
						'minimum'           => 1,
						'sanitize_callback' => 'absint',
						'validate_callback' => 'rest_validate_request_arg',
					),
					'name' => array(
						'description'       => __( 'Optional name for the duplicated menu. Defaults to "{original} (Copy)".', 'classic-menu-duplicator' ),
						'type'              => 'string',
						'required'          => false,
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// GET /cmd/v1/menus/{id}/export
		register_rest_route(
			self::NAMESPACE,
			'/menus/(?P<id>[\d]+)/export',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'export_menu' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'id' => array(
						'description'       => __( 'Term ID of the menu to export.', 'classic-menu-duplicator' ),
						'type'              => 'integer',
						'required'          => true,
						'minimum'           => 1,
						'sanitize_callback' => 'absint',
						'validate_callback' => 'rest_validate_request_arg',
					),
				),
			)
		);

		// POST /cmd/v1/menus/{id}/items/{item_id}/duplicate
		register_rest_route(
			self::NAMESPACE,
			'/menus/(?P<id>[\d]+)/items/(?P<item_id>[\d]+)/duplicate',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'duplicate_item' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'id'      => array(
						'description'       => __( 'Term ID of the menu that owns the item.', 'classic-menu-duplicator' ),
						'type'              => 'integer',
						'required'          => true,
						'minimum'           => 1,
						'sanitize_callback' => 'absint',
						'validate_callback' => 'rest_validate_request_arg',
					),
					'item_id' => array(
						'description'       => __( 'Post ID of the nav_menu_item to duplicate.', 'classic-menu-duplicator' ),
						'type'              => 'integer',
						'required'          => true,
						'minimum'           => 1,
						'sanitize_callback' => 'absint',
						'validate_callback' => 'rest_validate_request_arg',
					),
				),
			)
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

	// -----------------------------------------------------------------------
	// Permission callback.
	// -----------------------------------------------------------------------

	/**
	 * Default permission callback: requires edit_theme_options.
	 *
	 * Filterable via `cmd_rest_permission` for integrations that need to
	 * customise access control (e.g. WPML language-specific permissions).
	 *
	 * @param WP_REST_Request $request Current request.
	 *
	 * @return bool|WP_Error True if allowed, WP_Error or false otherwise.
	 */
	public function check_permission( WP_REST_Request $request ) {
		$allowed = current_user_can( 'edit_theme_options' );

		/**
		 * Filters the REST API permission check for all cmd/v1 routes.
		 *
		 * @since 1.1.0
		 *
		 * @param bool             $allowed  Whether the current user is permitted.
		 * @param WP_REST_Request $request  The incoming REST request.
		 */
		$allowed = (bool) apply_filters( 'classic_menu_duplicator_rest_permission', $allowed, $request );

		if ( ! $allowed ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to perform this action.', 'classic-menu-duplicator' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	// -----------------------------------------------------------------------
	// Private helpers.
	// -----------------------------------------------------------------------

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
			'invalid_menu' => 404,
			'invalid_item' => 404,
		);

		$status = $status_map[ $code ] ?? 500;

		$error->add_data( array( 'status' => $status ) );

		return $error;
	}
}
