<?php

/**
 * Class ActionScheduler_QueueRunner_Test
 * @group runners
 */
class ActionScheduler_QueueRunner_Test extends ActionScheduler_UnitTestCase {
	public function test_create_runner() {
		$store = ActionScheduler::store();
		$runner = ActionScheduler_Mocker::get_queue_runner( $store );
		$actions_run = $runner->run();

		$this->assertEquals( 0, $actions_run );
	}

	public function test_run() {
		$store = ActionScheduler::store();
		$runner = ActionScheduler_Mocker::get_queue_runner( $store );

		$mock = new MockAction();
		$random = md5(rand());
		add_action( $random, array( $mock, 'action' ) );
		$schedule = new ActionScheduler_SimpleSchedule(as_get_datetime_object('1 day ago'));

		for ( $i = 0 ; $i < 5 ; $i++ ) {
			$action = new ActionScheduler_Action( $random, array($random), $schedule );
			$store->save_action( $action );
		}

		$actions_run = $runner->run();

		remove_action( $random, array( $mock, 'action' ) );

		$this->assertEquals( 5, $mock->get_call_count() );
		$this->assertEquals( 5, $actions_run );
	}

	public function test_run_with_future_actions() {
		$store = ActionScheduler::store();
		$runner = ActionScheduler_Mocker::get_queue_runner( $store );

		$mock = new MockAction();
		$random = md5(rand());
		add_action( $random, array( $mock, 'action' ) );
		$schedule = new ActionScheduler_SimpleSchedule(as_get_datetime_object('1 day ago'));

		for ( $i = 0 ; $i < 3 ; $i++ ) {
			$action = new ActionScheduler_Action( $random, array($random), $schedule );
			$store->save_action( $action );
		}

		$schedule = new ActionScheduler_SimpleSchedule(as_get_datetime_object('tomorrow'));
		for ( $i = 0 ; $i < 3 ; $i++ ) {
			$action = new ActionScheduler_Action( $random, array($random), $schedule );
			$store->save_action( $action );
		}

		$actions_run = $runner->run();

		remove_action( $random, array( $mock, 'action' ) );

		$this->assertEquals( 3, $mock->get_call_count() );
		$this->assertEquals( 3, $actions_run );
	}

	public function test_completed_action_status() {
		$store = ActionScheduler::store();
		$runner = ActionScheduler_Mocker::get_queue_runner( $store );

		$random = md5(rand());
		$schedule = new ActionScheduler_SimpleSchedule(as_get_datetime_object('12 hours ago'));

		$action = new ActionScheduler_Action( $random, array(), $schedule );
		$action_id = $store->save_action( $action );

		$runner->run();

		$finished_action = $store->fetch_action( $action_id );

		$this->assertTrue( $finished_action->is_finished() );
	}

	public function test_next_instance_of_cron_action() {
		// Create an action with daily Cron expression (i.e. midnight each day)
		$random    = md5( rand() );
		$action_id = ActionScheduler::factory()->cron( $random, array(), null, '0 0 * * *' );
		$store     = ActionScheduler::store();
		$runner    = ActionScheduler_Mocker::get_queue_runner( $store );

		// Make sure the 1st instance of the action is scheduled to occur tomorrow
		$date = as_get_datetime_object( 'tomorrow' );
		$date->modify( '-1 minute' );
		$claim = $store->stake_claim( 10, $date );
		$this->assertCount( 0, $claim->get_actions() );

		$store->release_claim( $claim );

		$date->modify( '+1 minute' );

		$claim = $store->stake_claim( 10, $date );
		$actions = $claim->get_actions();
		$this->assertCount( 1, $actions );

		$fetched_action_id = reset( $actions );
		$fetched_action    = $store->fetch_action( $fetched_action_id );

		$this->assertEquals( $fetched_action_id, $action_id );
		$this->assertEquals( $random, $fetched_action->get_hook() );
		$this->assertEquals( $date->getTimestamp(), $fetched_action->get_schedule()->get_date()->getTimestamp(), '', 1 );

		$store->release_claim( $claim );

		// Make sure the 2nd instance of the cron action is scheduled to occur tomorrow still
		$runner->process_action( $action_id );

		$claim = $store->stake_claim( 10, $date );
		$actions = $claim->get_actions();
		$this->assertCount( 1, $actions );

		$fetched_action_id = reset( $actions );
		$fetched_action    = $store->fetch_action( $fetched_action_id );

		$this->assertNotEquals( $fetched_action_id, $action_id );
		$this->assertEquals( $random, $fetched_action->get_hook() );
		$this->assertEquals( $date->getTimestamp(), $fetched_action->get_schedule()->get_date()->getTimestamp(), '', 1 );
	}

	public function test_next_instance_of_interval_action() {
		// Create an action to recur every 24 hours, with the first instance scheduled to run 12 hours ago
		$random    = md5( rand() );
		$date      = as_get_datetime_object( '12 hours ago' );
		$action_id = ActionScheduler::factory()->recurring( $random, array(), $date->getTimestamp(), DAY_IN_SECONDS );
		$store     = ActionScheduler::store();
		$runner    = ActionScheduler_Mocker::get_queue_runner( $store );

		// Make sure the 1st instance of the action is scheduled to occur 12 hours ago
		$claim = $store->stake_claim( 10, $date );
		$actions = $claim->get_actions();
		$this->assertCount( 1, $actions );

		$fetched_action_id = reset( $actions );
		$fetched_action    = $store->fetch_action( $fetched_action_id );

		$this->assertEquals( $fetched_action_id, $action_id );
		$this->assertEquals( $random, $fetched_action->get_hook() );
		$this->assertEquals( $date->getTimestamp(), $fetched_action->get_schedule()->get_date()->getTimestamp(), '', 1 );

		$store->release_claim( $claim );

		// Make sure after the queue is run, the 2nd instance of the action is scheduled to occur in 24 hours
		$runner->run();

		$date = as_get_datetime_object( '+1 day' );
		$claim = $store->stake_claim( 10, $date );
		$actions = $claim->get_actions();
		$this->assertCount( 1, $actions );

		$fetched_action_id = reset( $actions );
		$fetched_action    = $store->fetch_action( $fetched_action_id );

		$this->assertNotEquals( $fetched_action_id, $action_id );
		$this->assertEquals( $random, $fetched_action->get_hook() );
		$this->assertEquals( $date->getTimestamp(), $fetched_action->get_schedule()->get_date()->getTimestamp(), '', 1 );

		$store->release_claim( $claim );

		// Make sure the 3rd instance of the cron action is scheduled for 24 hours from now, as the action was run early, ahead of schedule
		$runner->process_action( $fetched_action_id );
		$date = as_get_datetime_object( '+1 day' );

		$claim = $store->stake_claim( 10, $date );
		$actions = $claim->get_actions();
		$this->assertCount( 1, $actions );

		$fetched_action_id = reset( $actions );
		$fetched_action    = $store->fetch_action( $fetched_action_id );

		$this->assertNotEquals( $fetched_action_id, $action_id );
		$this->assertEquals( $random, $fetched_action->get_hook() );
		$this->assertEquals( $date->getTimestamp(), $fetched_action->get_schedule()->get_date()->getTimestamp(), '', 1 );
	}

	/**
	 * As soon as one recurring action has been executed its replacement will be scheduled.
	 *
	 * This is true even if the current action fails. This makes sense, since a failure may be temporary in nature.
	 * However, if the same recurring action consistently fails then it is likely that there is a problem and we should
	 * stop creating new instances. This test outlines the expected behavior in this regard.
	 *
	 * @return void
	 */
	public function test_failing_recurring_actions_are_not_rescheduled_when_threshold_met() {
		$store           = ActionScheduler_Store::instance();
		$runner          = ActionScheduler_Mocker::get_queue_runner( $store );
		$created_actions = array();

		// Create 4 failed actions (one below the threshold of what counts as 'consistently failing').
		for ( $i = 0; $i < 3; $i++ ) {
			// We give each action a unique set of args, this illustrates that in the context of determining consistent
			// failure we care only about the hook and not other properties of the action.
			$args      = array( 'unique-' . $i => hash( 'md5', $i ) );
			$hook      = 'will-fail';
			$date      = as_get_datetime_object( 12 - $i . ' hours ago' );
			$action_id = as_schedule_recurring_action( $date->getTimestamp(), HOUR_IN_SECONDS, $hook, $args );
			$store->mark_failure( $action_id );
			$created_actions[] = $action_id;
		}

		// Now create a further action using the same hook, that is also destined to fail.
		$date              = as_get_datetime_object( '6 hours ago' );
		$pending_action_id = as_schedule_recurring_action( $date->getTimestamp(), HOUR_IN_SECONDS, $hook, $args );
		$created_actions[] = $pending_action_id;

		// Process the queue!
		$runner->run();
		$pending_actions = $store->query_actions(
			array(
				'hook'   => $hook,
				'args'   => $args,
				'status' => ActionScheduler_Store::STATUS_PENDING,
			)
		);
		$new_pending_action_id = current( $pending_actions );

		// We now have 5 instances of the same recurring action. 4 have already failed, one is pending.
		$this->assertCount( 1, $pending_actions, 'If the threshold for consistent failure has not been met, a replacement action should have been scheduled.' );
		$this->assertNotContains( $new_pending_action_id, $created_actions, 'Confirm that the replacement action is new, and not one of those we created manually earlier in the test.' );

		// Process the pending action (we do this directly instead of via `$runner->run()` because it won't actually
		// become due for another hour).
		$runner->process_action( $new_pending_action_id );
		$pending_actions = $store->query_actions(
			array(
				'hook'   => $hook,
				'args'   => $args,
				'status' => ActionScheduler_Store::STATUS_PENDING,
			)
		);

		// Now 5 instances of the same recurring action have all failed, therefore the threshold for consistent failure
		// has been met and, this time, a new action should *not* have been scheduled.
		$this->assertCount( 0, $pending_actions, 'The failure threshold (5 consecutive fails for recurring actions with the same signature) having been met, no further actions were scheduled.' );
	}

	public function test_hooked_into_wp_cron() {
		$next = wp_next_scheduled( ActionScheduler_QueueRunner::WP_CRON_HOOK, array( 'WP Cron' ) );
		$this->assertNotEmpty($next);
	}

	public function test_batch_count_limit() {
		$store = ActionScheduler::store();
		$runner = ActionScheduler_Mocker::get_queue_runner( $store );

		$mock = new MockAction();
		$random = md5(rand());
		add_action( $random, array( $mock, 'action' ) );
		$schedule = new ActionScheduler_SimpleSchedule(new ActionScheduler_DateTime('1 day ago'));

		for ( $i = 0 ; $i < 2 ; $i++ ) {
			$action = new ActionScheduler_Action( $random, array($random), $schedule );
			$store->save_action( $action );
		}

		$claim = $store->stake_claim();

		$actions_run = $runner->run();

		$this->assertEquals( 0, $mock->get_call_count() );
		$this->assertEquals( 0, $actions_run );

		$store->release_claim( $claim );

		$actions_run = $runner->run();

		$this->assertEquals( 2, $mock->get_call_count() );
		$this->assertEquals( 2, $actions_run );

		remove_action( $random, array( $mock, 'action' ) );
	}

	public function test_changing_batch_count_limit() {
		$store = ActionScheduler::store();
		$runner = ActionScheduler_Mocker::get_queue_runner( $store );

		$random = md5(rand());
		$schedule = new ActionScheduler_SimpleSchedule(new ActionScheduler_DateTime('1 day ago'));

		for ( $i = 0 ; $i < 30 ; $i++ ) {
			$action = new ActionScheduler_Action( $random, array($random), $schedule );
			$store->save_action( $action );
		}

		$claims = array();

		for ( $i = 0 ; $i < 5 ; $i++ ) {
			$claims[] = $store->stake_claim( 5 );
		}

		$mock1 = new MockAction();
		add_action( $random, array( $mock1, 'action' ) );
		$actions_run = $runner->run();
		remove_action( $random, array( $mock1, 'action' ) );

		$this->assertEquals( 0, $mock1->get_call_count() );
		$this->assertEquals( 0, $actions_run );


		add_filter( 'action_scheduler_queue_runner_concurrent_batches', array( $this, 'return_6' ) );

		$mock2 = new MockAction();
		add_action( $random, array( $mock2, 'action' ) );
		$actions_run = $runner->run();
		remove_action( $random, array( $mock2, 'action' ) );

		$this->assertEquals( 5, $mock2->get_call_count() );
		$this->assertEquals( 5, $actions_run );

		remove_filter( 'action_scheduler_queue_runner_concurrent_batches', array( $this, 'return_6' ) );

		for ( $i = 0 ; $i < 5 ; $i++ ) { // to make up for the actions we just processed
			$action = new ActionScheduler_Action( $random, array($random), $schedule );
			$store->save_action( $action );
		}

		$mock3 = new MockAction();
		add_action( $random, array( $mock3, 'action' ) );
		$actions_run = $runner->run();
		remove_action( $random, array( $mock3, 'action' ) );

		$this->assertEquals( 0, $mock3->get_call_count() );
		$this->assertEquals( 0, $actions_run );

		remove_filter( 'action_scheduler_queue_runner_concurrent_batches', array( $this, 'return_6' ) );
	}

	public function return_6() {
		return 6;
	}

	public function test_store_fetch_action_failure_schedule_next_instance() {
		$random    = md5( rand() );
		$schedule  = new ActionScheduler_IntervalSchedule( as_get_datetime_object( '12 hours ago' ), DAY_IN_SECONDS );
		$action    = new ActionScheduler_Action( $random, array(), $schedule );
		$action_id = ActionScheduler::store()->save_action( $action );

		// Set up a mock store that will throw an exception when fetching actions.
		$store = $this
					->getMockBuilder( 'ActionScheduler_wpPostStore' )
					->setMethods( array( 'fetch_action' ) )
					->getMock();
		$store
			->method( 'fetch_action' )
			->with( array( $action_id ) )
			->will( $this->throwException( new Exception() ) );

		// Set up a mock queue runner to verify that schedule_next_instance()
		// isn't called for an undefined $action.
		$runner = $this
					->getMockBuilder( 'ActionScheduler_QueueRunner' )
					->setConstructorArgs( array( $store ) )
					->setMethods( array( 'schedule_next_instance' ) )
					->getMock();
		$runner
			->expects( $this->never() )
			->method( 'schedule_next_instance' );

		$runner->run();

		// Set up a mock store that will throw an exception when fetching actions.
		$store2 = $this
					->getMockBuilder( 'ActionScheduler_wpPostStore' )
					->setMethods( array( 'fetch_action' ) )
					->getMock();
		$store2
			->method( 'fetch_action' )
			->with( array( $action_id ) )
			->willReturn( null );

		// Set up a mock queue runner to verify that schedule_next_instance()
		// isn't called for an undefined $action.
		$runner2 = $this
					->getMockBuilder( 'ActionScheduler_QueueRunner' )
					->setConstructorArgs( array( $store ) )
					->setMethods( array( 'schedule_next_instance' ) )
					->getMock();
		$runner2
			->expects( $this->never() )
			->method( 'schedule_next_instance' );

		$runner2->run();
	}
}
