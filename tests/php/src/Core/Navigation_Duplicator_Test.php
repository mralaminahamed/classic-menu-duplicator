<?php
/**
 * Test suite for Navigation_Duplicator — block-theme navigation.
 *
 * @package SwiftMenuDuplicator
 */

namespace SwiftMenuDuplicator\Test\Core;

use SwiftMenuDuplicator\Core\Navigation_Duplicator;
use SwiftMenuDuplicator\Test\SwiftMenuDuplicatorTestCase;

/**
 * Covers duplication, export, validation, and import of `wp_navigation` posts.
 */
class Navigation_Duplicator_Test extends SwiftMenuDuplicatorTestCase {

	private Navigation_Duplicator $duplicator;

	/**
	 * Block markup used by the fixtures.
	 *
	 * @var string
	 */
	private const CONTENT = '<!-- wp:navigation-link {"label":"Home","url":"https://example.com/"} /-->'
		. '<!-- wp:navigation-submenu {"label":"More","url":"https://example.com/more/"} -->'
		. '<!-- wp:navigation-link {"label":"Nested","url":"https://example.com/nested/"} /-->'
		. '<!-- /wp:navigation-submenu -->';

	/**
	 * Setup test environment.
	 */
	public function set_up() {
		parent::set_up();

		$this->duplicator = new Navigation_Duplicator();
	}

	/**
	 * Clean up test data.
	 */
	public function tear_down() {
		global $wpdb;

		$wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_type = 'wp_navigation'" );

		parent::tear_down();
	}

	/**
	 * Creates a block navigation menu.
	 *
	 * @param string $title Navigation title.
	 *
	 * @return int Post ID.
	 */
	private function create_navigation( string $title = 'Header' ): int {
		return (int) self::factory()->post->create(
			array(
				'post_type'    => Navigation_Duplicator::POST_TYPE,
				'post_title'   => $title,
				'post_content' => self::CONTENT,
				'post_status'  => 'publish',
			)
		);
	}

	// -----------------------------------------------------------------------
	// duplicate()
	// -----------------------------------------------------------------------

	/**
	 * @covers Navigation_Duplicator::duplicate
	 */
	public function test_duplicate_invalid_id_returns_error(): void {
		$result = $this->duplicator->duplicate( 999999 );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'invalid_navigation', $result->get_error_code() );
	}

	/**
	 * @covers Navigation_Duplicator::duplicate
	 */
	public function test_duplicate_rejects_a_post_of_another_type(): void {
		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$result = $this->duplicator->duplicate( $page_id );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'invalid_navigation', $result->get_error_code() );
	}

	/**
	 * @covers Navigation_Duplicator::duplicate
	 */
	public function test_duplicate_copies_block_markup(): void {
		$source_id = $this->create_navigation( 'Header' );

		$new_id = $this->duplicator->duplicate( $source_id );

		$this->assertIsInt( $new_id );
		$this->assertSame( self::CONTENT, get_post( $new_id )->post_content );
		$this->assertSame( Navigation_Duplicator::POST_TYPE, get_post( $new_id )->post_type );
	}

	/**
	 * @covers Navigation_Duplicator::duplicate
	 */
	public function test_duplicate_uses_copy_suffix_then_custom_title(): void {
		$source_id = $this->create_navigation( 'Header' );

		$suffixed = $this->duplicator->duplicate( $source_id );
		$named    = $this->duplicator->duplicate( $source_id, 'Header (Staging)' );

		$this->assertStringContainsString( '(Copy)', get_post( $suffixed )->post_title );
		$this->assertSame( 'Header (Staging)', get_post( $named )->post_title );
	}

	/**
	 * @covers Navigation_Duplicator::duplicate
	 */
	public function test_duplicate_copies_meta_but_not_editor_bookkeeping(): void {
		$source_id = $this->create_navigation( 'Header' );

		update_post_meta( $source_id, 'custom_key', 'custom value' );
		update_post_meta( $source_id, '_edit_lock', '1234567890:1' );

		$new_id = $this->duplicator->duplicate( $source_id );

		$this->assertSame( 'custom value', get_post_meta( $new_id, 'custom_key', true ) );
		$this->assertSame( '', get_post_meta( $new_id, '_edit_lock', true ) );
	}

	/**
	 * @covers Navigation_Duplicator::duplicate
	 */
	public function test_duplicate_fires_its_actions(): void {
		$source_id = $this->create_navigation( 'Hooked' );

		$before = 0;
		$after  = 0;

		add_action(
			'swift_menu_duplicator_before_duplicate_navigation',
			static function () use ( &$before ) {
				++$before;
			}
		);
		add_action(
			'swift_menu_duplicator_after_duplicate_navigation',
			static function () use ( &$after ) {
				++$after;
			}
		);

		$this->duplicator->duplicate( $source_id );

		remove_all_actions( 'swift_menu_duplicator_before_duplicate_navigation' );
		remove_all_actions( 'swift_menu_duplicator_after_duplicate_navigation' );

		$this->assertSame( 1, $before );
		$this->assertSame( 1, $after );
	}

	// -----------------------------------------------------------------------
	// export() / validate() / import()
	// -----------------------------------------------------------------------

	/**
	 * @covers Navigation_Duplicator::export
	 */
	public function test_export_payload_structure(): void {
		$payload = $this->duplicator->export( $this->create_navigation( 'Header' ) );

		$this->assertSame( 'wp_navigation', $payload['type'] );
		$this->assertSame( 'Header', $payload['navigation']['title'] );
		$this->assertSame( self::CONTENT, $payload['content'] );
		$this->assertJson( (string) wp_json_encode( $payload ) );
	}

	/**
	 * @covers Navigation_Duplicator::validate
	 */
	public function test_validate_requires_content_and_title(): void {
		$this->assertInstanceOf(
			\WP_Error::class,
			$this->duplicator->validate( array( 'navigation' => array( 'title' => 'X' ) ) )
		);

		$this->assertInstanceOf(
			\WP_Error::class,
			$this->duplicator->validate( array( 'content' => '<!-- wp:navigation-link /-->' ) )
		);
	}

	/**
	 * @covers Navigation_Duplicator::validate
	 */
	public function test_validate_enforces_the_size_limit(): void {
		add_filter( 'swift_menu_duplicator_max_import_bytes', static fn () => 10 );

		$result = $this->duplicator->validate(
			array(
				'navigation' => array( 'title' => 'Too big' ),
				'content'    => str_repeat( 'x', 64 ),
			)
		);

		remove_all_filters( 'swift_menu_duplicator_max_import_bytes' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'too_large', $result->get_error_code() );
	}

	/**
	 * @covers Navigation_Duplicator::import
	 */
	public function test_import_round_trip_preserves_markup(): void {
		$payload = $this->duplicator->export( $this->create_navigation( 'Header' ) );

		$new_id = $this->duplicator->import( $payload, 'Imported Header' );

		$this->assertIsInt( $new_id );
		$this->assertSame( 'Imported Header', get_post( $new_id )->post_title );
		$this->assertSame( self::CONTENT, get_post( $new_id )->post_content );
	}

	// -----------------------------------------------------------------------
	// Helpers.
	// -----------------------------------------------------------------------

	/**
	 * @covers Navigation_Duplicator::get_all
	 */
	public function test_get_all_returns_navigation_posts_only(): void {
		$this->create_navigation( 'Header' );
		$this->create_navigation( 'Footer' );
		self::factory()->post->create( array( 'post_type' => 'page' ) );

		$all = $this->duplicator->get_all();

		$this->assertCount( 2, $all );
		$this->assertSame( array( 'Footer', 'Header' ), wp_list_pluck( $all, 'post_title' ) );
	}

	/**
	 * @covers Navigation_Duplicator::get_edit_url
	 */
	public function test_edit_url_points_at_the_site_editor(): void {
		$url = $this->duplicator->get_edit_url( 42 );

		$this->assertStringContainsString( 'site-editor.php', $url );
		$this->assertStringContainsString( 'postType=wp_navigation', $url );
		$this->assertStringContainsString( 'postId=42', $url );
	}
}
