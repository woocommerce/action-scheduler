<?php

/**
 * A plain, non-schedule support object of the kind a third party schedule might keep as a property
 * (for example, a small value object describing its recurrence).
 *
 * It is deliberately NOT on the default nested allow-list and does NOT implement
 * ActionScheduler_Schedule, so a schedule that nests it is only accepted once an extender allow-lists
 * it via the action_scheduler_allowed_nested_schedule_classes filter (or injects it into the
 * deserializer's constructor).
 */
class ActionScheduler_Test_Schedule_Helper {

	/**
	 * Arbitrary payload, present only so the round-trip can be asserted.
	 *
	 * @var int
	 */
	public $value;

	/**
	 * Construct.
	 *
	 * @param int $value Arbitrary payload.
	 */
	public function __construct( $value = 0 ) {
		$this->value = $value;
	}
}
