<?php

/**
 * Class ActionScheduler_QueueCleaner
 */
class ActionScheduler_QueueCleaner {

	/**
	 * The batch size.
	 *
	 * @var int
	 */
	protected $batch_size;

	/**
	 * ActionScheduler_Store instance.
	 *
	 * @var ActionScheduler_Store
	 */
	private $store = null;

	/**
	 * 31 days in seconds.
	 *
	 * @var int
	 */
	private $month_in_seconds = 2678400;

	/**
	 * Default list of statuses purged by the cleaner process.
	 *
	 * @var string[]
	 */
	private $default_statuses_to_purge = array(
		ActionScheduler_Store::STATUS_COMPLETE,
		ActionScheduler_Store::STATUS_CANCELED,
	);

	/**
	 * ActionScheduler_QueueCleaner constructor.
	 *
	 * @param ActionScheduler_Store|null $store      The store instance.
	 * @param int                        $batch_size The batch size.
	 */
	public function __construct( ?ActionScheduler_Store $store = null, $batch_size = 20 ) {
		$this->store      = $store ? $store : ActionScheduler_Store::instance();
		$this->batch_size = $batch_size;
	}

	/**
	 * Default queue cleaner process used by queue runner.
	 *
	 * @since 3.9.4 by default, failed actions are removed after three months.
	 * @return array
	 */
	public function delete_old_actions() {
		/**
		 * Filter the minimum scheduled date age for action deletion.
		 *
		 * @param int $retention_period Minimum scheduled age in seconds of the actions to be deleted.
		 */
		$lifespan = apply_filters( 'action_scheduler_retention_period', $this->month_in_seconds );

		/**
		 * Set the retention period, in seconds, for actions with a status returned by the action_scheduler_default_cleaner_statuses filter.
		 *
		 * @param int $retention_period Retention period in seconds.
		 */
		$lifespan_default = max( 0, (int) apply_filters( 'action_scheduler_retention_period_by_default', $lifespan ) );
		$lifespan_default = $lifespan_default > 0 ? $lifespan_default : $this->month_in_seconds;

		/**
		 * Set the retention period in seconds for actions with a failed status. If the action_scheduler_default_cleaner_statuses filter includes
		 * a failed status, this filter result will be ignored, and the retention period for failed actions will match that of other statuses.
		 *
		 * @param int $retention_period Retention period in seconds.
		 */
		$lifespan_failed = max( 0, (int) apply_filters( 'action_scheduler_retention_period_for_failed', 3 * $this->month_in_seconds ) );
		// We considered 12-month, 3-month, and 1-month options for failed action retention and selected a 3-month period
		// to align with the quarterly accounting cycle. Store owners may adjust the retention period to achieve PCI DSS
		// compliance or to align with a different accounting cycle, as needed.

		try {
			$cutoff_failed  = as_get_datetime_object( $lifespan_failed . ' seconds ago' );
			$cutoff_default = as_get_datetime_object( $lifespan_default . ' seconds ago' );
		} catch ( Exception $e ) {
			_doing_it_wrong(
				__METHOD__,
				sprintf(
					/* Translators: %s is the exception message. */
					esc_html__( 'It was not possible to determine a valid cut-off time: %s.', 'action-scheduler' ),
					esc_html( $e->getMessage() )
				),
				'3.5.5'
			);

			return array();
		}

		/**
		 * Filter the statuses when cleaning the queue.
		 *
		 * @param string[] $default_statuses_to_purge Action statuses to clean.
		 */
		$statuses_to_purge = (array) apply_filters( 'action_scheduler_default_cleaner_statuses', $this->default_statuses_to_purge );

		$deleted_failed_entries = array();
		// Backward compatibility note: if store already purging the failed statuses, don't change the behaviour.
		if ( $lifespan_failed > 0 && ! in_array( ActionScheduler_Store::STATUS_FAILED, $statuses_to_purge, true ) ) {
			// Use a fixed default batch size to ensure that the cleanup of failed actions does not interfere with the regular cleanup.
			$deleted_failed_entries = $this->clean_actions( array( ActionScheduler_Store::STATUS_FAILED ), $cutoff_failed, 20 );
		}

		$deleted_entries = $this->clean_actions( $statuses_to_purge, $cutoff_default, $this->get_batch_size() );

		return array_merge( $deleted_failed_entries, $deleted_entries );
	}

	/**
	 * Delete selected actions limited by status and date.
	 *
	 * @param string[] $statuses_to_purge List of action statuses to purge. Defaults to canceled, complete.
	 * @param DateTime $cutoff_date Date limit for selecting actions. Defaults to 31 days ago.
	 * @param int|null $batch_size Maximum number of actions per status to delete. Defaults to 20.
	 * @param string   $context Calling process context. Defaults to `old`.
	 * @return array Actions deleted.
	 */
	public function clean_actions( array $statuses_to_purge, DateTime $cutoff_date, $batch_size = null, $context = 'old' ) {
		$batch_size = ! is_null( $batch_size ) ? $batch_size : $this->batch_size;
		$cutoff     = ! is_null( $cutoff_date ) ? $cutoff_date : as_get_datetime_object( $this->month_in_seconds . ' seconds ago' );
		$lifespan   = time() - $cutoff->getTimestamp();

		if ( empty( $statuses_to_purge ) ) {
			$statuses_to_purge = $this->default_statuses_to_purge;
		}

		$deleted_actions = array();
		foreach ( $statuses_to_purge as $status ) {
			$actions_to_delete = $this->store->query_actions(
				array(
					'status'           => $status,
					'modified'         => $cutoff,
					'modified_compare' => '<=',
					'per_page'         => $batch_size,
					'orderby'          => 'none',
				)
			);
			$deleted_actions[] = $this->delete_actions( $actions_to_delete, $lifespan, $context );
		}

		return array_merge( array(), ...$deleted_actions );
	}

	/**
	 * Delete actions.
	 *
	 * @param int[]  $actions_to_delete List of action IDs to delete.
	 * @param int    $lifespan Minimum scheduled age in seconds of the actions being deleted.
	 * @param string $context Context of the delete request.
	 * @return array Deleted action IDs.
	 */
	private function delete_actions( array $actions_to_delete, $lifespan = null, $context = 'old' ) {
		$deleted_actions = array();

		if ( is_null( $lifespan ) ) {
			$lifespan = $this->month_in_seconds;
		}

		foreach ( $actions_to_delete as $action_id ) {
			try {
				$this->store->delete_action( $action_id );
				$deleted_actions[] = $action_id;
			} catch ( Exception $e ) {
				/**
				 * Notify 3rd party code of exceptions when deleting a completed action older than the retention period
				 *
				 * This hook provides a way for 3rd party code to log or otherwise handle exceptions relating to their
				 * actions.
				 *
				 * @param int $action_id The scheduled actions ID in the data store
				 * @param Exception $e The exception thrown when attempting to delete the action from the data store
				 * @param int $lifespan The retention period, in seconds, for old actions
				 * @param int $count_of_actions_to_delete The number of old actions being deleted in this batch
				 * @since 2.0.0
				 */
				do_action( "action_scheduler_failed_{$context}_action_deletion", $action_id, $e, $lifespan, count( $actions_to_delete ) );
			}
		}
		return $deleted_actions;
	}

	/**
	 * Unclaim pending actions that have not been run within a given time limit.
	 *
	 * When called by ActionScheduler_Abstract_QueueRunner::run_cleanup(), the time limit passed
	 * as a parameter is 10x the time limit used for queue processing.
	 *
	 * @param int $time_limit The number of seconds to allow a queue to run before unclaiming its pending actions. Default 300 (5 minutes).
	 */
	public function reset_timeouts( $time_limit = 300 ) {
		$timeout = apply_filters( 'action_scheduler_timeout_period', $time_limit );

		if ( $timeout < 0 ) {
			return;
		}

		$cutoff           = as_get_datetime_object( $timeout . ' seconds ago' );
		$actions_to_reset = $this->store->query_actions(
			array(
				'status'           => ActionScheduler_Store::STATUS_PENDING,
				'modified'         => $cutoff,
				'modified_compare' => '<=',
				'claimed'          => true,
				'per_page'         => $this->get_batch_size(),
				'orderby'          => 'none',
			)
		);

		foreach ( $actions_to_reset as $action_id ) {
			$this->store->unclaim_action( $action_id );
			do_action( 'action_scheduler_reset_action', $action_id );
		}
	}

	/**
	 * Mark actions that have been running for more than a given time limit as failed, based on
	 * the assumption some uncatchable and unloggable fatal error occurred during processing.
	 *
	 * When called by ActionScheduler_Abstract_QueueRunner::run_cleanup(), the time limit passed
	 * as a parameter is 10x the time limit used for queue processing.
	 *
	 * @param int $time_limit The number of seconds to allow an action to run before it is considered to have failed. Default 300 (5 minutes).
	 */
	public function mark_failures( $time_limit = 300 ) {
		$timeout = apply_filters( 'action_scheduler_failure_period', $time_limit );

		if ( $timeout < 0 ) {
			return;
		}

		$cutoff           = as_get_datetime_object( $timeout . ' seconds ago' );
		$actions_to_reset = $this->store->query_actions(
			array(
				'status'           => ActionScheduler_Store::STATUS_RUNNING,
				'modified'         => $cutoff,
				'modified_compare' => '<=',
				'per_page'         => $this->get_batch_size(),
				'orderby'          => 'none',
			)
		);

		foreach ( $actions_to_reset as $action_id ) {
			$this->store->mark_failure( $action_id );
			do_action( 'action_scheduler_failed_action', $action_id, $timeout );
		}
	}

	/**
	 * Do all of the cleaning actions.
	 *
	 * @param int $time_limit The number of seconds to use as the timeout and failure period. Default 300 (5 minutes).
	 */
	public function clean( $time_limit = 300 ) {
		$this->delete_old_actions();
		$this->reset_timeouts( $time_limit );
		$this->mark_failures( $time_limit );
	}

	/**
	 * Get the batch size for cleaning the queue.
	 *
	 * @return int
	 */
	protected function get_batch_size() {
		/**
		 * Filter the batch size when cleaning the queue.
		 *
		 * @param int $batch_size The number of actions to clean in one batch.
		 */
		return absint( apply_filters( 'action_scheduler_cleanup_batch_size', $this->batch_size ) );
	}
}
