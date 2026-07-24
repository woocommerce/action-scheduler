<?php

/**
 * WP-CLI command for continuously processing Action Scheduler actions.
 */
class ActionScheduler_WPCLI_Worker_Command extends WP_CLI_Command {

	/**
	 * Whether a graceful stop has been requested.
	 *
	 * @var bool
	 */
	private $stop_requested = false;

	/**
	 * Continuously process due actions.
	 *
	 * Unlike `wp action-scheduler run`, this command waits for new actions when
	 * the queue is empty. It is intended to run under a process monitor.
	 *
	 * ## OPTIONS
	 *
	 * [--batch-size=<size>]
	 * : The maximum number of actions to claim in one batch. Default 100.
	 *
	 * [--hooks=<hooks>]
	 * : Only process actions with these comma-separated hooks.
	 *
	 * [--group=<group>]
	 * : Only process actions from this group.
	 *
	 * [--exclude-groups=<groups>]
	 * : Ignore actions from these comma-separated groups. Ignored when --group is used.
	 *
	 * [--sleep=<seconds>]
	 * : Seconds to wait when no due actions are available. Fractions are accepted. Default 3.
	 *
	 * [--max-actions=<count>]
	 * : Stop after processing this many actions. Zero means unlimited. Default 0.
	 *
	 * [--max-runtime=<seconds>]
	 * : Stop after this many seconds. Zero means unlimited. Default 0.
	 *
	 * [--memory-limit=<megabytes>]
	 * : Stop when memory usage reaches this value. Zero means unlimited. Default 0.
	 *
	 * [--free-memory-on=<count>]
	 * : Clear WordPress runtime caches after this many actions. Zero disables it. Default 50.
	 *
	 * [--stop-when-empty]
	 * : Stop instead of waiting when no due actions are available.
	 *
	 * [--force]
	 * : Run despite Action Scheduler's concurrent batch limit.
	 *
	 * ## EXAMPLES
	 *
	 *     wp action-scheduler work
	 *
	 *     wp action-scheduler work --group=emails --sleep=1 --max-runtime=3600
	 *
	 *     wp action-scheduler work --stop-when-empty
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Keyed arguments.
	 * @throws \WP_CLI\ExitException When the worker cannot continue.
	 */
	public function __invoke( $args, $assoc_args ) {
		unset( $args );

		$options = $this->parse_options( $assoc_args );
		$this->apply_excluded_groups( $options['group'], $options['exclude_groups'] );
		$this->register_signal_handlers();

		$started_at      = microtime( true );
		$actions_run     = 0;
		$last_cache_free = 0;
		$runner          = $this->create_queue_runner();

		// Progress ticks restart with each batch. The worker needs a process-wide counter.
		ActionScheduler_DataController::set_free_ticks( 0 );

		$this->log(
			sprintf(
				/* translators: 1: action group, 2: idle sleep time in seconds */
				__( 'Action Scheduler worker started (group: %1$s, sleep: %2$s seconds).', 'action-scheduler' ),
				'' !== $options['group'] ? $options['group'] : __( 'all', 'action-scheduler' ),
				$options['sleep']
			)
		);

		while ( ! $this->should_stop( $options, $started_at, $actions_run ) ) {
			if ( ! $options['force'] && $runner->has_maximum_concurrent_batches() ) {
				$this->interruptible_sleep( $options['sleep'], $options['max_runtime'], $started_at );
				continue;
			}

			$batch_size = $options['batch_size'];
			if ( $options['max_actions'] > 0 ) {
				$batch_size = min( $batch_size, $options['max_actions'] - $actions_run );
			}

			$available = $runner->setup(
				$batch_size,
				$options['hooks'],
				$options['group'],
				$options['force']
			);

			if ( 0 === $available ) {
				if ( $options['stop_when_empty'] ) {
					break;
				}

				$this->interruptible_sleep( $options['sleep'], $options['max_runtime'], $started_at );
				continue;
			}

			$actions_run += $runner->run( 'WP CLI Worker' );

			if (
				$options['free_memory_on'] > 0
				&& $actions_run - $last_cache_free >= $options['free_memory_on']
			) {
				$this->free_memory();
				$last_cache_free = $actions_run;
			}
		}

		$this->success(
			sprintf(
				/* translators: 1: number of actions processed, 2: worker runtime in seconds */
				_n(
					'Worker stopped after processing %1$d action in %2$.2f seconds.',
					'Worker stopped after processing %1$d actions in %2$.2f seconds.',
					$actions_run,
					'action-scheduler'
				),
				$actions_run,
				microtime( true ) - $started_at
			)
		);
	}

	/**
	 * Parse and validate command options.
	 *
	 * @param array $assoc_args Keyed command arguments.
	 * @return array
	 */
	protected function parse_options( $assoc_args ) {
		$batch_size     = absint( \WP_CLI\Utils\get_flag_value( $assoc_args, 'batch-size', 100 ) );
		$sleep          = (float) \WP_CLI\Utils\get_flag_value( $assoc_args, 'sleep', 3 );
		$max_actions    = absint( \WP_CLI\Utils\get_flag_value( $assoc_args, 'max-actions', 0 ) );
		$max_runtime    = absint( \WP_CLI\Utils\get_flag_value( $assoc_args, 'max-runtime', 0 ) );
		$memory_limit   = absint( \WP_CLI\Utils\get_flag_value( $assoc_args, 'memory-limit', 0 ) );
		$free_memory_on = absint( \WP_CLI\Utils\get_flag_value( $assoc_args, 'free-memory-on', 50 ) );

		if ( $batch_size < 1 ) {
			WP_CLI::error( __( '--batch-size must be at least 1.', 'action-scheduler' ) );
		}

		if ( $sleep < 0 ) {
			WP_CLI::error( __( '--sleep cannot be negative.', 'action-scheduler' ) );
		}

		return array(
			'batch_size'      => $batch_size,
			'hooks'           => $this->parse_comma_separated_string( \WP_CLI\Utils\get_flag_value( $assoc_args, 'hooks', '' ) ),
			'group'           => (string) \WP_CLI\Utils\get_flag_value( $assoc_args, 'group', '' ),
			'exclude_groups'  => $this->parse_comma_separated_string( \WP_CLI\Utils\get_flag_value( $assoc_args, 'exclude-groups', '' ) ),
			'sleep'           => $sleep,
			'max_actions'     => $max_actions,
			'max_runtime'     => $max_runtime,
			'memory_limit'    => $memory_limit,
			'free_memory_on'  => $free_memory_on,
			'stop_when_empty' => (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'stop-when-empty', false ),
			'force'           => (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'force', false ),
		);
	}

	/**
	 * Apply the excluded-groups claim filter when supported by the active store.
	 *
	 * @param string $group          Included group.
	 * @param array  $exclude_groups Excluded groups.
	 */
	protected function apply_excluded_groups( $group, $exclude_groups ) {
		if (
			'' === $group
			&& ! empty( $exclude_groups )
			&& is_callable( array( ActionScheduler::store(), 'set_claim_filter' ) )
		) {
			ActionScheduler::store()->set_claim_filter( 'exclude-groups', $exclude_groups );
		}
	}

	/**
	 * Parse a comma-separated option.
	 *
	 * @param string $value Option value.
	 * @return array
	 */
	protected function parse_comma_separated_string( $value ) {
		return array_filter( array_map( 'trim', str_getcsv( (string) $value ) ) );
	}

	/**
	 * Create the queue runner.
	 *
	 * @return ActionScheduler_WPCLI_QueueRunner
	 */
	protected function create_queue_runner() {
		return new ActionScheduler_WPCLI_QueueRunner();
	}

	/**
	 * Register graceful shutdown handlers when PCNTL is available.
	 */
	protected function register_signal_handlers() {
		if ( ! function_exists( 'pcntl_async_signals' ) || ! function_exists( 'pcntl_signal' ) ) {
			return;
		}

		pcntl_async_signals( true );

		$handler = function () {
			$this->stop_requested = true;
			$this->log( __( 'Stop requested; the worker will exit after the current batch.', 'action-scheduler' ) );
		};

		pcntl_signal( SIGTERM, $handler );
		pcntl_signal( SIGINT, $handler );
	}

	/**
	 * Determine whether a worker stopping condition has been reached.
	 *
	 * @param array $options     Worker options.
	 * @param float $started_at  Worker start time.
	 * @param int   $actions_run Number of processed actions.
	 * @return bool
	 */
	protected function should_stop( $options, $started_at, $actions_run ) {
		if ( $this->stop_requested ) {
			return true;
		}

		if ( $options['max_actions'] > 0 && $actions_run >= $options['max_actions'] ) {
			return true;
		}

		if ( $options['max_runtime'] > 0 && microtime( true ) - $started_at >= $options['max_runtime'] ) {
			return true;
		}

		return $options['memory_limit'] > 0
			&& memory_get_usage( true ) >= $options['memory_limit'] * MB_IN_BYTES;
	}

	/**
	 * Sleep in short intervals so signals and the runtime limit remain responsive.
	 *
	 * @param float $seconds     Maximum number of seconds to sleep.
	 * @param int   $max_runtime Maximum worker runtime in seconds.
	 * @param float $started_at  Worker start time.
	 */
	protected function interruptible_sleep( $seconds, $max_runtime, $started_at ) {
		$deadline = microtime( true ) + $seconds;

		if ( $max_runtime > 0 ) {
			$deadline = min( $deadline, $started_at + $max_runtime );
		}

		while ( ! $this->stop_requested && microtime( true ) < $deadline ) {
			$remaining = $deadline - microtime( true );
			usleep( (int) min( 100000, max( 1, $remaining * 1000000 ) ) );
		}
	}

	/**
	 * Free process-local caches and collect cycles.
	 */
	protected function free_memory() {
		ActionScheduler_DataController::free_memory();
		gc_collect_cycles();
	}

	/**
	 * Write an informational message.
	 *
	 * @param string $message Message to write.
	 */
	protected function log( $message ) {
		WP_CLI::log( $message );
	}

	/**
	 * Write a success message.
	 *
	 * @param string $message Message to write.
	 */
	protected function success( $message ) {
		WP_CLI::success( $message );
	}
}
