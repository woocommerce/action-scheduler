<?php

/**
 * Class ActionScheduler_QueueCleaner_Test
 */
class ActionScheduler_QueueCleaner_Test extends ActionScheduler_UnitTestCase {

	public function test_delete_old_actions() {
		$store    = ActionScheduler::store();
		$runner   = ActionScheduler_Mocker::get_queue_runner( $store );
		$random   = md5( wp_rand() );
		$schedule = new ActionScheduler_SimpleSchedule( as_get_datetime_object( '1 day ago' ) );

		$created_actions = array();
		for ( $i = 0; $i < 5; $i++ ) {
			$action            = new ActionScheduler_Action( ActionScheduler_Callbacks::HOOK_WITH_CALLBACK, array( $random ), $schedule );
			$created_actions[] = $store->save_action( $action );
		}

		$runner->run();

		add_filter( 'action_scheduler_retention_period', '__return_zero' ); // delete any finished job.
		$cleaner = new ActionScheduler_QueueCleaner( $store );
		$cleaned = $cleaner->delete_old_actions();
		remove_filter( 'action_scheduler_retention_period', '__return_zero' );

		$this->assertIsArray( $cleaned, 'ActionScheduler_QueueCleaner::delete_old_actions() returns an array.' );
		$this->assertCount( 5, $cleaned, 'ActionScheduler_QueueCleaner::delete_old_actions() deleted the expected number of actions.' );

		foreach ( $created_actions as $action_id ) {
			$action = $store->fetch_action( $action_id );
			$this->assertFalse( $action->is_finished() ); // it's a NullAction.
		}
	}

	public function test_invalid_retention_period_filter_hook() {
		// Non-integer inputs are managed through type casting and range checking.
		add_filter( 'action_scheduler_retention_period', '__return_null' );
		$cleaner = new ActionScheduler_QueueCleaner( ActionScheduler::store() );
		$result  = $cleaner->delete_old_actions();
		remove_filter( 'action_scheduler_retention_period', '__return_null' );

		$this->assertIsArray(
			$result,
			'ActionScheduler_QueueCleaner::delete_old_actions() can be invoked without a fatal error, even if the retention period was invalid.'
		);

		$this->assertCount(
			0,
			$result,
			'ActionScheduler_QueueCleaner::delete_old_actions() will not delete any actions if the retention period was invalid.'
		);
	}

	public function test_delete_canceled_actions() {
		$store = ActionScheduler::store();

		$random   = md5( wp_rand() );
		$schedule = new ActionScheduler_SimpleSchedule( as_get_datetime_object( '1 day ago' ) );

		$created_actions = array();
		for ( $i = 0; $i < 5; $i++ ) {
			$action    = new ActionScheduler_Action( $random, array( $random ), $schedule );
			$action_id = $store->save_action( $action );
			$store->cancel_action( $action_id );
			$created_actions[] = $action_id;
		}

		// track the actions that are deleted.
		$mock_action = new MockAction();
		add_action( 'action_scheduler_deleted_action', array( $mock_action, 'action' ), 10, 1 );
		add_filter( 'action_scheduler_retention_period', '__return_zero' ); // delete any finished job.

		$cleaner = new ActionScheduler_QueueCleaner( $store );
		$cleaner->delete_old_actions();

		remove_filter( 'action_scheduler_retention_period', '__return_zero' );
		remove_action( 'action_scheduler_deleted_action', array( $mock_action, 'action' ), 10 );

		$deleted_actions = array();
		foreach ( $mock_action->get_args() as $action ) {
			$deleted_actions[] = reset( $action );
		}

		$this->assertEqualSets( $created_actions, $deleted_actions );
	}

	public function test_do_not_delete_recent_actions() {
		$store    = ActionScheduler::store();
		$runner   = ActionScheduler_Mocker::get_queue_runner( $store );
		$random   = md5( wp_rand() );
		$schedule = new ActionScheduler_SimpleSchedule( as_get_datetime_object( '1 day ago' ) );

		$created_actions = array();
		for ( $i = 0; $i < 5; $i++ ) {
			$action            = new ActionScheduler_Action( $random, array( $random ), $schedule );
			$created_actions[] = $store->save_action( $action );
		}

		$runner->run();

		$cleaner = new ActionScheduler_QueueCleaner( $store );
		$cleaner->delete_old_actions();

		foreach ( $created_actions as $action_id ) {
			$action = $store->fetch_action( $action_id );
			$this->assertTrue( $action->is_finished() ); // It's a FinishedAction.
		}
	}

	public function test_reset_unrun_actions() {
		$store = ActionScheduler::store();

		$random   = md5( wp_rand() );
		$schedule = new ActionScheduler_SimpleSchedule( as_get_datetime_object( '1 day ago' ) );

		$created_actions = array();
		for ( $i = 0; $i < 5; $i++ ) {
			$action            = new ActionScheduler_Action( $random, array( $random ), $schedule );
			$created_actions[] = $store->save_action( $action );
		}

		$store->stake_claim( 10 );

		// don't actually process the jobs, to simulate a request that timed out.

		add_filter( 'action_scheduler_timeout_period', '__return_zero' ); // delete any finished job.
		$cleaner = new ActionScheduler_QueueCleaner( $store );
		$cleaner->reset_timeouts();

		remove_filter( 'action_scheduler_timeout_period', '__return_zero' );

		$claim = $store->stake_claim( 10 );
		$this->assertEqualSets( $created_actions, $claim->get_actions() );
	}

	public function test_do_not_reset_failed_action() {
		$store    = ActionScheduler::store();
		$random   = md5( wp_rand() );
		$schedule = new ActionScheduler_SimpleSchedule( as_get_datetime_object( '1 day ago' ) );

		$created_actions = array();
		for ( $i = 0; $i < 5; $i++ ) {
			$action            = new ActionScheduler_Action( $random, array( $random ), $schedule );
			$created_actions[] = $store->save_action( $action );
		}

		$claim = $store->stake_claim( 10 );
		foreach ( $claim->get_actions() as $action_id ) {
			// simulate the first action interrupted by an uncatchable fatal error.
			$store->log_execution( $action_id );
			break;
		}

		add_filter( 'action_scheduler_timeout_period', '__return_zero' ); // delete any finished job.
		$cleaner = new ActionScheduler_QueueCleaner( $store );
		$cleaner->reset_timeouts();
		remove_filter( 'action_scheduler_timeout_period', '__return_zero' );

		$new_claim = $store->stake_claim( 10 );
		$this->assertCount( 4, $new_claim->get_actions() );

		add_filter( 'action_scheduler_failure_period', '__return_zero' );
		$cleaner->mark_failures();
		remove_filter( 'action_scheduler_failure_period', '__return_zero' );

		$failed = $store->query_actions( array( 'status' => ActionScheduler_Store::STATUS_FAILED ) );
		$this->assertEquals( $created_actions[0], $failed[0] );
		$this->assertCount( 1, $failed );
	}

	/**
	 * Ensures deleting old actions in stock state handles failed actions as well.
	 *
	 * @return void
	 */
	public function test_delete_old_failed_actions_separately_by_default() {
		$cleaner = $this->getMockBuilder( ActionScheduler_QueueCleaner::class )
			->setConstructorArgs( array() )
			->setMethodsExcept( array( 'delete_old_actions', 'get_batch_size' ) )
			->getMock();
		$cleaner->expects( $this->exactly( 2 ) )
			->method( 'clean_actions' )
			->withConsecutive(
				array( array( ActionScheduler_Store::STATUS_FAILED ), $this->anything(), 20 ),
				array( array( ActionScheduler_Store::STATUS_COMPLETE, ActionScheduler_Store::STATUS_CANCELED ), $this->anything(), 20 )
			)
			->willReturnOnConsecutiveCalls( array( '...', '...', '...', '...', '...', '...' ), array( '-' ) );

		$deleted = $cleaner->delete_old_actions();

		$this->assertSame( array( '...', '...', '...', '...', '...', '...', '-' ), $deleted );
	}

	/**
	 * Ensures deleting old actions handles failed actions in backward compatible way when the failed status is
	 * injected 'via action_scheduler_default_cleaner_statuses' and not processed separately compared to the stock state.
	 *
	 * @return void
	 */
	public function test_delete_old_failed_actions_with_other_statuses() {
		$filter = function () {
			return array( ActionScheduler_Store::STATUS_COMPLETE, ActionScheduler_Store::STATUS_FAILED );
		};
		add_filter( 'action_scheduler_default_cleaner_statuses', $filter );

		$cleaner = $this->getMockBuilder( ActionScheduler_QueueCleaner::class )
			->setConstructorArgs( array() )
			->setMethodsExcept( array( 'delete_old_actions', 'get_batch_size' ) )
			->getMock();
		$cleaner->expects( $this->once() )
			->method( 'clean_actions' )
			->with( array( ActionScheduler_Store::STATUS_COMPLETE, ActionScheduler_Store::STATUS_FAILED ), $this->anything(), 20 )
			->willReturn( array( '...', '...' ) );

		$deleted = $cleaner->delete_old_actions();

		$this->assertSame( array( '...', '...' ), $deleted );

		remove_filter( 'action_scheduler_default_cleaner_statuses', $filter );
	}

	/**
	 * Ensures failed action cleanup can be turned off entirely via the dedicated filter.
	 *
	 * @return void
	 */
	public function test_failed_cleanup_can_be_disabled() {
		add_filter( 'action_scheduler_enable_failed_action_cleanup', '__return_false' );

		$cleaner = $this->getMockBuilder( ActionScheduler_QueueCleaner::class )
			->setConstructorArgs( array() )
			->setMethodsExcept( array( 'delete_old_actions', 'get_batch_size' ) )
			->getMock();
		$cleaner->expects( $this->once() )
			->method( 'clean_actions' )
			->with( array( ActionScheduler_Store::STATUS_COMPLETE, ActionScheduler_Store::STATUS_CANCELED ), $this->anything(), 20 )
			->willReturn( array( 'x' ) );

		$deleted = $cleaner->delete_old_actions();

		remove_filter( 'action_scheduler_enable_failed_action_cleanup', '__return_false' );

		$this->assertSame( array( 'x' ), $deleted );
	}

	/**
	 * Ensures an empty default cleaner status list stops completed/canceled actions from being purged.
	 *
	 * @return void
	 */
	public function test_empty_cleaner_statuses_skips_default_purge() {
		$filter = function () {
			return array();
		};
		add_filter( 'action_scheduler_default_cleaner_statuses', $filter );

		$cleaner = $this->getMockBuilder( ActionScheduler_QueueCleaner::class )
			->setConstructorArgs( array() )
			->setMethodsExcept( array( 'delete_old_actions', 'get_batch_size' ) )
			->getMock();
		$cleaner->expects( $this->once() )
			->method( 'clean_actions' )
			->with( array( ActionScheduler_Store::STATUS_FAILED ), $this->anything(), 20 )
			->willReturn( array( 'f' ) );

		$deleted = $cleaner->delete_old_actions();

		remove_filter( 'action_scheduler_default_cleaner_statuses', $filter );

		$this->assertSame( array( 'f' ), $deleted );
	}

	/**
	 * Ensures a non-array return (e.g. a filter that forgot to return) falls back to the default statuses
	 * rather than disabling the purge the way an explicit empty array does.
	 *
	 * @return void
	 */
	public function test_null_cleaner_statuses_falls_back_to_defaults() {
		$filter = function () {
			return null;
		};
		add_filter( 'action_scheduler_default_cleaner_statuses', $filter );

		$cleaner = $this->getMockBuilder( ActionScheduler_QueueCleaner::class )
			->setConstructorArgs( array() )
			->setMethodsExcept( array( 'delete_old_actions', 'get_batch_size' ) )
			->getMock();
		$cleaner->expects( $this->exactly( 2 ) )
			->method( 'clean_actions' )
			->withConsecutive(
				array( array( ActionScheduler_Store::STATUS_FAILED ), $this->anything(), 20 ),
				array( array( ActionScheduler_Store::STATUS_COMPLETE, ActionScheduler_Store::STATUS_CANCELED ), $this->anything(), 20 )
			)
			->willReturnOnConsecutiveCalls( array( 'f' ), array( 'd' ) );

		$deleted = $cleaner->delete_old_actions();

		remove_filter( 'action_scheduler_default_cleaner_statuses', $filter );

		$this->assertSame( array( 'f', 'd' ), $deleted );
	}

	/**
	 * Ensures a zero failed retention period purges immediately rather than disabling the cleanup.
	 *
	 * @return void
	 */
	public function test_failed_retention_zero_purges_immediately() {
		add_filter( 'action_scheduler_retention_period_for_failed', '__return_zero' );

		$cutoff  = $this->capture_failed_cutoff();
		$cleaner = $cutoff['cleaner'];
		$cleaner->delete_old_actions();

		remove_filter( 'action_scheduler_retention_period_for_failed', '__return_zero' );

		$this->assertInstanceOf( DateTime::class, $cutoff['captured'](), 'Failed actions are still purged when the retention period is zero.' );
		$this->assertLessThanOrEqual( 5, abs( time() - $cutoff['captured']()->getTimestamp() ), 'A zero retention period purges failed actions as of now.' );
	}

	/**
	 * Ensures a negative failed retention period is clamped to zero (immediate) rather than a future cutoff.
	 *
	 * @return void
	 */
	public function test_negative_failed_retention_clamps_to_immediate() {
		$negative = function () {
			return -DAY_IN_SECONDS;
		};
		add_filter( 'action_scheduler_retention_period_for_failed', $negative );

		$cutoff  = $this->capture_failed_cutoff();
		$cleaner = $cutoff['cleaner'];
		$cleaner->delete_old_actions();

		remove_filter( 'action_scheduler_retention_period_for_failed', $negative );

		$this->assertInstanceOf( DateTime::class, $cutoff['captured'](), 'A negative retention period still purges failed actions.' );
		$this->assertLessThanOrEqual( 5, abs( time() - $cutoff['captured']()->getTimestamp() ), 'A negative retention period is clamped to now, not a future cutoff.' );
	}

	/**
	 * Build a cleaner whose clean_actions() records the cutoff used for the failed status.
	 *
	 * @return array{cleaner: ActionScheduler_QueueCleaner, captured: callable}
	 */
	private function capture_failed_cutoff() {
		$captured = null;

		$cleaner = $this->getMockBuilder( ActionScheduler_QueueCleaner::class )
			->setConstructorArgs( array() )
			->setMethodsExcept( array( 'delete_old_actions', 'get_batch_size' ) )
			->getMock();
		$cleaner->method( 'clean_actions' )
			->willReturnCallback(
				function ( $statuses, $cutoff ) use ( &$captured ) {
					if ( array( ActionScheduler_Store::STATUS_FAILED ) === $statuses ) {
						$captured = $cutoff;
					}
					return array();
				}
			);

		return array(
			'cleaner'  => $cleaner,
			'captured' => function () use ( &$captured ) {
				return $captured;
			},
		);
	}

	/**
	 * Verify that custom cleaners perform direct cleanup rather than task-based cleanup.
	 */
	public function test_custom_cleaner_performs_cleanup_in_queue_run_only() {
		$cleaner = $this->getMockBuilder( ActionScheduler_QueueCleaner::class )->disableOriginalConstructor()->getMock();
		$cleaner->expects( $this->never() )->method( 'register_cleaner_hooks' );
		$cleaner->expects( $this->once() )->method( 'clean' );

		$async_runner = $this->getMockBuilder( ActionScheduler_AsyncRequest_QueueRunner::class )->disableOriginalConstructor()->getMock();
		$runner       = new ActionScheduler_QueueRunner( ActionScheduler::store(), null, $cleaner, $async_runner );

		$runner->init();

		// Verify that custom cleaners perform direct cleanup rather than task-based cleanup.
		$this->assertFalse( has_action( 'action_scheduler_run_actions_cleanup_hook', array( $cleaner, 'delete_old_actions' ) ) );
		$this->assertFalse( has_action( 'action_scheduler_ensure_recurring_actions', array( $cleaner, 'register_recurring_actions' ) ) );

		$runner->run();
	}

	/**
	 * Verify that the default cleaner is limited to task-based cleanup.
	 */
	public function test_standard_cleaner_splits_cleanup_between_queue_and_action() {
		$store = $this->getMockBuilder( ActionScheduler_Store::class )->disableOriginalConstructor()->getMock();
		$store->expects( $this->exactly( 2 ) )
			->method( 'query_actions' )
			->with(
				$this->callback(
					static function( $query ) {
						// These statuses are relevant for releasing stale claims during queue processing.
						return ActionScheduler_Store::STATUS_PENDING === $query['status'] || ActionScheduler_Store::STATUS_RUNNING === $query['status'];
					}
				)
			)
			->willReturn( array() );
		$store->expects( $this->once() )->method( 'stake_claim' )->willReturn( new ActionScheduler_ActionClaim( 1, array() ) );

		$cleaner      = new ActionScheduler_QueueCleaner( $store );
		$async_runner = $this->getMockBuilder( ActionScheduler_AsyncRequest_QueueRunner::class )->disableOriginalConstructor()->getMock();
		$runner       = new ActionScheduler_QueueRunner( $store, null, $cleaner, $async_runner );

		$runner->init();

		// Verify that the default cleaner performs task-based cleanup rather than direct cleanup.
		$this->assertNotFalse( has_action( 'action_scheduler_run_actions_cleanup_hook', array( $cleaner, 'delete_old_actions' ) ) );
		$this->assertNotFalse( has_action( 'action_scheduler_ensure_recurring_actions', array( $cleaner, 'register_recurring_actions' ) ) );

		do_action( 'action_scheduler_ensure_recurring_actions' );
		$runner->run();
	}

	/**
	 * Verify that cleanup was executed as it does during the queue run, confirming that throughput optimization was bypassed.
	 */
	public function test_clean_actions_behaviour_as_cleanup_in_queue_run() {
		$store = $this->getMockBuilder( ActionScheduler_Store::class )->disableOriginalConstructor()->getMock();
		$store->expects( $this->exactly( 3 ) )
			->method( 'query_actions' )
			->withConsecutive(
				array(
					$this->callback(
						static function( $query ) {
							return ActionScheduler_Store::STATUS_FAILED === $query['status'] && 20 === $query['per_page'];
						}
					),
				),
				array(
					$this->callback(
						static function( $query ) {
							return ActionScheduler_Store::STATUS_COMPLETE === $query['status'] && 20 === $query['per_page'];
						}
					),
				),
				array(
					$this->callback(
						static function( $query ) {
							return ActionScheduler_Store::STATUS_CANCELED === $query['status'] && 20 === $query['per_page'];
						}
					),
				)
			)
			->willReturnOnConsecutiveCalls(
				array( 1 ),
				array( 2 ),
				array( 3 )
			);
		$store->expects( $this->exactly( 3 ) )
			->method( 'delete_action' )
			->withConsecutive(
				array( 1 ),
				array( 2 ),
				array( 3 )
			);

		// Verify that cleanup was executed as it does during the queue run, confirming that throughput optimization was bypassed.
		$cleaner = new ActionScheduler_QueueCleaner( $store );
		$cleaner->clean_actions(
			array( ActionScheduler_Store::STATUS_FAILED, ActionScheduler_Store::STATUS_COMPLETE, ActionScheduler_Store::STATUS_CANCELED ),
			as_get_datetime_object( '0 seconds ago' )
		);
	}

	/**
	 * Verify that cleanup was executed during the scheduled task, confirming that throughput optimization was applied.
	 */
	public function test_clean_actions_behaviour_as_scheduled_action_leverages_execution_budget() {
		$store = $this->getMockBuilder( ActionScheduler_Store::class )->disableOriginalConstructor()->getMock();
		$store->expects( $this->exactly( 3 ) )
			->method( 'query_actions' )
			->withConsecutive(
				array(
					$this->callback(
						static function( $query ) {
							return ActionScheduler_Store::STATUS_CANCELED === $query['status'] && 250 === $query['per_page'];
						}
					),
				),
				array(
					$this->callback(
						static function( $query ) {
							return ActionScheduler_Store::STATUS_FAILED === $query['status'] && 499 === $query['per_page'];
						}
					),
				),
				array(
					$this->callback(
						static function( $query ) {
							return ActionScheduler_Store::STATUS_COMPLETE === $query['status'] && 748 === $query['per_page'];
						}
					),
				)
			)
			->willReturnOnConsecutiveCalls(
				array( 1 ),
				array( 2 ),
				array( 3 )
			);
		$store->expects( $this->exactly( 3 ) )
			->method( 'delete_action' )
			->withConsecutive(
				array( 1 ),
				array( 2 ),
				array( 3 )
			);

		$filter_statuses = function () {
			return array( ActionScheduler_Store::STATUS_FAILED, ActionScheduler_Store::STATUS_COMPLETE, ActionScheduler_Store::STATUS_CANCELED );
		};
		add_filter( 'action_scheduler_default_cleaner_statuses', $filter_statuses );
		$trail              = 0;
		$filter_as_schedule = function ( $pre_option, $timestamp, $hook, $args, $group, $priority, $unique ) use ( &$trail ) {
			$trail += (int) ( 'action_scheduler_run_actions_cleanup_hook' === $hook );
			return $pre_option;
		};
		add_filter( 'pre_as_schedule_single_action', $filter_as_schedule, 10, 7 );

		// Verify that cleanup was executed during the scheduled task, confirming that throughput optimization was applied.
		$cleaner = new ActionScheduler_QueueCleaner( $store );
		$cleaner->register_cleaner_hooks();
		do_action( 'action_scheduler_run_actions_cleanup_hook' );

		$this->assertSame( 0, (int) $trail );

		remove_filter( 'action_scheduler_default_cleaner_statuses', $filter_statuses );
		remove_filter( 'pre_as_schedule_single_action', $filter_as_schedule );
		remove_action( 'action_scheduler_run_actions_cleanup_hook', array( $cleaner, 'delete_old_actions' ) );
	}

	/**
	 * Verify the follow-up cleanup is scheduled on the continuation hook with unique=true.
	 */
	public function test_clean_actions_behaviour_as_scheduled_action_spawns_trailing_action() {
		$store = $this->getMockBuilder( ActionScheduler_Store::class )->disableOriginalConstructor()->getMock();
		$store->expects( $this->once() )
			->method( 'query_actions' )
			->with(
				$this->callback(
					static function( $query ) {
						return ActionScheduler_Store::STATUS_FAILED === $query['status'] && 250 === $query['per_page'];
					}
				)
			)
			->willReturn( array_fill( 0, 250, 1 ) );
		$store->expects( $this->exactly( 250 ) )
			->method( 'delete_action' )
			->with( 1 );

		$filter_statuses = function () {
			return array( ActionScheduler_Store::STATUS_FAILED );
		};
		add_filter( 'action_scheduler_default_cleaner_statuses', $filter_statuses );
		$continuation_scheduled  = 0;
		$continuation_was_unique = false;
		$filter_as_schedule      = function ( $pre_option, $timestamp, $hook, $args, $group, $priority, $unique ) use ( &$continuation_scheduled, &$continuation_was_unique ) {
			if ( 'action_scheduler_continue_actions_cleanup_hook' === $hook ) {
				$continuation_scheduled++;
				$continuation_was_unique = (bool) $unique;
			}
			return $pre_option;
		};
		add_filter( 'pre_as_schedule_single_action', $filter_as_schedule, 10, 7 );

		$cleaner = new ActionScheduler_QueueCleaner( $store );
		$cleaner->register_cleaner_hooks();
		do_action( 'action_scheduler_run_actions_cleanup_hook' );

		$this->assertSame( 1, $continuation_scheduled );
		$this->assertTrue( $continuation_was_unique );

		remove_filter( 'action_scheduler_default_cleaner_statuses', $filter_statuses );
		remove_filter( 'pre_as_schedule_single_action', $filter_as_schedule );
		remove_action( 'action_scheduler_run_actions_cleanup_hook', array( $cleaner, 'delete_old_actions' ) );
		remove_action( 'action_scheduler_continue_actions_cleanup_hook', array( $cleaner, 'delete_old_actions' ) );
	}
}
