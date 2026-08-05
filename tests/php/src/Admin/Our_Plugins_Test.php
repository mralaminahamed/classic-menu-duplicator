<?php
/**
 * Test suite for Our_Plugins.
 *
 * @package SwiftMenuDuplicator
 */

namespace SwiftMenuDuplicator\Test\Admin;

use SwiftMenuDuplicator\Admin\Our_Plugins;
use WP_Error;
use WP_UnitTestCase;

/**
 * Exercises the Menu Manager's "Our Plugins" tab.
 *
 * The listing comes from the WordPress.org directory, so `plugins_api` is
 * filtered rather than called: what matters here is that the answer is cached,
 * that install state is read from this site rather than from the cache, that
 * the action links carry core's own nonces, and that an unreachable directory
 * degrades to a message instead of a fatal.
 */
class Our_Plugins_Test extends WP_UnitTestCase {

	/**
	 * Set up test.
	 */
	public function set_up(): void {
		parent::set_up();

		delete_transient( 'swmd_our_plugins' );

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
	}

	/**
	 * Tear down test.
	 */
	public function tear_down(): void {
		remove_all_filters( 'plugins_api' );
		delete_transient( 'swmd_our_plugins' );
		wp_cache_delete( 'plugins', 'plugins' );

		parent::tear_down();
	}

	/**
	 * Answer the directory with a fixture rather than a network call.
	 *
	 * @param array<int, array<string, mixed>>|null $plugins Rows, or null to fail.
	 *
	 * @return void
	 */
	private function fake_directory( ?array $plugins ): void {
		add_filter(
			'plugins_api',
			static function ( $result, $action ) use ( $plugins ) {
				if ( 'query_plugins' !== $action ) {
					return $result;
				}

				if ( null === $plugins ) {
					return new WP_Error( 'plugins_api_failed', 'The directory is unreachable.' );
				}

				return (object) array(
					'plugins' => array_map(
						static function ( $plugin ) {
							return (object) $plugin;
						},
						$plugins
					),
				);
			},
			10,
			2
		);
	}

	/**
	 * A row as the directory returns one.
	 *
	 * @param string $slug Plugin slug.
	 *
	 * @return array<string, mixed>
	 */
	private function row( string $slug ): array {
		return array(
			'slug'              => $slug,
			'name'              => ucfirst( $slug ),
			'short_description' => 'Does a useful thing.',
			'version'           => '2.0.0',
			'active_installs'   => 1000,
			'rating'            => 90,
			'num_ratings'       => 12,
			'icons'             => array( '2x' => 'https://ps.w.org/' . $slug . '/assets/icon-256.png' ),
		);
	}

	/**
	 * Call the page's private listing method.
	 *
	 * @return array{plugins: array<int, array<string, mixed>>, error: string}
	 */
	private function listing(): array {
		return ( new Our_Plugins() )->get_plugins_with_state();
	}

	/**
	 * The screen lists what the directory returned.
	 */
	public function test_it_lists_the_authors_plugins(): void {
		$this->fake_directory( array( $this->row( 'fixture-one' ), $this->row( 'fixture-two' ) ) );

		$listing = $this->listing();

		$this->assertCount( 2, $listing['plugins'] );
		$this->assertSame( 'fixture-one', $listing['plugins'][0]['slug'] );
		$this->assertSame( '', $listing['error'] );

		// The directory scores out of 100; the screen shows five stars.
		$this->assertSame( 4.5, $listing['plugins'][0]['rating'] );
		$this->assertSame( 12, $listing['plugins'][0]['num_ratings'] );
	}

	/**
	 * This plugin is not listed on its own screen.
	 */
	public function test_it_leaves_itself_out(): void {
		$this->fake_directory(
			array( $this->row( 'swift-menu-duplicator' ), $this->row( 'fixture-two' ) )
		);

		$slugs = wp_list_pluck( $this->listing()['plugins'], 'slug' );

		$this->assertNotContains( 'swift-menu-duplicator', $slugs );
		$this->assertContains( 'fixture-two', $slugs );
	}

	/**
	 * The directory is asked once and then cached.
	 *
	 * An admin screen that makes a third-party HTTP request on every load hangs
	 * for as long as WordPress.org is having a bad day.
	 */
	public function test_the_directory_is_only_asked_once(): void {
		$calls = 0;

		add_filter(
			'plugins_api',
			function ( $result, $action ) use ( &$calls ) {
				if ( 'query_plugins' !== $action ) {
					return $result;
				}

				++$calls;

				return (object) array( 'plugins' => array( (object) $this->row( 'fixture-two' ) ) );
			},
			10,
			2
		);

		$this->listing();
		$this->listing();
		$this->listing();

		$this->assertSame( 1, $calls );
	}

	/**
	 * Install state is read from this site, not from the cached listing.
	 *
	 * The directory's answer changes on their schedule; whether this site has a
	 * plugin changes on the administrator's. Caching the two together would
	 * leave a plugin reading "not installed" for hours after it was installed.
	 */
	public function test_install_state_is_not_cached_with_the_listing(): void {
		/*
		 * A slug that cannot be on disk. `get_plugins()` scans the real plugins
		 * directory, which on a development machine is the same one the site
		 * uses — a fixture named after a plugin the developer happens to have
		 * installed would measure their machine rather than this code.
		 */
		$this->fake_directory( array( $this->row( 'swmd-fixture-absent' ) ) );

		$first = $this->listing()['plugins'][0];

		$this->assertSame( 'missing', $first['state'] );

		/*
		 * The site gains the plugin while the listing stays cached. Written into
		 * the object cache because `get_plugins()` memoises its filesystem scan
		 * under the `plugins` group and has no filter of its own.
		 */
		$cache = wp_cache_get( 'plugins', 'plugins' );
		$cache = is_array( $cache ) ? $cache : array();

		$cache['']['swmd-fixture-absent/swmd-fixture-absent.php'] = array(
			'Name'    => 'Fixture',
			'Version' => '1.0.0',
		);

		wp_cache_set( 'plugins', $cache, 'plugins' );

		$this->assertSame( 'inactive', $this->listing()['plugins'][0]['state'] );
	}

	/**
	 * Action links point at core's own screens, carrying core's own nonces.
	 *
	 * Nothing here installs or activates anything itself — `update.php` and
	 * `plugins.php` already have the capability check, the filesystem
	 * credentials prompt and the rollback on a fatal.
	 */
	public function test_action_links_use_cores_screens_and_nonces(): void {
		$this->fake_directory( array( $this->row( 'swmd-fixture-absent' ) ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$plugin = $this->listing()['plugins'][0];

		$this->assertStringContainsString( 'update.php', $plugin['action_url'] );
		$this->assertStringContainsString( 'action=install-plugin', $plugin['action_url'] );
		$this->assertStringContainsString( '_wpnonce=', $plugin['action_url'] );
	}

	/**
	 * Somebody who cannot install plugins is offered no link to do it with.
	 */
	public function test_a_user_without_the_capability_gets_no_action_link(): void {
		$this->fake_directory( array( $this->row( 'swmd-fixture-absent' ) ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->assertSame( '', $this->listing()['plugins'][0]['action_url'] );
	}

	/**
	 * An unreachable directory is a message, not a fatal.
	 */
	public function test_an_unreachable_directory_degrades(): void {
		$this->fake_directory( null );

		$listing = $this->listing();

		$this->assertSame( array(), $listing['plugins'] );
		$this->assertNotSame( '', $listing['error'] );
	}
}
