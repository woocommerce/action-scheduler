<?php

/**
 * Class ActionScheduler_ActionFactory
 */
class ActionScheduler_ActionFactory {

	/**
	 * @param int $action_id The action's ID in the data store
	 * @param string $hook The hook to trigger when this action runs
	 * @param string $status The action's status in the data store
	 * @param string $claim_id The identifier for the claim this action belongs to, if any, derived from ActionScheduler_ActionClaim::get_id()
	 * @param array $args Args to pass to callbacks when the hook is triggered
	 * @param ActionScheduler_Schedule $schedule The action's schedule
	 * @param string $group A group to put the action in
	 *
	 * @return ActionScheduler_StoredAction An instance of the stored action
	 */
	public function get_stored_action_instance( $action_id, $hook, $status, $claim_id = '', array $args = array(), ActionScheduler_Schedule $schedule = NULL, $group = '' ) {

		switch ( $status ) {
			case ActionScheduler_Store::STATUS_PENDING :
				$action_class = 'ActionScheduler_StoredAction';
				break;
			case ActionScheduler_Store::STATUS_CANCELED :
				$action_class = 'ActionScheduler_CanceledAction';
				break;
			default :
				$action_class = 'ActionScheduler_NonexecutableAction';
				break;
		}

		$action_class = apply_filters( 'action_scheduler_stored_action_class', $action_class, $action_id, $hook, $status, $claim_id, $args, $schedule, $group );

		$action = new $action_class( $action_id, $hook, $status, $claim_id, $args, $schedule, $group );

		return apply_filters( 'action_scheduler_stored_action_instance', $action, $action_id, $hook, $status, $claim_id, $args, $schedule, $group )
	}

	/**
	 * @param string $hook The hook to trigger when this action runs
	 * @param array $args Args to pass when the hook is triggered
	 * @param int $when Unix timestamp when the action will run
	 * @param string $group A group to put the action in
	 *
	 * @return string The ID of the stored action
	 */
	public function single( $hook, $args = array(), $when = NULL, $group = '' ) {
		$date = as_get_datetime_object( $when );
		$schedule = new ActionScheduler_SimpleSchedule( $date );
		$action = new ActionScheduler_Action( $hook, $args, $schedule, $group );
		return $this->store( $action );
	}

	/**
	 * @param string $hook The hook to trigger when this action runs
	 * @param array $args Args to pass when the hook is triggered
	 * @param int $first Unix timestamp for the first run
	 * @param int $interval Seconds between runs
	 * @param string $group A group to put the action in
	 *
	 * @return string The ID of the stored action
	 */
	public function recurring( $hook, $args = array(), $first = NULL, $interval = NULL, $group = '' ) {
		if ( empty($interval) ) {
			return $this->single( $hook, $args, $first, $group );
		}
		$date = as_get_datetime_object( $first );
		$schedule = new ActionScheduler_IntervalSchedule( $date, $interval );
		$action = new ActionScheduler_Action( $hook, $args, $schedule, $group );
		return $this->store( $action );
	}


	/**
	 * @param string $hook The hook to trigger when this action runs
	 * @param array $args Args to pass when the hook is triggered
	 * @param int $first Unix timestamp for the first run
	 * @param int $schedule A cron definition string
	 * @param string $group A group to put the action in
	 *
	 * @return string The ID of the stored action
	 */
	public function cron( $hook, $args = array(), $first = NULL, $schedule = NULL, $group = '' ) {
		if ( empty($schedule) ) {
			return $this->single( $hook, $args, $first, $group );
		}
		$date = as_get_datetime_object( $first );
		$cron = CronExpression::factory( $schedule );
		$schedule = new ActionScheduler_CronSchedule( $date, $cron );
		$action = new ActionScheduler_Action( $hook, $args, $schedule, $group );
		return $this->store( $action );
	}

	/**
	 * @param ActionScheduler_Action $action
	 *
	 * @return string The ID of the stored action
	 */
	protected function store( ActionScheduler_Action $action ) {
		$store = ActionScheduler_Store::instance();
		return $store->save_action( $action );
	}
}
 