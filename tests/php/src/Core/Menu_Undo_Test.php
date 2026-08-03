<?php
/**
 * Test suite for Menu_Undo — the deleted-menu recovery buffer.
 *
 * @package SwiftMenuDuplicator
 */

namespace SwiftMenuDuplicator\Test\Core;

use SwiftMenuDuplicator\Core\Menu_Undo;
use SwiftMenuDuplicator\Test\SwiftMenuDuplicatorTestCase;

/**
 * Covers capture, restore, theme-location recovery, and buffer expiry.
 */
class Menu_Undo_Test extends SwiftMenuDuplicatorTestCase {

	private Menu_Undo $undo;

	/**
	 * Setup test environment.
	 */
	public function set_up() {
		parent::set_up();

		$this->undo = new Menu_Undo();
		$this->undo->clear();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * Clean up test data.
	 */
	public function tear_down() {
		$this->undo->clear();
		$this->delete_all_menus();
		remove_all_filters( 'swift_menu_duplicator_undo_limit' );

		parent::tear_down();
	}

	// -----------------------------------------------------------------------
	// capture()
	// -----------------------------------------------------------------------

	/**
	 * @covers Menu_Undo::capture
	 */
	public function test_capture_stores_the_menu(): void {
		$menu_id = $this->create_menu_with_items( 'Doomed Menu', 3 );

		$this->assertTrue( $this->undo->capture( $menu_id ) );

		$pending = $this->undo->get_pending();

		$this->assertCount( 1, $pending );
		$this->assertSame( 'Doomed Menu', $pending[0]['name'] );
		$this->assertCount( 3, $pending[0]['payload']['items'] );
	}

	/**
	 * @covers Menu_Undo::capture
	 */
	public function test_capture_rejects_an_unknown_menu(): void {
		$this->assertFalse( $this->undo->capture( 999999 ) );
		$this->assertSame( array(), $this->undo->get_pending() );
	}

	/**
	 * @covers Menu_Undo::capture
	 */
	public function test_buffer_is_capped(): void {
		add_filter( 'swift_menu_duplicator_undo_limit', static fn () => 2 );

		foreach ( array( 'One', 'Two', 'Three' ) as $name ) {
			$this->undo->capture( $this->create_menu_with_items( $name, 1 ) );
		}

		$pending = $this->undo->get_pending();

		// Oldest entries fall off the front.
		$this->assertCount( 2, $pending );
		$this->assertSame( array( 'Two', 'Three' ), wp_list_pluck( $pending, 'name' ) );
	}

	// -----------------------------------------------------------------------
	// restore()
	// -----------------------------------------------------------------------

	/**
	 * @covers Menu_Undo::restore
	 */
	public function test_restore_recreates_the_menu_and_its_items(): void {
		$menu_id = $this->create_menu_with_items( 'Recoverable', 2 );

		$this->undo->capture( $menu_id );
		wp_delete_nav_menu( $menu_id );

		$this->assertFalse( wp_get_nav_menu_object( 'Recoverable' ) );

		$restored = $this->undo->restore();

		$this->assertSame( array( 'Recoverable' ), $restored );

		$menu = wp_get_nav_menu_object( 'Recoverable' );

		$this->assertInstanceOf( \WP_Term::class, $menu );
		$this->assertCount( 2, wp_get_nav_menu_items( $menu->term_id ) );
	}

	/**
	 * @covers Menu_Undo::restore
	 */
	public function test_restore_reassigns_theme_locations(): void {
		register_nav_menu( 'swmd-test-location', 'Swift Test Location' );

		$menu_id = $this->create_menu_with_items( 'Located Menu', 1 );

		set_theme_mod( 'nav_menu_locations', array( 'swmd-test-location' => $menu_id ) );

		$this->undo->capture( $menu_id );
		wp_delete_nav_menu( $menu_id );

		$this->undo->restore();

		$locations = get_nav_menu_locations();
		$new_menu  = wp_get_nav_menu_object( 'Located Menu' );

		$this->assertInstanceOf( \WP_Term::class, $new_menu );
		$this->assertSame( $new_menu->term_id, (int) $locations['swmd-test-location'] );
	}

	/**
	 * @covers Menu_Undo::restore
	 */
	public function test_restore_empties_the_buffer(): void {
		$menu_id = $this->create_menu_with_items( 'One Shot', 1 );

		$this->undo->capture( $menu_id );
		wp_delete_nav_menu( $menu_id );

		$this->undo->restore();

		$this->assertSame( array(), $this->undo->get_pending() );
		$this->assertSame( array(), $this->undo->restore() );
	}

	/**
	 * @covers Menu_Undo::restore
	 */
	public function test_restore_fires_its_action(): void {
		$menu_id = $this->create_menu_with_items( 'Hooked Undo', 1 );

		$this->undo->capture( $menu_id );
		wp_delete_nav_menu( $menu_id );

		$fired = 0;
		add_action(
			'swift_menu_duplicator_after_undo_delete',
			static function () use ( &$fired ) {
				++$fired;
			}
		);

		$this->undo->restore();

		remove_all_actions( 'swift_menu_duplicator_after_undo_delete' );

		$this->assertSame( 1, $fired );
	}

	/**
	 * @covers Menu_Undo::get_pending
	 */
	public function test_buffer_is_per_user(): void {
		$menu_id = $this->create_menu_with_items( 'Mine', 1 );

		$this->undo->capture( $menu_id );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		// A different administrator must not see — or undo — someone else's delete.
		$this->assertSame( array(), ( new Menu_Undo() )->get_pending() );
	}
}
