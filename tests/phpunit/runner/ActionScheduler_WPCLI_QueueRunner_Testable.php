<?php

/**
 * Testable queue runner.
 */
class ActionScheduler_WPCLI_QueueRunner_Testable extends ActionScheduler_WPCLI_QueueRunner {

	/**
	 * Cleanup calls.
	 *
	 * @var int
	 */
	public $cleanup_count = 0;

	/**
	 * Hook registration calls.
	 *
	 * @var int
	 */
	public $add_hooks_count = 0;

	/**
	 * Processed action IDs.
	 *
	 * @var int[]
	 */
	public $processed_actions = array();

	/**
	 * Store test double.
	 *
	 * @var ActionScheduler_WPCLI_QueueRunner_Test_Store
	 */
	public $store;

	/**
	 * Constructor.
	 *
	 * @param int[] $actions Action IDs.
	 */
	public function __construct( $actions ) {
		$this->actions = $actions;
		$this->claim   = new ActionScheduler_WPCLI_QueueRunner_Test_Claim();
		$this->store   = new ActionScheduler_WPCLI_QueueRunner_Test_Store();
	}

	/**
	 * Expose runner preparation for testing.
	 */
	public function prepare_for_test() {
		$this->initialize();
		$this->maybe_perform_cleanup();
	}

	/**
	 * Count cleanup calls.
	 *
	 * @param int $cleanup_time_limit Cleanup interval.
	 */
	protected function perform_cleanup( $cleanup_time_limit ) {
		unset( $cleanup_time_limit );
		++$this->cleanup_count;
	}

	/**
	 * Return a stable cleanup interval.
	 *
	 * @return int
	 */
	protected function get_time_limit() {
		return 30;
	}

	/**
	 * Count hook registration calls.
	 */
	protected function add_hooks() {
		++$this->add_hooks_count;
	}

	/**
	 * Use a small progress bar test double.
	 */
	protected function setup_progress_bar() {
		$this->progress_bar = new ActionScheduler_WPCLI_QueueRunner_Test_ProgressBar();
	}

	/**
	 * Record an action without executing callbacks.
	 *
	 * @param int    $action_id Action ID.
	 * @param string $context   Runner context.
	 */
	public function process_action( $action_id, $context = '' ) {
		unset( $context );
		$this->processed_actions[] = $action_id;
	}
}
