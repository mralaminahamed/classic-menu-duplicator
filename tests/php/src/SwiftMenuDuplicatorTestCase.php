<?php
/**
 * Abstract base class for Swift Menu Duplicator test cases.
 *
 * @package SwiftMenuDuplicator
 */

namespace SwiftMenuDuplicator\Test;

use Brain\Monkey;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use WP_UnitTestCase;

/**
 * Abstract base class for Swift Menu Duplicator unit test cases.
 *
 * PHPUnit Docs: @see https://docs.phpunit.de/en/9.6/
 * Brain Monkey: @see https://giuseppe-mazzapica.gitbook.io/brain-monkey
 * Mockery: @see http://docs.mockery.io/en/latest/
 */
abstract class SwiftMenuDuplicatorTestCase extends WP_UnitTestCase {
	use MockeryPHPUnitIntegration;
	use MenuFactory;

	/**
	 * Setup test environment.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		Monkey\setUp();
	}

	/**
	 * Tear down test environment.
	 *
	 * @return void
	 */
	public function tear_down() {
		Monkey\tearDown();
		parent::tear_down();
	}

}
