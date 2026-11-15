<?php

/**
 * @group wp-cli
 */
class ActionScheduler_WPCLI_QueueRunner_Test extends ActionScheduler_UnitTestCase {

	public function test_run_deadline_reached() {
		$runner = $this->get_runner();
		$this->assertFalse( $runner->run_deadline_reached(), 'By default, there is no deadline.' );

		$runner->set_run_deadline( microtime( true ) - 1 );
		$this->assertTrue( $runner->run_deadline_reached(), 'A deadline in the past has been reached.' );

		$runner->set_run_deadline( microtime( true ) + HOUR_IN_SECONDS );
		$this->assertFalse( $runner->run_deadline_reached(), 'A deadline in the future has not been reached.' );

		$runner->set_run_deadline( 0 );
		$this->assertFalse( $runner->run_deadline_reached(), 'A deadline of zero removes the deadline.' );
	}

	public function test_run_without_deadline_processes_whole_batch() {
		$hook       = __FUNCTION__;
		$runner     = $this->get_runner();
		$call_count = 0;
		$callback   = function () use ( &$call_count ) {
			++$call_count;
		};

		add_action( $hook, $callback );
		$action_ids = $this->schedule_actions( $hook, 3 );

		$this->assertSame( 3, $runner->setup( 10, array( $hook ) ) );
		$this->assertSame( 3, $runner->run() );
		remove_action( $hook, $callback );

		$this->assertSame( 3, $call_count );
		foreach ( $action_ids as $action_id ) {
			$this->assertSame( ActionScheduler_Store::STATUS_COMPLETE, ActionScheduler::store()->get_status( $action_id ) );
		}
	}

	public function test_run_stops_starting_actions_once_deadline_reached() {
		$hook       = __FUNCTION__;
		$store      = ActionScheduler::store();
		$runner     = $this->get_runner();
		$call_count = 0;
		$callback   = function () use ( &$call_count, $runner ) {
			++$call_count;

			// Simulate the deadline passing while the first action is running.
			$runner->set_run_deadline( microtime( true ) );
		};

		add_action( $hook, $callback );
		$action_ids = $this->schedule_actions( $hook, 3 );

		$this->assertSame( 3, $runner->setup( 10, array( $hook ) ) );
		$this->assertSame( 1, $runner->run(), 'Only the action started before the deadline was reached is processed.' );
		remove_action( $hook, $callback );

		$this->assertSame( 1, $call_count );
		$this->assertSame( ActionScheduler_Store::STATUS_COMPLETE, $store->get_status( $action_ids[0] ) );

		foreach ( array_slice( $action_ids, 1 ) as $action_id ) {
			$this->assertSame( ActionScheduler_Store::STATUS_PENDING, $store->get_status( $action_id ), 'Actions that were not started remain pending.' );
			$this->assertEquals( 0, $store->get_claim_id( $action_id ), 'Actions that were not started are released from the claim.' );
		}
	}

	public function test_run_starts_no_actions_if_deadline_already_reached() {
		$hook       = __FUNCTION__;
		$runner     = $this->get_runner();
		$call_count = 0;
		$callback   = function () use ( &$call_count ) {
			++$call_count;
		};

		add_action( $hook, $callback );
		$action_ids = $this->schedule_actions( $hook, 2 );

		$this->assertSame( 2, $runner->setup( 10, array( $hook ) ) );
		$runner->set_run_deadline( microtime( true ) - 1 );
		$this->assertSame( 0, $runner->run() );
		remove_action( $hook, $callback );

		$this->assertSame( 0, $call_count );
		foreach ( $action_ids as $action_id ) {
			$this->assertSame( ActionScheduler_Store::STATUS_PENDING, ActionScheduler::store()->get_status( $action_id ) );
			$this->assertEquals( 0, ActionScheduler::store()->get_claim_id( $action_id ) );
		}
	}

	/**
	 * Schedules a number of past-due actions.
	 *
	 * @param string $hook  Action hook.
	 * @param int    $count Number of actions to schedule.
	 *
	 * @return int[] Action IDs.
	 */
	private function schedule_actions( $hook, $count ) {
		$action_ids = array();

		for ( $i = 0; $i < $count; $i++ ) {
			$action_ids[] = as_schedule_single_action( time() - MINUTE_IN_SECONDS, $hook, array( $i ) );
		}

		return $action_ids;
	}

	/**
	 * WP-CLI is not loaded by the test suite, so the runner's constructor (which insists on WP-CLI) is bypassed,
	 * along with the methods that write to the WP-CLI output.
	 *
	 * @return ActionScheduler_WPCLI_QueueRunner
	 */
	private function get_runner() {
		return new class( ActionScheduler::store() ) extends ActionScheduler_WPCLI_QueueRunner {
			/**
			 * @param ActionScheduler_Store $store Store object.
			 */
			public function __construct( ActionScheduler_Store $store ) {
				ActionScheduler_Abstract_QueueRunner::__construct( $store );
			}

			/**
			 * Do not add the hooks that log each action to the WP-CLI output.
			 */
			protected function add_hooks() {}

			/**
			 * Use a progress bar that only counts ticks.
			 */
			protected function setup_progress_bar() {
				$this->progress_bar = new class() {
					/**
					 * @var int
					 */
					private $ticks = 0;

					public function tick() {
						++$this->ticks;
					}

					public function current() {
						return $this->ticks;
					}

					public function finish() {}
				};
			}
		};
	}
}
