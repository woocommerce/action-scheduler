<?php

/**
 * Class ActionScheduler_FinishedAction
 *
 * Deprecated to avoid ambiguity with stored actions and objects instantiated purely to call the hook.
 *
 * @deprecated 1.6.0
 */
class ActionScheduler_FinishedAction extends ActionScheduler_Action {

	public function __construct( $hook, array $args = array(), ActionScheduler_Schedule $schedule = NULL, $group = '' ) {
		trigger_error( sprintf( __('%1$s is <strong>deprecated</strong> since version 1.6.0! Use an instance of ActionScheduler_NonexecutableAction instead.'), __CLASS__ ) );
		parent::__construct( $hook, $args, $schedule, $group );
		$this->is_finished = true;
	}

	public function execute() {
		// don't execute
	}
}
