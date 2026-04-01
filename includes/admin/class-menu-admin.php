<?php
/**
 * Admin integration: script enqueuing and AJAX handlers.
 *
 * @package SwiftMenuDuplicator
 */

declare( strict_types=1 );

namespace SwiftMenuDuplicator\Admin;

use SwiftMenuDuplicator\Core\Menu_Duplicator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Menu_Admin
 *
 * Registers all WordPress hooks required by the plugin: script enqueuing
 * on nav-menus.php, and the privileged AJAX actions for menu duplication,
 * item duplication, snapshot management, and JSON export.
 */
class Menu_Admin {

	/**
	 * Registers WordPress action and filter hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_head', array( $this, 'output_inline_styles' ) );

		// Menu-level actions.
		add_action( 'wp_ajax_swmd_duplicate_menu', array( $this, 'handle_ajax_duplicate_menu' ) );
		add_action( 'wp_ajax_swmd_export_menu', array( $this, 'handle_ajax_export_menu' ) );

		// Item-level actions.
		add_action( 'wp_ajax_swmd_duplicate_item', array( $this, 'handle_ajax_duplicate_item' ) );

		// Snapshot actions.
		add_action( 'wp_ajax_swmd_save_snapshot', array( $this, 'handle_ajax_save_snapshot' ) );
		add_action( 'wp_ajax_swmd_get_snapshots', array( $this, 'handle_ajax_get_snapshots' ) );
		add_action( 'wp_ajax_swmd_delete_snapshot', array( $this, 'handle_ajax_delete_snapshot' ) );

		// Auto-snapshot before core saves a menu so every manual save is captured.
		add_action( 'wp_update_nav_menu', array( $this, 'auto_snapshot_on_save' ), 5 );
	}

	/**
	 * Outputs inline CSS scoped to nav-menus.php.
	 *
	 * @return void
	 * @global string $pagenow Current admin page filename.
	 */
	public function output_inline_styles(): void {
		global $pagenow;

		if ( 'nav-menus.php' !== $pagenow ) {
			return;
		}

		$css = $this->get_inline_css();
		wp_add_inline_style( 'swmd-admin', $css );
	}

	/**
	 * Generates inline CSS for the admin menu page.
	 *
	 * @return string CSS styles.
	 */
	private function get_inline_css(): string {
		return '
	/* ── Toolbar button spacing ─────────────────────────────────── */
	#swmd-duplicate-menu,
	#swmd-snapshot-toggle,
	#swmd-export-menu {
		vertical-align: middle;
		margin-left: 6px;
	}

	/* ── Toast notice ───────────────────────────────────────────── */
	.swmd-toast {
		margin: 8px 0 0;
	}

	/* ── Name modal overlay ─────────────────────────────────────── */
	#swmd-modal-overlay {
		display: none;
		position: fixed;
		inset: 0;
		background: rgba(0, 0, 0, .55);
		z-index: 100100;
		align-items: center;
		justify-content: center;
	}

	#swmd-modal-overlay[style*="display: block"],
	#swmd-modal-overlay[style*="display:block"] {
		display: flex !important;
	}

	#swmd-modal {
		background: #fff;
		border-radius: 4px;
		box-shadow: 0 4px 24px rgba(0,0,0,.25);
		padding: 24px 28px;
		width: 100%;
		max-width: 420px;
	}

	#swmd-modal h2 {
		margin: 0 0 16px;
		font-size: 16px;
		font-weight: 600;
		color: #1d2327;
	}

	#swmd-modal label {
		display: block;
		margin-bottom: 6px;
		font-weight: 500;
		color: #1d2327;
	}

	#swmd-modal-name {
		width: 100%;
		box-sizing: border-box;
		margin-bottom: 16px;
	}

	.swmd-modal-actions {
		display: flex;
		gap: 8px;
	}

	/* ── Snapshot panel ─────────────────────────────────────────── */
	#swmd-snapshot-panel {
		position: fixed;
		top: 0;
		right: -320px;
		width: 300px;
		height: 100vh;
		background: #fff;
		border-left: 1px solid #c3c4c7;
		box-shadow: -2px 0 8px rgba(0,0,0,.1);
		z-index: 99990;
		display: flex;
		flex-direction: column;
		transition: right .25s ease;
		overflow: hidden;
	}

	#swmd-snapshot-panel.is-visible {
		right: 0;
	}

	#swmd-snapshot-panel-header {
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 12px 16px;
		border-bottom: 1px solid #c3c4c7;
		font-weight: 600;
		font-size: 13px;
		color: #1d2327;
		background: #f6f7f7;
	}

	#swmd-snapshot-close {
		font-size: 20px;
		line-height: 1;
		color: #787c82;
		padding: 0 4px;
		cursor: pointer;
	}

	#swmd-snapshot-close:hover {
		color: #1d2327;
	}

	#swmd-snapshot-save-row {
		padding: 12px 16px;
		border-bottom: 1px solid #f0f0f1;
	}

	#swmd-save-snapshot {
		width: 100%;
	}

	#swmd-snapshot-list {
		flex: 1;
		overflow-y: auto;
		margin: 0;
		padding: 0;
		list-style: none;
	}

	.swmd-snapshot-empty {
		padding: 16px;
		color: #787c82;
		font-size: 12px;
		font-style: italic;
	}

	.swmd-snapshot-item {
		display: flex;
		flex-direction: column;
		padding: 10px 16px;
		border-bottom: 1px solid #f0f0f1;
		position: relative;
	}

	.swmd-snapshot-item:hover {
		background: #f6f7f7;
	}

	.swmd-snapshot-label {
		font-size: 12px;
		font-weight: 500;
		color: #1d2327;
		padding-right: 24px;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}

	.swmd-snapshot-date {
		font-size: 11px;
		color: #787c82;
		margin-top: 2px;
	}

	.swmd-snapshot-delete {
		position: absolute;
		top: 10px;
		right: 12px;
		font-size: 16px;
		line-height: 1;
		color: #b32d2e;
		cursor: pointer;
		opacity: .6;
	}

	.swmd-snapshot-delete:hover {
		opacity: 1;
	}

	/* ── Duplicate item link ─────────────────────────────────────── */
	.swmd-duplicate-item {
		margin-left: 8px;
		color: #2271b1;
	}

	.swmd-duplicate-item:hover {
		color: #135e96;
	}
		';
	}

	/**
	 * Enqueues the admin JavaScript only on nav-menus.php.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 *
	 * @return void
	 */
	public function enqueue_scripts( string $hook_suffix ): void {
		if ( 'nav-menus.php' !== $hook_suffix ) {
			return;
		}

		$asset_file = SWIFT_MENU_DUPLICATOR_DIR . 'assets/js/admin.js';

		wp_enqueue_script(
			'swmd-admin',
			SWIFT_MENU_DUPLICATOR_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			file_exists( $asset_file )
				? (string) filemtime( $asset_file )
				: SWIFT_MENU_DUPLICATOR_VERSION,
			true
		);

		wp_localize_script(
			'swmd-admin',
			'swmdData',
			array(
				'ajaxUrl'              => admin_url( 'admin-ajax.php' ),
				'nonce'                => wp_create_nonce( 'swmd_menu_actions' ),
				'currentMenuId'        => absint( $_GET['menu'] ?? 0 ),
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				// Menu duplication strings.
				'buttonLabel'          => __( 'Duplicate Menu', 'swift-menu-duplicator' ),
				'duplicatingLabel'     => __( 'Duplicating…', 'swift-menu-duplicator' ),
				// Item duplication strings.
				'duplicateItemLabel'   => __( 'Duplicate', 'swift-menu-duplicator' ),
				'duplicatingItemLabel' => __( 'Duplicating…', 'swift-menu-duplicator' ),
				// Export strings.
				'exportLabel'          => __( 'Export JSON', 'swift-menu-duplicator' ),
				'exportingLabel'       => __( 'Exporting…', 'swift-menu-duplicator' ),
				// Snapshot strings.
				'snapshotLabel'        => __( 'Snapshots', 'swift-menu-duplicator' ),
				'saveSnapshotLabel'    => __( 'Save Snapshot', 'swift-menu-duplicator' ),
				'savingSnapshotLabel'  => __( 'Saving…', 'swift-menu-duplicator' ),
				'noSnapshotsText'      => __( 'No snapshots saved yet.', 'swift-menu-duplicator' ),
				'snapshotSavedText'    => __( 'Snapshot saved.', 'swift-menu-duplicator' ),
				'confirmDeleteText'    => __( 'Delete this snapshot?', 'swift-menu-duplicator' ),
				// Modal strings.
				'modalHeading'         => __( 'Duplicate Menu', 'swift-menu-duplicator' ),
				'modalNameLabel'       => __( 'New menu name', 'swift-menu-duplicator' ),
				'modalConfirmLabel'    => __( 'Duplicate', 'swift-menu-duplicator' ),
				'modalCancelLabel'     => __( 'Cancel', 'swift-menu-duplicator' ),
				// Generic error.
				'errorMessage'         => __( 'Action failed. Please try again.', 'swift-menu-duplicator' ),
			)
		);
	}

	// -----------------------------------------------------------------------
	// AJAX handlers — menu level.
	// -----------------------------------------------------------------------

	/**
	 * Handles the swmd_duplicate_menu AJAX request.
	 *
	 * Accepts an optional `menu_name` parameter; when omitted the server
	 * falls back to the "{original} (Copy)" convention.
	 *
	 * @return void Sends a JSON response and exits.
	 */
	public function handle_ajax_duplicate_menu(): void {
		$this->verify_nonce_and_capability();

		$menu_id = isset( $_POST['menu_id'] ) ? absint( $_POST['menu_id'] ) : 0;

		if ( $menu_id <= 0 ) {
			wp_send_json_error(
				array( 'message' => __( 'Invalid menu ID.', 'swift-menu-duplicator' ) ),
				400
			);
		}

		$menu_name = isset( $_POST['menu_name'] )
			? sanitize_text_field( wp_unslash( $_POST['menu_name'] ) )
			: '';

		$duplicator = new Menu_Duplicator();
		$result     = $duplicator->duplicate( $menu_id, $menu_name );

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

	/**
	 * Handles the swmd_export_menu AJAX request.
	 *
	 * Streams a JSON file download directly from the AJAX handler so the
	 * browser triggers a Save dialog without any intermediate page.
	 *
	 * @return void Sends file download headers and JSON body, then exits.
	 */
	public function handle_ajax_export_menu(): void {
		$this->verify_nonce_and_capability();

		$menu_id = isset( $_POST['menu_id'] ) ? absint( $_POST['menu_id'] ) : 0;

		if ( $menu_id <= 0 ) {
			wp_send_json_error(
				array( 'message' => __( 'Invalid menu ID.', 'swift-menu-duplicator' ) ),
				400
			);
		}

		$duplicator = new Menu_Duplicator();
		$payload    = $duplicator->export( $menu_id );

		if ( is_wp_error( $payload ) ) {
			wp_send_json_error( array( 'message' => $payload->get_error_message() ), 500 );
		}

		$term = get_term( $menu_id, 'nav_menu' );
		$slug = ( $term instanceof \WP_Term ) ? sanitize_file_name( $term->slug ) : 'menu';

		// Output as a file download.
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $slug . '-menu-export.json"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
		echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

		exit;
	}

	// -----------------------------------------------------------------------
	// AJAX handlers — item level.
	// -----------------------------------------------------------------------

	/**
	 * Handles the swmd_duplicate_item AJAX request.
	 *
	 * @return void Sends a JSON response and exits.
	 */
	public function handle_ajax_duplicate_item(): void {
		$this->verify_nonce_and_capability();

		$item_id = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0;
		$menu_id = isset( $_POST['menu_id'] ) ? absint( $_POST['menu_id'] ) : 0;

		if ( $item_id <= 0 || $menu_id <= 0 ) {
			wp_send_json_error(
				array( 'message' => __( 'Invalid item or menu ID.', 'swift-menu-duplicator' ) ),
				400
			);
		}

		$duplicator  = new Menu_Duplicator();
		$new_item_id = $duplicator->duplicate_item( $item_id, $menu_id );

		if ( is_wp_error( $new_item_id ) ) {
			wp_send_json_error( array( 'message' => $new_item_id->get_error_message() ), 500 );
		}

		wp_send_json_success( array( 'new_item_id' => $new_item_id ) );
	}

	// -----------------------------------------------------------------------
	// AJAX handlers — snapshots.
	// -----------------------------------------------------------------------

	/**
	 * Saves a new snapshot for the current menu state.
	 *
	 * @return void
	 */
	public function handle_ajax_save_snapshot(): void {
		$this->verify_nonce_and_capability();

		$menu_id = isset( $_POST['menu_id'] ) ? absint( $_POST['menu_id'] ) : 0;
		$label   = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';

		if ( $menu_id <= 0 ) {
			wp_send_json_error(
				array( 'message' => __( 'Invalid menu ID.', 'swift-menu-duplicator' ) ),
				400
			);
		}

		$duplicator = new Menu_Duplicator();
		$saved      = $duplicator->save_snapshot( $menu_id, $label );

		if ( ! $saved ) {
			wp_send_json_error(
				array( 'message' => __( 'Could not save snapshot.', 'swift-menu-duplicator' ) ),
				500
			);
		}

		$snapshots = $duplicator->get_snapshots( $menu_id );

		wp_send_json_success(
			array(
				'snapshots' => $this->prepare_snapshots_for_response( $snapshots ),
			)
		);
	}

	/**
	 * Returns all snapshots for the current menu.
	 *
	 * @return void
	 */
	public function handle_ajax_get_snapshots(): void {
		$this->verify_nonce_and_capability();

		$menu_id = isset( $_POST['menu_id'] ) ? absint( $_POST['menu_id'] ) : 0;

		if ( $menu_id <= 0 ) {
			wp_send_json_error(
				array( 'message' => __( 'Invalid menu ID.', 'swift-menu-duplicator' ) ),
				400
			);
		}

		$duplicator = new Menu_Duplicator();
		$snapshots  = $duplicator->get_snapshots( $menu_id );

		wp_send_json_success(
			array(
				'snapshots' => $this->prepare_snapshots_for_response( $snapshots ),
			)
		);
	}

	/**
	 * Deletes a specific snapshot by UUID.
	 *
	 * @return void
	 */
	public function handle_ajax_delete_snapshot(): void {
		$this->verify_nonce_and_capability();

		$menu_id     = isset( $_POST['menu_id'] ) ? absint( $_POST['menu_id'] ) : 0;
		$snapshot_id = isset( $_POST['snapshot_id'] )
			? sanitize_text_field( wp_unslash( $_POST['snapshot_id'] ) )
			: '';

		if ( $menu_id <= 0 || '' === $snapshot_id ) {
			wp_send_json_error(
				array( 'message' => __( 'Invalid parameters.', 'swift-menu-duplicator' ) ),
				400
			);
		}

		$duplicator = new Menu_Duplicator();
		$deleted    = $duplicator->delete_snapshot( $menu_id, $snapshot_id );

		if ( ! $deleted ) {
			wp_send_json_error(
				array( 'message' => __( 'Snapshot not found.', 'swift-menu-duplicator' ) ),
				404
			);
		}

		$snapshots = $duplicator->get_snapshots( $menu_id );

		wp_send_json_success(
			array(
				'snapshots' => $this->prepare_snapshots_for_response( $snapshots ),
			)
		);
	}

	// -----------------------------------------------------------------------
	// Auto-snapshot hook.
	// -----------------------------------------------------------------------

	/**
	 * Automatically saves a snapshot before WordPress updates a menu.
	 *
	 * Fires on the `wp_update_nav_menu` action (priority 5, before core
	 * processes the update) so the snapshot captures the pre-save state.
	 *
	 * @param int $menu_id Term ID of the menu being updated.
	 *
	 * @return void
	 */
	public function auto_snapshot_on_save( int $menu_id ): void {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return;
		}

		$duplicator = new Menu_Duplicator();
		$duplicator->save_snapshot(
			$menu_id,
			__( 'Auto-snapshot (before save)', 'swift-menu-duplicator' )
		);
	}

	// -----------------------------------------------------------------------
	// Private helpers.
	// -----------------------------------------------------------------------

	/**
	 * Verifies the shared nonce and user capability for all AJAX handlers.
	 *
	 * Sends a 403 JSON error and exits if either check fails.
	 *
	 * @return void
	 */
	private function verify_nonce_and_capability(): void {
		$nonce = isset( $_POST['nonce'] )
			? sanitize_text_field( wp_unslash( $_POST['nonce'] ) )
			: '';

		if ( ! wp_verify_nonce( $nonce, 'swmd_menu_actions' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Security check failed.', 'swift-menu-duplicator' ) ),
				403
			);
		}

		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Insufficient permissions.', 'swift-menu-duplicator' ) ),
				403
			);
		}
	}

	/**
	 * Strips the full export payload from snapshot data before sending to the
	 * browser — only meta fields needed by the UI are returned.
	 *
	 * @param array<int,array<string,mixed>> $snapshots Raw snapshots from term meta.
	 *
	 * @return array<int,array<string,string|int>> Lightweight snapshot descriptors.
	 */
	private function prepare_snapshots_for_response( array $snapshots ): array {
		return array_map(
			static function ( array $snap ): array {
				return array(
					'id'            => (string) $snap['id'],
					'label'         => (string) $snap['label'],
					'created'       => (int) $snap['created'],
					'created_human' => wp_date(
						get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
						(int) $snap['created']
					),
				);
			},
			$snapshots
		);
	}
}
