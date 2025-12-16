<?php

/**
 * Class ActionScheduler_NullSchedule
 */
class ActionScheduler_NullSchedule extends ActionScheduler_SimpleSchedule {

	/**
	 * DateTime instance.
	 *
	 * @var DateTime|null
	 */
	protected $scheduled_date;

	/**
	 * Make the $date param optional and default to null.
	 *
	 * @param null|DateTime $date The date & time to run the action.
	 */
	public function __construct( ?DateTime $date = null ) {
		$this->scheduled_date = null;
	}

	/**
	 * Serialize null schedule data (PHP 7.4+).
	 *
	 * @return array
	 */
	public function __serialize() {
		return array();
	}

	/**
	 * Unserialize null schedule data (PHP 7.4+).
	 *
	 * @param array $data Serialized data.
	 */
	public function __unserialize( array $data ) {
		$this->scheduled_date = null;
	}

	/**
	 * This schedule has no scheduled DateTime, so we need to override the parent __sleep().
	 *
	 * @return array
	 */
	public function __sleep() {
		return array();
	}

	/**
	 * Wakeup.
	 */
	public function __wakeup() {
		$this->scheduled_date = null;
	}
}
