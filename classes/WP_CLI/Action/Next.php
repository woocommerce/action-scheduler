<?php

use function \WP_CLI\Utils\get_flag_value;

/**
 * WP-CLI command: action-scheduler action next
 */
class ActionScheduler_WPCLI_Action_Next_Command extends ActionScheduler_WPCLI_Command {

	/**
	 * Execute command.
	 *
	 * @return void
	 */
	public function execute() {
		$hook          = $this->args[0];
		$group         = get_flag_value( $this->assoc_args, 'group', '' );
		$callback_args = get_flag_value( $this->assoc_args, 'args', null );

		if ( ! empty( $callback_args ) ) {
			$callback_args = json_decode( $callback_args, true );
		}

		$next_action_id = $this->as_next_scheduled_action( $hook, $callback_args, $group );

		if ( empty( $next_action_id ) ) {
			\WP_CLI::warning( 'No matching next action.' );
			return;
		}

		$fields = array(
			'id',
			'hook',
			'status',
			'group',
			'recurring',
			'scheduled_date',
		);

		$this->process_csv_arguments_to_arrays();

		if ( ! empty( $this->assoc_args['fields'] ) ) {
			$fields = $this->assoc_args['fields'];
		}

		$store     = \ActionScheduler::store();
		$logger    = \ActionScheduler::logger();
		$formatter = new \WP_CLI\Formatter( $this->assoc_args, $fields );

		if ( 'ids' === $formatter->format ) {
			echo $next_action_id;
			return;
		}

		$action = $store->fetch_action( $next_action_id );

		$action_arr = array(
			'id'             => $next_action_id,
			'hook'           => $action->get_hook(),
			'status'         => $store->get_status( $next_action_id ),
			'args'           => $action->get_args(),
			'group'          => $action->get_group(),
			'recurring'      => $action->get_schedule()->is_recurring() ? 'yes' : 'no',
			'scheduled_date' => $this->get_schedule_display_string( $action->get_schedule() ),
			'log_entries'    => array(),
		);

		foreach ( $logger->get_logs( $next_action_id ) as $log_entry ) {
			$action_arr['log_entries'][] = array(
				'date'    => $log_entry->get_date()->format( static::DATE_FORMAT ),
				'message' => $log_entry->get_message(),
			);
		}

		if ( ! empty( $this->assoc_args['fields'] ) ) {
			$fields = explode( ',', $this->assoc_args['fields'] );
		}

		$formatter->display_item( $action_arr );
	}

	/**
	 * Get next scheduled action.
	 *
	 * @see as_next_scheduled_action()
	 * @param string $hook Name of the hook to search for.
	 * @param array  $args Arguments of the action to be searched.
	 * @param string $group Group of the action to be searched.
	 * @return int
	 */
	protected function as_next_scheduled_action( $hook, $args = null, $group = '' ) {
		if ( ! ActionScheduler::is_initialized( 'as_next_scheduled_action' ) ) {
			return 0;
		}

		$params = array(
			'hook'    => $hook,
			'orderby' => 'date',
			'order'   => 'ASC',
			'group'   => $group,
		);

		if ( is_array( $args ) ) {
			$params['args'] = $args;
		}

		$params['status'] = ActionScheduler_Store::STATUS_RUNNING;
		$action_id        = ActionScheduler::store()->query_action( $params );
		if ( $action_id ) {
			return $action_id;
		}

		$params['status'] = ActionScheduler_Store::STATUS_PENDING;
		$action_id        = ActionScheduler::store()->query_action( $params );
		if ( null === $action_id ) {
			return 0;
		}

		$action         = ActionScheduler::store()->fetch_action( $action_id );
		$scheduled_date = $action->get_schedule()->get_date();
		if ( $scheduled_date ) {
			return (int) $scheduled_date->format( 'U' );
		} elseif ( null === $scheduled_date ) { // pending async action with NullSchedule.
			return $action_id;
		}

		return 0;
	}

}
