<?php

/**
 * Abstract for WP-CLI commands.
 */
abstract class ActionScheduler_WPCLI_Command extends \WP_CLI_Command {

	const DATE_FORMAT = 'Y-m-d H:i:s O';

	/** @var string[] */
	protected $args;

	/** @var array<string, string> */
	protected $assoc_args;

	/**
	 * Construct.
	 *
	 * @param string[]              $args       Positional arguments.
	 * @param array<string, string> $assoc_args Keyed arguments.
	 */
	public function __construct( array $args, array $assoc_args ) {
		$this->args       = $args;
		$this->assoc_args = $assoc_args;
	}

	/**
	 * Execute command.
	 */
	abstract public function execute();

	/**
	 * Get the scheduled date in a human friendly format.
	 *
	 * @see ActionScheduler_ListTable::get_schedule_display_string()
	 * @param ActionScheduler_Schedule $schedule Schedule.
	 * @return string
	 */
	protected function get_schedule_display_string( ActionScheduler_Schedule $schedule ) {

		$schedule_display_string = '';

		if ( ! $schedule->get_date() ) {
			return '0000-00-00 00:00:00';
		}

		$next_timestamp = $schedule->get_date()->getTimestamp();

		$schedule_display_string .= $schedule->get_date()->format( static::DATE_FORMAT );

		return $schedule_display_string;
	}

	/**
	 * Returns the recurrence of an action or 'Non-repeating'. The output is human readable.
	 *
	 * @see \ActionScheduler_ListTable::get_recurrence()
	 * @param ActionScheduler_Action $action Action.
	 *
	 * @return string
	 */
	protected function get_recurrence( $action ) {
		$schedule = $action->get_schedule();
		if ( $schedule->is_recurring() ) {
			$recurrence = $schedule->get_recurrence();

			if ( is_numeric( $recurrence ) ) {
				/* translators: %s: time interval */
				return sprintf( __( 'Every %s', 'action-scheduler' ), self::human_interval( $recurrence ) );
			} else {
				return $recurrence;
			}
		}

		return __( 'Non-repeating', 'action-scheduler' );
	}

	/**
	 * Transforms arguments with '__' from CSV into expected arrays.
	 *
	 * @see \WP_CLI\CommandWithDBObject::process_csv_arguments_to_arrays()
	 * @link https://github.com/wp-cli/entity-command/blob/6e0e77a297eefa3329b94bec16c15cf7528d343f/src/WP_CLI/CommandWithDBObject.php
	 * @return void
	 */
	protected function process_csv_arguments_to_arrays() {
		foreach ( $this->assoc_args as $k => $v ) {
			if ( false !== strpos( $k, '__' ) ) {
				$this->assoc_args[ $k ] = explode( ',', $v );
			}
		}
	}

}
