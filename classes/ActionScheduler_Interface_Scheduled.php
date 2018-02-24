<?php
/**
 * Interface ActionScheduler_Interface_Scheduled
 *
 * Define the public methods provided by scheduled objects, like an action with a schedule
 *
 * @since 1.6.0
 */
interface ActionScheduler_Interface_Scheduled {

	/**
	 * Get the schedule for the action.
	 *
	 * @author Jeremy Pry
	 * @return ActionScheduler_Schedule
	 */
	public function get_schedule();
}
