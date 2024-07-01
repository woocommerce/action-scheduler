<?php

/**
 * Class ActionScheduler_UnitTestCase
 */
class ActionScheduler_UnitTestCase extends WP_UnitTestCase {

	protected $existing_timezone;

	/**
	 * Perform test set-up work.
	 * @before
	 */
	public function set_up() {
		parent::set_up();
		ActionScheduler_Callbacks::add_callbacks();
	}

	/**
	 * Perform test tear-down work.
	 * @after
	 */
	public function tear_down() {
		ActionScheduler_Callbacks::remove_callbacks();
		parent::tear_down();
	}

	/**
	 * Counts the number of test cases executed by run(TestResult result).
	 *
	 * @return int
	 */
	public function count() {
		return 'UTC' == date_default_timezone_get() ? 2 : 3;
	}

	/**
	 * We want to run every test multiple times using a different timezone to make sure
	 * that they are unaffected by changes to PHP's timezone.
	 */
	public function run( PHPUnit_Framework_TestResult $result = NULL ){

		if ($result === NULL) {
			$result = $this->createResult();
		}

		if ( 'UTC' != ( $this->existing_timezone = date_default_timezone_get() ) ) {
			date_default_timezone_set( 'UTC' );
			$result->run( $this );
		}

		date_default_timezone_set( 'Pacific/Fiji' ); // UTC+12
		$result->run( $this );

		date_default_timezone_set( 'Pacific/Tahiti' ); // UTC-10: it's a magical place
		$result->run( $this );

		date_default_timezone_set( $this->existing_timezone );

		return $result;
	}
}
