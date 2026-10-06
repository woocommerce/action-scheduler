<?php

/**
 * Testable process supervisor.
 */
class Process_Supervisor_Testable extends \Action_Scheduler\WP_CLI\Process_Supervisor {

	/**
	 * Child exit codes.
	 *
	 * @var int[]
	 */
	private $exit_codes;

	/**
	 * Callback after launch.
	 *
	 * @var callable|null
	 */
	private $after_launch;

	/**
	 * Number of launches.
	 *
	 * @var int
	 */
	public $launch_count = 0;

	/**
	 * Number of sleeps.
	 *
	 * @var int
	 */
	public $sleep_count = 0;

	/**
	 * Constructor.
	 *
	 * @param int[]         $exit_codes   Child exit codes.
	 * @param callable|null $should_stop  Stop predicate.
	 * @param callable|null $after_launch Callback after launch.
	 */
	public function __construct( $exit_codes, $should_stop = null, $after_launch = null ) {
		$this->exit_codes   = $exit_codes;
		$this->after_launch = $after_launch;

		parent::__construct(
			'action-scheduler run',
			0,
			$should_stop ? $should_stop : static function () {
				return false;
			}
		);
	}

	/**
	 * Return the next configured exit code.
	 *
	 * @return int
	 */
	protected function launch_child() {
		++$this->launch_count;

		if ( is_callable( $this->after_launch ) ) {
			call_user_func( $this->after_launch );
		}

		return array_shift( $this->exit_codes );
	}

	/**
	 * Count sleeps without waiting.
	 */
	protected function sleep() {
		++$this->sleep_count;
	}
}
