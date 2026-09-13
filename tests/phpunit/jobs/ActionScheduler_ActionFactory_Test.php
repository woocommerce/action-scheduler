<?php

/**
 * Class ActionScheduler_ActionFactory_Test
 *
 * @group actions
 * @group factory
 */
class ActionScheduler_ActionFactory_Test extends ActionScheduler_UnitTestCase {

	/**
	 * Factory instance under test.
	 *
	 * @var ActionScheduler_ActionFactory
	 */
	protected $factory;

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->factory = new ActionScheduler_ActionFactory();
	}

	/**
	 * Test get_stored_action for pending action status.
	 */
	public function test_get_stored_action_pending() {
		$time     = as_get_datetime_object();
		$schedule = new ActionScheduler_SimpleSchedule( $time );
		$args     = array( 'order_id' => 123 );
		$action   = $this->factory->get_stored_action( ActionScheduler_Store::STATUS_PENDING, 'test_pending_hook', $args, $schedule, 'test_group', 5 );

		$this->assertInstanceOf( 'ActionScheduler_Action', $action );
		$this->assertSame( 'test_pending_hook', $action->get_hook() );
		$this->assertSame( $args, $action->get_args() );
		$this->assertSame( 'test_group', $action->get_group() );
		$this->assertSame( 5, $action->get_priority() );
		$this->assertSame( $schedule, $action->get_schedule() );
	}

	/**
	 * Test get_stored_action for canceled action status converts non-null schedules.
	 */
	public function test_get_stored_action_canceled() {
		$time     = as_get_datetime_object();
		$schedule = new ActionScheduler_SimpleSchedule( $time );
		$action   = $this->factory->get_stored_action( ActionScheduler_Store::STATUS_CANCELED, 'test_canceled_hook', array(), $schedule, 'test_group' );

		$this->assertInstanceOf( 'ActionScheduler_CanceledAction', $action );
		$this->assertInstanceOf( 'ActionScheduler_CanceledSchedule', $action->get_schedule() );

		// When schedule is already NullSchedule, it remains NullSchedule.
		$null_schedule = new ActionScheduler_NullSchedule();
		$action_null   = $this->factory->get_stored_action( ActionScheduler_Store::STATUS_CANCELED, 'test_canceled_null', array(), $null_schedule );
		$this->assertInstanceOf( 'ActionScheduler_NullSchedule', $action_null->get_schedule() );
	}

	/**
	 * Test get_stored_action for complete/finished action status.
	 */
	public function test_get_stored_action_finished() {
		$time     = as_get_datetime_object();
		$schedule = new ActionScheduler_SimpleSchedule( $time );
		$action   = $this->factory->get_stored_action( ActionScheduler_Store::STATUS_COMPLETE, 'test_complete_hook', array(), $schedule );

		$this->assertInstanceOf( 'ActionScheduler_FinishedAction', $action );
	}

	/**
	 * Test get_stored_action filters allow modifying class and instance.
	 */
	public function test_get_stored_action_filters() {
		$class_filter = function () {
			return 'ActionScheduler_FinishedAction';
		};
		add_filter( 'action_scheduler_stored_action_class', $class_filter );

		$action = $this->factory->get_stored_action( ActionScheduler_Store::STATUS_PENDING, 'test_filtered_hook' );
		$this->assertInstanceOf( 'ActionScheduler_FinishedAction', $action );

		remove_filter( 'action_scheduler_stored_action_class', $class_filter );

		$instance_filter = function ( $inst ) {
			$inst->set_priority( 99 );
			return $inst;
		};
		add_filter( 'action_scheduler_stored_action_instance', $instance_filter );

		$action = $this->factory->get_stored_action( ActionScheduler_Store::STATUS_PENDING, 'test_instance_hook' );
		$this->assertSame( 99, $action->get_priority() );

		remove_filter( 'action_scheduler_stored_action_instance', $instance_filter );
	}

	/**
	 * Test async and async_unique action creation.
	 */
	public function test_async_and_async_unique() {
		$action_id = $this->factory->async( 'test_async_action', array( 'foo' => 'bar' ), 'async_group' );
		$this->assertGreaterThan( 0, $action_id );

		$stored = ActionScheduler::store()->fetch_action( $action_id );
		$this->assertSame( 'test_async_action', $stored->get_hook() );
		$this->assertSame( array( 'foo' => 'bar' ), $stored->get_args() );
		$this->assertSame( 'async_group', $stored->get_group() );
		$this->assertInstanceOf( 'ActionScheduler_NullSchedule', $stored->get_schedule() );

		// Unique async action deduplication.
		$unique_id1 = $this->factory->async_unique( 'test_unique_async', array( 'key' => 1 ), 'async_group', true );
		$this->assertGreaterThan( 0, $unique_id1 );

		$unique_id2 = $this->factory->async_unique( 'test_unique_async', array( 'key' => 1 ), 'async_group', true );
		$this->assertSame( 0, $unique_id2 );
	}

	/**
	 * Test single and single_unique action creation.
	 */
	public function test_single_and_single_unique() {
		$time      = time() + 3600;
		$action_id = $this->factory->single( 'test_single_action', array( 10 ), $time, 'single_group' );
		$this->assertGreaterThan( 0, $action_id );

		$stored = ActionScheduler::store()->fetch_action( $action_id );
		$this->assertSame( 'test_single_action', $stored->get_hook() );
		$this->assertInstanceOf( 'ActionScheduler_SimpleSchedule', $stored->get_schedule() );

		// Unique single action deduplication.
		$unique_id1 = $this->factory->single_unique( 'test_unique_single', array( 20 ), $time, 'single_group', true );
		$this->assertGreaterThan( 0, $unique_id1 );

		$unique_id2 = $this->factory->single_unique( 'test_unique_single', array( 20 ), $time, 'single_group', true );
		$this->assertSame( 0, $unique_id2 );
	}

	/**
	 * Test recurring and recurring_unique action creation.
	 */
	public function test_recurring_and_recurring_unique() {
		$time      = time() + 60;
		$action_id = $this->factory->recurring( 'test_recurring_action', array(), $time, 1800, 'recurring_group' );
		$this->assertGreaterThan( 0, $action_id );

		$stored = ActionScheduler::store()->fetch_action( $action_id );
		$this->assertInstanceOf( 'ActionScheduler_IntervalSchedule', $stored->get_schedule() );
		$this->assertSame( 1800, $stored->get_schedule()->get_recurrence() );

		// Empty interval falls back to single action.
		$fallback_id = $this->factory->recurring( 'test_recurring_fallback', array(), $time, 0, 'recurring_group' );
		$stored_fb   = ActionScheduler::store()->fetch_action( $fallback_id );
		$this->assertInstanceOf( 'ActionScheduler_SimpleSchedule', $stored_fb->get_schedule() );

		// Unique recurring action deduplication.
		$unique_id1 = $this->factory->recurring_unique( 'test_unique_rec', array(), $time, 3600, 'recurring_group', true );
		$this->assertGreaterThan( 0, $unique_id1 );

		$unique_id2 = $this->factory->recurring_unique( 'test_unique_rec', array(), $time, 3600, 'recurring_group', true );
		$this->assertSame( 0, $unique_id2 );
	}

	/**
	 * Test cron and cron_unique action creation.
	 */
	public function test_cron_and_cron_unique() {
		$time      = time();
		$action_id = $this->factory->cron( 'test_cron_action', array(), $time, '0 * * * *', 'cron_group' );
		$this->assertGreaterThan( 0, $action_id );

		$stored = ActionScheduler::store()->fetch_action( $action_id );
		$this->assertInstanceOf( 'ActionScheduler_CronSchedule', $stored->get_schedule() );

		// Empty schedule falls back to single action.
		$fallback_id = $this->factory->cron( 'test_cron_fallback', array(), $time, '', 'cron_group' );
		$stored_fb   = ActionScheduler::store()->fetch_action( $fallback_id );
		$this->assertInstanceOf( 'ActionScheduler_SimpleSchedule', $stored_fb->get_schedule() );

		// Unique cron action deduplication.
		$unique_id1 = $this->factory->cron_unique( 'test_unique_cron', array(), $time, '0 * * * *', 'cron_group', true );
		$this->assertGreaterThan( 0, $unique_id1 );

		$unique_id2 = $this->factory->cron_unique( 'test_unique_cron', array(), $time, '0 * * * *', 'cron_group', true );
		$this->assertSame( 0, $unique_id2 );
	}

	/**
	 * Test repeat with a recurring action schedules the next instance.
	 */
	public function test_repeat_recurring_action() {
		$action_id = $this->factory->recurring( 'test_repeat_hook', array( 'foo' ), time() - 300, 600, 'repeat_group' );
		$action    = ActionScheduler::store()->fetch_action( $action_id );

		$next_id = $this->factory->repeat( $action );
		$this->assertGreaterThan( 0, $next_id );
		$this->assertNotSame( $action_id, $next_id );

		$repeated_action = ActionScheduler::store()->fetch_action( $next_id );
		$this->assertSame( 'test_repeat_hook', $repeated_action->get_hook() );
		$this->assertSame( array( 'foo' ), $repeated_action->get_args() );
		$this->assertSame( 'repeat_group', $repeated_action->get_group() );
		$this->assertInstanceOf( 'ActionScheduler_IntervalSchedule', $repeated_action->get_schedule() );
	}

	/**
	 * Test repeat on a non-recurring action throws InvalidArgumentException.
	 */
	public function test_repeat_non_recurring_throws_exception() {
		$action_id = $this->factory->single( 'test_non_recurring_hook' );
		$action    = ActionScheduler::store()->fetch_action( $action_id );

		$this->expectException( 'InvalidArgumentException' );
		$this->factory->repeat( $action );
	}

	/**
	 * Test create method with various schedule types and options.
	 *
	 * @dataProvider create_options_provider
	 *
	 * @param array  $options       Creation options.
	 * @param string $expected_type Expected schedule class name.
	 * @param int    $expected_prio Expected priority.
	 */
	public function test_create_with_options( array $options, $expected_type, $expected_prio ) {
		$action_id = $this->factory->create( $options );
		$this->assertGreaterThan( 0, $action_id );

		$stored = ActionScheduler::store()->fetch_action( $action_id );
		$this->assertInstanceOf( $expected_type, $stored->get_schedule() );
		$this->assertSame( $expected_prio, $stored->get_priority() );
	}

	/**
	 * Data provider for test_create_with_options.
	 *
	 * @return array[]
	 */
	public function create_options_provider() {
		return array(
			'default single action'          => array(
				'options'       => array(
					'hook' => 'test_create_default',
				),
				'expected_type' => 'ActionScheduler_SimpleSchedule',
				'expected_prio' => 10,
			),
			'explicit async action'          => array(
				'options'       => array(
					'type'     => 'async',
					'hook'     => 'test_create_async',
					'priority' => 5,
				),
				'expected_type' => 'ActionScheduler_NullSchedule',
				'expected_prio' => 5,
			),
			'recurring action with interval' => array(
				'options'       => array(
					'type'     => 'recurring',
					'hook'     => 'test_create_recurring',
					'pattern'  => 3600,
					'priority' => 15,
				),
				'expected_type' => 'ActionScheduler_IntervalSchedule',
				'expected_prio' => 15,
			),
			'recurring action with empty pattern falls back to single' => array(
				'options'       => array(
					'type'    => 'recurring',
					'hook'    => 'test_create_recurring_empty',
					'pattern' => 0,
				),
				'expected_type' => 'ActionScheduler_SimpleSchedule',
				'expected_prio' => 10,
			),
			'cron action with pattern'       => array(
				'options'       => array(
					'type'    => 'cron',
					'hook'    => 'test_create_cron',
					'pattern' => '0 * * * *',
				),
				'expected_type' => 'ActionScheduler_CronSchedule',
				'expected_prio' => 10,
			),
			'cron action with empty pattern falls back to single' => array(
				'options'       => array(
					'type'    => 'cron',
					'hook'    => 'test_create_cron_empty',
					'pattern' => '',
				),
				'expected_type' => 'ActionScheduler_SimpleSchedule',
				'expected_prio' => 10,
			),
		);
	}

	/**
	 * Test create with unknown type returns 0 and logs error.
	 */
	public function test_create_unknown_type_returns_zero() {
		$error_capture    = tmpfile();
		$actual_error_log = ini_set( 'error_log', stream_get_meta_data( $error_capture )['uri'] ); // phpcs:ignore WordPress.PHP.IniSet.Risky

		$action_id = $this->factory->create(
			array(
				'type' => 'unsupported_custom_type',
				'hook' => 'test_unknown_type',
			)
		);

		$logged_errors = stream_get_contents( $error_capture );
		ini_set( 'error_log', $actual_error_log ); // phpcs:ignore WordPress.PHP.IniSet.Risky

		$this->assertSame( 0, $action_id );
		if ( method_exists( $this, 'assertStringContainsString' ) ) {
			$this->assertStringContainsString( "Unknown action type 'unsupported_custom_type' specified when trying to create an action for 'test_unknown_type'.", $logged_errors );
		} else {
			$this->assertContains( "Unknown action type 'unsupported_custom_type' specified when trying to create an action for 'test_unknown_type'.", $logged_errors );
		}
	}

	/**
	 * Test create with unique option deduplicates identical actions.
	 */
	public function test_create_unique_deduplication() {
		$options = array(
			'type'      => 'single',
			'hook'      => 'test_unique_create_hook',
			'arguments' => array( 'unique_val' => 42 ),
			'unique'    => true,
		);

		$first_id = $this->factory->create( $options );
		$this->assertGreaterThan( 0, $first_id );

		$second_id = $this->factory->create( $options );
		$this->assertSame( 0, $second_id );
	}
}
