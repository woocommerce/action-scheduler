<?php

/**
 * Class ActionScheduler_UnitTestCase
 */
class ActionScheduler_UnitTestCase extends WP_UnitTestCase {

	protected $existing_timezone;

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

	/**
	 * A utility function to make certain methods public.
	 *
	 * Useful for testing protected methods that affect public APIs, but are not public to avoid
	 * use due to potential confusion.
	 *
	 * @param object $object
	 * @param string $method_name
	 *
	 * @return ReflectionMethod
	 * @throws ReflectionException When class doesn't exist.
	 */
	protected function get_accessible_protected_method( $object, $method_name ) {
		$reflection = new ReflectionClass( $object );
		$method     = $reflection->getMethod( $method_name );
		$method->setAccessible( true );

		return $method;
	}
}
