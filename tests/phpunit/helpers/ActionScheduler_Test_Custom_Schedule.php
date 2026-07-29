<?php

/**
 * A well-behaved third party schedule class. It implements ActionScheduler_Schedule
 * but is defined outside of Action Scheduler, exactly like a schedule shipped by a
 * plugin extending Action Scheduler. Hardening must NOT break these.
 */
class ActionScheduler_Test_Custom_Schedule implements ActionScheduler_Schedule {

	/**
	 * Scheduled run timestamp.
	 *
	 * @var int
	 */
	protected $timestamp = 0;

	/**
	 * Construct.
	 *
	 * @param int $timestamp Scheduled run timestamp.
	 */
	public function __construct( $timestamp ) {
		$this->timestamp = $timestamp;
	}

	/**
	 * Get the next run date.
	 *
	 * @param null|DateTime $after Timestamp.
	 * @return DateTime|null
	 */
	public function next( ?DateTime $after = null ) {
		return as_get_datetime_object( $this->timestamp );
	}

	/**
	 * Not recurring.
	 *
	 * @return bool
	 */
	public function is_recurring() {
		return false;
	}
}
