<?php
/**
 * Admin page: bulk menu manager, importer, and multisite copy.
 *
 * @package ClassicMenuDuplicator
 */

declare( strict_types=1 );

namespace ClassicMenuDuplicator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Menu_Admin_Page
 *
 * Registers the "Menu Manager" submenu page under Appearance, renders
 * the WP_List_Table, handles the import form (with preview and URL
 * replacement), the multisite copy UI, and all related AJAX/form
 * submissions for Tier 2 features.
 */
class Menu_Admin_Page {

	/**
	 * Admin page hook suffix returned by add_submenu_page().
	 *
	 * @var string
	 */
	private string $page_hook = '';

	/**
	 * Registers WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_menu_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_page_scripts' ) );

		// Form submissions (non-AJAX).
		add_action( 'admin_init', array( $this, 'handle_import_form' ) );
		add_action( 'admin_init', array( $this, 'handle_bulk_actions' ) );

		// AJAX for bulk table row actions.
		add_action( 'wp_ajax_cmd_bulk_duplicate', array( $this, 'handle_ajax_bulk_duplicate' ) );
		add_action( 'wp_ajax_cmd_bulk_export_zip', array( $this, 'handle_ajax_bulk_export_zip' ) );

		// AJAX for multisite copy (network-admin capable users only).
		add_action( 'wp_ajax_cmd_copy_to_site', array( $this, 'handle_ajax_copy_to_site' ) );

		// Store creation timestamp on new menus.
		add_action( 'wp_create_nav_menu', array( $this, 'record_creation_time' ) );
	}

	/**
	 * Registers the Appearance → Menu Manager submenu page.
	 *
	 * @return void
	 */
	public function register_menu_page(): void {
		$this->page_hook = (string) add_submenu_page(
			'themes.php',
			__( 'Menu Manager', 'classic-menu-duplicator' ),
			__( 'Menu Manager', 'classic-menu-duplicator' ),
			'edit_theme_options',
			'cmd-menu-manager',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueues scripts and styles scoped to the Menu Manager page.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 *
	 * @return void
	 */
	public function enqueue_page_scripts( string $hook_suffix ): void {
		if ( $this->page_hook !== $hook_suffix ) {
			return;
		}

		$asset_file = CLASSIC_MENU_DUPLICATOR_DIR . 'assets/js/menu-manager.js';

		wp_enqueue_script(
			'cmd-menu-manager',
			CLASSIC_MENU_DUPLICATOR_URL . 'assets/js/menu-manager.js',
			array( 'jquery' ),
			file_exists( $asset_file )
				? (string) filemtime( $asset_file )
				: CLASSIC_MENU_DUPLICATOR_VERSION,
			true
		);

		$sites_data = array();

		if ( is_multisite() && current_user_can( 'manage_network' ) ) {
			$sites = get_sites( array( 'number' => 100, 'spam' => 0, 'deleted' => 0, 'archived' => 0 ) );

			foreach ( $sites as $site ) {
				$blog_id = (int) $site->blog_id;

				if ( $blog_id === get_current_blog_id() ) {
					continue;
				}

				$sites_data[] = array(
					'id'   => $blog_id,
					'name' => get_blog_details( $blog_id )->blogname ?? "Site {$blog_id}",
				);
			}
		}

		wp_localize_script(
			'cmd-menu-manager',
			'cmdManagerData',
			array(
				'ajaxUrl'               => admin_url( 'admin-ajax.php' ),
				'nonce'                 => wp_create_nonce( 'cmd_menu_actions' ),
				'sites'                 => $sites_data,
				'isMultisite'           => is_multisite() && current_user_can( 'manage_network' ),
				// Strings.
				'copyToSiteLabel'       => __( 'Copy to site…', 'classic-menu-duplicator' ),
				'copyingLabel'          => __( 'Copying…', 'classic-menu-duplicator' ),
				'copiedLabel'           => __( 'Copied!', 'classic-menu-duplicator' ),
				'selectSiteLabel'       => __( 'Select destination site', 'classic-menu-duplicator' ),
				'exportingLabel'        => __( 'Exporting…', 'classic-menu-duplicator' ),
				'duplicatingLabel'      => __( 'Duplicating…', 'classic-menu-duplicator' ),
				'duplicatedLabel'       => __( 'Duplicated!', 'classic-menu-duplicator' ),
				'errorMessage'          => __( 'Action failed. Please try again.', 'classic-menu-duplicator' ),
				'confirmBulkDeleteText' => __( 'Delete selected menus? This cannot be undone.', 'classic-menu-duplicator' ),
			)
		);
	}

	/**
	 * Records the current timestamp as term-meta when a new menu is created.
	 *
	 * @param int $menu_id New menu term ID.
	 *
	 * @return void
	 */
	public function record_creation_time( int $menu_id ): void {
		add_term_meta( $menu_id, '_cmd_created', time(), true );
	}

	// -----------------------------------------------------------------------
	// Page rendering.
	// -----------------------------------------------------------------------

	/**
	 * Renders the full Menu Manager admin page.
	 *
	 * The page has three sections: the bulk menu table (always visible),
	 * a collapsible import form, and — on multisite networks — a
	 * collapsible cross-site copy form.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'classic-menu-duplicator' ) );
		}

		$table = new Menu_Table();
		$table->prepare_items();

		include CLASSIC_MENU_DUPLICATOR_DIR . 'templates/admin/menu-manager.php';
	}

	// -----------------------------------------------------------------------
	// Form handlers (non-AJAX, standard admin_init flow).
	// -----------------------------------------------------------------------

	/**
	 * Handles the JSON import form submission (both preview and live import).
	 *
	 * Detects whether the `_cmd_import_action` hidden field equals 'preview'
	 * or 'import', validates the uploaded file, and stores the result in a
	 * transient keyed to the current user ID for the template to consume.
	 *
	 * @return void
	 */
	public function handle_import_form(): void {
		if ( ! isset( $_POST['_cmd_import_action'] ) ) {
			return;
		}

		$action = sanitize_key( $_POST['_cmd_import_action'] );

		if ( ! in_array( $action, array( 'preview', 'import' ), true ) ) {
			return;
		}

		check_admin_referer( 'cmd_import_menu' );

		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'classic-menu-duplicator' ) );
		}

		$find    = isset( $_POST['cmd_find'] ) ? sanitize_text_field( wp_unslash( $_POST['cmd_find'] ) ) : '';
		$replace = isset( $_POST['cmd_replace'] ) ? sanitize_text_field( wp_unslash( $_POST['cmd_replace'] ) ) : '';
		$name    = isset( $_POST['cmd_menu_name'] ) ? sanitize_text_field( wp_unslash( $_POST['cmd_menu_name'] ) ) : '';

		// Resolve JSON: either from a fresh upload or from a previously
		// base64-encoded hidden field (re-submitted from the preview step).
		$json = '';

		if ( ! empty( $_FILES['cmd_json_file']['tmp_name'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$json = file_get_contents( sanitize_text_field( wp_unslash( $_FILES['cmd_json_file']['tmp_name'] ) ) );
			$json = ( false === $json ) ? '' : $json;
		} elseif ( ! empty( $_POST['cmd_json_data'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$json = base64_decode( sanitize_text_field( wp_unslash( $_POST['cmd_json_data'] ) ), true );
			$json = ( false === $json ) ? '' : $json;
		}

		$importer = new Menu_Importer();
		$payload  = $importer->parse( $json );

		$transient_key = 'cmd_import_state_' . get_current_user_id();

		if ( is_wp_error( $payload ) ) {
			set_transient(
				$transient_key,
				array( 'error' => $payload->get_error_message() ),
				60
			);
			wp_safe_redirect( admin_url( 'themes.php?page=cmd-menu-manager&tab=import' ) );
			exit;
		}

		if ( 'preview' === $action ) {
			$preview = $importer->preview( $payload, $name, $find, $replace );

			set_transient(
				$transient_key,
				array(
					'preview'   => $preview,
					'json_data' => base64_encode( $json ),
					// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
					'find'      => $find,
					'replace'   => $replace,
					'name'      => $name,
				),
				300
			);
			wp_safe_redirect( admin_url( 'themes.php?page=cmd-menu-manager&tab=import' ) );
			exit;
		}

		// Live import.
		$new_menu_id = $importer->import( $payload, $name, $find, $replace );

		if ( is_wp_error( $new_menu_id ) ) {
			set_transient(
				$transient_key,
				array( 'error' => $new_menu_id->get_error_message() ),
				60
			);
		} else {
			set_transient(
				$transient_key,
				array(
					'success'     => true,
					'new_menu_id' => $new_menu_id,
					'menu_name'   => get_term( $new_menu_id, 'nav_menu' )->name ?? '',
				),
				60
			);
		}

		wp_safe_redirect( admin_url( 'themes.php?page=cmd-menu-manager&tab=import' ) );
		exit;
	}

	/**
	 * Handles bulk delete form submissions from the Menu_Table.
	 *
	 * Duplicate and export bulk actions are handled client-side via AJAX.
	 * Only delete requires a form POST for safety (irreversible operation).
	 *
	 * @return void
	 */
	public function handle_bulk_actions(): void {
		if ( ! isset( $_POST['action'] ) || 'cmd_bulk_delete' !== $_POST['action'] ) {
			return;
		}

		if ( empty( $_POST['cmd_bulk_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cmd_bulk_nonce'] ) ), 'cmd_bulk_delete' ) ) {
			return;
		}

		if ( ! current_user_can( 'delete_theme_options' ) ) {
			return;
		}

		$ids = isset( $_POST['menu_ids'] ) ? array_map( 'absint', (array) $_POST['menu_ids'] ) : array();

		foreach ( $ids as $id ) {
			wp_delete_nav_menu( $id );
		}

		wp_safe_redirect( admin_url( 'themes.php?page=cmd-menu-manager&deleted=' . count( $ids ) ) );
		exit;
	}

	// -----------------------------------------------------------------------
	// AJAX handlers — page-specific.
	// -----------------------------------------------------------------------

	/**
	 * Duplicates one or more menus via AJAX (row action or bulk).
	 *
	 * @return void
	 */
	public function handle_ajax_bulk_duplicate(): void {
		$this->verify_nonce_and_capability();

		$ids = isset( $_POST['menu_ids'] ) ? array_map( 'absint', (array) $_POST['menu_ids'] ) : array();

		if ( empty( $ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No menus selected.', 'classic-menu-duplicator' ) ), 400 );
		}

		$duplicator = new Menu_Duplicator();
		$created    = array();
		$errors     = array();

		foreach ( $ids as $id ) {
			$result = $duplicator->duplicate( $id );

			if ( is_wp_error( $result ) ) {
				$errors[] = $result->get_error_message();
			} else {
				$created[] = $result;
			}
		}

		wp_send_json_success(
			array(
				'created' => $created,
				'errors'  => $errors,
				'reload'  => true,
			)
		);
	}

	/**
	 * Exports one or more menus as a ZIP archive of JSON files.
	 *
	 * Falls back to a single JSON file download when only one menu is
	 * selected (avoids requiring ZipArchive on the server).
	 *
	 * @return void
	 */
	public function handle_ajax_bulk_export_zip(): void {
		$this->verify_nonce_and_capability();

		$ids = isset( $_POST['menu_ids'] ) ? array_map( 'absint', (array) $_POST['menu_ids'] ) : array();

		if ( empty( $ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No menus selected.', 'classic-menu-duplicator' ) ), 400 );
		}

		$duplicator = new Menu_Duplicator();

		// Single menu: stream JSON directly (matches nav-menus.php export).
		if ( 1 === count( $ids ) ) {
			$payload = $duplicator->export( $ids[0] );

			if ( is_wp_error( $payload ) ) {
				wp_send_json_error( array( 'message' => $payload->get_error_message() ), 500 );
			}

			$term = get_term( $ids[0], 'nav_menu' );
			$slug = ( $term instanceof \WP_Term ) ? sanitize_file_name( $term->slug ) : 'menu';

			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="' . $slug . '-menu-export.json"' );
			header( 'Pragma: no-cache' );

			// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
			echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			exit;
		}

		// Multiple menus: ZIP if ZipArchive is available.
		if ( ! class_exists( 'ZipArchive' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'ZIP export requires the PHP ZipArchive extension.', 'classic-menu-duplicator' ) ),
				500
			);
		}

		$zip_file = wp_tempnam( 'cmd-export' );
		$zip      = new \ZipArchive();

		if ( true !== $zip->open( $zip_file, \ZipArchive::OVERWRITE ) ) {
			wp_send_json_error( array( 'message' => __( 'Could not create ZIP archive.', 'classic-menu-duplicator' ) ), 500 );
		}

		foreach ( $ids as $id ) {
			$payload = $duplicator->export( $id );

			if ( is_wp_error( $payload ) ) {
				continue;
			}

			$term = get_term( $id, 'nav_menu' );
			$slug = ( $term instanceof \WP_Term ) ? sanitize_file_name( $term->slug ) : 'menu-' . $id;

			// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
			$zip->addFromString(
				$slug . '-menu-export.json',
				(string) wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
			);
		}

		$zip->close();

		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="menus-export.zip"' );
		header( 'Content-Length: ' . filesize( $zip_file ) );
		header( 'Pragma: no-cache' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		readfile( $zip_file );
		unlink( $zip_file );
		exit;
	}

	/**
	 * Copies a menu to another sub-site on a multisite network.
	 *
	 * Restricted to users with manage_network capability.
	 *
	 * @return void
	 */
	public function handle_ajax_copy_to_site(): void {
		$nonce = isset( $_POST['nonce'] )
			? sanitize_text_field( wp_unslash( $_POST['nonce'] ) )
			: '';

		if ( ! wp_verify_nonce( $nonce, 'cmd_menu_actions' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'classic-menu-duplicator' ) ), 403 );
		}

		if ( ! is_multisite() || ! current_user_can( 'manage_network' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'classic-menu-duplicator' ) ), 403 );
		}

		$source_menu_id = isset( $_POST['menu_id'] ) ? absint( $_POST['menu_id'] ) : 0;
		$target_blog_id = isset( $_POST['target_blog_id'] ) ? absint( $_POST['target_blog_id'] ) : 0;
		$new_name       = isset( $_POST['menu_name'] )
			? sanitize_text_field( wp_unslash( $_POST['menu_name'] ) )
			: '';

		if ( $source_menu_id <= 0 || $target_blog_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid menu or site ID.', 'classic-menu-duplicator' ) ), 400 );
		}

		// Export from current site.
		$duplicator = new Menu_Duplicator();
		$payload    = $duplicator->export( $source_menu_id );

		if ( is_wp_error( $payload ) ) {
			wp_send_json_error( array( 'message' => $payload->get_error_message() ), 500 );
		}

		// Switch context and import on the target site.
		switch_to_blog( $target_blog_id );

		$importer    = new Menu_Importer();
		$new_menu_id = $importer->import( $payload, $new_name );

		restore_current_blog();

		if ( is_wp_error( $new_menu_id ) ) {
			wp_send_json_error( array( 'message' => $new_menu_id->get_error_message() ), 500 );
		}

		$target_edit_url = get_admin_url( $target_blog_id, 'nav-menus.php?action=edit&menu=' . $new_menu_id );

		wp_send_json_success(
			array(
				'new_menu_id' => $new_menu_id,
				'edit_url'    => $target_edit_url,
			)
		);
	}

	// -----------------------------------------------------------------------
	// Private helpers.
	// -----------------------------------------------------------------------

	/**
	 * Shared nonce + capability check for page AJAX handlers.
	 *
	 * @return void
	 */
	private function verify_nonce_and_capability(): void {
		$nonce = isset( $_POST['nonce'] )
			? sanitize_text_field( wp_unslash( $_POST['nonce'] ) )
			: '';

		if ( ! wp_verify_nonce( $nonce, 'cmd_menu_actions' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'classic-menu-duplicator' ) ), 403 );
		}

		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'classic-menu-duplicator' ) ), 403 );
		}
	}
}
