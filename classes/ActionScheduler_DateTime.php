<?php

/**
 * ActionScheduler DateTime class.
 *
 * This is a custom extension to DateTime that
 */
class ActionScheduler_DateTime extends DateTime {

	/**
	 * UTC offset.
	 *
	 * Only used when a timezone is not set. When a timezone string is
	 * used, this will be set to 0.
	 *
	 * @var int
	 */
	protected $utcOffset = 0;

	/**
	 * Set the UTC offset.
	 *
	 * This represents a fixed offset instead of a timezone setting.
	 *
	 * @param $offset
	 */
	public function setUtcOffset( $offset ) {
		$this->utcOffset = intval( $offset );
	}

	/**
	 * Returns the timezone offset.
	 *
	 * @return int
	 * @link http://php.net/manual/en/datetime.getoffset.php
	 */
	public function getOffset() {
		return $this->utcOffset ? $this->utcOffset : parent::getOffset();
	}

	/**
	 * Set the TimeZone associated with the DateTime
	 *
	 * @param DateTimeZone $timezone
	 *
	 * @return static
	 * @link http://php.net/manual/en/datetime.settimezone.php
	 */
	public function setTimezone( $timezone ) {
		$this->utcOffset = 0;
		parent::setTimezone( $timezone );

		return $this;
	}

	/**
	 * Get the timestamp with the WordPress timezone offset added or subtracted.
	 *
	 * @since  3.0.0
	 * @return int
	 */
	public function getOffsetTimestamp() {
		return $this->getTimestamp() + $this->getOffset();
	}
}
