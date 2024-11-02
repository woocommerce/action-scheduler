<?php

namespace Action_Scheduler\Tests\DataStores;

use ActionScheduler_Action;
use ActionScheduler_Callbacks;
use ActionScheduler_IntervalSchedule;
use ActionScheduler_Mocker;
use ActionScheduler_SimpleSchedule;
use ActionScheduler_Store;
use ActionScheduler_UnitTestCase;
use InvalidArgumentException;

/**
 * Abstract store test class.
 *
 * Many tests for the WP Post store or the custom tables store can be shared. This abstract class contains tests that
 * apply to both stores without having to duplicate code.
 */
abstract class AbstractStoreTest extends ActionScheduler_UnitTestCase {

	/**
	 * Get data store for tests.
	 *
	 * @return ActionScheduler_Store
	 */
	abstract protected function get_store();

	public function test_get_status() {
		$time      = as_get_datetime_object( '-10 minutes' );
		$schedule  = new ActionScheduler_IntervalSchedule( $time, HOUR_IN_SECONDS );
		$action    = new ActionScheduler_Action( ActionScheduler_Callbacks::HOOK_WITH_CALLBACK, array(), $schedule );
		$store     = $this->get_store();
		$action_id = $store->save_action( $action );

		$this->assertEquals( ActionScheduler_Store::STATUS_PENDING, $store->get_status( $action_id ) );

		$store->mark_complete( $action_id );
		$this->assertEquals( ActionScheduler_Store::STATUS_COMPLETE, $store->get_status( $action_id ) );

		$store->mark_failure( $action_id );
		$this->assertEquals( ActionScheduler_Store::STATUS_FAILED, $store->get_status( $action_id ) );
	}

	// Start tests for \ActionScheduler_Store::query_actions().

	// phpcs:ignore Squiz.Commenting.FunctionComment.WrongStyle
	public function test_query_actions_query_type_arg_invalid_option() {
		$this->expectException( InvalidArgumentException::class );
		$this->get_store()->query_actions( array( 'hook' => ActionScheduler_Callbacks::HOOK_WITH_CALLBACK ), 'invalid' );
	}

	public function test_query_actions_query_type_arg_valid_options() {
		$store    = $this->get_store();
		$schedule = new ActionScheduler_SimpleSchedule( as_get_datetime_object( 'tomorrow' ) );

		$action_id_1 = $store->save_action( new ActionScheduler_Action( ActionScheduler_Callbacks::HOOK_WITH_CALLBACK, array( 1 ), $schedule ) );
		$action_id_2 = $store->save_action( new ActionScheduler_Action( ActionScheduler_Callbacks::HOOK_WITH_CALLBACK, array( 1 ), $schedule ) );

		$this->assertEquals( array( $action_id_1, $action_id_2 ), $store->query_actions( array( 'hook' => ActionScheduler_Callbacks::HOOK_WITH_CALLBACK ) ) );
		$this->assertEquals( 2, $store->query_actions( array( 'hook' => ActionScheduler_Callbacks::HOOK_WITH_CALLBACK ), 'count' ) );
	}

	public function test_query_actions_by_single_status() {
		$store    = $this->get_store();
		$schedule = new ActionScheduler_SimpleSchedule( as_get_datetime_object( 'tomorrow' ) );

		$this->assertEquals( 0, $store->query_actions( array( 'status' => ActionScheduler_Store::STATUS_PENDING ), 'count' ) );

		$action_id_1 = $store->save_action( new ActionScheduler_Action( 'my_hook_1', array( 1 ), $schedule ) );
		$action_id_2 = $store->save_action( new ActionScheduler_Action( 'my_hook_2', array( 1 ), $schedule ) );
		$action_id_3 = $store->save_action( new ActionScheduler_Action( 'my_hook_3', array( 1 ), $schedule ) );
		$store->mark_complete( $action_id_3 );

		$this->assertEquals( 2, $store->query_actions( array( 'status' => ActionScheduler_Store::STATUS_PENDING ), 'count' ) );
		$this->assertEquals( 1, $store->query_actions( array( 'status' => ActionScheduler_Store::STATUS_COMPLETE ), 'count' ) );
	}

	public function test_query_actions_by_array_status() {
		$store    = $this->get_store();
		$schedule = new ActionScheduler_SimpleSchedule( as_get_datetime_object( 'tomorrow' ) );

		$this->assertEquals(
			0,
			$store->query_actions(
				array(
					'status' => array( ActionScheduler_Store::STATUS_PENDING, ActionScheduler_Store::STATUS_RUNNING ),
				),
				'count'
			)
		);

		$action_id_1 = $store->save_action( new ActionScheduler_Action( 'my_hook_1', array( 1 ), $schedule ) );
		$action_id_2 = $store->save_action( new ActionScheduler_Action( 'my_hook_2', array( 1 ), $schedule ) );
		$action_id_3 = $store->save_action( new ActionScheduler_Action( 'my_hook_3', array( 1 ), $schedule ) );
		$store->mark_failure( $action_id_3 );

		$this->assertEquals(
			3,
			$store->query_actions(
				array(
					'status' => array( ActionScheduler_Store::STATUS_PENDING, ActionScheduler_Store::STATUS_FAILED ),
				),
				'count'
			)
		);
		$this->assertEquals(
			2,
			$store->query_actions(
				array(
					'status' => array( ActionScheduler_Store::STATUS_PENDING, ActionScheduler_Store::STATUS_COMPLETE ),
				),
				'count'
			)
		);
	}

	// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
	// End tests for \ActionScheduler_Store::query_actions().

	/**
	 * The `has_pending_actions_due` method should return a boolean value depending on whether there are
	 * pending actions.
	 *
	 * @return void
	 */
	public function test_has_pending_actions_due() {
		$store  = $this->get_store();
		$runner = ActionScheduler_Mocker::get_queue_runner( $store );

		for ( $i = - 3; $i <= 3; $i ++ ) {
			// Some past actions, some future actions.
			$time     = as_get_datetime_object( $i . ' hours' );
			$schedule = new ActionScheduler_SimpleSchedule( $time );
			$action   = new ActionScheduler_Action( ActionScheduler_Callbacks::HOOK_WITH_CALLBACK, array( $i ), $schedule, 'my_group' );

			$store->save_action( $action );
		}
		$this->assertTrue( $store->has_pending_actions_due() );

		$runner->run();
		$this->assertFalse( $store->has_pending_actions_due() );
	}

	/**
	 * The `has_pending_actions_due` method should return false when all pending actions are in the future.
	 *
	 * @return void
	 */
	public function test_has_pending_actions_due_only_future_actions() {
		$store = $this->get_store();

		for ( $i = 1; $i <= 3; $i ++ ) {
			// Only future actions.
			$time     = as_get_datetime_object( $i . ' hours' );
			$schedule = new ActionScheduler_SimpleSchedule( $time );
			$action   = new ActionScheduler_Action( ActionScheduler_Callbacks::HOOK_WITH_CALLBACK, array( $i ), $schedule, 'my_group' );

			$store->save_action( $action );
		}
		$this->assertFalse( $store->has_pending_actions_due() );
	}
}
