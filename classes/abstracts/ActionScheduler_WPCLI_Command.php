<?php declare( strict_types=1 );

abstract class ActionScheduler_WPCLI_Command extends \WP_CLI_Command {

	const DATE_FORMAT = 'Y-m-d H:i:s O';

	protected $args;
	protected $assoc_args;

	public function __construct( array $args, array $assoc_args ) {
		$this->args = $args;
		$this->assoc_args = $assoc_args;
	}

	abstract public function execute() : void;

	/**
	 * Get the scheduled date in a human friendly format.
	 *
	 * @see \ActionScheduler_ListTable::get_schedule_display_string()
	 * @param ActionScheduler_Schedule $schedule
	 * @return string
	 */
	protected function get_schedule_display_string( \ActionScheduler_Schedule $schedule ) {

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
	 * @param ActionScheduler_Action $action
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
	protected function process_csv_arguments_to_arrays() : void {
		foreach ( $this->assoc_args as $k => $v ) {
			if ( false !== strpos( $k, '__' ) ) {
				$this->assoc_args[ $k ] = explode( ',', $v );
			}
		}
	}

}
