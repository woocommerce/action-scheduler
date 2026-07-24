<?php

require_once __DIR__ . '/ActionScheduler_WPCLI_Worker_Command_Test_Runner.php';
require_once __DIR__ . '/ActionScheduler_WPCLI_Worker_Command_Testable.php';

/**
 * Tests for ActionScheduler_WPCLI_Worker_Command.
 */
class ActionScheduler_WPCLI_Worker_Command_Test extends PHPUnit\Framework\TestCase {

	/**
	 * The worker exits without processing actions when the queue is empty.
	 */
	public function test_stop_when_empty() {
		$runner  = new ActionScheduler_WPCLI_Worker_Command_Test_Runner( array( 0 ) );
		$command = new ActionScheduler_WPCLI_Worker_Command_Testable( $runner );

		$command( array(), array( 'stop-when-empty' => true ) );

		$this->assertSame( array( 100 ), $runner->batch_sizes );
		$this->assertSame( 0, $runner->run_count );
		$this->assertSame( 0, $command->sleep_count );
		$this->assertStringContainsString( '0 actions', $command->success_message );
	}

	/**
	 * The maximum action count limits the claim size and stops the worker.
	 */
	public function test_max_actions_limits_the_claim() {
		$runner  = new ActionScheduler_WPCLI_Worker_Command_Test_Runner( array( 2 ), 2 );
		$command = new ActionScheduler_WPCLI_Worker_Command_Testable( $runner );

		$command( array(), array( 'max-actions' => 2 ) );

		$this->assertSame( array( 2 ), $runner->batch_sizes );
		$this->assertSame( 1, $runner->run_count );
		$this->assertStringContainsString( '2 actions', $command->success_message );
	}

	/**
	 * The worker waits for work and resumes processing when actions become due.
	 */
	public function test_worker_sleeps_when_no_actions_are_due() {
		$runner  = new ActionScheduler_WPCLI_Worker_Command_Test_Runner( array( 0, 1 ), 1 );
		$command = new ActionScheduler_WPCLI_Worker_Command_Testable( $runner );

		$command( array(), array( 'max-actions' => 1 ) );

		$this->assertSame( 1, $command->sleep_count );
		$this->assertSame( 1, $runner->run_count );
	}

	/**
	 * Runtime caches are freed according to the process-wide action count.
	 */
	public function test_worker_frees_memory_after_configured_number_of_actions() {
		$runner  = new ActionScheduler_WPCLI_Worker_Command_Test_Runner( array( 2 ), 2 );
		$command = new ActionScheduler_WPCLI_Worker_Command_Testable( $runner );

		$command(
			array(),
			array(
				'max-actions'    => 2,
				'free-memory-on' => 2,
			)
		);

		$this->assertSame( 1, $command->free_memory_count );
	}
}
