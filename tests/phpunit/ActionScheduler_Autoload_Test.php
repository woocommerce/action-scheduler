<?php
/**
 * Autoloader regression tests.
 *
 * @package ActionScheduler
 */

/**
 * Isolate redeclaration errors so an autoloader regression does not stop the suite.
 */
class ActionScheduler_Autoload_Test extends PHPUnit\Framework\TestCase {

	/**
	 * A global Controller lookup must not include the loaded migration controller again.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_foreign_lookups_do_not_include_files() {
		$this->assertTrue( class_exists( 'Action_Scheduler\\Migration\\Controller' ) );
		$this->assertFalse( class_exists( 'Controller' ) );
	}

	/**
	 * A lookalike namespace must not include the loaded migration controller again.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_namespace_prefix_requires_a_separator() {
		$this->assertTrue( class_exists( 'Action_Scheduler\\Migration\\Controller' ) );
		$this->assertFalse( class_exists( 'Action_SchedulerExtra\\Migration\\Controller' ) );
	}
}
