<?php

/**
 * Action Scheduler WP CLI command to display system status.
 */
class ActionScheduler_WPCLI_Command_Info extends ActionScheduler_Abstract_WPCLI_Command {

	/**
	 * @var ActionScheduler_Store
	 */
	protected $store = null;

	/**
	 * Execute command.
	 */
	public function execute() {
		$this->store = ActionScheduler::store();

		$this->completed_actions();
		$this->pending_actions();
		$this->overdue_actions();
	}

	/**
	 * Print count of completed actions.
	 */
	protected function completed_actions() {
		$completed_actions = (int) $this->store->query_actions( array( 'status' => $this->store::STATUS_COMPLETE ), 'count' );
		\WP_CLI::log( 'Completed actions: ' . $completed_actions );
	}

	/**
	 * Print count of pending actions.
	 */
	protected function pending_actions() {
		$pending_actions = (int) $this->store->query_actions( array( 'status' => $this->store::STATUS_PENDING ), 'count' );
		\WP_CLI::log( 'Pending actions: ' . $pending_actions );
	}

	/**
	 * Print count of overdue actions.
	 */
	protected function overdue_actions() {
		$overdue_actions = (int) $this->store->query_actions( array( 'status' => $this->store::STATUS_PENDING, 'date' => as_get_datetime_object()->format( 'Y-m-d H:i:s' ) ), 'count' );
		\WP_CLI::log( 'Overdue actions: ' . $overdue_actions );
	}

}
