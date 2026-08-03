<?php
/**
 * Admin page: bulk menu manager, importer, and multisite copy.
 *
 * @package SwiftMenuDuplicator
 */

declare( strict_types=1 );

namespace SwiftMenuDuplicator\Admin;

use SwiftMenuDuplicator\Core\Menu_Duplicator;
use SwiftMenuDuplicator\Core\Navigation_Duplicator;
use SwiftMenuDuplicator\Import\Menu_Importer;
use SwiftMenuDuplicator\Utils\Filesystem;
use WP_Term;
use ZipArchive;

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
		add_action( 'wp_ajax_swmd_bulk_duplicate', array( $this, 'handle_ajax_bulk_duplicate' ) );
		add_action( 'wp_ajax_swmd_bulk_export_zip', array( $this, 'handle_ajax_bulk_export_zip' ) );

		// AJAX for multisite copy (network-admin capable users only).
		add_action( 'wp_ajax_swmd_copy_to_site', array( $this, 'handle_ajax_copy_to_site' ) );

		// AJAX for block-theme navigation menus.
		add_action( 'wp_ajax_swmd_duplicate_navigation', array( $this, 'handle_ajax_duplicate_navigation' ) );
		add_action( 'wp_ajax_swmd_export_navigation', array( $this, 'handle_ajax_export_navigation' ) );

		// Persist the Screen Options "Menus per page" value.
		add_filter( 'set_screen_option_swmd_menus_per_page', array( $this, 'save_screen_option' ), 10, 3 );

		// Store creation timestamp on new menus.
		add_action( 'wp_create_nav_menu', array( $this, 'record_creation_time' ) );

		// Explain the classic-menu scope on block themes.
		add_action( 'admin_notices', array( $this, 'maybe_render_block_theme_notice' ) );

		// Plugin action links on the Plugins list page.
		add_filter(
			'plugin_action_links_' . plugin_basename( SWIFT_MENU_DUPLICATOR_FILE ),
			array( $this, 'add_action_links' )
		);
	}

	/**
	 * Registers the Appearance → Menu Manager submenu page.
	 *
	 * @return void
	 */
	public function register_menu_page(): void {
		$this->page_hook = (string) add_submenu_page(
			'themes.php',
			__( 'Menu Manager', 'swift-menu-duplicator' ),
			__( 'Menu Manager', 'swift-menu-duplicator' ),
			'edit_theme_options',
			'swmd-menu-manager',
			array( $this, 'render_page' )
		);

		if ( '' !== $this->page_hook ) {
			add_action( 'load-' . $this->page_hook, array( $this, 'add_screen_options' ) );
		}
	}

	/**
	 * Registers the Screen Options control for the menus table.
	 *
	 * Menu_Table reads `swmd_menus_per_page` through get_items_per_page(); with
	 * no add_screen_option() call there was no way for a user to set it, so the
	 * default was the only value it ever had.
	 *
	 * @return void
	 */
	public function add_screen_options(): void {
		add_screen_option(
			'per_page',
			array(
				'label'   => __( 'Menus per page', 'swift-menu-duplicator' ),
				'default' => 20,
				'option'  => 'swmd_menus_per_page',
			)
		);

		// Registering the columns on the screen is what makes the Screen Options
		// "Columns" checkboxes appear, so Slug and Description can stay hidden
		// until someone wants them.
		add_filter(
			'manage_' . $this->page_hook . '_columns',
			static function (): array {
				return ( new Menu_Table() )->get_columns();
			}
		);

		add_filter(
			'default_hidden_columns',
			static function ( array $hidden, \WP_Screen $screen ): array {
				// strpos(), not str_contains(): the plugin supports PHP 7.4.
				if ( false === strpos( $screen->id, 'swmd-menu-manager' ) ) {
					return $hidden;
				}

				return array_merge( $hidden, Menu_Table::default_hidden_columns() );
			},
			10,
			2
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

		$style_file = SWIFT_MENU_DUPLICATOR_DIR . 'assets/css/menu-manager.css';

		wp_enqueue_style(
			'swmd-menu-manager',
			SWIFT_MENU_DUPLICATOR_URL . 'assets/css/menu-manager.css',
			array(),
			file_exists( $style_file )
				? (string) filemtime( $style_file )
				: SWIFT_MENU_DUPLICATOR_VERSION
		);

		$asset_file = SWIFT_MENU_DUPLICATOR_DIR . 'assets/js/menu-manager.js';

		wp_enqueue_script(
			'swmd-menu-manager',
			SWIFT_MENU_DUPLICATOR_URL . 'assets/js/menu-manager.js',
			array( 'jquery', 'wp-a11y' ),
			file_exists( $asset_file )
				? (string) filemtime( $asset_file )
				: SWIFT_MENU_DUPLICATOR_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		$sites_data = array();

		if ( is_multisite() && current_user_can( 'manage_network' ) ) {
			$sites = get_sites(
				array(
					'number'   => 100,
					'spam'     => 0,
					'deleted'  => 0,
					'archived' => 0,
				)
			);

			foreach ( $sites as $site ) {
				$blog_id = (int) $site->blog_id;

				if ( get_current_blog_id() === $blog_id ) {
					continue;
				}

				$sites_data[] = array(
					'id'   => $blog_id,
					'name' => '' !== $site->blogname ? $site->blogname : sprintf(
						/* translators: %d: numeric site ID on a multisite network */
						__( 'Site %d', 'swift-menu-duplicator' ),
						$blog_id
					),
				);
			}
		}

		wp_localize_script(
			'swmd-menu-manager',
			'swmdManagerData',
			array(
				'ajaxUrl'               => admin_url( 'admin-ajax.php' ),
				'nonce'                 => wp_create_nonce( 'swmd_menu_actions' ),
				'sites'                 => $sites_data,
				'isMultisite'           => is_multisite() && current_user_can( 'manage_network' ),
				// Strings.
				'copyToSiteLabel'       => __( 'Copy to site…', 'swift-menu-duplicator' ),
				'copyingLabel'          => __( 'Copying…', 'swift-menu-duplicator' ),
				'copiedLabel'           => __( 'Copied!', 'swift-menu-duplicator' ),
				'selectSiteLabel'       => __( 'Select destination site', 'swift-menu-duplicator' ),
				'exportingLabel'        => __( 'Exporting…', 'swift-menu-duplicator' ),
				'duplicatingLabel'      => __( 'Duplicating…', 'swift-menu-duplicator' ),
				'duplicatedLabel'       => __( 'Duplicated!', 'swift-menu-duplicator' ),
				'duplicateLabel'        => __( 'Duplicate', 'swift-menu-duplicator' ),
				'exportLabel'           => __( 'Export JSON', 'swift-menu-duplicator' ),
				'errorMessage'          => __( 'Action failed. Please try again.', 'swift-menu-duplicator' ),
				'confirmBulkDeleteText' => __( 'Delete selected menus? This cannot be undone.', 'swift-menu-duplicator' ),
			)
		);
	}

	/**
	 * Saves the "Menus per page" screen option.
	 *
	 * @param mixed  $status Value to save, or false to keep the default.
	 * @param string $option Option name.
	 * @param mixed  $value  Submitted value.
	 *
	 * @return int|false Sanitised per-page value, or false to skip saving.
	 */
	public function save_screen_option( $status, string $option, $value ) {
		if ( 'swmd_menus_per_page' !== $option ) {
			return $status;
		}

		$value = absint( $value );

		return ( $value > 0 && $value <= 999 ) ? $value : false;
	}

	/**
	 * Records the current timestamp as term-meta when a new menu is created.
	 *
	 * @param int $menu_id New menu term ID.
	 *
	 * @return void
	 */
	public function record_creation_time( int $menu_id ): void {
		add_term_meta( $menu_id, '_swmd_created', time(), true );
	}

	/**
	 * Warns, on the Menu Manager screen only, that block themes do not render
	 * classic menus.
	 *
	 * Core hides Appearance → Menus entirely unless the theme supports `menus`
	 * or `widgets`, and a block theme renders Navigation blocks instead of
	 * nav_menu terms. Saying so is more useful than letting someone duplicate a
	 * menu their front end will never display.
	 *
	 * @return void
	 */
	public function maybe_render_block_theme_notice(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen instanceof \WP_Screen || $screen->id !== $this->page_hook ) {
			return;
		}

		if ( ! function_exists( 'wp_is_block_theme' ) || ! wp_is_block_theme() ) {
			return;
		}

		if ( current_theme_supports( 'menus' ) || current_theme_supports( 'widgets' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-info"><p>%s %s</p></div>',
			esc_html__( 'This theme is a block theme: its navigation lives in Navigation blocks, not in the classic menus listed here.', 'swift-menu-duplicator' ),
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'site-editor.php' ) ),
				esc_html__( 'Edit navigation in the Site Editor', 'swift-menu-duplicator' )
			)
		);
	}

	/**
	 * Adds Menu Manager and Nav Menus links to the plugin row on the Plugins page.
	 *
	 * @param array<int,string> $links Existing action links.
	 *
	 * @return array<int,string>
	 */
	public function add_action_links( array $links ): array {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return $links;
		}

		$plugin_links = array(
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'themes.php?page=swmd-menu-manager' ) ),
				esc_html__( 'Menu Manager', 'swift-menu-duplicator' )
			),
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'nav-menus.php' ) ),
				esc_html__( 'Nav Menus', 'swift-menu-duplicator' )
			),
		);

		return array_merge( $plugin_links, $links );
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
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'swift-menu-duplicator' ) );
		}

		$table = new Menu_Table();
		$table->prepare_items();

		$has_block_navigation = $this->has_block_navigation();
		$navigation_table     = null;

		if ( $has_block_navigation ) {
			$navigation_table = new Navigation_Table();
			$navigation_table->prepare_items();
		}

		include SWIFT_MENU_DUPLICATOR_DIR . 'templates/admin/menu-manager.php';
	}

	// -----------------------------------------------------------------------
	// Form handlers (non-AJAX, standard admin_init flow).
	// -----------------------------------------------------------------------

	/**
	 * Handles the JSON import form submission (both preview and live import).
	 *
	 * Detects whether the `_swmd_import_action` hidden field equals 'preview'
	 * or 'import', validates the uploaded file, and stores the result in a
	 * transient keyed to the current user ID for the template to consume.
	 *
	 * @return void
	 */
	public function handle_import_form(): void {
		if ( ! isset( $_POST['_swmd_import_action'] ) ) {
			return;
		}

		$action = sanitize_key( $_POST['_swmd_import_action'] );

		if ( ! in_array( $action, array( 'preview', 'import' ), true ) ) {
			return;
		}

		check_admin_referer( 'swmd_import_menu' );

		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'swift-menu-duplicator' ) );
		}

		$find    = isset( $_POST['swmd_find'] ) ? sanitize_text_field( wp_unslash( $_POST['swmd_find'] ) ) : '';
		$replace = isset( $_POST['swmd_replace'] ) ? sanitize_text_field( wp_unslash( $_POST['swmd_replace'] ) ) : '';
		$name    = isset( $_POST['swmd_menu_name'] ) ? sanitize_text_field( wp_unslash( $_POST['swmd_menu_name'] ) ) : '';

		$transient_key = 'swmd_import_state_' . get_current_user_id();

		// Resolve JSON from one of three sources: a fresh upload, JSON pasted
		// into the textarea, or the base64-encoded hidden field re-submitted
		// from the preview step.
		$json = '';

		if ( ! empty( $_FILES['swmd_json_file']['tmp_name'] ) ) {
			$tmp  = sanitize_text_field( wp_unslash( $_FILES['swmd_json_file']['tmp_name'] ) );
			$size = isset( $_FILES['swmd_json_file']['size'] ) ? (int) $_FILES['swmd_json_file']['size'] : 0;

			// Accept only a genuine PHP upload within a sane size ceiling (2 MB
			// by default) — the client-side `accept=".json"` is not enforceable.
			$max_bytes = (int) apply_filters( 'swift_menu_duplicator_max_import_bytes', 2 * MB_IN_BYTES );

			if ( ! is_uploaded_file( $tmp ) || $size <= 0 || $size > $max_bytes ) {
				set_transient(
					$transient_key,
					array( 'error' => __( 'The uploaded file is invalid or exceeds the size limit.', 'swift-menu-duplicator' ) ),
					60
				);
				wp_safe_redirect( admin_url( 'themes.php?page=swmd-menu-manager&tab=import' ) );
				exit;
			}

			$json = Filesystem::read( $tmp );
			$json = ( false === $json ) ? '' : $json;
		} elseif ( ! empty( $_POST['swmd_json_paste'] ) ) {
			// Raw JSON typed or pasted by the user. Only unslashed here — the
			// importer validates the structure and sanitizes every field.
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$json      = (string) wp_unslash( $_POST['swmd_json_paste'] );
			$max_bytes = (int) apply_filters( 'swift_menu_duplicator_max_import_bytes', 2 * MB_IN_BYTES );

			if ( strlen( $json ) > $max_bytes ) {
				set_transient(
					$transient_key,
					array( 'error' => __( 'The pasted JSON exceeds the size limit.', 'swift-menu-duplicator' ) ),
					60
				);
				wp_safe_redirect( admin_url( 'themes.php?page=swmd-menu-manager&tab=import' ) );
				exit;
			}
		} elseif ( ! empty( $_POST['swmd_json_data'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$json = base64_decode( sanitize_text_field( wp_unslash( $_POST['swmd_json_data'] ) ), true );
			$json = ( false === $json ) ? '' : $json;
		}

		$importer = new Menu_Importer();
		$payload  = $importer->parse( $json );

		if ( is_wp_error( $payload ) ) {
			set_transient(
				$transient_key,
				array( 'error' => $payload->get_error_message() ),
				60
			);
			wp_safe_redirect( admin_url( 'themes.php?page=swmd-menu-manager&tab=import' ) );
			exit;
		}

		if ( 'preview' === $action ) {
			$preview = $importer->preview( $payload, $name, $find, $replace );

			set_transient(
				$transient_key,
				array(
					'preview'   => $preview,
					// Round-trips the raw JSON through a hidden field between the
					// preview and import steps; not used for obfuscation.
					'json_data' => base64_encode( $json ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
					'find'      => $find,
					'replace'   => $replace,
					'name'      => $name,
				),
				300
			);
			wp_safe_redirect( admin_url( 'themes.php?page=swmd-menu-manager&tab=import' ) );
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

		wp_safe_redirect( admin_url( 'themes.php?page=swmd-menu-manager&tab=import' ) );
		exit;
	}

	/**
	 * Handles bulk action form submissions from the Menu_Table.
	 *
	 * The Menu Manager's JavaScript intercepts duplicate and export and runs
	 * them over AJAX, but the bulk-action <select> offers them regardless of
	 * whether that script loaded. Every action therefore has a server-side
	 * path, so a scripting failure degrades instead of silently doing nothing.
	 *
	 * @return void
	 */
	public function handle_bulk_actions(): void {
		$action = $this->current_bulk_action();

		if ( '' === $action ) {
			return;
		}

		if ( empty( $_POST['swmd_bulk_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['swmd_bulk_nonce'] ) ), 'swmd_bulk_delete' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return;
		}

		if ( in_array( $action, array( 'swmd_bulk_duplicate_navigation', 'swmd_bulk_delete_navigation' ), true ) ) {
			$this->handle_navigation_bulk_action( $action );

			return;
		}

		$ids = isset( $_POST['menu_ids'] ) ? array_map( 'absint', (array) $_POST['menu_ids'] ) : array();
		$ids = array_values( array_filter( $ids ) );

		if ( empty( $ids ) ) {
			wp_safe_redirect( admin_url( 'themes.php?page=swmd-menu-manager' ) );
			exit;
		}

		if ( 'swmd_bulk_delete' === $action ) {
			foreach ( $ids as $id ) {
				wp_delete_nav_menu( $id );
			}

			wp_safe_redirect( admin_url( 'themes.php?page=swmd-menu-manager&deleted=' . count( $ids ) ) );
			exit;
		}

		if ( 'swmd_bulk_duplicate' === $action ) {
			$duplicator = new Menu_Duplicator();
			$created    = 0;

			foreach ( $ids as $id ) {
				if ( ! is_wp_error( $duplicator->duplicate( $id ) ) ) {
					++$created;
				}
			}

			wp_safe_redirect( admin_url( 'themes.php?page=swmd-menu-manager&duplicated=' . $created ) );
			exit;
		}

		if ( 'swmd_bulk_export' === $action ) {
			$this->stream_export( $ids );
		}
	}

	/**
	 * Applies a bulk action to the selected block navigation menus.
	 *
	 * Deletion goes to the trash rather than deleting outright: navigation
	 * posts support revisions and are recoverable in the Site Editor.
	 *
	 * @param string $action Bulk action name.
	 *
	 * @return void
	 */
	private function handle_navigation_bulk_action( string $action ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by the caller.
		$ids = isset( $_POST['navigation_ids'] ) ? array_map( 'absint', (array) $_POST['navigation_ids'] ) : array();
		$ids = array_values( array_filter( $ids ) );

		if ( empty( $ids ) ) {
			wp_safe_redirect( admin_url( 'themes.php?page=swmd-menu-manager&tab=navigation' ) );
			exit;
		}

		$duplicator = new Navigation_Duplicator();
		$done       = 0;

		foreach ( $ids as $id ) {
			if ( 'swmd_bulk_delete_navigation' === $action ) {
				if ( wp_trash_post( $id ) ) {
					++$done;
				}

				continue;
			}

			if ( ! is_wp_error( $duplicator->duplicate( $id ) ) ) {
				++$done;
			}
		}

		$arg = 'swmd_bulk_delete_navigation' === $action ? 'nav_trashed' : 'nav_duplicated';

		wp_safe_redirect( admin_url( 'themes.php?page=swmd-menu-manager&tab=navigation&' . $arg . '=' . $done ) );
		exit;
	}

	/**
	 * Returns the bulk action chosen in either tablenav selector.
	 *
	 * WP_List_Table names the bottom selector `action2`; core's own
	 * current_action() reads only `action` because admin JS mirrors the two.
	 * Reading both keeps the bottom bar working without JavaScript.
	 *
	 * @return string Action name, or an empty string when none was chosen.
	 */
	private function current_bulk_action(): string {
		$allowed = array(
			'swmd_bulk_delete',
			'swmd_bulk_duplicate',
			'swmd_bulk_export',
			'swmd_bulk_duplicate_navigation',
			'swmd_bulk_delete_navigation',
		);

		foreach ( array( 'action', 'action2' ) as $field ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The caller verifies the nonce before acting on this value.
			$value = isset( $_POST[ $field ] ) ? sanitize_key( wp_unslash( $_POST[ $field ] ) ) : '';

			if ( in_array( $value, $allowed, true ) ) {
				return $value;
			}
		}

		return '';
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
			wp_send_json_error( array( 'message' => __( 'No menus selected.', 'swift-menu-duplicator' ) ), 400 );
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
	 * @return void
	 */
	public function handle_ajax_bulk_export_zip(): void {
		$this->verify_nonce_and_capability();

		$ids = isset( $_POST['menu_ids'] ) ? array_map( 'absint', (array) $_POST['menu_ids'] ) : array();
		$ids = array_values( array_filter( $ids ) );

		if ( empty( $ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No menus selected.', 'swift-menu-duplicator' ) ), 400 );
		}

		$error = $this->stream_export( $ids );

		// stream_export() exits on success; only failures return.
		wp_send_json_error( array( 'message' => $error ), 500 );
	}

	/**
	 * Streams the given menus to the browser as a file download.
	 *
	 * A single menu is sent as plain JSON — matching the menu editor's export
	 * and avoiding a hard dependency on ZipArchive. Several menus are bundled
	 * into a ZIP.
	 *
	 * Exits on success. Shared by the AJAX handler and the no-JavaScript bulk
	 * form path.
	 *
	 * @param int[] $ids Menu term IDs to export.
	 *
	 * @return string Error message when nothing could be streamed.
	 */
	private function stream_export( array $ids ): string {
		$duplicator = new Menu_Duplicator();

		if ( 1 === count( $ids ) ) {
			$payload = $duplicator->export( $ids[0] );

			if ( is_wp_error( $payload ) ) {
				return $payload->get_error_message();
			}

			$term = get_term( $ids[0], 'nav_menu' );
			$slug = ( $term instanceof WP_Term ) ? sanitize_file_name( $term->slug ) : 'menu';

			nocache_headers();
			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="' . $slug . '-menu-export.json"' );
			header( 'X-Content-Type-Options: nosniff' );

			// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
			echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			exit;
		}

		if ( ! class_exists( 'ZipArchive' ) ) {
			return __( 'ZIP export requires the PHP ZipArchive extension.', 'swift-menu-duplicator' );
		}

		$zip_file = wp_tempnam( 'swmd-export' );
		$zip      = new ZipArchive();

		if ( true !== $zip->open( $zip_file, ZipArchive::OVERWRITE ) ) {
			return __( 'Could not create ZIP archive.', 'swift-menu-duplicator' );
		}

		$added = 0;

		foreach ( $ids as $id ) {
			$payload = $duplicator->export( $id );

			if ( is_wp_error( $payload ) ) {
				continue;
			}

			$term = get_term( $id, 'nav_menu' );
			$slug = ( $term instanceof WP_Term ) ? sanitize_file_name( $term->slug ) : 'menu-' . $id;

			// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
			$zip->addFromString(
				$slug . '-menu-export.json',
				(string) wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
			);

			++$added;
		}

		$zip->close();

		if ( 0 === $added ) {
			Filesystem::delete( $zip_file );

			return __( 'None of the selected menus could be exported.', 'swift-menu-duplicator' );
		}

		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="menus-export.zip"' );
		header( 'Content-Length: ' . filesize( $zip_file ) );
		header( 'X-Content-Type-Options: nosniff' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		readfile( $zip_file );
		Filesystem::delete( $zip_file );
		exit;
	}

	/**
	 * Whether the Navigation (Block) tab should be offered.
	 *
	 * Shown for block themes — where classic menus are not what the front end
	 * renders — and for any site that already has block navigation menus, since
	 * a classic theme can still have them from a previous theme.
	 *
	 * @return bool
	 */
	public function has_block_navigation(): bool {
		if ( ! post_type_exists( Navigation_Duplicator::POST_TYPE ) ) {
			return false;
		}

		if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
			return true;
		}

		return array() !== ( new Navigation_Duplicator() )->get_all( 1 );
	}

	/**
	 * Duplicates a block navigation menu via AJAX.
	 *
	 * @return void
	 */
	public function handle_ajax_duplicate_navigation(): void {
		$this->verify_nonce_and_capability();

		$post_id = isset( $_POST['navigation_id'] ) ? absint( $_POST['navigation_id'] ) : 0;
		$title   = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';

		if ( $post_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid navigation menu ID.', 'swift-menu-duplicator' ) ), 400 );
		}

		$duplicator = new Navigation_Duplicator();
		$new_id     = $duplicator->duplicate( $post_id, $title );

		if ( is_wp_error( $new_id ) ) {
			wp_send_json_error( array( 'message' => $new_id->get_error_message() ), 500 );
		}

		wp_send_json_success(
			array(
				'new_navigation_id' => $new_id,
				'edit_url'          => $duplicator->get_edit_url( $new_id ),
			)
		);
	}

	/**
	 * Streams a block navigation menu as a JSON download.
	 *
	 * @return void
	 */
	public function handle_ajax_export_navigation(): void {
		$this->verify_nonce_and_capability();

		$post_id = isset( $_POST['navigation_id'] ) ? absint( $_POST['navigation_id'] ) : 0;

		if ( $post_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid navigation menu ID.', 'swift-menu-duplicator' ) ), 400 );
		}

		$payload = ( new Navigation_Duplicator() )->export( $post_id );

		if ( is_wp_error( $payload ) ) {
			wp_send_json_error( array( 'message' => $payload->get_error_message() ), 404 );
		}

		$slug = '' !== $payload['navigation']['slug']
			? sanitize_file_name( $payload['navigation']['slug'] )
			: 'navigation';

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $slug . '-navigation-export.json"' );
		header( 'X-Content-Type-Options: nosniff' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
		echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

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

		if ( ! wp_verify_nonce( $nonce, 'swmd_menu_actions' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'swift-menu-duplicator' ) ), 403 );
		}

		if ( ! is_multisite() || ! current_user_can( 'manage_network' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'swift-menu-duplicator' ) ), 403 );
		}

		$source_menu_id = isset( $_POST['menu_id'] ) ? absint( $_POST['menu_id'] ) : 0;
		$target_blog_id = isset( $_POST['target_blog_id'] ) ? absint( $_POST['target_blog_id'] ) : 0;
		$new_name       = isset( $_POST['menu_name'] )
			? sanitize_text_field( wp_unslash( $_POST['menu_name'] ) )
			: '';
		$find           = isset( $_POST['find'] )
			? sanitize_text_field( wp_unslash( $_POST['find'] ) )
			: '';
		$replace        = isset( $_POST['replace'] )
			? sanitize_text_field( wp_unslash( $_POST['replace'] ) )
			: '';

		if ( $source_menu_id <= 0 || $target_blog_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid menu or site ID.', 'swift-menu-duplicator' ) ), 400 );
		}

		// Confirm the destination is a real site before switching into it.
		if ( null === get_site( $target_blog_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Destination site not found.', 'swift-menu-duplicator' ) ), 404 );
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
		$new_menu_id = $importer->import( $payload, $new_name, $find, $replace );

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

		if ( ! wp_verify_nonce( $nonce, 'swmd_menu_actions' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'swift-menu-duplicator' ) ), 403 );
		}

		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'swift-menu-duplicator' ) ), 403 );
		}
	}
}
