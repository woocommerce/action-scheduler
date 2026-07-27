<?php

require_once dirname( __DIR__, 3 ) . '/vendor/wp-cli/wp-cli/php/utils.php';
require_once __DIR__ . '/ActionScheduler_WPCLI_Scheduler_Command_Testable.php';

/**
 * Tests for ActionScheduler_WPCLI_Scheduler_command.
 */
class ActionScheduler_WPCLI_Scheduler_Command_Test extends PHPUnit\Framework\TestCase {

	/**
	 * The primary continuous flag enables continuous mode.
	 */
	public function test_continuous_flag() {
		$command = new ActionScheduler_WPCLI_Scheduler_Command_Testable();
		$options = $command->parse_run_options_for_test( array( 'continuous' => true ) );

		$this->assertTrue( $options['continuous'] );
	}

	/**
	 * The keep-alive alias enables the same mode.
	 */
	public function test_keep_alive_alias() {
		$command = new ActionScheduler_WPCLI_Scheduler_Command_Testable();
		$options = $command->parse_run_options_for_test( array( 'keep-alive' => true ) );

		$this->assertTrue( $options['continuous'] );
	}
}
