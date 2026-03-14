<?php
/**
 * Admin integration: script enqueuing and AJAX handler.
 *
 * @package WPMenuDuplicator
 */

declare( strict_types=1 );

namespace WPMenuDuplicator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Menu_Admin
 *
 * Registers all WordPress hooks required by the plugin: script enqueuing
 * on nav-menus.php, and the privileged AJAX action for menu duplication.
 */
class Menu_Admin {

	/**
	 * Registers WordPress action and filter hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_enqueue_scripts',      array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_wmd_duplicate_menu', array( $this, 'handle_ajax' ) );
	}

	/**
	 * Enqueues the admin JavaScript only on nav-menus.php.
	 *
	 * Passes localised data including the AJAX URL, a nonce, and i18n
	 * strings used by the JS to construct and manage the button UI.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 *
	 * @return void
	 */
	public function enqueue_scripts( string $hook_suffix ): void {
		if ( 'nav-menus.php' !== $hook_suffix ) {
			return;
		}

		$asset_file = WP_MENU_DUPLICATOR_DIR . 'assets/js/admin.js';

		wp_enqueue_script(
			'wmd-admin',
			WP_MENU_DUPLICATOR_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			file_exists( $asset_file )
				? (string) filemtime( $asset_file )
				: WP_MENU_DUPLICATOR_VERSION,
			true
		);

		wp_localize_script(
			'wmd-admin',
			'wmdData',
			array(
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( 'wmd_duplicate_menu' ),
				'buttonLabel'      => __( 'Duplicate Menu', 'wp-menu-duplicator' ),
				'duplicatingLabel' => __( 'Duplicating\u2026', 'wp-menu-duplicator' ),
				'errorMessage'     => __( 'Duplication failed. Please try again.', 'wp-menu-duplicator' ),
			)
		);
	}

	/**
	 * Handles the wmd_duplicate_menu AJAX request.
	 *
	 * Verifies the nonce, confirms the current user has the required
	 * capability, validates the submitted menu ID, then delegates to
	 * Menu_Duplicator::duplicate() and returns a JSON response.
	 *
	 * @return void Sends a JSON response and exits.
	 */
	public function handle_ajax(): void {
		// 1. Nonce verification.
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'wmd_duplicate_menu' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Security check failed.', 'wp-menu-duplicator' ) ),
				403
			);
		}

		// 2. Capability check.
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Insufficient permissions.', 'wp-menu-duplicator' ) ),
				403
			);
		}

		// 3. Input validation.
		$menu_id = isset( $_POST['menu_id'] ) ? absint( $_POST['menu_id'] ) : 0;

		if ( $menu_id <= 0 ) {
			wp_send_json_error(
				array( 'message' => __( 'Invalid menu ID.', 'wp-menu-duplicator' ) ),
				400
			);
		}

		// 4. Duplicate.
		$duplicator = new Menu_Duplicator();
		$result     = $duplicator->duplicate( $menu_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
		}

		wp_send_json_success(
			array(
				'new_menu_id' => $result,
				'redirect'    => admin_url( 'nav-menus.php?action=edit&menu=' . $result ),
			)
		);
	}
}
