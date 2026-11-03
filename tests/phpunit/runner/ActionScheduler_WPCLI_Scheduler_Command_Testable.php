<?php

/**
 * Testable Action Scheduler WP-CLI command.
 */
class ActionScheduler_WPCLI_Scheduler_Command_Testable extends ActionScheduler_WPCLI_Scheduler_command {

	/**
	 * Expose option parsing for tests.
	 *
	 * @param array $assoc_args Keyed command arguments.
	 * @return array
	 */
	public function parse_run_options_for_test( $assoc_args ) {
		return $this->parse_run_options( $assoc_args );
	}
}
