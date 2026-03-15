<?php
/**
 * Admin list table for navigation menus.
 *
 * @package ClassicMenuDuplicator
 */

declare( strict_types=1 );

namespace ClassicMenuDuplicator;

use WP_List_Table;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class Menu_Table
 *
 * Renders a WP_List_Table listing every nav_menu term on the current site
 * with per-row actions (Duplicate, Export, Delete) and bulk actions
 * (Duplicate selected, Export selected, Delete selected).
 */
class Menu_Table extends WP_List_Table {

	/**
	 * Constructs the list table.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => __( 'menu', 'classic-menu-duplicator' ),
				'plural'   => __( 'menus', 'classic-menu-duplicator' ),
				'ajax'     => false,
			)
		);
	}

	/**
	 * Returns the list of columns.
	 *
	 * @return array<string,string>
	 */
	public function get_columns(): array {
		return array(
			'cb'         => '<input type="checkbox" />',
			'name'       => __( 'Menu Name', 'classic-menu-duplicator' ),
			'item_count' => __( 'Items', 'classic-menu-duplicator' ),
			'locations'  => __( 'Theme Locations', 'classic-menu-duplicator' ),
			'created'    => __( 'Created', 'classic-menu-duplicator' ),
		);
	}

	/**
	 * Returns sortable columns.
	 *
	 * @return array<string,array<int,string|bool>>
	 */
	protected function get_sortable_columns(): array {
		return array(
			'name'    => array( 'name', true ),
			'created' => array( 'created', false ),
		);
	}

	/**
	 * Returns bulk actions available for this table.
	 *
	 * @return array<string,string>
	 */
	protected function get_bulk_actions(): array {
		$actions = array(
			'cmd_bulk_duplicate' => __( 'Duplicate', 'classic-menu-duplicator' ),
			'cmd_bulk_export'    => __( 'Export as JSON', 'classic-menu-duplicator' ),
		);

		if ( current_user_can( 'delete_theme_options' ) ) {
			$actions['cmd_bulk_delete'] = __( 'Delete', 'classic-menu-duplicator' );
		}

		return $actions;
	}

	/**
	 * Prepares items for display, including sorting and pagination.
	 *
	 * @return void
	 */
	public function prepare_items(): void {
		$this->_column_headers = array(
			$this->get_columns(),
			array(),
			$this->get_sortable_columns(),
		);

		$menus = wp_get_nav_menus( array( 'orderby' => 'name' ) );

		if ( ! is_array( $menus ) ) {
			$menus = array();
		}

		// Sorting.
		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( $_GET['orderby'] ) : 'name'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order   = isset( $_GET['order'] ) && 'desc' === strtolower( sanitize_key( $_GET['order'] ) ) ? 'desc' : 'asc'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		usort(
			$menus,
			static function ( \WP_Term $a, \WP_Term $b ) use ( $orderby, $order ): int {
				$val_a = 'created' === $orderby ? (int) get_term_meta( $a->term_id, '_cmd_created', true ) : strtolower( $a->name );
				$val_b = 'created' === $orderby ? (int) get_term_meta( $b->term_id, '_cmd_created', true ) : strtolower( $b->name );

				$cmp = 'created' === $orderby ? ( $val_a <=> $val_b ) : strcmp( (string) $val_a, (string) $val_b );

				return 'desc' === $order ? - $cmp : $cmp;
			}
		);

		// Pagination.
		$per_page     = $this->get_items_per_page( 'cmd_menus_per_page', 20 );
		$current_page = $this->get_pagenum();
		$total_items  = count( $menus );

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
			)
		);

		$this->items = array_slice( $menus, ( $current_page - 1 ) * $per_page, $per_page );
	}

	/**
	 * Renders the checkbox column.
	 *
	 * @param \WP_Term $item Current row term.
	 *
	 * @return string
	 */
	protected function column_cb( $item ): string {
		return sprintf(
			'<input type="checkbox" name="menu_ids[]" value="%d" />',
			$item->term_id
		);
	}

	/**
	 * Renders the Name column with row actions.
	 *
	 * @param \WP_Term $item Current row term.
	 *
	 * @return string
	 */
	protected function column_name( $item ): string {
		$edit_url = admin_url( 'nav-menus.php?action=edit&menu=' . $item->term_id );

		$nonce = wp_create_nonce( 'cmd_menu_actions' );

		$actions = array(
			'edit'      => sprintf(
				'<a href="%s">%s</a>',
				esc_url( $edit_url ),
				esc_html__( 'Edit', 'classic-menu-duplicator' )
			),
			'duplicate' => sprintf(
				'<a href="#" class="cmd-row-duplicate" data-menu-id="%d" data-menu-name="%s" data-nonce="%s">%s</a>',
				$item->term_id,
				esc_attr( $item->name ),
				esc_attr( $nonce ),
				esc_html__( 'Duplicate', 'classic-menu-duplicator' )
			),
			'export'    => sprintf(
				'<a href="#" class="cmd-row-export" data-menu-id="%d" data-nonce="%s">%s</a>',
				$item->term_id,
				esc_attr( $nonce ),
				esc_html__( 'Export JSON', 'classic-menu-duplicator' )
			),
		);

		if ( current_user_can( 'delete_theme_options' ) ) {
			$delete_url        = wp_nonce_url(
				admin_url( 'nav-menus.php?action=delete-menu&menu=' . $item->term_id ),
				'delete-nav_menu-' . $item->term_id
			);
			$actions['delete'] = sprintf(
				'<a href="%s" class="submitdelete" onclick="return confirm(\'%s\')">%s</a>',
				esc_url( $delete_url ),
				esc_js( __( 'Delete this menu?', 'classic-menu-duplicator' ) ),
				esc_html__( 'Delete', 'classic-menu-duplicator' )
			);
		}

		return sprintf(
			'<strong><a href="%s">%s</a></strong>%s',
			esc_url( $edit_url ),
			esc_html( $item->name ),
			$this->row_actions( $actions )
		);
	}

	/**
	 * Renders the Item Count column.
	 *
	 * @param \WP_Term $item Current row term.
	 *
	 * @return string
	 */
	protected function column_item_count( $item ): string {
		return esc_html( (string) $item->count );
	}

	/**
	 * Renders the Theme Locations column.
	 *
	 * @param \WP_Term $item Current row term.
	 *
	 * @return string
	 */
	protected function column_locations( $item ): string {
		$nav_menu_locations = get_nav_menu_locations();
		$theme_locations    = get_registered_nav_menus();
		$assigned           = array();

		foreach ( $nav_menu_locations as $location => $menu_id ) {
			if ( (int) $menu_id === $item->term_id && isset( $theme_locations[ $location ] ) ) {
				$assigned[] = esc_html( $theme_locations[ $location ] );
			}
		}

		return ! empty( $assigned )
			? implode( ', ', $assigned )
			: '<span class="cmd-muted">' . esc_html__( '—', 'classic-menu-duplicator' ) . '</span>';
	}

	/**
	 * Renders the Created column.
	 *
	 * WordPress nav_menu terms have no native creation timestamp; we store
	 * one in term-meta on first access (lazy initialised from the term_id
	 * order as a rough proxy).
	 *
	 * @param \WP_Term $item Current row term.
	 *
	 * @return string
	 */
	protected function column_created( $item ): string {
		$ts = (int) get_term_meta( $item->term_id, '_cmd_created', true );

		if ( $ts <= 0 ) {
			return '<span class="cmd-muted">' . esc_html__( '—', 'classic-menu-duplicator' ) . '</span>';
		}

		return esc_html(
			wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $ts )
		);
	}

	/**
	 * Renders the default column output for columns not handled explicitly.
	 *
	 * @param \WP_Term $item Current row term.
	 * @param string   $column_name Column identifier.
	 *
	 * @return string
	 */
	protected function column_default( $item, $column_name ): string {
		return '';
	}

	/**
	 * Renders the message when no menus exist.
	 *
	 * @return void
	 */
	public function no_items(): void {
		esc_html_e( 'No menus found.', 'classic-menu-duplicator' );
	}
}
