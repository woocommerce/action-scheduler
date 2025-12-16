<?php

/**
 * Class ActionScheduler_SimpleSchedule
 */
class ActionScheduler_SimpleSchedule extends ActionScheduler_Abstract_Schedule {

	/**
	 * Deprecated property @see $this->__wakeup() for details.
	 *
	 * @var null|DateTime
	 */
	private $timestamp = null;

	/**
	 * Calculate when this schedule should start after a given date & time using
	 * the number of seconds between recurrences.
	 *
	 * @param DateTime $after Timestamp.
	 *
	 * @return DateTime|null
	 */
	public function calculate_next( DateTime $after ) {
		return null;
	}

	/**
	 * Schedule is not recurring.
	 *
	 * @return bool
	 */
	public function is_recurring() {
		return false;
	}

	/**
	 * Serialize simple schedule data (PHP 7.4+).
	 *
	 * @return array
	 */
	public function __serialize() {
		$parent_data = parent::__serialize();
		// For backward compatibility with AS < 3.0.0, include the 'timestamp' property.
		return array_merge(
			$parent_data,
			array(
				'timestamp' => $parent_data['scheduled_timestamp'],
			)
		);
	}

	/**
	 * Unserialize simple schedule data (PHP 7.4+).
	 *
	 * @param array $data Serialized data.
	 */
	public function __unserialize( array $data ) {
		// Handle backward compatibility with AS < 3.0.0.
		if ( ! isset( $data['scheduled_timestamp'] ) && isset( $data['timestamp'] ) ) {
			$data['scheduled_timestamp'] = $data['timestamp'];
		}
		parent::__unserialize( $data );
	}

	/**
	 * Serialize schedule with data required prior to AS 3.0.0
	 *
	 * Prior to Action Scheduler 3.0.0, schedules used different property names to refer
	 * to equivalent data. For example, ActionScheduler_IntervalSchedule::start_timestamp
	 * was the same as ActionScheduler_SimpleSchedule::timestamp. Action Scheduler 3.0.0
	 * aligned properties and property names for better inheritance. To guard against the
	 * scheduled date for single actions always being seen as "now" if downgrading to
	 * Action Scheduler < 3.0.0, we need to also store the data with the old property names
	 * so if it's unserialized in AS < 3.0, the schedule doesn't end up with a null recurrence.
	 *
	 * @return array
	 */
	public function __sleep() {

		$sleep_params = parent::__sleep();

		$this->timestamp = $this->scheduled_timestamp;

		return array_merge(
			$sleep_params,
			array(
				'timestamp',
			)
		);
	}

	/**
	 * Unserialize recurring schedules serialized/stored prior to AS 3.0.0
	 *
	 * Prior to Action Scheduler 3.0.0, schedules used different property names to refer
	 * to equivalent data. For example, ActionScheduler_IntervalSchedule::start_timestamp
	 * was the same as ActionScheduler_SimpleSchedule::timestamp. Action Scheduler 3.0.0
	 * aligned properties and property names for better inheritance. To maintain backward
	 * compatibility with schedules serialized and stored prior to 3.0, we need to correctly
	 * map the old property names with matching visibility.
	 */
	public function __wakeup() {

		if ( is_null( $this->scheduled_timestamp ) && ! is_null( $this->timestamp ) ) {
			$this->scheduled_timestamp = $this->timestamp;
			unset( $this->timestamp );
		}
		parent::__wakeup();
	}
}
