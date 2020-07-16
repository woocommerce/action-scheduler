<?php

/**
 * Action Scheduler abstract class for WP CLI commands.
 */
abstract class ActionScheduler_Abstract_WPCLI_Command {

	/**
	 * @var array
	 */
	protected $args;
	protected $assoc_args;

	/**
	 * Construct.
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function __construct( $args, $assoc_args ) {
		$this->args = $args;
		$this->assoc_args = $assoc_args;
	}

	/**
	 * Execute command.
	 */
	abstract public function execute();

}
