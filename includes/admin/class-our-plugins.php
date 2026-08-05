<?php
/**
 * The author's other plugins, listed from the WordPress.org directory.
 *
 * @package SwiftMenuDuplicator
 */

declare( strict_types=1 );

namespace SwiftMenuDuplicator\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Our_Plugins
 *
 * Supplies the Menu Manager's "Our Plugins" tab: everything else the author
 * publishes, with its state on this site.
 *
 * A tab rather than a screen of its own. The point of this list is to be seen
 * by people who use this plugin, and those people are already on the Menu
 * Manager — a submenu under Plugins is where it belongs by classification and
 * nowhere near where anybody would find it.
 *
 * The list is asked of WordPress.org rather than written down here. A hard-coded
 * list is wrong the day a plugin is released, renamed or retired, and it cannot
 * know a version number, a rating, or how many sites run it.
 *
 * Nothing here installs or activates anything itself. The buttons point at
 * wp-admin's own `update.php` and `plugins.php`, which already carry the
 * capability checks, the nonces, the filesystem-credentials prompt and the
 * rollback on a fatal — reimplementing any of that inside a menu plugin would
 * be a liability rather than a convenience.
 */
class Our_Plugins {

	/**
	 * The WordPress.org username whose plugins are listed.
	 */
	private const AUTHOR = 'mralaminahamed';

	/**
	 * This plugin's own slug, left off its own screen.
	 */
	private const SELF_SLUG = 'swift-menu-duplicator';

	/**
	 * Where the directory's answer is kept.
	 */
	private const CACHE_KEY = 'swmd_our_plugins';

	/**
	 * How long to keep it.
	 *
	 * The directory is a third-party HTTP request, and an admin screen that
	 * makes one on every page load hangs for as long as WordPress.org is having
	 * a bad day. Twelve hours is far longer than the data changes and far
	 * shorter than a release cycle.
	 */
	private const CACHE_TTL = 12 * HOUR_IN_SECONDS;

	/**
	 * The author's plugins, as the directory reports them.
	 *
	 * @return array{plugins: array<int, array<string, mixed>>, error: string}
	 */
	private function from_directory(): array {
		$cached = get_transient( self::CACHE_KEY );

		if ( is_array( $cached ) ) {
			return array(
				'plugins' => $cached,
				'error'   => '',
			);
		}

		if ( ! function_exists( 'plugins_api' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		}

		/**
		 * Filters the WordPress.org author whose plugins this screen lists.
		 *
		 * @param string $author The directory username.
		 */
		$author = (string) apply_filters( 'swmd_our_plugins_author', self::AUTHOR );

		$response = plugins_api(
			'query_plugins',
			array(
				'author'   => $author,
				'per_page' => 24,
				'fields'   => array(
					'short_description' => true,
					'icons'             => true,
					'active_installs'   => true,
					'ratings'           => false,
					'sections'          => false,
					'contributors'      => false,
					'tags'              => false,
					'compatibility'     => false,
				),
			)
		);

		if ( is_wp_error( $response ) || empty( $response->plugins ) ) {
			/*
			 * Cached briefly even on failure, or a directory that is down means
			 * every load of this screen waits out the same timeout again.
			 */
			set_transient( self::CACHE_KEY, array(), 5 * MINUTE_IN_SECONDS );

			return array(
				'plugins' => array(),
				'error'   => is_wp_error( $response )
					? $response->get_error_message()
					: __( 'The plugin directory returned nothing.', 'swift-menu-duplicator' ),
			);
		}

		$plugins = array();

		foreach ( $response->plugins as $plugin ) {
			$plugin = (array) $plugin;
			$slug   = (string) ( $plugin['slug'] ?? '' );

			/**
			 * Filters the slugs left off this screen.
			 *
			 * Only this plugin by default — listing the screen you are already
			 * looking at.
			 *
			 * @param array $skip Plugin slugs.
			 */
			$skip = (array) apply_filters( 'swmd_our_plugins_skip', array( self::SELF_SLUG ) );

			if ( '' === $slug || in_array( $slug, $skip, true ) ) {
				continue;
			}

			$icons = (array) ( $plugin['icons'] ?? array() );

			$plugins[] = array(
				'slug'            => $slug,
				'name'            => wp_strip_all_tags( (string) ( $plugin['name'] ?? $slug ) ),
				'description'     => wp_strip_all_tags( (string) ( $plugin['short_description'] ?? '' ) ),
				'version'         => (string) ( $plugin['version'] ?? '' ),
				'active_installs' => (int) ( $plugin['active_installs'] ?? 0 ),

				/*
				 * The directory scores out of 100, not out of five. Converted
				 * here so the template does not have to know that, and
				 * `num_ratings` travels with it — four stars from three people
				 * and four stars from four hundred are not the same claim, and a
				 * rating shown without its count implies the second.
				 */
				'rating'          => (float) ( $plugin['rating'] ?? 0 ) / 20,
				'num_ratings'     => (int) ( $plugin['num_ratings'] ?? 0 ),

				/*
				 * `2x` where the directory has one: these render at 48px on a
				 * frequently retina screen, and the 1x asset is visibly soft.
				 */
				'icon'            => (string) ( $icons['2x'] ?? $icons['1x'] ?? $icons['svg'] ?? '' ),
				'url'             => 'https://wordpress.org/plugins/' . $slug . '/',
			);
		}

		set_transient( self::CACHE_KEY, $plugins, self::CACHE_TTL );

		return array(
			'plugins' => $plugins,
			'error'   => '',
		);
	}

	/**
	 * The installed plugin file for a slug, or ''.
	 *
	 * @param string $slug Plugin directory slug.
	 *
	 * @return string
	 */
	private function plugin_file( string $slug ): string {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		foreach ( array_keys( get_plugins() ) as $file ) {
			if ( dirname( $file ) === $slug ) {
				return $file;
			}
		}

		return '';
	}

	/**
	 * The directory's list, marked with what this site already has.
	 *
	 * Install state is worked out on every request rather than cached with the
	 * listing. The directory's answer changes on their schedule; whether this
	 * site has a plugin changes on the administrator's, and caching the two
	 * together would leave a plugin reading "not installed" for hours after
	 * somebody installed it.
	 *
	 * @return array{plugins: array<int, array<string, mixed>>, error: string}
	 */
	public function get_plugins_with_state(): array {
		$directory = $this->from_directory();
		$entries   = array();

		foreach ( $directory['plugins'] as $plugin ) {
			$file      = $this->plugin_file( $plugin['slug'] );
			$is_active = '' !== $file && is_plugin_active( $file );

			/*
			 * Three states, not two. "Installed but switched off" is the one
			 * worth distinguishing: the site already has the plugin and needs a
			 * click rather than a download, and telling somebody to install what
			 * they already have is how a screen loses their trust.
			 */
			$plugin['state'] = $is_active ? 'active' : ( '' !== $file ? 'inactive' : 'missing' );

			/*
			 * Core's own URLs, with core's own nonces. `update.php` prompts for
			 * filesystem credentials where the server needs them and rolls back
			 * a fatal activation by itself — none of which is worth rebuilding
			 * inside a menu plugin.
			 */
			if ( 'missing' === $plugin['state'] ) {
				$plugin['action_url'] = current_user_can( 'install_plugins' )
					? wp_nonce_url(
						self_admin_url( 'update.php?action=install-plugin&plugin=' . rawurlencode( $plugin['slug'] ) ),
						'install-plugin_' . $plugin['slug']
					)
					: '';
			} elseif ( 'inactive' === $plugin['state'] ) {
				$plugin['action_url'] = current_user_can( 'activate_plugins' )
					? wp_nonce_url(
						self_admin_url( 'plugins.php?action=activate&plugin=' . rawurlencode( $file ) ),
						'activate-plugin_' . $file
					)
					: '';
			} else {
				$plugin['action_url'] = '';
			}

			$entries[] = $plugin;
		}

		return array(
			'plugins' => $entries,
			'error'   => $directory['error'],
		);
	}
}
