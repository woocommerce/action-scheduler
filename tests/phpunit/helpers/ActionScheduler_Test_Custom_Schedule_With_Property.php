<?php

/**
 * A third party schedule (defined outside Action Scheduler) that keeps date/time value objects — and,
 * optionally, a plain support object — as properties, rather than collapsing them to scalars in
 * __sleep() the way Action Scheduler's own schedules do.
 *
 * Used to prove that (a) the built-in date/time classes on the default nested allow-list round-trip,
 * and (b) an extra, non-schedule support class is only instantiated once it has been allow-listed.
 */
class ActionScheduler_Test_Custom_Schedule_With_Property implements ActionScheduler_Schedule {

	/**
	 * @var DateTime
	 */
	protected $at;

	/**
	 * @var DateTimeImmutable
	 */
	protected $created;

	/**
	 * @var DateTimeZone
	 */
	protected $zone;

	/**
	 * @var DateInterval
	 */
	protected $every;

	/**
	 * A non-schedule support object, or null when the schedule nests only date/time value objects.
	 *
	 * @var ActionScheduler_Test_Schedule_Helper|null
	 */
	protected $helper;

	/**
	 * Construct.
	 *
	 * @param int                                       $timestamp Scheduled run timestamp.
	 * @param ActionScheduler_Test_Schedule_Helper|null $helper    Optional non-schedule support object.
	 */
	public function __construct( $timestamp, ?ActionScheduler_Test_Schedule_Helper $helper = null ) {
		$this->at      = new DateTime( '@' . $timestamp );
		$this->created = new DateTimeImmutable( '@' . $timestamp );
		$this->zone    = new DateTimeZone( 'UTC' );
		$this->every   = new DateInterval( 'P1D' );
		$this->helper  = $helper;
	}

	/**
	 * Get the next run date.
	 *
	 * @param null|DateTime $after Timestamp.
	 * @return DateTime|null
	 */
	public function next( ?DateTime $after = null ) {
		return as_get_datetime_object( $this->at->getTimestamp() );
	}

	/**
	 * Recurring.
	 *
	 * @return bool
	 */
	public function is_recurring() {
		return true;
	}
}
