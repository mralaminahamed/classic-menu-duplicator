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
	 * `site_url` recorded in the payload currently being imported.
	 *
	 * Used to tell a same-site restore (object IDs still valid) from a
	 * cross-site migration (object IDs must be re-resolved).
	 *
	 * @var string
	 */
	private string $payload_site_url = '';

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
		// Presence, not truthiness: an export of an empty menu carries a valid
		// but empty "items" array and must still import.
		foreach ( array( 'menu', 'items' ) as $key ) {
			if ( ! array_key_exists( $key, $data ) ) {
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

		if ( ! is_array( $data['menu'] ) || empty( $data['menu']['name'] ) ) {
			return new WP_Error( 'missing_menu_name', __( 'Import file does not contain a menu name.', 'swift-menu-duplicator' ) );
		}

		if ( ! is_array( $data['items'] ) ) {
			return new WP_Error( 'invalid_items', __( 'Import file "items" key must be an array.', 'swift-menu-duplicator' ) );
		}

		$max_items = (int) apply_filters( 'swift_menu_duplicator_max_import_items', 5000 );

		if ( count( $data['items'] ) > $max_items ) {
			return new WP_Error(
				'too_many_items',
				sprintf(
					/* translators: %d: maximum number of menu items allowed in an import */
					__( 'Import file exceeds the maximum of %d menu items.', 'swift-menu-duplicator' ),
					$max_items
				)
			);
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
		 * @since 1.0.0
		 *
		 * @param string $resolved_name Proposed menu name.
		 * @param array<string,mixed> $payload Import payload.
		 */
		$resolved_name = (string) apply_filters( 'swift_menu_duplicator_import_menu_name', $resolved_name, $payload );

		// Core rejects a duplicate menu name outright. Importing a file back
		// into the site it came from is a normal restore flow, so pick the next
		// free variant instead of failing.
		$resolved_name = $this->resolve_unique_menu_name( $resolved_name );

		$new_term = wp_create_nav_menu( $resolved_name );

		if ( is_wp_error( $new_term ) ) {
			return $new_term;
		}

		// wp_create_nav_menu() returns the new term ID (int) or a WP_Error.
		$new_menu_id = (int) $new_term;

		// wp_create_nav_menu() sets the name only; the export carries the menu
		// description as well.
		if ( ! empty( $payload['menu']['description'] ) ) {
			wp_update_nav_menu_object(
				$new_menu_id,
				array(
					'menu-name'   => $resolved_name,
					'description' => sanitize_text_field( (string) $payload['menu']['description'] ),
				)
			);
		}

		/**
		 * Fires immediately before items are inserted during an import.
		 *
		 * @since 1.0.0
		 *
		 * @param int $new_menu_id New menu term ID.
		 * @param array<string,mixed> $payload Import payload.
		 */
		do_action( 'swift_menu_duplicator_before_import_menu', $new_menu_id, $payload );

		$id_map = $this->import_items( $payload, $new_menu_id, $find, $replace );

		/**
		 * Fires after all items have been imported.
		 *
		 * @since 1.0.0
		 *
		 * @param int $new_menu_id New menu term ID.
		 * @param array<int,int> $id_map Map of original => new item IDs.
		 * @param array $payload Full import payload.
		 */
		do_action( 'swift_menu_duplicator_after_import_menu', $new_menu_id, $id_map, $payload );

		return $new_menu_id;
	}

	/**
	 * Inserts every item of a payload into an existing nav_menu term.
	 *
	 * Runs the same two-pass insert used by import(): items are created
	 * first, then parent references are re-mapped to the new post IDs.
	 * Exposed separately so a snapshot restore can refill an existing menu
	 * without creating a new term.
	 *
	 * @param array<string,mixed> $payload Validated import payload.
	 * @param int                 $menu_id Destination menu term ID.
	 * @param string              $find    Optional. URL string to search for.
	 * @param string              $replace Optional. URL string to replace with.
	 *
	 * @return array<int,int> Map of payload item IDs to newly created post IDs.
	 */
	public function import_items( array $payload, int $menu_id, string $find = '', string $replace = '' ): array {
		$id_map = array();

		$this->payload_site_url = isset( $payload['site_url'] ) ? (string) $payload['site_url'] : '';

		if ( empty( $payload['items'] ) || ! is_array( $payload['items'] ) ) {
			return $id_map;
		}

		foreach ( $payload['items'] as $item ) {
			$new_id = $this->insert_item( $item, $menu_id, $find, $replace );

			if ( is_wp_error( $new_id ) ) {
				// Non-fatal: log when WP_DEBUG_LOG is enabled and continue with remaining items.
				if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log(
						sprintf(
							'Swift Menu Duplicator: import failed for item "%s" — %s',
							$item['title'] ?? '',
							$new_id->get_error_message()
						)
					);
				}
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

		return $id_map;
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
		$meta = ( isset( $item['meta'] ) && is_array( $item['meta'] ) ) ? $item['meta'] : array();

		$type   = isset( $meta['_menu_item_type'] ) ? sanitize_key( (string) $meta['_menu_item_type'] ) : 'custom';
		$object = isset( $meta['_menu_item_object'] ) ? sanitize_key( (string) $meta['_menu_item_object'] ) : '';
		$url    = isset( $meta['_menu_item_url'] ) ? (string) $meta['_menu_item_url'] : '';

		if ( '' !== $find ) {
			$url = str_replace( $find, $replace, $url );
		}

		$target = $this->resolve_target( $item, $type, $object, $find, $replace );

		// The referenced object does not exist here: keep the item pointing
		// somewhere real by demoting it to a custom link.
		if ( 'custom' === $target['type'] && 'custom' !== $type ) {
			$type   = 'custom';
			$object = 'custom';
			$url    = $target['url'];
		}

		$classes = $meta['_menu_item_classes'] ?? '';
		$classes = is_array( $classes ) ? implode( ' ', $classes ) : (string) $classes;

		$new_id = wp_update_nav_menu_item(
			$new_menu_id,
			0,
			array(
				'menu-item-object-id'   => $target['object_id'],
				'menu-item-object'      => $object,
				// Parent references are remapped once every item exists.
				'menu-item-parent-id'   => 0,
				'menu-item-position'    => isset( $item['menu_order'] ) ? absint( $item['menu_order'] ) : 0,
				'menu-item-type'        => $type,
				'menu-item-title'       => isset( $item['title'] ) ? sanitize_text_field( (string) $item['title'] ) : '',
				'menu-item-url'         => $url,
				// Item description; kses-filtered because the payload is user-supplied.
				'menu-item-description' => isset( $item['content'] ) ? wp_kses_post( (string) $item['content'] ) : '',
				'menu-item-attr-title'  => isset( $item['excerpt'] ) ? sanitize_text_field( (string) $item['excerpt'] ) : '',
				'menu-item-target'      => ( isset( $meta['_menu_item_target'] ) && '_blank' === $meta['_menu_item_target'] ) ? '_blank' : '',
				'menu-item-classes'     => implode( ' ', array_filter( array_map( 'sanitize_html_class', explode( ' ', $classes ) ) ) ),
				'menu-item-xfn'         => isset( $meta['_menu_item_xfn'] ) ? sanitize_text_field( (string) $meta['_menu_item_xfn'] ) : '',
				'menu-item-status'      => ( isset( $item['status'] ) && 'draft' === $item['status'] ) ? 'draft' : 'publish',
			)
		);

		if ( is_wp_error( $new_id ) ) {
			return $new_id;
		}

		/**
		 * Fires after a single item has been inserted during import.
		 *
		 * @since 1.0.0
		 *
		 * @param int $new_id Inserted post ID.
		 * @param array<string,mixed> $item Original item payload entry.
		 */
		do_action( 'swift_menu_duplicator_after_import_item', $new_id, $item );

		return $new_id;
	}

	/**
	 * Resolves what an imported item should point at on this site.
	 *
	 * Object IDs are local to the site that produced the export, so importing
	 * them verbatim makes a `post_type` or `taxonomy` item address whatever
	 * content happens to hold that ID here — or nothing at all. Resolution
	 * order:
	 *
	 * 1. Same site (the payload's `site_url` matches) — the ID is already right.
	 * 2. Look the object up by the slug recorded at export time.
	 * 3. Give up and report a custom link, using the exported URL so the item
	 *    still leads somewhere.
	 *
	 * @param array<string,mixed> $item    Item payload entry.
	 * @param string              $type    Menu item type (post_type/taxonomy/custom).
	 * @param string              $object_name Post type or taxonomy name.
	 * @param string              $find    URL find string.
	 * @param string              $replace URL replace string.
	 *
	 * @return array{type:string,object_id:int,url:string} Resolution result.
	 */
	private function resolve_target( array $item, string $type, string $object_name, string $find, string $replace ): array {
		$meta      = ( isset( $item['meta'] ) && is_array( $item['meta'] ) ) ? $item['meta'] : array();
		$object_id = isset( $meta['_menu_item_object_id'] ) ? absint( $meta['_menu_item_object_id'] ) : 0;

		if ( ! in_array( $type, array( 'post_type', 'taxonomy' ), true ) || '' === $object_name ) {
			return array(
				'type'      => $type,
				'object_id' => $object_id,
				'url'       => '',
			);
		}

		$keep = array(
			'type'      => $type,
			'object_id' => $object_id,
			'url'       => '',
		);

		if ( $this->is_same_site() ) {
			return $keep;
		}

		$slug = isset( $item['object_slug'] ) ? sanitize_title( (string) $item['object_slug'] ) : '';

		if ( '' !== $slug ) {
			if ( 'post_type' === $type && post_type_exists( $object_name ) ) {
				$found = get_page_by_path( $slug, OBJECT, $object_name );

				if ( $found instanceof \WP_Post ) {
					$keep['object_id'] = $found->ID;
					return $keep;
				}
			}

			if ( 'taxonomy' === $type && taxonomy_exists( $object_name ) ) {
				$found = get_term_by( 'slug', $slug, $object_name );

				if ( $found instanceof \WP_Term ) {
					$keep['object_id'] = $found->term_id;
					return $keep;
				}
			}
		}

		// Nothing matched — fall back to a custom link.
		$url = isset( $item['object_url'] ) ? (string) $item['object_url'] : '';

		if ( '' !== $url && '' !== $find ) {
			$url = str_replace( $find, $replace, $url );
		}

		return array(
			'type'      => 'custom',
			'object_id' => 0,
			'url'       => $url,
		);
	}

	/**
	 * Whether the payload currently being imported came from this same site.
	 *
	 * @return bool
	 */
	private function is_same_site(): bool {
		return '' !== $this->payload_site_url && untrailingslashit( $this->payload_site_url ) === untrailingslashit( home_url() );
	}

	/**
	 * Returns a menu name that is not already taken on the current site.
	 *
	 * Appends an incrementing numeric suffix — "Main Menu", "Main Menu (2)",
	 * "Main Menu (3)" — until a free name is found.
	 *
	 * @param string $name Desired menu name.
	 *
	 * @return string Name that no existing nav_menu term uses.
	 */
	private function resolve_unique_menu_name( string $name ): string {
		if ( ! wp_get_nav_menu_object( $name ) ) {
			return $name;
		}

		$suffix = 2;

		do {
			$candidate = sprintf(
				/* translators: 1: menu name, 2: numeric suffix making the name unique */
				_x( '%1$s (%2$d)', 'imported menu name suffix', 'swift-menu-duplicator' ),
				$name,
				$suffix
			);
			++$suffix;
		} while ( wp_get_nav_menu_object( $candidate ) && $suffix < 1000 );

		return $candidate;
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
