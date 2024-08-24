<?php

/**
 * Class ActionScheduler_Schedule
 */
interface ActionScheduler_Schedule {
	/**
	 * Time to next run.
	 *
	 * @param null|DateTime $after Timestamp.
	 * @return DateTime|null
	 */
	public function next( DateTime $after = null );

	/**
	 * Recurring indicator.
	 *
	 * @return bool
	 */
	public function is_recurring();
}
