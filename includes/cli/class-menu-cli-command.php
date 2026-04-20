<?php
/**
 * WP-CLI integration for Swift Menu Duplicator.
 *
 * @package SwiftMenuDuplicator
 */

declare( strict_types=1 );

namespace SwiftMenuDuplicator\Cli;

use SwiftMenuDuplicator\Core\Menu_Duplicator;
use SwiftMenuDuplicator\Import\Menu_Importer;
use SwiftMenuDuplicator\Utils\Filesystem;
use WP_CLI;
use WP_CLI_Command;
use WP_Term;
use function WP_CLI\Utils\format_items;
use function WP_CLI\Utils\get_flag_value;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages navigation menus: duplicate, export, and import.
 *
 * ## EXAMPLES
 *
 *     # Duplicate a menu by its term ID
 *     $ wp swift-menu-duplicator duplicate 42
 *
 *     # Duplicate with a custom name
 *     $ wp swift-menu-duplicator duplicate 42 --name="Holiday Menu"
 *
 *     # Export a menu to a JSON file
 *     $ wp swift-menu-duplicator export 42 --output=./my-menu.json
 *
 *     # Import a menu from a JSON file
 *     $ wp swift-menu-duplicator import ./my-menu.json
 *
 *     # Import with URL replacement
 *     $ wp swift-menu-duplicator import ./my-menu.json --find=https://staging.example.com --replace=https://example.com
 *
 *     # Preview an import without writing to the database
 *     $ wp swift-menu-duplicator import ./my-menu.json --dry-run
 *
 *     # Copy a menu to another site on a multisite network
 *     $ wp swift-menu-duplicator copy-to-site 42 --target-blog=3
 *
 * @when after_wp_load
 */
class Menu_CLI_Command extends WP_CLI_Command {

	/**
	 * Duplicates a navigation menu.
	 *
	 * ## OPTIONS
	 *
	 * <menu-id>
	 * : The term ID of the menu to duplicate.
	 *
	 * [--name=<name>]
	 * : Custom name for the duplicated menu.
	 * Falls back to "{original} (Copy)".
	 *
	 * [--porcelain]
	 * : Output only the new menu term ID.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp swift-menu-duplicator duplicate 42
	 *     Success: Duplicated "Main Menu" → "Main Menu (Copy)" (ID: 43)
	 *
	 *     $ wp swift-menu-duplicator duplicate 42 --name="Holiday Menu" --porcelain
	 *     43
	 *
	 * @subcommand duplicate
	 *
	 * @param string[] $args Positional arguments.
	 * @param string[] $assoc_args Named arguments.
	 *
	 * @return void
	 */
	public function duplicate( array $args, array $assoc_args ): void {
		$source_id = (int) ( $args[0] ?? 0 );

		if ( $source_id <= 0 ) {
			WP_CLI::error( 'Please provide a valid menu term ID.' );
		}

		$source_term = get_term( $source_id, 'nav_menu' );

		if ( is_wp_error( $source_term ) || ! $source_term instanceof WP_Term ) {
			WP_CLI::error( sprintf( 'Menu with ID %d not found.', $source_id ) );
		}

		$name      = get_flag_value( $assoc_args, 'name', '' );
		$porcelain = (bool) get_flag_value( $assoc_args, 'porcelain', false );

		$duplicator = new Menu_Duplicator();
		$result     = $duplicator->duplicate( $source_id, (string) $name );

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}

		if ( $porcelain ) {
			WP_CLI::line( (string) $result );

			return;
		}

		$new_term = get_term( $result, 'nav_menu' );

		WP_CLI::success(
			sprintf(
				'Duplicated "%s" → "%s" (ID: %d)',
				$source_term->name,
				$new_term instanceof WP_Term ? $new_term->name : '',
				$result
			)
		);
	}

	/**
	 * Exports a navigation menu to a JSON file.
	 *
	 * ## OPTIONS
	 *
	 * <menu-id>
	 * : The term ID of the menu to export.
	 *
	 * [--output=<file>]
	 * : Path to the output JSON file.
	 * Defaults to "{menu-slug}-menu-export.json" in the current directory.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp swift-menu-duplicator export 42
	 *     Success: Exported "Main Menu" to ./main-menu-menu-export.json
	 *
	 *     $ wp swift-menu-duplicator export 42 --output=/tmp/backup.json
	 *     Success: Exported "Main Menu" to /tmp/backup.json
	 *
	 * @subcommand export
	 *
	 * @param string[] $args       Positional arguments (menu-id).
	 * @param string[] $assoc_args Named arguments (--output).
	 *
	 * @return void
	 */
	public function export( array $args, array $assoc_args ): void {
		$menu_id = (int) ( $args[0] ?? 0 );

		if ( $menu_id <= 0 ) {
			WP_CLI::error( 'Please provide a valid menu term ID.' );
		}

		$term = get_term( $menu_id, 'nav_menu' );

		if ( is_wp_error( $term ) || ! $term instanceof WP_Term ) {
			WP_CLI::error( sprintf( 'Menu with ID %d not found.', $menu_id ) );
		}

		$duplicator = new Menu_Duplicator();
		$payload    = $duplicator->export( $menu_id );

		if ( is_wp_error( $payload ) ) {
			WP_CLI::error( $payload->get_error_message() );
		}

		$default_file = sanitize_file_name( $term->slug ) . '-menu-export.json';
		$output_file  = (string) get_flag_value( $assoc_args, 'output', $default_file );

		$json = wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode

		if ( false === $json ) {
			WP_CLI::error( 'Failed to encode export payload to JSON.' );
		}

		if ( ! Filesystem::write( $output_file, $json ) ) {
			WP_CLI::error( sprintf( 'Could not write to file: %s', $output_file ) );
		}

		WP_CLI::success(
			sprintf( 'Exported "%s" to %s', $term->name, $output_file )
		);
	}

	/**
	 * Imports a navigation menu from a JSON file.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Path to the JSON file to import.
	 *
	 * [--name=<name>]
	 * : Override the menu name from the file.
	 *
	 * [--find=<url>]
	 * : URL string to search for in item URLs (used with --replace).
	 *
	 * [--replace=<url>]
	 * : URL string to replace the --find value with.
	 *
	 * [--dry-run]
	 * : Preview what would be imported without writing to the database.
	 *
	 * [--porcelain]
	 * : Output only the new menu term ID (ignored with --dry-run).
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp swift-menu-duplicator import ./my-menu.json
	 *     Success: Imported "Main Menu" (ID: 45, 12 items)
	 *
	 *     $ wp swift-menu-duplicator import ./staging-menu.json --find=https://staging.example.com --replace=https://example.com
	 *     Success: Imported "Main Menu" (ID: 46, 12 items)
	 *
	 *     $ wp swift-menu-duplicator import ./my-menu.json --dry-run
	 *     (Dry run — no changes were made)
	 *     Menu name   : Main Menu
	 *     Items       : 12
	 *     Source site : https://staging.example.com
	 *
	 * @subcommand import
	 *
	 * @param string[] $args       Positional arguments (file path).
	 * @param string[] $assoc_args Named arguments (--name, --find, --replace, --dry-run, --porcelain).
	 *
	 * @return void
	 */
	public function import( array $args, array $assoc_args ): void {
		$file = $args[0] ?? '';

		if ( '' === $file || ! file_exists( $file ) ) {
			WP_CLI::error( sprintf( 'File not found: %s', $file ) );
		}

		$json = Filesystem::read( $file );

		if ( false === $json ) {
			WP_CLI::error( sprintf( 'Could not read file: %s', $file ) );
		}

		$importer = new Menu_Importer();
		$payload  = $importer->parse( $json );

		if ( is_wp_error( $payload ) ) {
			WP_CLI::error( $payload->get_error_message() );
		}

		$name    = (string) get_flag_value( $assoc_args, 'name', '' );
		$find    = (string) get_flag_value( $assoc_args, 'find', '' );
		$replace = (string) get_flag_value( $assoc_args, 'replace', '' );
		$dry_run = (bool) get_flag_value( $assoc_args, 'dry-run', false );

		if ( $dry_run ) {
			$preview = $importer->preview( $payload, $name, $find, $replace );

			WP_CLI::log( '(Dry run — no changes were made)' );
			WP_CLI::log( sprintf( 'Menu name   : %s', $preview['menu_name'] ) );
			WP_CLI::log( sprintf( 'Items       : %d', $preview['item_count'] ) );

			if ( ! empty( $preview['source_url'] ) ) {
				WP_CLI::log( sprintf( 'Source site : %s', $preview['source_url'] ) );
			}

			if ( ! empty( $preview['exported'] ) ) {
				WP_CLI::log( sprintf( 'Exported at : %s', $preview['exported'] ) );
			}

			if ( ! empty( $preview['items'] ) ) {
				WP_CLI::log( '' );
				$table_data = array_map(
					static function ( array $item ): array {
						return array(
							'title'  => $item['title'],
							'type'   => $item['type'],
							'url'    => $item['url'],
							'parent' => $item['parent'],
						);
					},
					$preview['items']
				);

				format_items( 'table', $table_data, array( 'title', 'type', 'url', 'parent' ) );
			}

			return;
		}

		$new_menu_id = $importer->import( $payload, $name, $find, $replace );

		if ( is_wp_error( $new_menu_id ) ) {
			WP_CLI::error( $new_menu_id->get_error_message() );
		}

		$porcelain = (bool) get_flag_value( $assoc_args, 'porcelain', false );

		if ( $porcelain ) {
			WP_CLI::line( (string) $new_menu_id );

			return;
		}

		$new_term = get_term( $new_menu_id, 'nav_menu' );
		$name_out = $new_term instanceof WP_Term ? $new_term->name : (string) $new_menu_id;

		WP_CLI::success(
			sprintf(
				'Imported "%s" (ID: %d, %d items)',
				$name_out,
				$new_menu_id,
				count( $payload['items'] )
			)
		);
	}

	/**
	 * Copies a navigation menu to another sub-site on a multisite network.
	 *
	 * ## OPTIONS
	 *
	 * <menu-id>
	 * : The term ID of the source menu on the current site.
	 *
	 * --target-blog=<id>
	 * : The blog ID of the destination site.
	 *
	 * [--name=<name>]
	 * : Override the menu name on the destination site.
	 *
	 * [--find=<url>]
	 * : URL string to search for in item URLs.
	 *
	 * [--replace=<url>]
	 * : URL string to replace the --find value with.
	 *
	 * [--porcelain]
	 * : Output only the new menu term ID on the destination site.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp swift-menu-duplicator copy-to-site 42 --target-blog=3
	 *     Success: Copied "Main Menu" to site 3 (new ID: 7)
	 *
	 * @subcommand copy-to-site
	 *
	 * @param string[] $args       Positional arguments (menu-id).
	 * @param string[] $assoc_args Named arguments (--target-blog, --name, --find, --replace, --porcelain).
	 *
	 * @return void
	 */
	public function copy_to_site( array $args, array $assoc_args ): void {
		if ( ! is_multisite() ) {
			WP_CLI::error( 'This command is only available on multisite installations.' );
		}

		$source_id      = (int) ( $args[0] ?? 0 );
		$target_blog_id = (int) get_flag_value( $assoc_args, 'target-blog', 0 );

		if ( $source_id <= 0 ) {
			WP_CLI::error( 'Please provide a valid source menu term ID.' );
		}

		if ( $target_blog_id <= 0 ) {
			WP_CLI::error( 'Please provide a --target-blog=<id> value.' );
		}

		$source_term = get_term( $source_id, 'nav_menu' );

		if ( is_wp_error( $source_term ) || ! $source_term instanceof WP_Term ) {
			WP_CLI::error( sprintf( 'Menu with ID %d not found on the current site.', $source_id ) );
		}

		$name    = (string) get_flag_value( $assoc_args, 'name', '' );
		$find    = (string) get_flag_value( $assoc_args, 'find', '' );
		$replace = (string) get_flag_value( $assoc_args, 'replace', '' );

		$duplicator = new Menu_Duplicator();
		$payload    = $duplicator->export( $source_id );

		if ( is_wp_error( $payload ) ) {
			WP_CLI::error( $payload->get_error_message() );
		}

		switch_to_blog( $target_blog_id );

		$importer    = new Menu_Importer();
		$new_menu_id = $importer->import( $payload, $name, $find, $replace );

		restore_current_blog();

		if ( is_wp_error( $new_menu_id ) ) {
			WP_CLI::error( $new_menu_id->get_error_message() );
		}

		$porcelain = (bool) get_flag_value( $assoc_args, 'porcelain', false );

		if ( $porcelain ) {
			WP_CLI::line( (string) $new_menu_id );

			return;
		}

		WP_CLI::success(
			sprintf(
				'Copied "%s" to site %d (new ID: %d)',
				$source_term->name,
				$target_blog_id,
				$new_menu_id
			)
		);
	}
}
