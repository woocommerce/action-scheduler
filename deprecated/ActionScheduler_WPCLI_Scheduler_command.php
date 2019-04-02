<?php

/**
 * Class ActionScheduler_WPCLI_Scheduler_command
 *
 * Retain public API for backwards compatibility.
 */
class ActionScheduler_WPCLI_Scheduler_command extends ActionScheduler_WPCLI_Command_Run {

	/**
	 * Construct.
	 */
	function __construct() {
		_deprecated_function( __METHOD__, '2.3.0' );
	}

	/**
	 * Deprecated 'run' command.
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Keyed arguments.
	 * @uses ActionScheduler_WPCLI_Command_Run::execute()
	 */
	function run( $args, $assoc_args ) {
		_deprecated_function( __METHOD__, '2.3.0' );

		parent::__construct( $args, $assoc_args );

		$this->execute();
	}

}
