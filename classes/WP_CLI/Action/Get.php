<?php

/**
 * WP-CLI command: action-scheduler action get
 */
class ActionScheduler_WPCLI_Action_Get_Command extends ActionScheduler_WPCLI_Command {

	/**
	 * Execute command.
	 *
	 * @return void
	 */
	public function execute() {
		$action_id = $this->args[0];
		$store     = \ActionScheduler::store();
		$logger    = \ActionScheduler::logger();
		$action    = $store->fetch_action( $action_id );

		$action_arr = array(
			'id'             => $this->args[0],
			'hook'           => $action->get_hook(),
			'status'         => $store->get_status( $action_id ),
			'args'           => $action->get_args(),
			'group'          => $action->get_group(),
			'recurring'      => $action->get_schedule()->is_recurring() ? 'yes' : 'no',
			'scheduled_date' => $this->get_schedule_display_string( $action->get_schedule() ),
			'log_entries'    => array(),
		);

		foreach ( $logger->get_logs( $action_id ) as $log_entry ) {
			$action_arr['log_entries'][] = array(
				'date'    => $log_entry->get_date()->format( static::DATE_FORMAT ),
				'message' => $log_entry->get_message(),
			);
		}

		$fields = array_keys( $action_arr );

		if ( ! empty( $this->assoc_args['fields'] ) ) {
			$fields = explode( ',', $this->assoc_args['fields'] );
		}

		$formatter = new \WP_CLI\Formatter( $this->assoc_args, $fields );
		$formatter->display_item( $action_arr );
	}

}
