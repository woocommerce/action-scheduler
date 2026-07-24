<?php

/**
 * Testable worker command.
 */
class ActionScheduler_WPCLI_Worker_Command_Testable extends ActionScheduler_WPCLI_Worker_Command {

	/**
	 * Queue runner.
	 *
	 * @var ActionScheduler_WPCLI_Worker_Command_Test_Runner
	 */
	private $runner;

	/**
	 * Number of idle sleeps.
	 *
	 * @var int
	 */
	public $sleep_count = 0;

	/**
	 * Number of cache cleanup calls.
	 *
	 * @var int
	 */
	public $free_memory_count = 0;

	/**
	 * Final success message.
	 *
	 * @var string
	 */
	public $success_message = '';

	/**
	 * Constructor.
	 *
	 * @param ActionScheduler_WPCLI_Worker_Command_Test_Runner $runner Queue runner.
	 */
	public function __construct( $runner ) {
		$this->runner = $runner;
	}

	/**
	 * Return the test runner.
	 *
	 * @return ActionScheduler_WPCLI_Worker_Command_Test_Runner
	 */
	protected function create_queue_runner() {
		return $this->runner;
	}

	/**
	 * Disable signal handlers in tests.
	 */
	protected function register_signal_handlers() {}

	/**
	 * Count idle sleeps without waiting.
	 *
	 * @param float $seconds     Sleep duration.
	 * @param int   $max_runtime Maximum runtime.
	 * @param float $started_at  Start time.
	 */
	protected function interruptible_sleep( $seconds, $max_runtime, $started_at ) {
		unset( $seconds, $max_runtime, $started_at );
		++$this->sleep_count;
	}

	/**
	 * Count cache cleanup calls.
	 */
	protected function free_memory() {
		++$this->free_memory_count;
	}

	/**
	 * Suppress informational output in tests.
	 *
	 * @param string $message Message.
	 */
	protected function log( $message ) {
		unset( $message );
	}

	/**
	 * Capture the success message.
	 *
	 * @param string $message Message.
	 */
	protected function success( $message ) {
		$this->success_message = $message;
	}
}
