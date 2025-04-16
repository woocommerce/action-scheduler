<?php

/**
 * Test suite for the ActionScheduler_RecurringActionScheduler class.
 */
class ActionScheduler_RecurringActionScheduler_Test extends ActionScheduler_UnitTestCase {

	public function tear_down() {
		wp_cache_delete( 'as_is_recurring_scheduler_scheduled' );
		as_unschedule_action( 'action_scheduler_ensure_recurring_actions' );

		parent::tear_down();
	}

	/**
	 * Test that the init method hooks into 'action_scheduler_init' correctly.
	 */
	public function test_init_hooks_into_action_scheduler_init() {
		global $current_screen;

		$scheduler = new ActionScheduler_RecurringActionScheduler();
		$scheduler->init();

		// Only apply hooks when in the admin.
		$_current_screen = $current_screen;
		set_current_screen( 'dashboard' );

		try {
			// Verify that the 'action_scheduler_init' hook is registered with the correct callback
			$this->assertTrue(
				has_action( 'action_scheduler_init', array(
					ActionScheduler_RecurringActionScheduler::class,
					'schedule_recurring_scheduler_hook'
				) ) > 0,
				'The schedule_recurring_scheduler_hook method should be hooked into action_scheduler_init.'
			);
		} finally {
			// Clean up to avoid affecting any other tests.
			$current_screen = $_current_screen;
			remove_action(
				'action_scheduler_init',
				array(
					ActionScheduler_RecurringActionScheduler::class,
					'schedule_recurring_scheduler_hook'
				)
			);
		}
	}

	/**
	 * Test that schedule_recurring_scheduler_hook schedules the recurring action when not already scheduled.
	 */
	public function test_schedule_recurring_scheduler_hook_schedules_action() {
		// Ensure no action is scheduled initially
		$this->assertFalse(
			as_has_scheduled_action( 'action_scheduler_ensure_recurring_actions' ),
			'No recurring action should be scheduled initially.'
		);

		$scheduler = new ActionScheduler_RecurringActionScheduler();
		$scheduler->schedule_recurring_scheduler_hook();

		$this->assertTrue(
			as_has_scheduled_action( 'action_scheduler_ensure_recurring_actions' ),
			'The recurring action should now be scheduled.'
		);
	}

	/**
	 * Test that schedule_recurring_scheduler_hook respects caching and does not schedule actions redundantly.
	 */
	public function test_schedule_recurring_scheduler_hook__respects_cache() {
		// Ensure no action is scheduled initially
		$this->assertFalse(
			as_has_scheduled_action( 'action_scheduler_ensure_recurring_actions' ),
			'No recurring action should be scheduled initially.'
		);

		// Simulate a cache hit
		wp_cache_set( 'as_is_recurring_scheduler_scheduled', true, '', HOUR_IN_SECONDS );

		// Spy on as_schedule_recurring_action to verify it does NOT get called
		$scheduler = new ActionScheduler_RecurringActionScheduler();
		$scheduler->schedule_recurring_scheduler_hook();

		// Assert that no new action was scheduled due to cache hit
		$this->assertFalse(
			as_has_scheduled_action( 'action_scheduler_ensure_recurring_actions' ),
			'No new recurring action should be scheduled due to cache hit.'
		);
	}
}
