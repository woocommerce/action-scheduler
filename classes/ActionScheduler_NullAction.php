<?php

/**
 * Class ActionScheduler_NullAction
 */
class ActionScheduler_NullAction extends ActionScheduler_AbstractAction {

	public function __construct( $hook = '', array $args = array(), ActionScheduler_Schedule $schedule = NULL ) {
		$this->set_schedule( new ActionScheduler_NullSchedule() );
		$this->is_finished = true;
	}

	public function execute() {
		// don't execute
	}
}
