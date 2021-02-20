<?php

/**
 * Class ActionScheduler_Retry
 */
class ActionScheduler_Retry {
	/** @var int */
	protected $limit;
	/** @var int */
	protected $fails;

	/**
	 * ActionScheduler_Retry constructor.
	 *
	 * @param $limit int Number of retries to attempt.
	 * @param $fails int Track failures.
	 */
	public function __construct( $limit = 0, $fails = 0 ) {
		$this->set_limit( $limit );
		$this->set_fails( $fails );
	}

	/**
	 * @return int
	 */
	public function get_limit() {
		return $this->limit;
	}

	/**
	 * @param int $limit
	 */
	public function set_limit( $limit ) {
		$this->limit = abs( intval( $limit ) );
	}

	/**
	 * @return int
	 */
	public function get_fails() {
		return $this->fails;
	}

	/**
	 * @param int $fails
	 */
	public function set_fails( $fails ) {
		$this->fails = abs( intval( $fails ) );
	}

	/**
	 * Increment the failures and check that the failures <= limit.
	 * @return bool
	 */
	public function is_valid_after_fail() {
		// Increment the failures.
		$this->fails ++;

		// Evaluate attempts vs. failures.
		if ( $this->fails > $this->limit ) {
			return false;
		}
		return true;
	}
}
