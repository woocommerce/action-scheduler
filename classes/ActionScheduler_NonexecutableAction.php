<?php

/**
 * Class ActionScheduler_NonexecutableAction
 *
 * Stored action which should not be executed because it has already been executed, and has the
 * failed or completed status, or becuase it has been cancelled, and has the cancelled status.
 */
class ActionScheduler_NonexecutableAction extends ActionScheduler_StoredAction {

	public function __construct( $id, $hook, $status, $claim_id = '', array $args = array(), ActionScheduler_Schedule $schedule = NULL, $group = '' ) {
		parent::__construct( $id, $hook, $status, $claim_id, $args, $schedule, $group );
		$this->is_finished = true;
	}

	public function execute() {
		// don't execute
	}
}
