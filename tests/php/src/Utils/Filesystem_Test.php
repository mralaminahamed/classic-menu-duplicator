<?php
/**
 * Test suite for the Filesystem utility class.
 *
 * @package SwiftMenuDuplicator
 */

declare( strict_types=1 );

namespace SwiftMenuDuplicator\Test\Utils;

use SwiftMenuDuplicator\Test\SwiftMenuDuplicatorTestCase;
use SwiftMenuDuplicator\Utils\Filesystem;

/**
 * Covers Filesystem::read(), ::write(), and ::delete().
 *
 * Tests use real temporary files via wp_tempnam() so that WP_Filesystem
 * (direct method) performs actual I/O — matching how the class behaves
 * in production.
 */
class Filesystem_Test extends SwiftMenuDuplicatorTestCase {

	/**
	 * Temporary file paths created during a test, cleaned up in tear_down.
	 *
	 * @var string[]
	 */
	private array $tmp_files = array();

	// -----------------------------------------------------------------------
	// Helpers.
	// -----------------------------------------------------------------------

	/**
	 * Creates a temporary file, registers it for clean-up, and returns its path.
	 *
	 * @param string $content Initial content written to the file.
	 *
	 * @return string Absolute path to the temp file.
	 */
	private function make_tmp_file( string $content = '' ): string {
		$path = wp_tempnam( 'swmd-test' );

		if ( '' !== $content ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $path, $content );
		}

		$this->tmp_files[] = $path;

		return $path;
	}

	/**
	 * Remove any leftover temp files after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		foreach ( $this->tmp_files as $path ) {
			if ( file_exists( $path ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				unlink( $path );
			}
		}

		$this->tmp_files = array();

		parent::tear_down();
	}

	// -----------------------------------------------------------------------
	// Filesystem::read()
	// -----------------------------------------------------------------------

	/**
	 * @covers Filesystem::read
	 */
	public function test_read_returns_file_contents(): void {
		$path   = $this->make_tmp_file( 'hello world' );
		$result = Filesystem::read( $path );

		$this->assertSame( 'hello world', $result );
	}

	/**
	 * @covers Filesystem::read
	 */
	public function test_read_returns_empty_string_for_empty_file(): void {
		$path   = $this->make_tmp_file( '' );
		$result = Filesystem::read( $path );

		$this->assertSame( '', $result );
	}

	/**
	 * @covers Filesystem::read
	 */
	public function test_read_returns_false_for_nonexistent_file(): void {
		$result = Filesystem::read( '/tmp/swmd-does-not-exist-' . uniqid() . '.json' );

		$this->assertFalse( $result );
	}

	/**
	 * @covers Filesystem::read
	 */
	public function test_read_preserves_unicode_content(): void {
		$content = '{"menu":"Ünïcödé Mëñü","items":[]}';
		$path    = $this->make_tmp_file( $content );

		$this->assertSame( $content, Filesystem::read( $path ) );
	}

	// -----------------------------------------------------------------------
	// Filesystem::write()
	// -----------------------------------------------------------------------

	/**
	 * @covers Filesystem::write
	 */
	public function test_write_creates_file_with_content(): void {
		$path = wp_tempnam( 'swmd-write-test' );
		$this->tmp_files[] = $path;

		$result = Filesystem::write( $path, 'test content' );

		$this->assertTrue( $result );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$this->assertSame( 'test content', file_get_contents( $path ) );
	}

	/**
	 * @covers Filesystem::write
	 */
	public function test_write_overwrites_existing_content(): void {
		$path = $this->make_tmp_file( 'original' );

		Filesystem::write( $path, 'overwritten' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$this->assertSame( 'overwritten', file_get_contents( $path ) );
	}

	/**
	 * @covers Filesystem::write
	 */
	public function test_write_handles_json_content(): void {
		$path    = wp_tempnam( 'swmd-json-test' );
		$this->tmp_files[] = $path;
		$payload = array( 'version' => '1.0.0', 'items' => array( 1, 2, 3 ) );
		$json    = (string) wp_json_encode( $payload );

		$result = Filesystem::write( $path, $json );

		$this->assertTrue( $result );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$this->assertJson( file_get_contents( $path ) );
	}

	// -----------------------------------------------------------------------
	// Filesystem::delete()
	// -----------------------------------------------------------------------

	/**
	 * @covers Filesystem::delete
	 */
	public function test_delete_removes_existing_file(): void {
		$path = $this->make_tmp_file( 'to be deleted' );

		Filesystem::delete( $path );

		$this->assertFileDoesNotExist( $path );
		// Prevent tear_down from trying to delete it again.
		$this->tmp_files = array_filter( $this->tmp_files, static fn( $f ) => $f !== $path );
	}

	/**
	 * @covers Filesystem::delete
	 */
	public function test_delete_does_not_throw_on_nonexistent_file(): void {
		// wp_delete_file() is a no-op for missing files — no exception expected.
		Filesystem::delete( '/tmp/swmd-nonexistent-' . uniqid() . '.tmp' );

		$this->assertTrue( true ); // Reached without exception.
	}

	// -----------------------------------------------------------------------
	// Round-trip.
	// -----------------------------------------------------------------------

	/**
	 * Write → read → delete full cycle via Filesystem utility.
	 *
	 * @covers Filesystem::write
	 * @covers Filesystem::read
	 * @covers Filesystem::delete
	 */
	public function test_write_read_delete_round_trip(): void {
		$path    = wp_tempnam( 'swmd-roundtrip' );
		$this->tmp_files[] = $path;
		$content = wp_json_encode( array( 'menu' => array( 'name' => 'Round Trip' ), 'items' => array() ) );

		Filesystem::write( $path, (string) $content );

		$read = Filesystem::read( $path );
		$this->assertSame( $content, $read );

		Filesystem::delete( $path );
		$this->assertFileDoesNotExist( $path );

		$this->tmp_files = array_filter( $this->tmp_files, static fn( $f ) => $f !== $path );
	}
}
