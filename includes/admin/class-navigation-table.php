<?php
/**
 * Admin list table for block-theme navigation menus.
 *
 * @package SwiftMenuDuplicator
 */

declare( strict_types=1 );

namespace SwiftMenuDuplicator\Admin;

use SwiftMenuDuplicator\Core\Navigation_Duplicator;
use WP_List_Table;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class Navigation_Table
 *
 * Lists `wp_navigation` posts — the navigation block themes actually render —
 * with the same duplicate and export actions the classic menus table offers.
 */
class Navigation_Table extends WP_List_Table {

	/**
	 * Duplicator used for edit links and block counts.
	 *
	 * @var Navigation_Duplicator
	 */
	private Navigation_Duplicator $duplicator;

	/**
	 * Constructs the list table.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => __( 'navigation menu', 'swift-menu-duplicator' ),
				'plural'   => __( 'navigation menus', 'swift-menu-duplicator' ),
				'ajax'     => false,
			)
		);

		$this->duplicator = new Navigation_Duplicator();
	}

	/**
	 * Returns the list of columns.
	 *
	 * @return array<string,string>
	 */
	public function get_columns(): array {
		return array(
			'cb'       => '<input type="checkbox" />',
			'title'    => __( 'Navigation Menu', 'swift-menu-duplicator' ),
			'status'   => __( 'Status', 'swift-menu-duplicator' ),
			'links'    => __( 'Links', 'swift-menu-duplicator' ),
			'modified' => __( 'Last Modified', 'swift-menu-duplicator' ),
		);
	}

	/**
	 * Returns bulk actions available for this table.
	 *
	 * @return array<string,string>
	 */
	protected function get_bulk_actions(): array {
		return array(
			'swmd_bulk_duplicate_navigation' => __( 'Duplicate', 'swift-menu-duplicator' ),
			'swmd_bulk_delete_navigation'    => __( 'Move to Trash', 'swift-menu-duplicator' ),
		);
	}

	/**
	 * Prepares items for display.
	 *
	 * @return void
	 */
	public function prepare_items(): void {
		$this->_column_headers = array( $this->get_columns(), array(), array() );

		$this->items = $this->duplicator->get_all();

		$this->set_pagination_args(
			array(
				'total_items' => count( $this->items ),
				'per_page'    => max( 1, count( $this->items ) ),
			)
		);
	}

	/**
	 * Renders the checkbox column.
	 *
	 * @param WP_Post $item Current row post.
	 *
	 * @return string
	 */
	protected function column_cb( $item ): string {
		return sprintf( '<input type="checkbox" name="navigation_ids[]" value="%d" />', $item->ID );
	}

	/**
	 * Renders the Title column with row actions.
	 *
	 * @param WP_Post $item Current row post.
	 *
	 * @return string
	 */
	protected function column_title( $item ): string {
		$edit_url = $this->duplicator->get_edit_url( $item->ID );
		$nonce    = wp_create_nonce( 'swmd_menu_actions' );

		$title = '' !== $item->post_title
			? $item->post_title
			/* translators: %d: navigation menu post ID */
			: sprintf( __( 'Navigation %d', 'swift-menu-duplicator' ), $item->ID );

		$actions = array(
			'edit'      => sprintf(
				'<a href="%s">%s</a>',
				esc_url( $edit_url ),
				esc_html__( 'Edit in Site Editor', 'swift-menu-duplicator' )
			),
			'duplicate' => sprintf(
				'<a href="#" class="swmd-row-duplicate-navigation" data-navigation-id="%d" data-nonce="%s">%s</a>',
				$item->ID,
				esc_attr( $nonce ),
				esc_html__( 'Duplicate', 'swift-menu-duplicator' )
			),
			'export'    => sprintf(
				'<a href="#" class="swmd-row-export-navigation" data-navigation-id="%d" data-nonce="%s">%s</a>',
				$item->ID,
				esc_attr( $nonce ),
				esc_html__( 'Export JSON', 'swift-menu-duplicator' )
			),
		);

		return sprintf(
			'<strong><a href="%s">%s</a></strong>%s',
			esc_url( $edit_url ),
			esc_html( $title ),
			$this->row_actions( $actions )
		);
	}

	/**
	 * Renders the Status column.
	 *
	 * @param WP_Post $item Current row post.
	 *
	 * @return string
	 */
	protected function column_status( $item ): string {
		$status = get_post_status_object( $item->post_status );

		return esc_html( $status ? $status->label : $item->post_status );
	}

	/**
	 * Renders the Links column — how many navigation links the markup holds.
	 *
	 * @param WP_Post $item Current row post.
	 *
	 * @return string
	 */
	protected function column_links( $item ): string {
		$blocks = parse_blocks( $item->post_content );

		return esc_html( (string) $this->count_link_blocks( $blocks ) );
	}

	/**
	 * Renders the Last Modified column.
	 *
	 * @param WP_Post $item Current row post.
	 *
	 * @return string
	 */
	protected function column_modified( $item ): string {
		return esc_html(
			wp_date(
				get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
				(int) get_post_timestamp( $item, 'modified' )
			)
		);
	}

	/**
	 * Renders the default column output.
	 *
	 * @param WP_Post $item        Current row post.
	 * @param string  $column_name Column identifier.
	 *
	 * @return string
	 */
	protected function column_default( $item, $column_name ): string {
		return '';
	}

	/**
	 * Renders the message when no navigation menus exist.
	 *
	 * @return void
	 */
	public function no_items(): void {
		esc_html_e( 'No block navigation menus found.', 'swift-menu-duplicator' );
	}

	/**
	 * Counts navigation link blocks, including nested submenus.
	 *
	 * @param array<int,array<string,mixed>> $blocks Parsed blocks.
	 *
	 * @return int
	 */
	private function count_link_blocks( array $blocks ): int {
		$count = 0;

		foreach ( $blocks as $block ) {
			$name = $block['blockName'] ?? '';

			if ( in_array( $name, array( 'core/navigation-link', 'core/navigation-submenu' ), true ) ) {
				++$count;
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$count += $this->count_link_blocks( $block['innerBlocks'] );
			}
		}

		return $count;
	}
}
