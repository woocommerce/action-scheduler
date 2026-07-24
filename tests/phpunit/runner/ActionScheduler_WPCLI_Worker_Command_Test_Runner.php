<?php

/**
 * Minimal queue runner test double.
 */
class ActionScheduler_WPCLI_Worker_Command_Test_Runner {

	/**
	 * Available action counts returned by setup.
	 *
	 * @var int[]
	 */
	private $available;

	/**
	 * Number of actions returned by run.
	 *
	 * @var int
	 */
	private $actions_per_run;

	/**
	 * Claimed batch sizes.
	 *
	 * @var int[]
	 */
	public $batch_sizes = array();

	/**
	 * Number of run calls.
	 *
	 * @var int
	 */
	public $run_count = 0;

	/**
	 * Constructor.
	 *
	 * @param int[] $available       Available action counts.
	 * @param int   $actions_per_run Number of processed actions.
	 */
	public function __construct( $available, $actions_per_run = 0 ) {
		$this->available       = $available;
		$this->actions_per_run = $actions_per_run;
	}

	/**
	 * No other runner is active in tests.
	 *
	 * @return bool
	 */
	public function has_maximum_concurrent_batches() {
		return false;
	}

	/**
	 * Record the claim size and return the next available count.
	 *
	 * @param int    $batch_size Batch size.
	 * @param array  $hooks      Included hooks.
	 * @param string $group      Included group.
	 * @param bool   $force      Force execution.
	 * @return int
	 */
	public function setup( $batch_size, $hooks, $group, $force ) {
		unset( $hooks, $group, $force );
		$this->batch_sizes[] = $batch_size;
		return array_shift( $this->available );
	}

	/**
	 * Return the configured number of processed actions.
	 *
	 * @param string $context Runner context.
	 * @return int
	 */
	public function run( $context ) {
		unset( $context );
		++$this->run_count;
		return $this->actions_per_run;
	}
}
