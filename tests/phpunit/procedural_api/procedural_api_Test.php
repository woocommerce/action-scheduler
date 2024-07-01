<?php

/**
 * Class procedural_api_Test
 */
class Procedural_API_Test extends ActionScheduler_UnitTestCase {

	// phpcs:disable Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.FunctionComment.MissingParamTag
	public function test_schedule_action() {
		$time      = time();
		$hook      = md5( rand() );
		$action_id = as_schedule_single_action( $time, $hook );

		$store  = ActionScheduler::store();
		$action = $store->fetch_action( $action_id );
		$this->assertEquals( $time, $action->get_schedule()->get_date()->getTimestamp() );
		$this->assertEquals( $hook, $action->get_hook() );
	}

	public function test_recurring_action() {
		$time      = time();
		$hook      = md5( rand() );
		$action_id = as_schedule_recurring_action( $time, HOUR_IN_SECONDS, $hook );

		$store  = ActionScheduler::store();
		$action = $store->fetch_action( $action_id );
		$this->assertEquals( $time, $action->get_schedule()->get_date()->getTimestamp() );
		$this->assertEquals( $time + HOUR_IN_SECONDS + 2, $action->get_schedule()->get_next( as_get_datetime_object( $time + 2 ) )->getTimestamp() );
		$this->assertEquals( $hook, $action->get_hook() );
	}

	/**
	 * Test that we reject attempts to register a recurring action with an invalid interval. This guards against
	 * 'runaway' recurring actions that are created accidentally and treated as having a zero-second interval.
	 *
	 * @return void
	 */
	public function test_recurring_actions_reject_invalid_intervals() {
		$this->assertGreaterThan(
			0,
			as_schedule_recurring_action( time(), 1, 'foo' ),
			'When an integer is provided as the interval, a recurring action is successfully created.'
		);

		$this->assertGreaterThan(
			0,
			as_schedule_recurring_action( time(), '10', 'foo' ),
			'When an integer-like string is provided as the interval, a recurring action is successfully created.'
		);

		$this->assertGreaterThan(
			0,
			as_schedule_recurring_action( time(), 100.0, 'foo' ),
			'When an integer-value as a double is provided as the interval, a recurring action is successfully created.'
		);

		$this->setExpectedIncorrectUsage( 'as_schedule_recurring_action' );
		$this->assertEquals(
			0,
			as_schedule_recurring_action( time(), 'nonsense', 'foo' ),
			'When a non-numeric string is provided as the interval, a recurring action is not created and a doing-it-wrong notice is emitted.'
		);

		$this->setExpectedIncorrectUsage( 'as_schedule_recurring_action' );
		$this->assertEquals(
			0,
			as_schedule_recurring_action( time(), 123.456, 'foo' ),
			'When a non-integer double is provided as the interval, a recurring action is not created and a doing-it-wrong notice is emitted.'
		);
	}

	public function test_cron_schedule() {
		$time      = as_get_datetime_object( '2014-01-01' );
		$hook      = md5( rand() );
		$action_id = as_schedule_cron_action( $time->getTimestamp(), '0 0 10 10 *', $hook );

		$store         = ActionScheduler::store();
		$action        = $store->fetch_action( $action_id );
		$expected_date = as_get_datetime_object( '2014-10-10' );
		$this->assertEquals( $expected_date->getTimestamp(), $action->get_schedule()->get_date()->getTimestamp() );
		$this->assertEquals( $hook, $action->get_hook() );

		$expected_date = as_get_datetime_object( '2015-10-10' );
		$this->assertEquals( $expected_date->getTimestamp(), $action->get_schedule()->get_next( as_get_datetime_object( '2015-01-02' ) )->getTimestamp() );
	}

	public function test_get_next() {
		$time = as_get_datetime_object( 'tomorrow' );
		$hook = md5( rand() );
		as_schedule_recurring_action( $time->getTimestamp(), HOUR_IN_SECONDS, $hook );

		$next = as_next_scheduled_action( $hook );

		$this->assertEquals( $time->getTimestamp(), $next );
	}

	public function test_get_next_async() {
		$hook      = md5( rand() );
		$action_id = as_enqueue_async_action( $hook );

		$next = as_next_scheduled_action( $hook );

		$this->assertTrue( $next );

		$store = ActionScheduler::store();

		// Completed async actions should still return false.
		$store->mark_complete( $action_id );
		$next = as_next_scheduled_action( $hook );
		$this->assertFalse( $next );

		// Failed async actions should still return false.
		$store->mark_failure( $action_id );
		$next = as_next_scheduled_action( $hook );
		$this->assertFalse( $next );

		// Cancelled async actions should still return false.
		$store->cancel_action( $action_id );
		$next = as_next_scheduled_action( $hook );
		$this->assertFalse( $next );
	}

	public function provider_time_hook_args_group() {
		$time  = time() + 60 * 2;
		$hook  = md5( rand() );
		$args  = array( rand(), rand() );
		$group = 'test_group';

		return array(

			// Test with no args or group.
			array(
				'time'  => $time,
				'hook'  => $hook,
				'args'  => array(),
				'group' => '',
			),

			// Test with args but no group.
			array(
				'time'  => $time,
				'hook'  => $hook,
				'args'  => $args,
				'group' => '',
			),

			// Test with group but no args.
			array(
				'time'  => $time,
				'hook'  => $hook,
				'args'  => array(),
				'group' => $group,
			),

			// Test with args & group.
			array(
				'time'  => $time,
				'hook'  => $hook,
				'args'  => $args,
				'group' => $group,
			),
		);
	}

	/**
	 * @dataProvider provider_time_hook_args_group
	 */
	public function test_unschedule( $time, $hook, $args, $group ) {

		$action_id_unscheduled = as_schedule_single_action( $time, $hook, $args, $group );
		$action_scheduled_time = $time + 1;
		$action_id_scheduled   = as_schedule_single_action( $action_scheduled_time, $hook, $args, $group );

		as_unschedule_action( $hook, $args, $group );

		$next = as_next_scheduled_action( $hook, $args, $group );
		$this->assertEquals( $action_scheduled_time, $next );

		$store              = ActionScheduler::store();
		$unscheduled_action = $store->fetch_action( $action_id_unscheduled );

		// Make sure the next scheduled action is unscheduled.
		$this->assertEquals( $hook, $unscheduled_action->get_hook() );
		$this->assertEquals( as_get_datetime_object( $time ), $unscheduled_action->get_schedule()->get_date() );
		$this->assertEquals( ActionScheduler_Store::STATUS_CANCELED, $store->get_status( $action_id_unscheduled ) );
		$this->assertNull( $unscheduled_action->get_schedule()->get_next( as_get_datetime_object() ) );

		// Make sure other scheduled actions are not unscheduled.
		$this->assertEquals( ActionScheduler_Store::STATUS_PENDING, $store->get_status( $action_id_scheduled ) );
		$scheduled_action = $store->fetch_action( $action_id_scheduled );

		$this->assertEquals( $hook, $scheduled_action->get_hook() );
		$this->assertEquals( $action_scheduled_time, $scheduled_action->get_schedule()->get_date()->getTimestamp() );
	}

	/**
	 * @dataProvider provider_time_hook_args_group
	 */
	public function test_unschedule_all( $time, $hook, $args, $group ) {

		$hook       = md5( $hook );
		$action_ids = array();

		for ( $i = 0; $i < 3; $i++ ) {
			$action_ids[] = as_schedule_single_action( $time, $hook, $args, $group );
		}

		as_unschedule_all_actions( $hook, $args, $group );

		$next = as_next_scheduled_action( $hook );
		$this->assertFalse( $next );

		$after = as_get_datetime_object( $time );
		$after->modify( '+1 minute' );

		$store = ActionScheduler::store();

		foreach ( $action_ids as $action_id ) {
			$action = $store->fetch_action( $action_id );

			$this->assertEquals( $hook, $action->get_hook() );
			$this->assertEquals( as_get_datetime_object( $time ), $action->get_schedule()->get_date() );
			$this->assertEquals( ActionScheduler_Store::STATUS_CANCELED, $store->get_status( $action_id ) );
			$this->assertNull( $action->get_schedule()->get_next( $after ) );
		}
	}

	public function test_as_get_datetime_object_default() {

		$utc_now = new ActionScheduler_DateTime( 'now', new DateTimeZone( 'UTC' ) );
		$as_now  = as_get_datetime_object();

		// Don't want to use 'U' as timestamps will always be in UTC.
		$this->assertEquals( $utc_now->format( 'Y-m-d H:i:s' ), $as_now->format( 'Y-m-d H:i:s' ) );
	}

	public function test_as_get_datetime_object_relative() {

		$utc_tomorrow = new ActionScheduler_DateTime( 'tomorrow', new DateTimeZone( 'UTC' ) );
		$as_tomorrow  = as_get_datetime_object( 'tomorrow' );

		$this->assertEquals( $utc_tomorrow->format( 'Y-m-d H:i:s' ), $as_tomorrow->format( 'Y-m-d H:i:s' ) );

		$utc_tomorrow = new ActionScheduler_DateTime( 'yesterday', new DateTimeZone( 'UTC' ) );
		$as_tomorrow  = as_get_datetime_object( 'yesterday' );

		$this->assertEquals( $utc_tomorrow->format( 'Y-m-d H:i:s' ), $as_tomorrow->format( 'Y-m-d H:i:s' ) );
	}

	public function test_as_get_datetime_object_fixed() {

		$utc_tomorrow = new ActionScheduler_DateTime( '29 February 2016', new DateTimeZone( 'UTC' ) );
		$as_tomorrow  = as_get_datetime_object( '29 February 2016' );

		$this->assertEquals( $utc_tomorrow->format( 'Y-m-d H:i:s' ), $as_tomorrow->format( 'Y-m-d H:i:s' ) );

		$utc_tomorrow = new ActionScheduler_DateTime( '1st January 2024', new DateTimeZone( 'UTC' ) );
		$as_tomorrow  = as_get_datetime_object( '1st January 2024' );

		$this->assertEquals( $utc_tomorrow->format( 'Y-m-d H:i:s' ), $as_tomorrow->format( 'Y-m-d H:i:s' ) );
	}

	public function test_as_get_datetime_object_timezone() {

		$timezone_au      = 'Australia/Brisbane';
		$timezone_default = date_default_timezone_get();

		// phpcs:ignore
		date_default_timezone_set( $timezone_au );

		$au_now = new ActionScheduler_DateTime( 'now' );
		$as_now = as_get_datetime_object();

		// Make sure they're for the same time.
		$this->assertEquals( $au_now->getTimestamp(), $as_now->getTimestamp() );

		// But not in the same timezone, as $as_now should be using UTC.
		$this->assertNotEquals( $au_now->format( 'Y-m-d H:i:s' ), $as_now->format( 'Y-m-d H:i:s' ) );

		$au_now    = new ActionScheduler_DateTime( 'now' );
		$as_au_now = as_get_datetime_object();

		$this->assertEquals( $au_now->getTimestamp(), $as_now->getTimestamp(), '', 2 );

		// But not in the same timezone, as $as_now should be using UTC.
		$this->assertNotEquals( $au_now->format( 'Y-m-d H:i:s' ), $as_now->format( 'Y-m-d H:i:s' ) );

		// phpcs:ignore
		date_default_timezone_set( $timezone_default );
	}

	public function test_as_get_datetime_object_type() {
		$f   = 'Y-m-d H:i:s';
		$now = as_get_datetime_object();
		$this->assertInstanceOf( 'ActionScheduler_DateTime', $now );

		$datetime   = new DateTime( 'now', new DateTimeZone( 'UTC' ) );
		$as_datetime = as_get_datetime_object( $datetime );
		$this->assertEquals( $datetime->format( $f ), $as_datetime->format( $f ) );
	}

	public function test_as_has_scheduled_action() {
		$store = ActionScheduler::store();

		$time      = as_get_datetime_object( 'tomorrow' );
		$action_id = as_schedule_single_action( $time->getTimestamp(), 'hook_1' );

		$this->assertTrue( as_has_scheduled_action( 'hook_1' ) );
		$this->assertFalse( as_has_scheduled_action( 'hook_2' ) );

		// Go to in-progress.
		$store->log_execution( $action_id );
		$this->assertTrue( as_has_scheduled_action( 'hook_1' ) );

		// Go to complete.
		$store->mark_complete( $action_id );
		$this->assertFalse( as_has_scheduled_action( 'hook_1' ) );

		// Go to failed.
		$store->mark_failure( $action_id );
		$this->assertFalse( as_has_scheduled_action( 'hook_1' ) );
	}

	public function test_as_has_scheduled_action_with_args() {
		as_schedule_single_action( time(), 'hook_1', array( 'a' ) );

		$this->assertTrue( as_has_scheduled_action( 'hook_1' ) );
		$this->assertFalse( as_has_scheduled_action( 'hook_1', array( 'b' ) ) );

		// Test for any args.
		$this->assertTrue( as_has_scheduled_action( 'hook_1', array( 'a' ) ) );
	}

	public function test_as_has_scheduled_action_with_group() {
		as_schedule_single_action( time(), 'hook_1', array(), 'group_1' );

		$this->assertTrue( as_has_scheduled_action( 'hook_1', null, 'group_1' ) );
		$this->assertTrue( as_has_scheduled_action( 'hook_1', array(), 'group_1' ) );
	}
	// phpcs:enable

	/**
	 * Test as_enqueue_async_action with unique param.
	 */
	public function test_as_enqueue_async_action_unique() {
		$this->set_action_scheduler_store( new ActionScheduler_DBStore() );

		$action_id = as_enqueue_async_action( 'hook_1', array( 'a' ), 'dummy', true );
		$this->assertValidAction( $action_id );

		$action_id_duplicate = as_enqueue_async_action( 'hook_1', array( 'a' ), 'dummy', true );
		$this->assertEquals( 0, $action_id_duplicate );
	}

	/**
	 * Test enqueuing a unique action using the hybrid store.
	 * This is using a best-effort approach, so it's possible that the action will be enqueued even if it's not unique.
	 */
	public function test_as_enqueue_async_action_unique_hybrid_best_effort() {
		$this->set_action_scheduler_store( new ActionScheduler_HybridStore() );

		$action_id = as_enqueue_async_action( 'hook_1', array( 'a' ), 'dummy', true );
		$this->assertValidAction( $action_id );

		$action_id_duplicate = as_enqueue_async_action( 'hook_1', array( 'a' ), 'dummy', true );
		$this->assertEquals( 0, $action_id_duplicate );
	}

	/**
	 * Test as_schedule_single_action with unique param.
	 */
	public function test_as_schedule_single_action_unique() {
		$this->set_action_scheduler_store( new ActionScheduler_DBStore() );

		$action_id = as_schedule_single_action( time(), 'hook_1', array( 'a' ), 'dummy', true );
		$this->assertValidAction( $action_id );

		$action_id_duplicate = as_schedule_single_action( time(), 'hook_1', array( 'a' ), 'dummy', true );
		$this->assertEquals( 0, $action_id_duplicate );
	}

	/**
	 * Test as_schedule_recurring_action with unique param.
	 */
	public function test_as_schedule_recurring_action_unique() {
		$this->set_action_scheduler_store( new ActionScheduler_DBStore() );

		$action_id = as_schedule_recurring_action( time(), MINUTE_IN_SECONDS, 'hook_1', array( 'a' ), 'dummy', true );
		$this->assertValidAction( $action_id );

		$action_id_duplicate = as_schedule_recurring_action( time(), MINUTE_IN_SECONDS, 'hook_1', array( 'a' ), 'dummy', true );
		$this->assertEquals( 0, $action_id_duplicate );
	}

	/**
	 * Test as_schedule_cron with unique param.
	 */
	public function test_as_schedule_cron_action() {
		$this->set_action_scheduler_store( new ActionScheduler_DBStore() );

		$action_id = as_schedule_cron_action( time(), '0 0 * * *', 'hook_1', array( 'a' ), 'dummy', true );
		$this->assertValidAction( $action_id );

		$action_id_duplicate = as_schedule_cron_action( time(), '0 0 * * *', 'hook_1', array( 'a' ), 'dummy', true );
		$this->assertEquals( 0, $action_id_duplicate );
	}

	/**
	 * Test recovering from an incorrect database schema when scheduling a single action.
	 */
	public function test_as_recover_from_incorrect_schema() {
		// custom error reporting so we can test for errors sent to error_log.
		global $wpdb;
		$wpdb->suppress_errors( true );
		$error_capture = tmpfile();
		$actual_error_log = ini_set( 'error_log', stream_get_meta_data( $error_capture )['uri'] );

		// we need a hybrid store so that dropping the priority column will cause an exception.
		$this->set_action_scheduler_store( new ActionScheduler_HybridStore() );
		$this->assertEquals( 'ActionScheduler_HybridStore', get_class( ActionScheduler::store() ) );

		// drop the priority column from the actions table.
		$wpdb->query( "ALTER TABLE {$wpdb->actionscheduler_actions} DROP COLUMN priority" );

		// try to schedule a single action.
		$action_id = as_schedule_single_action( time(), 'hook_17', array( 'a', 'b' ), 'dummytest', true );

		// ensure that no exception was thrown and zero was returned.
		$this->assertEquals( 0, $action_id );

		// try to schedule an async action.
		$action_id = as_enqueue_async_action( 'hook_18', array( 'a', 'b' ), 'dummytest', true );
		// ensure that no exception was thrown and zero was returned.
		$this->assertEquals( 0, $action_id );

		// try to schedule a recurring action.
		$action_id = as_schedule_recurring_action( time(), MINUTE_IN_SECONDS, 'hook_19', array( 'a', 'b' ), 'dummytest', true );
		// ensure that no exception was thrown and zero was returned.
		$this->assertEquals( 0, $action_id );

		// try to schedule a cron action.
		$action_id = as_schedule_cron_action( time(), '0 0 * * *', 'hook_20', array( 'a', 'b' ), 'dummytest', true );
		// ensure that no exception was thrown and zero was returned.
		$this->assertEquals( 0, $action_id );

		// ensure that all four errors were logged to error_log.
		$logged_errors = stream_get_contents( $error_capture );
		if ( method_exists( $this, 'assertStringContainsString' ) ) {
			$this->assertStringContainsString( 'Caught exception while enqueuing action "hook_17": Error saving action', $logged_errors );
			$this->assertStringContainsString( 'Caught exception while enqueuing action "hook_18": Error saving action', $logged_errors );
			$this->assertStringContainsString( 'Caught exception while enqueuing action "hook_19": Error saving action', $logged_errors );
			$this->assertStringContainsString( 'Caught exception while enqueuing action "hook_20": Error saving action', $logged_errors );
			$this->assertStringContainsString( "Unknown column 'priority' in 'field list'", $logged_errors );
		} else {
			$this->assertContains( 'Caught exception while enqueuing action "hook_17": Error saving action', $logged_errors );
			$this->assertContains( 'Caught exception while enqueuing action "hook_18": Error saving action', $logged_errors );
			$this->assertContains( 'Caught exception while enqueuing action "hook_19": Error saving action', $logged_errors );
			$this->assertContains( 'Caught exception while enqueuing action "hook_20": Error saving action', $logged_errors );
			$this->assertContains( "Unknown column 'priority' in 'field list'", $logged_errors );
		}

		// recreate the priority column.
		$wpdb->query( "ALTER TABLE {$wpdb->actionscheduler_actions} ADD COLUMN priority tinyint(10) UNSIGNED NOT NULL DEFAULT 10" );
		// restore error logging.
		$wpdb->suppress_errors( false );
		ini_set( 'error_log', $actual_error_log );
	}

	/**
	 * Helper method to set actions scheduler store.
	 *
	 * @param ActionScheduler_Store $store Store instance to set.
	 */
	private function set_action_scheduler_store( $store ) {
		$store_factory_setter = function() use ( $store ) {
			self::$store = $store;
		};
		$binded_store_factory_setter = Closure::bind( $store_factory_setter, null, ActionScheduler_Store::class );
		$binded_store_factory_setter();
	}

	/**
	 * Helper method to assert valid action.
	 *
	 * @param int $action_id Action ID to assert.
	 */
	private function assertValidAction( $action_id ) {
		$this->assertNotEquals( 0, $action_id );
		$action = ActionScheduler::store()->fetch_action( $action_id );
		$this->assertInstanceOf( 'ActionScheduler_Action', $action );
	}
}
