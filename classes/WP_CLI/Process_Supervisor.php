<?php

namespace Action_Scheduler\WP_CLI;

use WP_CLI;

/**
 * Supervises repeated execution of a WP-CLI command in fresh processes.
 */
class Process_Supervisor {

	/**
	 * Marks the launched process as the worker rather than the supervisor.
	 */
	const CHILD_ENVIRONMENT_VARIABLE = 'ACTION_SCHEDULER_CONTINUOUS_CHILD';

	/**
	 * Passes the supervisor stop file to the worker.
	 */
	const STOP_FILE_ENVIRONMENT_VARIABLE = 'ACTION_SCHEDULER_CONTINUOUS_STOP_FILE';

	/**
	 * Command to launch.
	 *
	 * @var string
	 */
	private $command;

	/**
	 * Seconds to wait before restarting.
	 *
	 * @var float
	 */
	private $sleep;

	/**
	 * Stop predicate.
	 *
	 * @var callable
	 */
	private $should_stop;

	/**
	 * File used to relay a parent-only stop signal to the child.
	 *
	 * @var string
	 */
	private $stop_file;

	/**
	 * Constructor.
	 *
	 * @param string   $command     WP-CLI command to launch.
	 * @param float    $sleep       Seconds to wait before restarting.
	 * @param callable $should_stop Stop predicate.
	 */
	public function __construct( $command, $sleep, $should_stop ) {
		$this->command     = $command;
		$this->sleep       = $sleep;
		$this->should_stop = $should_stop;
		$this->stop_file   = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'action-scheduler-' . getmypid() . '-' . uniqid() . '.stop';
	}

	/**
	 * Run children until stopped or a child fails.
	 *
	 * @return int Exit code.
	 */
	public function run() {
		try {
			while ( ! $this->should_stop() ) {
				$exit_code = $this->launch_child();

				if ( $this->should_stop() ) {
					return 0;
				}

				if ( 0 !== $exit_code ) {
					return $exit_code;
				}

				$this->sleep();
			}

			return 0;
		} finally {
			$this->remove_stop_file();
		}
	}

	/**
	 * Request a graceful stop and notify the current child.
	 */
	public function request_stop() {
		if ( ! file_exists( $this->stop_file ) ) {
			// Direct access is required before WordPress filesystem credentials are available.
			// phpcs:ignore
			touch( $this->stop_file );
		}
	}

	/**
	 * Check whether the current worker's supervisor requested a stop.
	 *
	 * @return bool
	 */
	public static function child_stop_requested() {
		$stop_file = getenv( self::STOP_FILE_ENVIRONMENT_VARIABLE );
		return is_string( $stop_file ) && '' !== $stop_file && file_exists( $stop_file );
	}

	/**
	 * Launch one worker process.
	 *
	 * @return int Exit code.
	 */
	protected function launch_child() {
		// The marker must be inherited by the newly launched process.
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv
		putenv( self::CHILD_ENVIRONMENT_VARIABLE . '=1' );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv
		putenv( self::STOP_FILE_ENVIRONMENT_VARIABLE . '=' . $this->stop_file );

		try {
			return (int) WP_CLI::runcommand(
				$this->command,
				array(
					'launch'     => true,
					'exit_error' => true,
					'return'     => false,
				)
			);
		} finally {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv
			putenv( self::CHILD_ENVIRONMENT_VARIABLE );
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv
			putenv( self::STOP_FILE_ENVIRONMENT_VARIABLE );
		}
	}

	/**
	 * Sleep in short intervals to respond promptly to stop signals.
	 */
	protected function sleep() {
		$deadline = microtime( true ) + $this->sleep;

		while ( ! $this->should_stop() && microtime( true ) < $deadline ) {
			$remaining = $deadline - microtime( true );
			usleep( (int) min( 100000, max( 1, $remaining * 1000000 ) ) );
		}
	}

	/**
	 * Check whether a stop was requested.
	 *
	 * @return bool
	 */
	private function should_stop() {
		return file_exists( $this->stop_file ) || (bool) call_user_func( $this->should_stop );
	}

	/**
	 * Remove the stop relay file.
	 */
	private function remove_stop_file() {
		if ( file_exists( $this->stop_file ) ) {
			// This is a process-local marker in the system temporary directory.
			// phpcs:ignore
			unlink( $this->stop_file );
		}
	}
}
