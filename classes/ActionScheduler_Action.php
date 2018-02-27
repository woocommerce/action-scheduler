<?php

/**
 * Class ActionScheduler_Action
 */
class ActionScheduler_Action extends ActionScheduler_AbstractAction {

	public function __construct( $hook, array $args = array(), ActionScheduler_Schedule $schedule = NULL, $group = '' ) {
		$schedule = empty( $schedule ) ? new ActionScheduler_NullSchedule() : $schedule;
		$this->set_hook($hook);
		$this->set_schedule($schedule);
		$this->set_args($args);
		$this->set_group($group);
	}
}
