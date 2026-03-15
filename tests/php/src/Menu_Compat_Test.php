<?php
/**
 * Test suite for Menu_Compat — Tier 3.
 *
 * @package ClassicMenuDuplicator
 */

namespace ClassicMenuDuplicator\Test;

use ClassicMenuDuplicator\Menu_Compat;
use ClassicMenuDuplicator\Menu_Duplicator;

/**
 * Tests the multilingual compatibility layer in isolation by using the
 * cmdu_compat_wpml_active and cmdu_compat_polylang_active filters to
 * simulate both plugins without requiring them to be installed.
 */
class Menu_Compat_Test extends ClassicMenuDuplicatorTestCase {

	/**
	 * @inheritDoc
	 */
	public function tear_down() {
		global $wpdb;

		$wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_type = 'nav_menu_item'" );
		$wpdb->query( "DELETE FROM {$wpdb->term_taxonomy} WHERE taxonomy = 'nav_menu'" );
		$wpdb->query( "DELETE FROM {$wpdb->terms} WHERE 1=1" );
		$wpdb->query( "DELETE FROM {$wpdb->termmeta} WHERE 1=1" );

		remove_all_filters( 'cmdu_compat_wpml_active' );
		remove_all_filters( 'cmdu_compat_polylang_active' );
		remove_all_filters( 'cmdu_item_meta_keys' );
		remove_all_filters( 'cmdu_compat_excluded_meta_keys' );
		remove_all_actions( 'cmdu_after_duplicate_menu_item' );
		remove_all_actions( 'cmdu_after_import_item' );

		parent::tear_down();
	}

	// -----------------------------------------------------------------------
	// register_hooks — no-op when neither plugin is active.
	// -----------------------------------------------------------------------

	/**
	 * @covers Menu_Compat::register_hooks
	 */
	public function test_no_hooks_registered_when_neither_plugin_active(): void {
		$compat = new Menu_Compat();
		$compat->register_hooks();

		$this->assertFalse( has_filter( 'cmdu_item_meta_keys', array( $compat, 'filter_item_meta_keys' ) ) );
	}

	/**
	 * @covers Menu_Compat::register_hooks
	 */
	public function test_hooks_registered_when_wpml_active(): void {
		add_filter( 'cmdu_compat_wpml_active', '__return_true' );

		$compat = new Menu_Compat();
		$compat->register_hooks();

		$this->assertNotFalse( has_filter( 'cmdu_item_meta_keys', array( $compat, 'filter_item_meta_keys' ) ) );
	}

	/**
	 * @covers Menu_Compat::register_hooks
	 */
	public function test_hooks_registered_when_polylang_active(): void {
		add_filter( 'cmdu_compat_polylang_active', '__return_true' );

		$compat = new Menu_Compat();
		$compat->register_hooks();

		$this->assertNotFalse( has_filter( 'cmdu_item_meta_keys', array( $compat, 'filter_item_meta_keys' ) ) );
	}

	// -----------------------------------------------------------------------
	// filter_item_meta_keys — WPML keys removed.
	// -----------------------------------------------------------------------

	/**
	 * @covers Menu_Compat::filter_item_meta_keys
	 */
	public function test_wpml_meta_keys_removed_from_copy_list(): void {
		add_filter( 'cmdu_compat_wpml_active', '__return_true' );

		$compat = new Menu_Compat();
		$compat->register_hooks();

		$keys = apply_filters(
			'cmdu_item_meta_keys',
			array(
				'_menu_item_type',
				'_icl_lang_duplicate_of',
				'wpml_language',
				'_menu_item_url',
			)
		);

		$this->assertNotContains( '_icl_lang_duplicate_of', $keys );
		$this->assertNotContains( 'wpml_language', $keys );
		$this->assertContains( '_menu_item_type', $keys );
		$this->assertContains( '_menu_item_url', $keys );
	}

	/**
	 * @covers Menu_Compat::filter_item_meta_keys
	 */
	public function test_polylang_meta_keys_removed_from_copy_list(): void {
		add_filter( 'cmdu_compat_polylang_active', '__return_true' );

		$compat = new Menu_Compat();
		$compat->register_hooks();

		$keys = apply_filters(
			'cmdu_item_meta_keys',
			array(
				'_menu_item_type',
				'_pll_synced_taxonomies',
				'_pll_menu_language',
			)
		);

		$this->assertNotContains( '_pll_synced_taxonomies', $keys );
		$this->assertNotContains( '_pll_menu_language', $keys );
	}

	// -----------------------------------------------------------------------
	// clean_item_meta — residual meta stripped after duplicate.
	// -----------------------------------------------------------------------

	/**
	 * @covers Menu_Compat::clean_item_meta
	 */
	public function test_clean_item_meta_deletes_wpml_keys_from_duplicated_item(): void {
		add_filter( 'cmdu_compat_wpml_active', '__return_true' );

		$compat = new Menu_Compat();
		$compat->register_hooks();

		// Create a source item with WPML meta already on it.
		$page_id  = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$menu_id  = wp_create_nav_menu( 'WPML Compat Menu' );
		$item_id  = wp_update_nav_menu_item(
			$menu_id,
			0,
			array(
				'menu-item-title'     => 'WPML Item',
				'menu-item-type'      => 'post_type',
				'menu-item-object'    => 'page',
				'menu-item-object-id' => $page_id,
				'menu-item-status'    => 'publish',
			)
		);

		// Plant WPML meta on the source item to simulate what WPML would write.
		update_post_meta( $item_id, '_icl_lang_duplicate_of', 99 );
		update_post_meta( $item_id, 'wpml_language', 'en' );

		// Duplicate the full menu — this exercises both filter_item_meta_keys
		// (prevents copy) and clean_item_meta (strips residual).
		$duplicator  = new Menu_Duplicator();
		$new_menu_id = $duplicator->duplicate( $menu_id );

		$new_items = wp_get_nav_menu_items( $new_menu_id );
		$this->assertCount( 1, $new_items );

		$new_id = $new_items[0]->ID;

		$this->assertEmpty( get_post_meta( $new_id, '_icl_lang_duplicate_of', true ) );
		$this->assertEmpty( get_post_meta( $new_id, 'wpml_language', true ) );
	}

	/**
	 * @covers Menu_Compat::clean_item_meta
	 */
	public function test_clean_item_meta_deletes_polylang_keys_from_duplicated_item(): void {
		add_filter( 'cmdu_compat_polylang_active', '__return_true' );

		$compat = new Menu_Compat();
		$compat->register_hooks();

		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$menu_id = wp_create_nav_menu( 'PLL Compat Menu' );
		$item_id = wp_update_nav_menu_item(
			$menu_id,
			0,
			array(
				'menu-item-title'     => 'Polylang Item',
				'menu-item-type'      => 'post_type',
				'menu-item-object'    => 'page',
				'menu-item-object-id' => $page_id,
				'menu-item-status'    => 'publish',
			)
		);

		update_post_meta( $item_id, '_pll_synced_taxonomies', array( 'category' ) );
		update_post_meta( $item_id, '_pll_menu_language', 'fr' );

		$duplicator  = new Menu_Duplicator();
		$new_menu_id = $duplicator->duplicate( $menu_id );

		$new_items = wp_get_nav_menu_items( $new_menu_id );
		$new_id    = $new_items[0]->ID;

		$this->assertEmpty( get_post_meta( $new_id, '_pll_synced_taxonomies', true ) );
		$this->assertEmpty( get_post_meta( $new_id, '_pll_menu_language', true ) );
	}

	// -----------------------------------------------------------------------
	// cmdu_compat_excluded_meta_keys filter.
	// -----------------------------------------------------------------------

	/**
	 * @covers Menu_Compat::get_excluded_meta_keys (via filter)
	 */
	public function test_excluded_meta_keys_filter_allows_extending_list(): void {
		add_filter( 'cmdu_compat_wpml_active', '__return_true' );

		add_filter(
			'cmdu_compat_excluded_meta_keys',
			static function ( array $keys ): array {
				$keys[] = '_my_custom_i18n_meta';
				return $keys;
			}
		);

		$compat = new Menu_Compat();
		$compat->register_hooks();

		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$menu_id = wp_create_nav_menu( 'Custom Compat Menu' );
		$item_id = wp_update_nav_menu_item(
			$menu_id,
			0,
			array(
				'menu-item-title'     => 'Custom Meta Item',
				'menu-item-type'      => 'post_type',
				'menu-item-object'    => 'page',
				'menu-item-object-id' => $page_id,
				'menu-item-status'    => 'publish',
			)
		);

		update_post_meta( $item_id, '_my_custom_i18n_meta', 'should-be-stripped' );

		$duplicator  = new Menu_Duplicator();
		$new_menu_id = $duplicator->duplicate( $menu_id );

		$new_items = wp_get_nav_menu_items( $new_menu_id );
		$new_id    = $new_items[0]->ID;

		$this->assertEmpty( get_post_meta( $new_id, '_my_custom_i18n_meta', true ) );
	}

	// -----------------------------------------------------------------------
	// cmdu_before_duplicate_item action (added in Tier 3 to Menu_Duplicator).
	// -----------------------------------------------------------------------

	/**
	 * @covers Menu_Duplicator::duplicate_item
	 */
	public function test_cmdu_before_duplicate_item_action_fires(): void {
		$menu_id = $this->create_menu_with_items( 'Before Item Test', 1 );
		$items   = wp_get_nav_menu_items( $menu_id );

		$fired = false;
		add_action( 'cmdu_before_duplicate_item', static function () use ( &$fired ) {
			$fired = true;
		} );

		( new Menu_Duplicator() )->duplicate_item( $items[0]->ID, $menu_id );

		remove_all_actions( 'cmdu_before_duplicate_item' );

		$this->assertTrue( $fired );
	}

	/**
	 * @covers Menu_Duplicator::duplicate_item
	 */
	public function test_cmdu_before_duplicate_item_receives_correct_args(): void {
		$menu_id = $this->create_menu_with_items( 'Before Args Test', 1 );
		$items   = wp_get_nav_menu_items( $menu_id );
		$item_id = $items[0]->ID;

		$captured = array();
		add_action(
			'cmdu_before_duplicate_item',
			static function ( int $i, int $m ) use ( &$captured ) {
				$captured = array( 'item_id' => $i, 'menu_id' => $m );
			},
			10,
			2
		);

		( new Menu_Duplicator() )->duplicate_item( $item_id, $menu_id );

		remove_all_actions( 'cmdu_before_duplicate_item' );

		$this->assertEquals( $item_id, $captured['item_id'] );
		$this->assertEquals( $menu_id, $captured['menu_id'] );
	}
}
