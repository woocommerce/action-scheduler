<?php

/**
 * Commands for Action Scheduler.
 */
class ActionScheduler_WPCLI_Scheduler_command extends WP_CLI_Command {

	/**
	 * Whether a graceful stop has been requested.
	 *
	 * @var bool
	 */
	private $stop_requested = false;

	/**
	 * Signal that requested the stop.
	 *
	 * @var int
	 */
	private $stop_signal = 0;

	/**
	 * Force tables schema creation for Action Scheduler
	 *
	 * ## OPTIONS
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Keyed arguments.
	 *
	 * @subcommand fix-schema
	 */
	public function fix_schema( $args, $assoc_args ) {
		$schema_classes = array( ActionScheduler_LoggerSchema::class, ActionScheduler_StoreSchema::class );

		foreach ( $schema_classes as $classname ) {
			if ( is_subclass_of( $classname, ActionScheduler_Abstract_Schema::class ) ) {
				$obj = new $classname();
				$obj->init();
				$obj->register_tables( true );

				WP_CLI::success(
					sprintf(
						/* translators: %s refers to the schema name*/
						__( 'Registered schema for %s', 'action-scheduler' ),
						$classname
					)
				);
			}
		}
	}

	/**
	 * Run the Action Scheduler
	 *
	 * ## OPTIONS
	 *
	 * [--batch-size=<size>]
	 * : The maximum number of actions to run. Defaults to 100.
	 *
	 * [--batches=<size>]
	 * : Limit execution to a number of batches. Defaults to 0, meaning batches will continue being executed until all actions are complete.
	 *
	 * [--cleanup-batch-size=<size>]
	 * : The maximum number of actions to clean up. Defaults to the value of --batch-size.
	 *
	 * [--hooks=<hooks>]
	 * : Only run actions with the specified hook. Omitting this option runs actions with any hook. Define multiple hooks as a comma separated string (without spaces), e.g. `--hooks=hook_one,hook_two,hook_three`
	 *
	 * [--group=<group>]
	 * : Only run actions from the specified group. Omitting this option runs actions from all groups.
	 *
	 * [--exclude-groups=<groups>]
	 * : Run actions from all groups except the specified group(s). Define multiple groups as a comma separated string (without spaces), e.g. '--group_a,group_b'. This option is ignored when `--group` is used.
	 *
	 * [--free-memory-on=<count>]
	 * : The number of actions to process between freeing memory. 0 disables freeing memory. Default 50.
	 *
	 * [--pause=<seconds>]
	 * : The number of seconds to pause when freeing memory. Default no pause.
	 *
	 * [--force]
	 * : Whether to force execution despite the maximum number of concurrent processes being exceeded.
	 *
	 * [--poll-every-ms=<milliseconds>]
	 * : Keep polling for new actions at this interval and recycle the worker in a fresh process when a limit is reached.
	 *
	 * [--max-actions=<count>]
	 * : Stop after processing this many actions. When polling, recycle the worker. Default 0 means unlimited.
	 *
	 * [--max-runtime=<seconds>]
	 * : Stop after this many seconds. When polling, recycle the worker. Default 0 means unlimited.
	 *
	 * [--memory-limit=<megabytes>]
	 * : Stop at this memory usage. When polling, recycle the worker. Default 0 means unlimited.
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Keyed arguments.
	 * @throws \WP_CLI\ExitException When an error occurs.
	 *
	 * @subcommand run
	 */
	public function run( $args, $assoc_args ) {
		unset( $args );

		$options          = $this->parse_run_options( $assoc_args );
		$is_polling_child = '1' === getenv( \Action_Scheduler\WP_CLI\Process_Supervisor::CHILD_ENVIRONMENT_VARIABLE );

		if ( null !== $options['poll_every_ms'] && ! $is_polling_child ) {
			$supervisor = $this->create_process_supervisor( $assoc_args, $options['poll_every_ms'] );
			$this->register_signal_handlers( array( $supervisor, 'request_stop' ) );
			$exit_code = $supervisor->run();

			if ( $this->stop_signal > 0 ) {
				WP_CLI::halt( 128 + $this->stop_signal );
			}

			if ( 0 !== $exit_code ) {
				WP_CLI::halt( $exit_code );
			}

			return;
		}

		if ( $is_polling_child ) {
			$this->register_signal_handlers();
		}

		$this->run_queue( $options );

		if ( $this->stop_signal > 0 ) {
			WP_CLI::halt( 128 + $this->stop_signal );
		}
	}

	/**
	 * Parse and validate run options.
	 *
	 * @param array $assoc_args Keyed arguments.
	 * @return array
	 */
	protected function parse_run_options( $assoc_args ) {
		$batch               = absint( \WP_CLI\Utils\get_flag_value( $assoc_args, 'batch-size', 100 ) );
		$poll_every_ms_value = \WP_CLI\Utils\get_flag_value( $assoc_args, 'poll-every-ms', null );
		$poll_every_ms       = null;

		if ( $batch < 1 ) {
			WP_CLI::error( __( '--batch-size must be at least 1.', 'action-scheduler' ) );
		}

		if ( null !== $poll_every_ms_value ) {
			$poll_every_ms = filter_var( $poll_every_ms_value, FILTER_VALIDATE_INT );

			if ( false === $poll_every_ms || $poll_every_ms < 1 ) {
				WP_CLI::error( __( '--poll-every-ms must be a positive integer.', 'action-scheduler' ) );
			}
		}

		return array(
			'batch_size'         => $batch,
			'batches'            => absint( \WP_CLI\Utils\get_flag_value( $assoc_args, 'batches', 0 ) ),
			'cleanup_batch_size' => absint( \WP_CLI\Utils\get_flag_value( $assoc_args, 'cleanup-batch-size', $batch ) ),
			'hooks'              => $this->parse_comma_separated_string( \WP_CLI\Utils\get_flag_value( $assoc_args, 'hooks', '' ) ),
			'group'              => (string) \WP_CLI\Utils\get_flag_value( $assoc_args, 'group', '' ),
			'exclude_groups'     => $this->parse_comma_separated_string( \WP_CLI\Utils\get_flag_value( $assoc_args, 'exclude-groups', '' ) ),
			'free_memory_on'     => absint( \WP_CLI\Utils\get_flag_value( $assoc_args, 'free-memory-on', 50 ) ),
			'pause'              => (float) \WP_CLI\Utils\get_flag_value( $assoc_args, 'pause', 0 ),
			'force'              => (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'force', false ),
			'poll_every_ms'      => $poll_every_ms,
			'max_actions'        => absint( \WP_CLI\Utils\get_flag_value( $assoc_args, 'max-actions', 0 ) ),
			'max_runtime'        => absint( \WP_CLI\Utils\get_flag_value( $assoc_args, 'max-runtime', 0 ) ),
			'memory_limit'       => absint( \WP_CLI\Utils\get_flag_value( $assoc_args, 'memory-limit', 0 ) ),
		);
	}

	/**
	 * Process actions in the current process.
	 *
	 * @param array $options Run options.
	 */
	protected function run_queue( $options ) {
		ActionScheduler_DataController::set_free_ticks( $options['free_memory_on'] );
		ActionScheduler_DataController::set_sleep_time( $options['pause'] );

		$batches_completed = 0;
		$actions_completed = 0;
		$started_at        = microtime( true );

		if ( is_callable( array( ActionScheduler::store(), 'set_claim_filter' ) ) ) {
			if ( '' === $options['group'] && ! empty( $options['exclude_groups'] ) ) {
				ActionScheduler::store()->set_claim_filter( 'exclude-groups', $options['exclude_groups'] );
			}
		}

		try {
			// Custom queue cleaner instance.
			$cleaner = new ActionScheduler_QueueCleaner( null, $options['cleanup_batch_size'] );

			// Get the queue runner instance.
			$runner = $this->create_queue_runner( $cleaner );

			while (
				! $this->should_stop( $options, $started_at, $actions_completed )
				&& ( 0 === $options['batches'] || $batches_completed < $options['batches'] )
			) {
				if (
					null !== $options['poll_every_ms']
					&& ! $options['force']
					&& $runner->has_maximum_concurrent_batches()
				) {
					$this->interruptible_sleep( $options['poll_every_ms'] / 1000, $options['max_runtime'], $started_at );
					continue;
				}

				$batch_size = $options['batch_size'];
				if ( $options['max_actions'] > 0 ) {
					$batch_size = min( $batch_size, $options['max_actions'] - $actions_completed );
				}

				$total = $runner->setup( $batch_size, $options['hooks'], $options['group'], $options['force'] );
				if ( 0 === $total ) {
					if ( null === $options['poll_every_ms'] ) {
						break;
					}

					$this->interruptible_sleep( $options['poll_every_ms'] / 1000, $options['max_runtime'], $started_at );
					continue;
				}

				$this->print_total_actions( $total );
				$actions_before_batch = $actions_completed;
				$actions_completed   += $runner->run(
					'WP CLI',
					function ( $batch_actions_processed ) use ( $options, $started_at, $actions_before_batch ) {
						return $this->should_stop(
							$options,
							$started_at,
							$actions_before_batch + $batch_actions_processed
						);
					}
				);
				++$batches_completed;
			}
		} catch ( Exception $e ) {
			$this->print_error( $e );
		}

		$this->print_total_batches( $batches_completed );
		$this->print_success( $actions_completed );
	}

	/**
	 * Create the queue runner.
	 *
	 * @param ActionScheduler_QueueCleaner $cleaner Queue cleaner.
	 * @return ActionScheduler_WPCLI_QueueRunner
	 */
	protected function create_queue_runner( $cleaner ) {
		return new ActionScheduler_WPCLI_QueueRunner( null, null, $cleaner );
	}

	/**
	 * Create a supervisor for polling execution.
	 *
	 * @param array $assoc_args    Original command arguments.
	 * @param int   $poll_every_ms Polling and restart interval in milliseconds.
	 * @return \Action_Scheduler\WP_CLI\Process_Supervisor
	 */
	protected function create_process_supervisor( $assoc_args, $poll_every_ms ) {
		$command = 'action-scheduler run';
		if ( ! empty( $assoc_args ) ) {
			$command .= \WP_CLI\Utils\assoc_args_to_str( $assoc_args );
		}

		return new \Action_Scheduler\WP_CLI\Process_Supervisor(
			$command,
			$poll_every_ms / 1000,
			function () {
				return $this->stop_requested;
			}
		);
	}

	/**
	 * Check process stopping conditions.
	 *
	 * @param array $options           Run options.
	 * @param float $started_at        Process start time.
	 * @param int   $actions_completed Number of processed actions.
	 * @return bool
	 */
	protected function should_stop( $options, $started_at, $actions_completed ) {
		if ( $this->stop_requested || \Action_Scheduler\WP_CLI\Process_Supervisor::child_stop_requested() ) {
			return true;
		}

		if ( $options['max_actions'] > 0 && $actions_completed >= $options['max_actions'] ) {
			return true;
		}

		if ( $options['max_runtime'] > 0 && microtime( true ) - $started_at >= $options['max_runtime'] ) {
			return true;
		}

		return $options['memory_limit'] > 0
			&& memory_get_usage( true ) >= $options['memory_limit'] * MB_IN_BYTES;
	}

	/**
	 * Register graceful stop handlers for polling mode.
	 *
	 * @param callable|null $on_stop Optional callback invoked when a signal arrives.
	 */
	protected function register_signal_handlers( $on_stop = null ) {
		if (
			! function_exists( 'pcntl_async_signals' )
			|| ! function_exists( 'pcntl_signal' )
			|| ! defined( 'SIGINT' )
			|| ! defined( 'SIGTERM' )
		) {
			WP_CLI::error( __( 'Polling mode requires the PHP PCNTL extension.', 'action-scheduler' ) );
		}

		pcntl_async_signals( true );

		$handler = function ( $signal ) use ( $on_stop ) {
			$this->stop_requested = true;
			$this->stop_signal    = (int) $signal;

			if ( is_callable( $on_stop ) ) {
				call_user_func( $on_stop );
			}
		};

		pcntl_signal( SIGINT, $handler );
		pcntl_signal( SIGTERM, $handler );
	}

	/**
	 * Sleep in short intervals to remain responsive to signals and runtime limits.
	 *
	 * @param float $seconds     Maximum sleep duration.
	 * @param int   $max_runtime Maximum process runtime.
	 * @param float $started_at  Process start time.
	 */
	protected function interruptible_sleep( $seconds, $max_runtime, $started_at ) {
		$deadline = microtime( true ) + $seconds;

		if ( $max_runtime > 0 ) {
			$deadline = min( $deadline, $started_at + $max_runtime );
		}

		while (
			! $this->stop_requested
			&& ! \Action_Scheduler\WP_CLI\Process_Supervisor::child_stop_requested()
			&& microtime( true ) < $deadline
		) {
			$remaining = $deadline - microtime( true );
			usleep( (int) min( 100000, max( 1, $remaining * 1000000 ) ) );
		}
	}

	/**
	 * Converts a string of comma-separated values into an array of those same values.
	 *
	 * @param string $string The string of one or more comma separated values.
	 *
	 * @return array
	 */
	private function parse_comma_separated_string( $string ): array {
		return array_filter( str_getcsv( $string ) );
	}

	/**
	 * Print WP CLI message about how many actions are about to be processed.
	 *
	 * @param int $total Number of actions found.
	 */
	protected function print_total_actions( $total ) {
		WP_CLI::log(
			sprintf(
				/* translators: %d refers to how many scheduled tasks were found to run */
				_n( 'Found %d scheduled task', 'Found %d scheduled tasks', $total, 'action-scheduler' ),
				$total
			)
		);
	}

	/**
	 * Print WP CLI message about how many batches of actions were processed.
	 *
	 * @param int $batches_completed Number of completed batches.
	 */
	protected function print_total_batches( $batches_completed ) {
		WP_CLI::log(
			sprintf(
				/* translators: %d refers to the total number of batches executed */
				_n( '%d batch executed.', '%d batches executed.', $batches_completed, 'action-scheduler' ),
				$batches_completed
			)
		);
	}

	/**
	 * Convert an exception into a WP CLI error.
	 *
	 * @param Exception $e The error object.
	 *
	 * @throws \WP_CLI\ExitException Under some conditions WP CLI may throw an exception.
	 */
	protected function print_error( Exception $e ) {
		WP_CLI::error(
			sprintf(
				/* translators: %s refers to the exception error message */
				__( 'There was an error running the action scheduler: %s', 'action-scheduler' ),
				$e->getMessage()
			)
		);
	}

	/**
	 * Print a success message with the number of completed actions.
	 *
	 * @param int $actions_completed Number of completed actions.
	 */
	protected function print_success( $actions_completed ) {
		WP_CLI::success(
			sprintf(
				/* translators: %d refers to the total number of tasks completed */
				_n( '%d scheduled task completed.', '%d scheduled tasks completed.', $actions_completed, 'action-scheduler' ),
				$actions_completed
			)
		);
	}
}
