<?php
/**
 * JSON menu importer.
 *
 * @package SwiftMenuDuplicator
 */

declare( strict_types=1 );

namespace SwiftMenuDuplicator\Import;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Menu_Importer
 *
 * Imports a navigation menu from a JSON payload previously produced by
 * Menu_Duplicator::export(). Supports URL search-and-replace on item
 * URLs and custom-link href values, and an optional dry-run mode that
 * returns a preview of what would be created without writing to the DB.
 */
class Menu_Importer {

	/**
	 * Parses and validates a raw JSON string into an import payload array.
	 *
	 * @param string $json Raw JSON string.
	 *
	 * @return array<string,mixed>|WP_Error Decoded payload or WP_Error on failure.
	 */
	public function parse( string $json ) {
		if ( '' === trim( $json ) ) {
			return new WP_Error( 'empty_json', __( 'The uploaded file is empty.', 'swift-menu-duplicator' ) );
		}

		$data = json_decode( $json, true );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return new WP_Error(
				'invalid_json',
				sprintf(
				/* translators: %s: JSON error message */
					__( 'JSON parse error: %s', 'swift-menu-duplicator' ),
					json_last_error_msg()
				)
			);
		}

		if ( ! is_array( $data ) ) {
			return new WP_Error( 'invalid_structure', __( 'Unexpected JSON structure.', 'swift-menu-duplicator' ) );
		}

		return $this->validate( $data );
	}

	/**
	 * Validates a decoded payload array against the expected schema.
	 *
	 * @param array<string,mixed> $data Decoded JSON payload.
	 *
	 * @return array<string,mixed>|WP_Error Validated payload or WP_Error.
	 */
	public function validate( array $data ) {
		$required = array( 'menu', 'items' );

		foreach ( $required as $key ) {
			if ( empty( $data[ $key ] ) ) {
				return new WP_Error(
					'missing_key',
					sprintf(
					/* translators: %s: missing key name */
						__( 'Import file is missing required key: "%s".', 'swift-menu-duplicator' ),
						$key
					)
				);
			}
		}

		if ( empty( $data['menu']['name'] ) ) {
			return new WP_Error( 'missing_menu_name', __( 'Import file does not contain a menu name.', 'swift-menu-duplicator' ) );
		}

		if ( ! is_array( $data['items'] ) ) {
			return new WP_Error( 'invalid_items', __( 'Import file "items" key must be an array.', 'swift-menu-duplicator' ) );
		}

		return $data;
	}

	/**
	 * Performs a dry-run import: validates the payload and returns a preview
	 * of what would be created without writing anything to the database.
	 *
	 * @param array<string,mixed> $payload Validated import payload.
	 * @param string              $menu_name Optional. Name for the new menu.
	 *                                                    Falls back to the payload's menu name.
	 * @param string              $find Optional. URL string to find.
	 * @param string              $replace Optional. URL string to replace with.
	 *
	 * @return array<string,mixed> Preview data — menu name and item list.
	 */
	public function preview(
		array $payload,
		string $menu_name = '',
		string $find = '',
		string $replace = ''
	): array {
		$resolved_name = '' !== trim( $menu_name ) ? trim( $menu_name ) : $payload['menu']['name'];

		$items = array();

		foreach ( $payload['items'] as $item ) {
			$title = isset( $item['title'] ) ? (string) $item['title'] : '';
			$url   = $this->get_item_url( $item );

			if ( '' !== $find && '' !== $url ) {
				$url = str_replace( $find, $replace, $url );
			}

			$items[] = array(
				'title'  => $title,
				'url'    => $url,
				'type'   => $item['meta']['_menu_item_type'] ?? '',
				'object' => $item['meta']['_menu_item_object'] ?? '',
				'parent' => $item['meta']['_menu_item_menu_item_parent'] ?? '0',
			);
		}

		return array(
			'menu_name'  => $resolved_name,
			'item_count' => count( $items ),
			'items'      => $items,
			'source_url' => $payload['site_url'] ?? '',
			'exported'   => $payload['exported'] ?? '',
		);
	}

	/**
	 * Imports a menu payload into the current site's database.
	 *
	 * Creates a new nav_menu term and inserts one nav_menu_item post per
	 * item in the payload. Parent–child relationships are re-mapped using
	 * the original item IDs stored in the export. Optionally applies a
	 * find-and-replace transformation to all item URLs before insertion.
	 *
	 * @param array<string,mixed> $payload Validated import payload.
	 * @param string              $menu_name Optional. Override name for the new menu.
	 * @param string              $find Optional. URL string to search for.
	 * @param string              $replace Optional. URL string to replace with.
	 *
	 * @return int|WP_Error New menu term ID on success, WP_Error on failure.
	 */
	public function import(
		array $payload,
		string $menu_name = '',
		string $find = '',
		string $replace = ''
	) {
		$resolved_name = '' !== trim( $menu_name ) ? trim( $menu_name ) : $payload['menu']['name'];

		/**
		 * Filters the name assigned to an imported menu.
		 *
		 * @since 1.2.0
		 *
		 * @param string $resolved_name Proposed menu name.
		 * @param array<string,mixed> $payload Import payload.
		 */
		$resolved_name = (string) apply_filters( 'classic_menu_duplicator_import_menu_name', $resolved_name, $payload );

		$new_term = wp_create_nav_menu( $resolved_name );

		if ( is_wp_error( $new_term ) ) {
			return $new_term;
		}

		$new_menu_id = (int) $new_term['term_id'];

		/**
		 * Fires immediately before items are inserted during an import.
		 *
		 * @since 1.2.0
		 *
		 * @param int $new_menu_id New menu term ID.
		 * @param array<string,mixed> $payload Import payload.
		 */
		do_action( 'classic_menu_duplicator_before_import_menu', $new_menu_id, $payload );

		// Maps original export item ID => new inserted post ID.
		/** @var array<int,int> $id_map */
		$id_map = array();

		foreach ( $payload['items'] as $item ) {
			$new_id = $this->insert_item( $item, $new_menu_id, $find, $replace );

			if ( is_wp_error( $new_id ) ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log(
					sprintf(
						'Classic Menu Duplicator: import failed for item "%s" — %s',
						$item['title'] ?? '',
						$new_id->get_error_message()
					)
				);
				continue;
			}

			$original_id            = (int) ( $item['id'] ?? 0 );
			$id_map[ $original_id ] = $new_id;
		}

		// Second pass: re-map parent references to the new post IDs.
		foreach ( $payload['items'] as $item ) {
			$original_id     = (int) ( $item['id'] ?? 0 );
			$original_parent = (int) ( $item['meta']['_menu_item_menu_item_parent'] ?? 0 );

			if ( $original_parent > 0 && isset( $id_map[ $original_id ], $id_map[ $original_parent ] ) ) {
				update_post_meta( $id_map[ $original_id ], '_menu_item_menu_item_parent', (string) $id_map[ $original_parent ] );
			}
		}

		/**
		 * Fires after all items have been imported.
		 *
		 * @since 1.2.0
		 *
		 * @param int $new_menu_id New menu term ID.
		 * @param array<int,int> $id_map Map of original => new item IDs.
		 * @param array $payload Full import payload.
		 */
		do_action( 'classic_menu_duplicator_after_import_menu', $new_menu_id, $id_map, $payload );

		return $new_menu_id;
	}

	// -----------------------------------------------------------------------
	// Private helpers.
	// -----------------------------------------------------------------------

	/**
	 * Inserts a single nav_menu_item post from an import payload item entry.
	 *
	 * @param array<string,mixed> $item Single item array from the payload.
	 * @param int                 $new_menu_id Destination menu term ID.
	 * @param string              $find URL find string.
	 * @param string              $replace URL replace string.
	 *
	 * @return int|WP_Error New post ID or WP_Error.
	 */
	private function insert_item( array $item, int $new_menu_id, string $find, string $replace ) {
		$new_id = wp_insert_post(
			array(
				'post_type'    => 'nav_menu_item',
				'post_status'  => isset( $item['status'] ) ? sanitize_key( $item['status'] ) : 'publish',
				'post_title'   => isset( $item['title'] ) ? sanitize_text_field( $item['title'] ) : '',
				'post_excerpt' => isset( $item['excerpt'] ) ? sanitize_text_field( $item['excerpt'] ) : '',
				'menu_order'   => isset( $item['menu_order'] ) ? absint( $item['menu_order'] ) : 0,
				'post_parent'  => 0,
			),
			true
		);

		if ( is_wp_error( $new_id ) ) {
			return $new_id;
		}

		wp_set_object_terms( $new_id, array( $new_menu_id ), 'nav_menu' );

		if ( ! empty( $item['meta'] ) && is_array( $item['meta'] ) ) {
			$allowed_keys = array(
				'_menu_item_type',
				'_menu_item_menu_item_parent',
				'_menu_item_object_id',
				'_menu_item_object',
				'_menu_item_target',
				'_menu_item_classes',
				'_menu_item_xfn',
				'_menu_item_url',
			);

			foreach ( $allowed_keys as $key ) {
				if ( ! isset( $item['meta'][ $key ] ) ) {
					continue;
				}

				$value = $item['meta'][ $key ];

				// Apply URL replacement to the custom link href.
				if ( '_menu_item_url' === $key && '' !== $find ) {
					$value = str_replace( $find, $replace, (string) $value );
				}

				update_post_meta( $new_id, $key, $value );
			}
		}

		/**
		 * Fires after a single item has been inserted during import.
		 *
		 * @since 1.2.0
		 *
		 * @param int $new_id Inserted post ID.
		 * @param array<string,mixed> $item Original item payload entry.
		 */
		do_action( 'classic_menu_duplicator_after_import_item', $new_id, $item );

		return $new_id;
	}

	/**
	 * Resolves the display URL for an item, preferring the custom link URL.
	 *
	 * @param array<string,mixed> $item Item payload entry.
	 *
	 * @return string URL string, or empty string if none.
	 */
	private function get_item_url( array $item ): string {
		if ( isset( $item['meta']['_menu_item_url'] ) && '' !== $item['meta']['_menu_item_url'] ) {
			return (string) $item['meta']['_menu_item_url'];
		}

		return '';
	}
}
