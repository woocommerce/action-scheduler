<?php

/**
 * Class ActionScheduler_CanceledAction
 *
 * Stored action which was canceled and therefore acts like a finished action but should always return a null schedule,
 * regardless of schedule passed to its constructor.
 */
class ActionScheduler_CanceledAction extends ActionScheduler_NonexecutableAction {

	public function __construct( $id, $hook, $status, $claim_id = '', array $args = array(), ActionScheduler_Schedule $schedule = NULL, $group = '' ) {
		$null_schedule = new ActionScheduler_NullSchedule();
		parent::__construct( $id, $hook, $status, $claim_id, $args, $null_schedule, $group );
	}
}
