<?php

require_once dirname( __DIR__, 3 ) . '/vendor/wp-cli/wp-cli/php/utils.php';
require_once __DIR__ . '/ActionScheduler_WPCLI_Scheduler_Command_Testable.php';

/**
 * Tests for ActionScheduler_WPCLI_Scheduler_command.
 */
class ActionScheduler_WPCLI_Scheduler_Command_Test extends PHPUnit\Framework\TestCase {

	/**
	 * A polling interval enables polling mode.
	 */
	public function test_poll_every_ms_enables_polling() {
		$command = new ActionScheduler_WPCLI_Scheduler_Command_Testable();
		$options = $command->parse_run_options_for_test( array( 'poll-every-ms' => '1500' ) );

		$this->assertSame( 1500, $options['poll_every_ms'] );
	}

	/**
	 * Polling is disabled when no interval is supplied.
	 */
	public function test_polling_is_disabled_when_option_is_omitted() {
		$command = new ActionScheduler_WPCLI_Scheduler_Command_Testable();
		$options = $command->parse_run_options_for_test( array() );

		$this->assertNull( $options['poll_every_ms'] );
	}
}
