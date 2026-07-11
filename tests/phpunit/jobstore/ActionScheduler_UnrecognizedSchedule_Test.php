<?php

/**
 * Tests for how the queue runner handles an action whose stored schedule references an unrecognized
 * class: it is marked failed (for operator review) rather than cancelled, and a forced run executes
 * its callback regardless of the unreadable schedule.
 *
 * @see https://github.com/woocommerce/action-scheduler/issues/1318
 *
 * @group tables
 */
class ActionScheduler_UnrecognizedSchedule_Test extends ActionScheduler_UnitTestCase {

	public function setUp(): void {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->actionscheduler_actions}" );
		parent::setUp();
	}

	/**
	 * Persist a real, due, pending action, then overwrite its stored schedule with a blob that is a
	 * valid outer schedule nesting an unrecognized class — which the deserializer resolves to an
	 * ActionScheduler_UnrecognizedSchedule.
	 *
	 * @param string $hook Hook to schedule.
	 * @return int Action ID.
	 */
	private function store_due_action_with_unrecognized_schedule( $hook ) {
		global $wpdb;

		$store     = new ActionScheduler_DBStore();
		$action    = new ActionScheduler_Action( $hook, array(), new ActionScheduler_SimpleSchedule( as_get_datetime_object( '1 hour ago' ) ) );
		$action_id = $store->save_action( $action );

		// A valid third party schedule (a placeholder in the safe parse, so nothing is instantiated)
		// whose property holds a class Action Scheduler does not recognize.
		$nul       = chr( 0 );
		$prop      = $nul . '*' . $nul . 'timestamp';
		$container = 'ActionScheduler_Test_Custom_Schedule';
		$unknown   = 'O:14:"Some_Unknown_X":0:{}';
		$blob      = 'O:' . strlen( $container ) . ':"' . $container . '":1:{s:' . strlen( $prop ) . ':"' . $prop . '";' . $unknown . '}';

		$wpdb->update(
			$wpdb->actionscheduler_actions,
			array( 'schedule' => $blob ),
			array( 'action_id' => $action_id ),
			array( '%s' ),
			array( '%d' )
		);

		return $action_id;
	}

	/**
	 * The deserializer resolves the tampered schedule to the placeholder, so the action fetches as a
	 * normal (displayable) action rather than a null action.
	 */
	public function test_action_fetches_with_unrecognized_schedule_placeholder() {
		$store     = new ActionScheduler_DBStore();
		$action_id = $this->store_due_action_with_unrecognized_schedule( 'as_test_unrecognized_fetch' );

		$fetched = $store->fetch_action( $action_id );

		$this->assertNotInstanceOf( 'ActionScheduler_NullAction', $fetched );
		$this->assertInstanceOf( 'ActionScheduler_UnrecognizedSchedule', $fetched->get_schedule() );
	}

	/**
	 * Automatic processing must mark an unrecognized-schedule action failed (for review) rather than
	 * running it or cancelling it.
	 */
	public function test_unrecognized_schedule_action_is_failed_not_run_or_cancelled() {
		$hook = 'as_test_unrecognized_auto';
		$ran  = 0;
		add_action( $hook, function () use ( &$ran ) { ++$ran; } );

		$store     = new ActionScheduler_DBStore();
		$runner    = ActionScheduler_Mocker::get_queue_runner( $store );
		$action_id = $this->store_due_action_with_unrecognized_schedule( $hook );

		$runner->process_action( $action_id );

		$this->assertSame( ActionScheduler_Store::STATUS_FAILED, $store->get_status( $action_id ), 'The action should be failed, not cancelled or run.' );
		$this->assertSame( 0, $ran, 'An unrecognized-schedule action must not run automatically.' );

		remove_all_actions( $hook );
	}

	/**
	 * A forced run (as a site operator would trigger from the admin list table) executes the action's
	 * callback despite the unreadable schedule, flushing the work, and completes the action.
	 */
	public function test_forced_run_executes_unrecognized_schedule_action() {
		$hook = 'as_test_unrecognized_forced';
		$ran  = 0;
		add_action( $hook, function () use ( &$ran ) { ++$ran; } );

		$store     = new ActionScheduler_DBStore();
		$runner    = ActionScheduler_Mocker::get_queue_runner( $store );
		$action_id = $this->store_due_action_with_unrecognized_schedule( $hook );

		// First, automatic processing marks it failed.
		$runner->process_action( $action_id );
		$this->assertSame( ActionScheduler_Store::STATUS_FAILED, $store->get_status( $action_id ) );

		// Then a forced run executes the callback and completes it.
		$runner->force_run_action( $action_id, 'Test' );

		$this->assertSame( 1, $ran, 'A forced run must execute the action callback.' );
		$this->assertSame( ActionScheduler_Store::STATUS_COMPLETE, $store->get_status( $action_id ) );

		remove_all_actions( $hook );
	}
}
