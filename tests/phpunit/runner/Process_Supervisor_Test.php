<?php

require_once __DIR__ . '/Process_Supervisor_Testable.php';

/**
 * Tests for the polling process supervisor.
 */
class Process_Supervisor_Test extends PHPUnit\Framework\TestCase {

	/**
	 * A successful child is recycled and a failing child is not.
	 */
	public function test_recycles_successful_child_until_failure() {
		$supervisor = new Process_Supervisor_Testable( array( 0, 7 ) );

		$this->assertSame( 7, $supervisor->run() );
		$this->assertSame( 2, $supervisor->launch_count );
		$this->assertSame( 1, $supervisor->sleep_count );
	}

	/**
	 * A stop request prevents another child from being launched.
	 */
	public function test_stop_request_prevents_restart() {
		$stop       = false;
		$supervisor = new Process_Supervisor_Testable(
			array( 0 ),
			static function () use ( &$stop ) {
				return $stop;
			},
			static function () use ( &$stop ) {
				$stop = true;
			}
		);

		$this->assertSame( 0, $supervisor->run() );
		$this->assertSame( 1, $supervisor->launch_count );
		$this->assertSame( 0, $supervisor->sleep_count );
	}

	/**
	 * A relayed parent signal prevents the first child from being launched.
	 */
	public function test_relayed_stop_request_prevents_launch() {
		$supervisor = new Process_Supervisor_Testable( array( 0 ) );
		$supervisor->request_stop();

		$this->assertSame( 0, $supervisor->run() );
		$this->assertSame( 0, $supervisor->launch_count );
	}
}
