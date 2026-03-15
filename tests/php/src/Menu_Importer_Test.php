<?php
/**
 * Test suite for Menu_Importer class — Tier 2.
 *
 * @package ClassicMenuDuplicator
 */

namespace ClassicMenuDuplicator\Test;

use ClassicMenuDuplicator\Menu_Duplicator;
use ClassicMenuDuplicator\Menu_Importer;

/**
 * Covers Menu_Importer::parse(), validate(), preview(), and import().
 */
class Menu_Importer_Test extends ClassicMenuDuplicatorTestCase {

	private Menu_Importer  $importer;
	private Menu_Duplicator $duplicator;

	/**
	 * @inheritDoc
	 */
	public function set_up() {
		parent::set_up();

		$this->importer   = new Menu_Importer();
		$this->duplicator = new Menu_Duplicator();
	}

	/**
	 * @inheritDoc
	 */
	public function tear_down() {
		global $wpdb;

		$wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_type = 'nav_menu_item'" );
		$wpdb->query( "DELETE FROM {$wpdb->term_taxonomy} WHERE taxonomy = 'nav_menu'" );
		$wpdb->query( "DELETE FROM {$wpdb->terms} WHERE 1=1" );
		$wpdb->query( "DELETE FROM {$wpdb->termmeta} WHERE 1=1" );

		parent::tear_down();
	}

	// -----------------------------------------------------------------------
	// parse()
	// -----------------------------------------------------------------------

	/**
	 * @covers Menu_Importer::parse
	 */
	public function test_parse_empty_string_returns_error(): void {
		$result = $this->importer->parse( '' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'empty_json', $result->get_error_code() );
	}

	/**
	 * @covers Menu_Importer::parse
	 */
	public function test_parse_invalid_json_returns_error(): void {
		$result = $this->importer->parse( '{not: valid json' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'invalid_json', $result->get_error_code() );
	}

	/**
	 * @covers Menu_Importer::parse
	 */
	public function test_parse_non_object_json_returns_error(): void {
		$result = $this->importer->parse( '"just a string"' );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * @covers Menu_Importer::parse
	 */
	public function test_parse_valid_payload_returns_array(): void {
		$json   = $this->make_export_json( 'Parse Test', 1 );
		$result = $this->importer->parse( $json );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'menu', $result );
		$this->assertArrayHasKey( 'items', $result );
	}

	// -----------------------------------------------------------------------
	// validate()
	// -----------------------------------------------------------------------

	/**
	 * @covers Menu_Importer::validate
	 */
	public function test_validate_missing_menu_key_returns_error(): void {
		$result = $this->importer->validate( array( 'items' => array() ) );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'missing_key', $result->get_error_code() );
	}

	/**
	 * @covers Menu_Importer::validate
	 */
	public function test_validate_missing_items_key_returns_error(): void {
		$result = $this->importer->validate( array( 'menu' => array( 'name' => 'Test' ) ) );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * @covers Menu_Importer::validate
	 */
	public function test_validate_missing_menu_name_returns_error(): void {
		$result = $this->importer->validate(
			array(
				'menu'  => array( 'slug' => 'test' ),
				'items' => array(),
			)
		);

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'missing_menu_name', $result->get_error_code() );
	}

	// -----------------------------------------------------------------------
	// preview()
	// -----------------------------------------------------------------------

	/**
	 * @covers Menu_Importer::preview
	 */
	public function test_preview_returns_menu_name_from_payload(): void {
		$payload = $this->make_export_payload( 'Preview Menu', 2 );
		$result  = $this->importer->preview( $payload );

		$this->assertEquals( 'Preview Menu', $result['menu_name'] );
	}

	/**
	 * @covers Menu_Importer::preview
	 */
	public function test_preview_overrides_name_when_supplied(): void {
		$payload = $this->make_export_payload( 'Original', 2 );
		$result  = $this->importer->preview( $payload, 'Custom Name' );

		$this->assertEquals( 'Custom Name', $result['menu_name'] );
	}

	/**
	 * @covers Menu_Importer::preview
	 */
	public function test_preview_item_count_matches_payload(): void {
		$payload = $this->make_export_payload( 'Menu', 4 );
		$result  = $this->importer->preview( $payload );

		$this->assertEquals( 4, $result['item_count'] );
	}

	/**
	 * @covers Menu_Importer::preview
	 */
	public function test_preview_applies_url_replacement(): void {
		$menu_id = $this->create_custom_link_menu( 'URL Menu', 'https://staging.example.com/page' );
		$payload = $this->duplicator->export( $menu_id );

		$result = $this->importer->preview(
			$payload,
			'',
			'https://staging.example.com',
			'https://production.example.com'
		);

		$urls = array_column( $result['items'], 'url' );

		foreach ( $urls as $url ) {
			if ( '' !== $url ) {
				$this->assertStringNotContainsString( 'staging.example.com', $url );
				$this->assertStringContainsString( 'production.example.com', $url );
			}
		}
	}

	// -----------------------------------------------------------------------
	// import() — full round-trip.
	// -----------------------------------------------------------------------

	/**
	 * @covers Menu_Importer::import
	 */
	public function test_import_creates_new_menu_term(): void {
		$payload     = $this->make_export_payload( 'Import Test', 3 );
		$new_menu_id = $this->importer->import( $payload );

		$this->assertIsInt( $new_menu_id );
		$this->assertGreaterThan( 0, $new_menu_id );

		$term = get_term( $new_menu_id, 'nav_menu' );
		$this->assertInstanceOf( \WP_Term::class, $term );
		$this->assertEquals( 'Import Test', $term->name );
	}

	/**
	 * @covers Menu_Importer::import
	 */
	public function test_import_custom_name_overrides_payload_name(): void {
		$payload     = $this->make_export_payload( 'Original Name', 1 );
		$new_menu_id = $this->importer->import( $payload, 'Override Name' );

		$term = get_term( $new_menu_id, 'nav_menu' );
		$this->assertEquals( 'Override Name', $term->name );
	}

	/**
	 * @covers Menu_Importer::import
	 */
	public function test_import_creates_correct_item_count(): void {
		$payload     = $this->make_export_payload( 'Three Items', 3 );
		$new_menu_id = $this->importer->import( $payload );

		$items = wp_get_nav_menu_items( $new_menu_id );
		$this->assertCount( 3, $items );
	}

	/**
	 * @covers Menu_Importer::import
	 */
	public function test_import_round_trip_preserves_hierarchy(): void {
		$source_id   = $this->create_nested_menu( 'Nested Source' );
		$payload     = $this->duplicator->export( $source_id );
		$new_menu_id = $this->importer->import( $payload );

		$items = wp_get_nav_menu_items( $new_menu_id );

		$parents  = array_filter( $items, static fn ( $i ) => 0 === (int) $i->menu_item_parent );
		$children = array_filter( $items, static fn ( $i ) => 0 !== (int) $i->menu_item_parent );

		$this->assertCount( 1, $parents );
		$this->assertCount( 1, $children );

		$parent_id   = array_values( $parents )[0]->ID;
		$child_parent = (int) array_values( $children )[0]->menu_item_parent;

		$this->assertEquals( $parent_id, $child_parent );
	}

	/**
	 * @covers Menu_Importer::import
	 */
	public function test_import_applies_url_find_replace(): void {
		$source_id = $this->create_custom_link_menu( 'URL Source', 'https://staging.example.com/page' );
		$payload   = $this->duplicator->export( $source_id );

		$new_menu_id = $this->importer->import(
			$payload,
			'',
			'https://staging.example.com',
			'https://production.example.com'
		);

		$items = wp_get_nav_menu_items( $new_menu_id );

		foreach ( $items as $item ) {
			$url = get_post_meta( $item->ID, '_menu_item_url', true );

			if ( '' !== $url ) {
				$this->assertStringNotContainsString( 'staging.example.com', $url );
				$this->assertStringContainsString( 'production.example.com', $url );
			}
		}
	}

	/**
	 * @covers Menu_Importer::import
	 */
	public function test_import_fires_after_import_menu_action(): void {
		$payload = $this->make_export_payload( 'Action Test', 1 );
		$fired   = false;

		add_action( 'cmd_after_import_menu', static function () use ( &$fired ) {
			$fired = true;
		} );

		$this->importer->import( $payload );

		remove_all_actions( 'cmd_after_import_menu' );

		$this->assertTrue( $fired );
	}

	/**
	 * @covers Menu_Importer::import
	 */
	public function test_cmd_import_menu_name_filter_is_applied(): void {
		$payload = $this->make_export_payload( 'Filter Menu', 1 );

		add_filter( 'cmd_import_menu_name', static fn () => 'Filtered Import Name' );

		$new_menu_id = $this->importer->import( $payload );

		remove_all_filters( 'cmd_import_menu_name' );

		$term = get_term( $new_menu_id, 'nav_menu' );
		$this->assertEquals( 'Filtered Import Name', $term->name );
	}

	// -----------------------------------------------------------------------
	// Export → Import round-trip (integration).
	// -----------------------------------------------------------------------

	/**
	 * Full export → JSON → parse → import round-trip test.
	 *
	 * @covers Menu_Duplicator::export
	 * @covers Menu_Importer::parse
	 * @covers Menu_Importer::import
	 */
	public function test_full_export_import_round_trip(): void {
		$source_id = $this->create_menu_with_items( 'Round Trip', 5 );
		$payload   = $this->duplicator->export( $source_id );

		$json    = wp_json_encode( $payload );
		$decoded = $this->importer->parse( $json );

		$this->assertIsArray( $decoded );

		$new_menu_id = $this->importer->import( $decoded, 'Imported Copy' );

		$this->assertIsInt( $new_menu_id );

		$source_items   = wp_get_nav_menu_items( $source_id );
		$imported_items = wp_get_nav_menu_items( $new_menu_id );

		$this->assertCount( count( $source_items ), $imported_items );
	}

	// -----------------------------------------------------------------------
	// Private helpers.
	// -----------------------------------------------------------------------

	/**
	 * Creates an export payload array in memory (without hitting the DB for
	 * all scenarios that don't need DB round-trips).
	 *
	 * @param string $name       Menu name.
	 * @param int    $item_count Number of items.
	 *
	 * @return array<string,mixed>
	 */
	private function make_export_payload( string $name, int $item_count ): array {
		$items = array();

		for ( $i = 1; $i <= $item_count; ++$i ) {
			$items[] = array(
				'id'         => $i,
				'title'      => "Item {$i}",
				'excerpt'    => '',
				'status'     => 'publish',
				'menu_order' => $i,
				'meta'       => array(
					'_menu_item_type'              => 'custom',
					'_menu_item_menu_item_parent'  => '0',
					'_menu_item_object_id'         => '0',
					'_menu_item_object'            => 'custom',
					'_menu_item_url'               => "https://example.com/item-{$i}",
					'_menu_item_target'            => '',
					'_menu_item_classes'           => array( '' ),
					'_menu_item_xfn'               => '',
				),
			);
		}

		return array(
			'version'  => '1.2.0',
			'exported' => current_time( 'c' ),
			'site_url' => home_url(),
			'menu'     => array(
				'name'        => $name,
				'slug'        => sanitize_title( $name ),
				'description' => '',
			),
			'items'    => $items,
		);
	}

	/**
	 * Returns a JSON string of a valid export payload.
	 *
	 * @param string $name
	 * @param int    $item_count
	 *
	 * @return string
	 */
	private function make_export_json( string $name, int $item_count ): string {
		return (string) wp_json_encode( $this->make_export_payload( $name, $item_count ) );
	}

	/**
	 * Creates a menu with a single custom-link item pointing to $url.
	 *
	 * @param string $name Menu name.
	 * @param string $url  Custom link URL.
	 *
	 * @return int Menu term ID.
	 */
	private function create_custom_link_menu( string $name, string $url ): int {
		$menu_id = wp_create_nav_menu( $name );

		if ( is_wp_error( $menu_id ) ) {
			return 0;
		}

		wp_update_nav_menu_item(
			$menu_id,
			0,
			array(
				'menu-item-title'   => 'Custom Link',
				'menu-item-url'     => $url,
				'menu-item-type'    => 'custom',
				'menu-item-status'  => 'publish',
				'menu-item-position' => 1,
			)
		);

		return $menu_id;
	}
}
