<?php

class ActionScheduler_WPCLI_Command_Info extends ActionScheduler_Abstract_WPCLI_Command {

	/**
	 * Execute command.
	 */
	public function execute() {
		$columns = explode( ',', \WP_CLI\Utils\get_flag_value( $this->assoc_args, 'columns', 'status,count' ) );

		$store = ActionScheduler::store();

		$rows = array();
		$status_labels = $store->get_status_labels();
		$action_counts = $store->action_counts();
		$status_dates = $store->action_dates();

		foreach ( $status_labels as $post_status => $label ) {
			$rows[] = array(
				'status' => $label,
				'count' => $action_counts[ $post_status ],
				'oldest' => $status_dates[ $post_status ]['oldest'],
				'newest' => $status_dates[ $post_status ]['newest'],
			);
		}

		$this->table( $rows, $columns );
	}

}
