<?php

/**
 * WP CLI command to add scheduled tasks.
 */
class ActionScheduler_WPCLI_Command_Add extends ActionScheduler_Abstract_WPCLI_Command {

	public function execute() {
		// Handle passed arguments.
		$hook      = $this->args[0];
		$start     = $this->args[1];
		$hook_args = \WP_CLI\Utils\get_flag_value( $this->assoc_args, 'args', array() );
		$group     = \WP_CLI\Utils\get_flag_value( $this->assoc_args, 'group', '' );
		$interval  = absint( \WP_CLI\Utils\get_flag_value( $this->assoc_args, 'interval', 0 ) );
		$limit     = absint( \WP_CLI\Utils\get_flag_value( $this->assoc_args, 'limit', 0 ) );
		$cron      = \WP_CLI\Utils\get_flag_value( $this->assoc_args, 'cron', false );
		$start     = as_get_datetime_object( $start );

		if ( ! empty( $hook_args ) ) {
			$hook_args = json_decode( $hook_args, true );
		}

		$func_args = array(
			$start->format( 'U' ),
			$cron,
			$interval,
			$limit,
			$hook,
			$hook_args,
			$group
		);

		$action_type = 'single';

		if ( ! empty( $cron ) ) {
			$action_type = 'cron';
			$func = 'as_schedule_cron_action';
		} else if ( ! empty( $limit ) && ! empty( $interval ) ) {
			$func = array( $this, 'add_multiple_single_actions' );
		} else if ( ! empty( $interval ) ) {
			$action_type = 'recurring';
			$func = 'as_schedule_recurring_action';
		} else {
			$func = 'as_schedule_single_action';
		}

		$func_args = array_filter( $func_args );

		try {
			$actions_added = call_user_func_array( $func, $func_args );
			$num_actions_added = count( $actions_added );
		} catch ( Exception $e ) {
			$this->print_error( $e );
		}

		$this->print_success( $num_actions_added, $action_type );
	}

	/**
	 * Schedule multiple single actions.
	 *
	 * @param int $start_timestamp Starting timestamp of first action.
	 * @param int $interval How long to wait between runs.
	 * @param int $limit Limit number of actions to schedule.
	 * @param string $hook The hook to trigger.
	 * @param array $args Arguments to pass when the hook triggers.
	 * @param string $group The group to assign this job to.
	 *
	 * @return int[] IDs of actions added.
	 */
	protected function add_multiple_single_actions( $start_timestamp, $interval, $limit, $hook, $args = array(), $group = '' ) {
		$actions_added = array();
		$progress_bar = \WP_CLI\Utils\make_progress_bar(
			sprintf( _n( 'Creating %d action', 'Creating %d actions', $limit, 'action-scheduler' ), number_format_i18n( $limit ) ),
			$limit
		);

		for ( $i = 0; $i < $limit; $i++ ) {
			$start_timestamp += $i * $interval;
			$actions_added[] = as_schedule_single_action( $start_timestamp, $hook, $args, $group );
			$progress_bar->tick();
		}

		$progress_bar->finish();

		return $actions_added;
	}

	/**
	 * Print a success message with the action ID.
	 *
	 * @author Caleb Stauffer
	 *
	 * @param int $action_added
	 * @param string $action_type
	 */
	protected function print_success( $actions_added, $action_type ) {
		$this->success(
			sprintf(
				/* translators: %d refers to the total number of taskes added */
				_n( '%d %s task scheduled.', '%d %s tasks scheduled.', $actions_added, 'action-scheduler' ),
				number_format_i18n( $actions_added ),
				$action_type
			)
		);
	}

	/**
	 * Convert an exception into a WP CLI error.
	 *
	 * @author Caleb Stauffer
	 *
	 * @param Exception $e The error object.
	 *
	 * @throws \WP_CLI\ExitException
	 */
	protected function print_error( Exception $e ) {
		$this->error(
			sprintf(
				/* translators: %s refers to the exception error message. */
				__( 'There was an error adding the task: %s', 'action-scheduler' ),
				$e->getMessage()
			)
		);
	}

}
