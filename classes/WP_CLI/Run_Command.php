<?php

namespace Action_Scheduler\WP_CLI;

/**
 * Action Scheduler WP CLI command to run the queue.
 */
class Run_Command extends \ActionScheduler_Abstract_WPCLI_Command {

	/**
	 * @var bool Enable printing of timestamp.
	 */
	protected $timestamp = false;

	/**
	 * @var string Format of timestamp.
	 */
	protected $timestamp_format = 'Y-m-d H:i:s T';

	/**
	 * Construct.
	 *
	 * @param array $args
	 * @param array $assoc_args
	 * @uses \ActionScheduler_Abstract_WPCLI_Command::__construct()
	 */
	public function __construct( $args, $assoc_args ) {
		parent::__construct( $args, $assoc_args );

		$this->timestamp = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'time', false );
		$this->timestamp_format = \WP_CLI\Utils\get_flag_value( $assoc_args, 'time-format', $this->timestamp_format );
	}

	/**
	 * Execute command.
	 */
	public function execute() {
		// Handle passed arguments.
		$batch   = absint( \WP_CLI\Utils\get_flag_value( $this->assoc_args, 'batch-size', 100 ) );
		$batches = absint( \WP_CLI\Utils\get_flag_value( $this->assoc_args, 'batches', 0 ) );
		$clean   = absint( \WP_CLI\Utils\get_flag_value( $this->assoc_args, 'cleanup-batch-size', $batch ) );
		$hooks   = explode( ',', \WP_CLI\Utils\get_flag_value( $this->assoc_args, 'hooks', '' ) );
		$hooks   = array_filter( array_map( 'trim', $hooks ) );
		$group   = \WP_CLI\Utils\get_flag_value( $this->assoc_args, 'group', '' );
		$free_on = \WP_CLI\Utils\get_flag_value( $this->assoc_args, 'free-memory-on', 50 );
		$sleep   = \WP_CLI\Utils\get_flag_value( $this->assoc_args, 'pause', 0 );
		$force   = \WP_CLI\Utils\get_flag_value( $this->assoc_args, 'force', false );

		\ActionScheduler_DataController::set_free_ticks( $free_on );
		\ActionScheduler_DataController::set_sleep_time( $sleep );

		$batches_completed = 0;
		$actions_completed = 0;
		$unlimited         = $batches === 0;

		try {
			// Custom queue cleaner instance.
			$cleaner = new \ActionScheduler_QueueCleaner( null, $clean );

			// Get the queue runner instance
			$runner = new \ActionScheduler_WPCLI_QueueRunner( null, null, $cleaner );

			// Determine how many tasks will be run in the first batch.
			$total = $runner->setup( $batch, $hooks, $group, $force );

			// Run actions for as long as possible.
			while ( $total > 0 ) {
				$this->print_total_actions( $total );
				$actions_completed += $runner->run();
				$batches_completed++;

				// Maybe set up tasks for the next batch.
				$total = ( $unlimited || $batches_completed < $batches ) ? $runner->setup( $batch, $hooks, $group, $force ) : 0;
			}
		} catch ( \Exception $e ) {
			$this->print_error( $e );
		}

		$this->print_total_batches( $batches_completed );
		$this->print_success( $actions_completed );
	}

	/**
	 * Print WP CLI message about how many actions are about to be processed.
	 *
	 * @author Jeremy Pry
	 *
	 * @param int $total
	 */
	protected function print_total_actions( $total ) {
		\WP_CLI::log(
			sprintf(
				/* translators: 1: refers to timestamp 2: refers to how many scheduled tasks were found to run */
				'%1$s' . _n( 'Found %s$d scheduled task', 'Found %2$d scheduled tasks', $total, 'action-scheduler' ),
				$this->output_timestamp(),
				number_format_i18n( $total )
			)
		);
	}

	/**
	 * Print WP CLI message about how many batches of actions were processed.
	 *
	 * @author Jeremy Pry
	 *
	 * @param int $batches_completed
	 */
	protected function print_total_batches( $batches_completed ) {
		\WP_CLI::log(
			sprintf(
				/* translators: 1: refers to timestamp 2: refers to the total number of batches executed */
				'%1$s' . _n( '%2$d batch executed.', '%2$d batches executed.', $batches_completed, 'action-scheduler' ),
				$this->output_timestamp(),
				number_format_i18n( $batches_completed )
			)
		);
	}

	/**
	 * Convert an exception into a WP CLI error.
	 *
	 * @author Jeremy Pry
	 *
	 * @param \Exception $e The error object.
	 *
	 * @throws \WP_CLI\ExitException
	 */
	protected function print_error( \Exception $e ) {
		\WP_CLI::error(
			sprintf(
				/* translators: 1: refers to timestamp 2: refers to the exception error message */
				'%1$s' . __( 'There was an error running the action scheduler: %2$s', 'action-scheduler' ),
				$this->output_timestamp(),
				$e->getMessage()
			)
		);
	}

	/**
	 * Print a success message with the number of completed actions.
	 *
	 * @author Jeremy Pry
	 *
	 * @param int $actions_completed
	 */
	protected function print_success( $actions_completed ) {
		\WP_CLI::success(
			sprintf(
				/* translators: 1: refers to timestamp 2: refers to the total number of taskes completed */
				'%1$s' . _n( '%2$d scheduled task completed.', '%2$d scheduled tasks completed.', $actions_completed, 'action-scheduler' ),
				$this->output_timestamp(),
				number_format_i18n( $actions_completed )
			)
		);
	}

	/**
	 * Print timestamp if enabled.
	 *
	 * @return string
	 */
	protected function output_timestamp() {
		if ( empty( $this->timestamp ) ) {
			return '';
		}

		return '[' . as_get_datetime_object()->format( $this->timestamp_format ) . '] ';
	}
}
