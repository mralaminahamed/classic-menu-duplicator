<?php
/**
 * Duplication, export, and import for block-theme navigation.
 *
 * @package SwiftMenuDuplicator
 */

declare( strict_types=1 );

namespace SwiftMenuDuplicator\Core;

use WP_Error;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Navigation_Duplicator
 *
 * Block themes do not use the `nav_menu` taxonomy: their navigation lives in
 * `wp_navigation` posts whose post_content is block markup, rendered by the
 * Navigation block. This class gives those the same duplicate / export /
 * import treatment Menu_Duplicator gives classic menus.
 *
 * Every capability on the `wp_navigation` post type maps to
 * `edit_theme_options`, which is the capability the rest of the plugin
 * already requires.
 */
class Navigation_Duplicator {

	/**
	 * Post type holding block-theme navigation.
	 *
	 * @var string
	 */
	public const POST_TYPE = 'wp_navigation';

	/**
	 * Meta keys that must not travel with a duplicate.
	 *
	 * @var string[]
	 */
	private const SKIPPED_META_KEYS = array(
		'_edit_lock',
		'_edit_last',
		'_wp_old_slug',
		'_wp_old_date',
	);

	/**
	 * Returns every block navigation menu on the site.
	 *
	 * @param int $limit Optional. Maximum number of posts to return.
	 *
	 * @return WP_Post[]
	 */
	public function get_all( int $limit = 100 ): array {
		if ( ! post_type_exists( self::POST_TYPE ) ) {
			return array();
		}

		return get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => array( 'publish', 'draft' ),
				'numberposts'      => $limit,
				'orderby'          => 'title',
				'order'            => 'ASC',
				'suppress_filters' => false,
			)
		);
	}

	/**
	 * Duplicates a block navigation menu.
	 *
	 * The post_content is block markup and is copied verbatim; core's own
	 * kses filtering applies on insert for users without `unfiltered_html`,
	 * exactly as it does when the Site Editor saves the same content.
	 *
	 * @param int    $post_id   Post ID of the navigation menu to duplicate.
	 * @param string $new_title Optional. Title for the copy. Falls back to
	 *                          "{original} (Copy)".
	 *
	 * @return int|WP_Error New post ID, or WP_Error on failure.
	 */
	public function duplicate( int $post_id, string $new_title = '' ) {
		$source = $this->get_navigation( $post_id );

		if ( is_wp_error( $source ) ) {
			return $source;
		}

		$new_title = trim( $new_title );

		if ( '' === $new_title ) {
			$new_title = sprintf(
				/* translators: %s: original navigation menu title */
				_x( '%s (Copy)', 'duplicated navigation name suffix', 'swift-menu-duplicator' ),
				$source->post_title
			);
		}

		/**
		 * Filters the title given to a duplicated block navigation menu.
		 *
		 * @since 1.0.5
		 *
		 * @param string  $new_title Proposed title.
		 * @param WP_Post $source    Source navigation post.
		 */
		$new_title = (string) apply_filters( 'swift_menu_duplicator_new_navigation_title', $new_title, $source );

		/**
		 * Fires before a block navigation menu is duplicated.
		 *
		 * @since 1.0.5
		 *
		 * @param int    $post_id   Source post ID.
		 * @param string $new_title Title for the copy.
		 */
		do_action( 'swift_menu_duplicator_before_duplicate_navigation', $post_id, $new_title );

		$new_post_id = wp_insert_post(
			array(
				'post_type'    => self::POST_TYPE,
				'post_status'  => $source->post_status,
				'post_title'   => $new_title,
				'post_content' => $source->post_content,
				'post_excerpt' => $source->post_excerpt,
				'menu_order'   => $source->menu_order,
			),
			true
		);

		if ( is_wp_error( $new_post_id ) ) {
			return $new_post_id;
		}

		$this->copy_meta( $post_id, $new_post_id );

		/**
		 * Fires after a block navigation menu has been duplicated.
		 *
		 * @since 1.0.5
		 *
		 * @param int $post_id     Source post ID.
		 * @param int $new_post_id New post ID.
		 */
		do_action( 'swift_menu_duplicator_after_duplicate_navigation', $post_id, $new_post_id );

		return $new_post_id;
	}

	/**
	 * Exports a block navigation menu to a JSON-serialisable array.
	 *
	 * The payload deliberately mirrors the classic-menu export envelope
	 * (version, exported, site_url) so both file types are recognisable, with
	 * `type` distinguishing them.
	 *
	 * @param int $post_id Post ID of the navigation menu.
	 *
	 * @return array<string,mixed>|WP_Error Export payload, or WP_Error.
	 */
	public function export( int $post_id ) {
		$navigation = $this->get_navigation( $post_id );

		if ( is_wp_error( $navigation ) ) {
			return $navigation;
		}

		/**
		 * Filters the block navigation export payload.
		 *
		 * @since 1.0.5
		 *
		 * @param array<string,mixed> $payload    Export payload.
		 * @param WP_Post             $navigation Source navigation post.
		 */
		return apply_filters(
			'swift_menu_duplicator_navigation_export_payload',
			array(
				'version'    => SWIFT_MENU_DUPLICATOR_VERSION,
				'type'       => 'wp_navigation',
				'exported'   => current_time( 'c' ),
				'site_url'   => home_url(),
				'navigation' => array(
					'title'  => $navigation->post_title,
					'slug'   => $navigation->post_name,
					'status' => $navigation->post_status,
				),
				'content'    => $navigation->post_content,
			),
			$navigation
		);
	}

	/**
	 * Validates a decoded navigation export payload.
	 *
	 * @param array<string,mixed> $data Decoded payload.
	 *
	 * @return array<string,mixed>|WP_Error Validated payload or WP_Error.
	 */
	public function validate( array $data ) {
		if ( ! array_key_exists( 'content', $data ) || ! is_string( $data['content'] ) ) {
			return new WP_Error(
				'missing_key',
				__( 'Import file is missing the navigation content.', 'swift-menu-duplicator' )
			);
		}

		if ( empty( $data['navigation']['title'] ) ) {
			return new WP_Error(
				'missing_navigation_title',
				__( 'Import file does not contain a navigation menu title.', 'swift-menu-duplicator' )
			);
		}

		$max_bytes = (int) apply_filters( 'swift_menu_duplicator_max_import_bytes', 2 * MB_IN_BYTES );

		if ( strlen( $data['content'] ) > $max_bytes ) {
			return new WP_Error(
				'too_large',
				__( 'The navigation content exceeds the size limit.', 'swift-menu-duplicator' )
			);
		}

		return $data;
	}

	/**
	 * Imports a block navigation menu from an export payload.
	 *
	 * @param array<string,mixed> $payload Validated payload.
	 * @param string              $title   Optional. Override title.
	 *
	 * @return int|WP_Error New post ID, or WP_Error on failure.
	 */
	public function import( array $payload, string $title = '' ) {
		$resolved = '' !== trim( $title ) ? trim( $title ) : (string) $payload['navigation']['title'];

		$new_post_id = wp_insert_post(
			array(
				'post_type'    => self::POST_TYPE,
				'post_status'  => ( isset( $payload['navigation']['status'] ) && 'draft' === $payload['navigation']['status'] )
					? 'draft'
					: 'publish',
				'post_title'   => sanitize_text_field( $resolved ),
				'post_content' => (string) $payload['content'],
			),
			true
		);

		if ( is_wp_error( $new_post_id ) ) {
			return $new_post_id;
		}

		/**
		 * Fires after a block navigation menu has been imported.
		 *
		 * @since 1.0.5
		 *
		 * @param int                 $new_post_id New post ID.
		 * @param array<string,mixed> $payload     Import payload.
		 */
		do_action( 'swift_menu_duplicator_after_import_navigation', $new_post_id, $payload );

		return $new_post_id;
	}

	/**
	 * Returns the Site Editor URL for a navigation menu.
	 *
	 * @param int $post_id Navigation post ID.
	 *
	 * @return string
	 */
	public function get_edit_url( int $post_id ): string {
		return add_query_arg(
			array(
				'postType' => self::POST_TYPE,
				'postId'   => $post_id,
				'canvas'   => 'edit',
			),
			admin_url( 'site-editor.php' )
		);
	}

	// -----------------------------------------------------------------------
	// Private helpers.
	// -----------------------------------------------------------------------

	/**
	 * Loads a navigation post, or an error when the ID is not one.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return WP_Post|WP_Error
	 */
	private function get_navigation( int $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post || self::POST_TYPE !== $post->post_type ) {
			return new WP_Error(
				'invalid_navigation',
				__( 'Navigation menu not found.', 'swift-menu-duplicator' )
			);
		}

		return $post;
	}

	/**
	 * Copies a navigation post's meta, minus the editor bookkeeping keys.
	 *
	 * @param int $source_id Source post ID.
	 * @param int $dest_id   Destination post ID.
	 *
	 * @return void
	 */
	private function copy_meta( int $source_id, int $dest_id ): void {
		$meta = get_post_meta( $source_id );

		if ( ! is_array( $meta ) ) {
			return;
		}

		/**
		 * Filters the meta keys skipped when duplicating a navigation menu.
		 *
		 * @since 1.0.5
		 *
		 * @param string[] $keys Meta keys to skip.
		 */
		$skipped = (array) apply_filters(
			'swift_menu_duplicator_navigation_skipped_meta_keys',
			self::SKIPPED_META_KEYS
		);

		foreach ( $meta as $key => $values ) {
			if ( in_array( $key, $skipped, true ) ) {
				continue;
			}

			foreach ( (array) $values as $value ) {
				add_post_meta( $dest_id, $key, maybe_unserialize( $value ) );
			}
		}
	}
}
