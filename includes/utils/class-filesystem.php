<?php
/**
 * WordPress Filesystem utility wrapper.
 *
 * @package SwiftMenuDuplicator
 */

declare( strict_types=1 );

namespace SwiftMenuDuplicator\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Filesystem
 *
 * Thin wrapper around the WordPress Filesystem API (WP_Filesystem) for
 * common read / write / delete operations. Always prefer this over native PHP
 * file functions to comply with WordPress coding standards and plugin-review
 * requirements.
 */
class Filesystem {

	/**
	 * Reads the entire contents of a file.
	 *
	 * @param string $file Absolute (or stream-safe) path to the file.
	 *
	 * @return string|false File contents on success, false on failure.
	 */
	public static function read( string $file ) {
		global $wp_filesystem;

		if ( ! self::init() ) {
			return false;
		}

		return $wp_filesystem->get_contents( $file );
	}

	/**
	 * Writes content to a file, creating it if it does not exist.
	 *
	 * @param string $file    Absolute path to the destination file.
	 * @param string $content Content to write.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function write( string $file, string $content ): bool {
		global $wp_filesystem;

		if ( ! self::init() ) {
			return false;
		}

		return $wp_filesystem->put_contents( $file, $content, FS_CHMOD_FILE );
	}

	/**
	 * Deletes a file using the WordPress-recommended function.
	 *
	 * @param string $file Absolute path to the file to delete.
	 *
	 * @return void
	 */
	public static function delete( string $file ): void {
		wp_delete_file( $file );
	}

	/**
	 * Initialises the WordPress Filesystem API if not already loaded.
	 *
	 * Requires wp-admin/includes/file.php when called outside of the admin
	 * context (e.g. WP-CLI). In a standard web-admin request that file is
	 * already included by WordPress core.
	 *
	 * @return bool True when the filesystem object is available, false otherwise.
	 */
	private static function init(): bool {
		global $wp_filesystem;

		if ( $wp_filesystem instanceof \WP_Filesystem_Base ) {
			return true;
		}

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		return (bool) WP_Filesystem();
	}
}
