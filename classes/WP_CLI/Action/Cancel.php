<?php

use function \WP_CLI\Utils\get_flag_value;

/**
 * WP-CLI command: action-scheduler action cancel
 */
class ActionScheduler_WPCLI_Action_Cancel_Command extends ActionScheduler_WPCLI_Command {

	/**
	 * Execute command.
	 *
	 * @uses as_unschedule_action()
	 * @uses as_unschedule_all_actions()
	 * @uses $this->print_error()
	 * @uses $this->print_success()
	 * @return void
	 */
	public function execute() {
		$hook          = $this->args[0];
		$group         = null;
		$callback_args = get_flag_value( $this->assoc_args, 'args', null );
		$all           = get_flag_value( $this->assoc_args, 'all' );

		if ( ! empty( $this->args[1] ) ) {
			$group = $this->args[1];
		}

		if ( ! empty( $callback_args ) ) {
			$callback_args = json_decode( $callback_args, true );
		}

		$function_name = 'as_unschedule_action';
		$multiple      = false;

		if ( $all ) {
			$function_name = 'as_unschedule_all_actions';
			$multiple      = true;
		}

		try {
			call_user_func( $function_name, $hook, $callback_args, $group );
		} catch ( \Exception $e ) {
			$this->print_error( $e, $multiple );
		}

		$this->print_success( $multiple );
	}

	/**
	 * Print a success message.
	 *
	 * @param bool $multiple Boolean if multiple actions.
	 * @return void
	 */
	protected function print_success( $multiple ) {
		\WP_CLI::success( _n( 'Scheduled action cancelled.', 'All scheduled actions cancelled.', $multiple ? 2 : 1, 'action-scheduler' ) );
	}

	/**
	 * Convert an exception into a WP CLI error.
	 *
	 * @param \Exception $e The error object.
	 * @param bool       $multiple Boolean if multiple actions.
	 * @throws \WP_CLI\ExitException
	 * @return void
	 */
	protected function print_error( \Exception $e, $multiple ) {
		\WP_CLI::error(
			sprintf(
				/* translators: %1$s: singular or plural %2$s: refers to the exception error message. */
				__( 'There was an error cancelling the scheduled %1$s: %2$s', 'action-scheduler' ),
				$multiple ? 'actions' : 'action',
				$e->getMessage()
			)
		);
	}

}
