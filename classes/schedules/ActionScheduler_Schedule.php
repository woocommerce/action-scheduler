<?php

/**
 * Class ActionScheduler_Schedule
 */
interface ActionScheduler_Schedule {
	/**
	 * @param null|DateTime $after Timestamp.
	 * @return DateTime|null
	 */
	public function next( DateTime $after = NULL );

	/**
	 * @return bool
	 */
	public function is_recurring();
}
