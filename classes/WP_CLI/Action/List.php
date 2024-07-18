<?php

/**
 * WP-CLI command: action-scheduler action list
 */
class ActionScheduler_WPCLI_Action_List_Command extends ActionScheduler_WPCLI_Command {

	const PARAMETERS = array(
		'hook',
		'args',
		'date',
		'date_compare',
		'modified',
		'modified_compare',
		'group',
		'status',
		'claimed',
		'per_page',
		'offset',
		'orderby',
		'order',
	);

	/**
	 * Execute command.
	 *
	 * @return void
	 */
	public function execute() {
		$store  = \ActionScheduler::store();
		$logger = \ActionScheduler::logger();

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

		$formatter = new \WP_CLI\Formatter( $this->assoc_args, $fields );

		$query_args = array_filter(
			$this->assoc_args,
			static function ( $key ) {
				return in_array( $key, static::PARAMETERS, true );
			},
			ARRAY_FILTER_USE_KEY
		);

		/**
		 * The `claimed` parameter expects a boolean or integer:
		 * check for string 'false', and set explicitly to `false` boolean.
		 */
		if ( array_key_exists( 'claimed', $query_args ) && 'false' === strtolower( $query_args['claimed'] ) ) {
			$query_args['claimed'] = false;
		}

		WP_CLI::debug( 'as_get_scheduled_actions( ' . var_export( $query_args, true ) . ' )' );

		if ( ! empty( $query_args['args'] ) ) {
			$query_args['args'] = json_decode( $query_args['args'], true );
		}

		switch ( $formatter->format ) {

			case 'ids':
				$actions = as_get_scheduled_actions( $query_args, 'ids' );
				echo implode( ' ', $actions );
				break;

			case 'count':
				$actions = as_get_scheduled_actions( $query_args, 'ids' );
				$formatter->display_items( $actions );
				break;

			default:
				$actions = as_get_scheduled_actions( $query_args );

				$actions_arr = array();

				foreach ( $actions as $action_id => $action ) {
					$action_arr = array(
						'id'             => $action_id,
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

					$actions_arr[] = $action_arr;
				}

				$formatter->display_items( $actions_arr );
				break;

		}
	}

}
