<?php

class ActionScheduler_WPCLI_Command_Info extends ActionScheduler_Abstract_WPCLI_Command {

	/**
	 * Execute command.
	 */
	public function execute() {
		$this->completed_actions();
		$this->pending_actions();
		$this->overdue_actions();
	}

	protected function completed_actions() {
		$completed_actions = (int) $this->store->query_actions( array( 'status' => $this->store::STATUS_COMPLETE ), 'count' );
		$this->log( 'Completed actions: ' . $completed_actions );
	}

	protected function pending_actions() {
		$pending_actions = (int) $this->store->query_actions( array( 'status' => $this->store::STATUS_PENDING ), 'count' );
		$this->log( 'Pending actions: ' . $pending_actions );
	}

	protected function overdue_actions() {
		$overdue_actions = (int) $this->store->query_actions( array( 'status' => $this->store::STATUS_PENDING, 'date' => as_get_datetime_object()->format( 'Y-m-d H:i:s' ) ), 'count' );
		$this->log( 'Overdue actions: ' . $overdue_actions );
	}

}
