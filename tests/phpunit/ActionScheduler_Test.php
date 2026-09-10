<?php

/**
 * Test suite for the ActionScheduler class.
 */
class ActionScheduler_Test extends ActionScheduler_UnitTestCase {

	/**
	 * Test that the test suite's own bootstrap is not mistaken for a plugin uninstall.
	 */
	public function test_normal_bootstrap_is_not_an_uninstall() {
		$this->assertFalse(
			ActionScheduler::is_uninstalling(),
			'Action Scheduler was initialized the way a plugin initializes it, which is not an uninstall.'
		);
	}

	/**
	 * Test that WP_UNINSTALL_PLUGIN appearing after initialization is not treated as an uninstall.
	 *
	 * The flag is captured once, in ActionScheduler::init(), rather than tested on demand. Some test
	 * suites (WooCommerce's, for one) define the constant for the whole process, and evaluating the
	 * check later would disable the runtime under them: by the time the store and the runner are
	 * initialized on 'init', a normal bootstrap looks exactly like a late one.
	 */
	public function test_constant_defined_after_initialization_is_not_an_uninstall() {
		// Cannot be undefined again, but only ActionScheduler::init() and uninstall_plugin() read it,
		// and neither runs again in this process.
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'my-plugin/my-plugin.php' );
		}

		$this->assertFalse(
			ActionScheduler::is_uninstalling(),
			'The uninstall flag should reflect the state at initialization, not the state when it is read.'
		);
	}
}
