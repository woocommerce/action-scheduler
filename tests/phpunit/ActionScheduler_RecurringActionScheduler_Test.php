<?php

/**
 * Test suite for the ActionScheduler_RecurringActionScheduler class.
 */
class ActionScheduler_RecurringActionScheduler_Test extends ActionScheduler_UnitTestCase {

	/**
	 * The hook of the action the scheduler keeps scheduled.
	 *
	 * Mirrors ActionScheduler_RecurringActionScheduler::RUN_SCHEDULED_RECURRING_ACTIONS_HOOK, which is private.
	 *
	 * @var string
	 */
	private const RUN_SCHEDULED_RECURRING_ACTIONS_HOOK = 'action_scheduler_run_recurring_actions_schedule_hook';

	public function set_up() {
		delete_transient( 'as_is_ensure_recurring_actions_scheduled' );

		parent::set_up();
	}

	public function tear_down() {
		delete_transient( 'as_is_ensure_recurring_actions_scheduled' );
		as_unschedule_all_actions( self::RUN_SCHEDULED_RECURRING_ACTIONS_HOOK );

		parent::tear_down();
	}

	/**
	 * Test that the init method hooks into 'action_scheduler_init' correctly.
	 */
	public function test_init_hooks_into_action_scheduler_init() {
		// The hook is only added in the admin.
		set_current_screen( 'dashboard' );

		try {
			$scheduler = new ActionScheduler_RecurringActionScheduler();
			$scheduler->init();

			$this->assertNotFalse(
				has_action( 'action_scheduler_init', array( $scheduler, 'schedule_recurring_scheduler_hook' ) ),
				'The schedule_recurring_scheduler_hook method should be hooked into action_scheduler_init.'
			);
		} finally {
			set_current_screen( 'front' );
		}
	}

	/**
	 * Test that schedule_recurring_scheduler_hook schedules the recurring action when not already scheduled.
	 */
	public function test_schedule_recurring_scheduler_hook_schedules_action() {
		// Ensure no action is scheduled initially
		$this->assertFalse(
			as_has_scheduled_action( self::RUN_SCHEDULED_RECURRING_ACTIONS_HOOK ),
			'No recurring action should be scheduled initially.'
		);

		$scheduler = new ActionScheduler_RecurringActionScheduler();
		$scheduler->schedule_recurring_scheduler_hook();

		$this->assertTrue(
			as_has_scheduled_action( self::RUN_SCHEDULED_RECURRING_ACTIONS_HOOK ),
			'The recurring action should now be scheduled.'
		);
	}

	/**
	 * Test that schedule_recurring_scheduler_hook respects caching and does not schedule actions redundantly.
	 */
	public function test_schedule_recurring_scheduler_hook__respects_cache() {
		// Ensure no action is scheduled initially
		$this->assertFalse(
			as_has_scheduled_action( self::RUN_SCHEDULED_RECURRING_ACTIONS_HOOK ),
			'No recurring action should be scheduled initially.'
		);

		// Simulate a transient hit
		set_transient( 'as_is_ensure_recurring_actions_scheduled', true, HOUR_IN_SECONDS );

		// Spy on as_schedule_recurring_action to verify it does NOT get called
		$scheduler = new ActionScheduler_RecurringActionScheduler();
		$scheduler->schedule_recurring_scheduler_hook();

		// Assert that no new action was scheduled due to transient hit
		$this->assertFalse(
			as_has_scheduled_action( self::RUN_SCHEDULED_RECURRING_ACTIONS_HOOK ),
			'No new recurring action should be scheduled due to transient hit.'
		);
	}

	/**
	 * Test that nothing is set up, and no action is scheduled, during a plugin uninstall.
	 */
	public function test_init_does_nothing_when_uninstalling() {
		// An uninstall is an admin request, which is also when the housekeeping check normally runs.
		set_current_screen( 'dashboard' );
		$was_uninstalling = $this->set_uninstalling( true );

		try {
			$scheduler = new ActionScheduler_RecurringActionScheduler();
			$scheduler->init();

			// Fired synchronously by ActionScheduler::init() on a late bootstrap.
			do_action( 'action_scheduler_init' ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.HookCommentWrongStyle

			$this->assertFalse(
				has_action( 'action_scheduler_init', array( $scheduler, 'schedule_recurring_scheduler_hook' ) ),
				'The housekeeping check should not be hooked into action_scheduler_init during an uninstall.'
			);
			$this->assertFalse(
				has_action( 'action_scheduler_before_process_queue', array( $scheduler, 'schedule_recurring_scheduler_hook' ) ),
				'The housekeeping check should not be hooked into action_scheduler_before_process_queue during an uninstall.'
			);
			$this->assertFalse(
				as_has_scheduled_action( self::RUN_SCHEDULED_RECURRING_ACTIONS_HOOK ),
				'No recurring action should be scheduled during an uninstall.'
			);
		} finally {
			$this->set_uninstalling( $was_uninstalling );
			set_current_screen( 'front' );
		}
	}
}
