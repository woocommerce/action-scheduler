<?php

/**
 * Class ActionScheduler_UnitTestCase
 */
class ActionScheduler_UnitTestCase extends WP_UnitTestCase {
	/**
	 * Scheduled action hook that can be used when we want to simulate an action
	 * with a registered callback.
	 */
	const HOOK_WITH_CALLBACK = 'hook_with_callback';

	protected $existing_timezone;

	/**
	 * Shared setup logic.
	 */
	public function set_up() {
		add_action( self::HOOK_WITH_CALLBACK, array( $this, 'empty_callback') );
		parent::set_up();
	}

	/**
	 * Shared tear-down logic.
	 */
	public function tear_down() {
		remove_action( self::HOOK_WITH_CALLBACK, array( $this, 'empty_callback' ) );
		parent::tear_down();
	}

	/**
	 * This stub is used as the callback function for the self::HOOK_WITH_CALLBACK hook.
	 *
	 * Action Scheduler will mark actions as 'failed' if a callback does not exist, this
	 * simply serves to act as the callback for various test scenarios in child classes.
	 */
	public function empty_callback() {}

	/**
	 * Counts the number of test cases executed by run(TestResult result).
	 *
	 * @return int
	 */
	public function count(): int {
		return 'UTC' == date_default_timezone_get() ? 2 : 3;
	}

	/**
	 * We want to run every test multiple times using a different timezone to make sure
	 * that they are unaffected by changes to PHP's timezone.
	 */
	public function run( PHPUnit\Framework\TestResult $result = NULL ): \PHPUnit\Framework\TestResult {

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
