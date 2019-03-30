<?php

/**
 * Class ActionScheduler_WPCLI_Scheduler_command
 *
 * Retain public API for backwards compatibility.
 */
class ActionScheduler_WPCLI_Scheduler_command {

	/**
	 * Deprecated 'run' command.
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Keyed arguments.
	 * @uses ActionScheduler_WPCLI_Command_Run::execute()
	 */
	function run( $args, $assoc_args ) {
		_deprecated_function( __METHOD__, '2.3.0' );

		$command = new ActionScheduler_WPCLI_Command_Run( $args, $assoc_args );
		$command->execute();
	}

}
